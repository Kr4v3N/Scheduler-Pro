<?php
/**
 * ENGINE - Scheduler Pro v2.6
 *
 * Scheduling engine. Depends on:
 * - includes/memory-guard.php (memory check)
 * - includes/logger.php (sp_log)
 * - includes/lock-manager.php (anti-concurrency locking)
 * - includes/slot-finder.php (slot search, Adhesive mode)
 * - includes/human-time-generator.php (human time generation)
 * - includes/database-manager.php (traceability, optional)
 *
 * These files must be loaded BEFORE this one (see sp_load_core_files()
 * in scheduler-pro.php, which defines the order).
 *
 * IMPORTANT: This engine ONLY processes posts with post_status = 'future'
 */

if (!defined('ABSPATH')) exit;

/**
 * 🚀 MAIN SCHEDULING ENGINE
 *
 * IMPORTANT: Only processes posts with post_status = 'future'
 */
if (!function_exists('sp_process_scheduling')) {
    /**
     * @param bool $dry_run When true, simulates the run (same selection and
     *                      date-generation logic) without writing anything:
     *                      no wp_update_post(), no meta, no queue entry, no
     *                      lock. Used by the "Preview" action in Settings.
     */
    function sp_process_scheduling($dry_run = false) {
        // === STEP 1: ACQUIRE THE LOCK ===
        // A dry run only reads: no lock needed, and it must never be
        // blocked by (or block) a real run in progress.
        if (!$dry_run && !SP_Lock_Manager::acquire('main_scheduling', 600)) {
            sp_log("❌ A scheduling run is already in progress - aborting", 'ERROR');
            return array(
                'success' => false,
                'message' => 'A scheduling run is already in progress',
                'processed' => 0
            );
        }

        try {
            sp_log($dry_run ? "=== STARTING PREVIEW (FUTURE posts only, no write) ===" : "=== STARTING SCHEDULING (FUTURE posts only) ===", 'INFO');

            // === STEP 2: RETRIEVE SETTINGS ===
            // Cadence: 'day' keeps the original one-or-more-per-day bucket
            // model (sp_cadence_count = posts/day, same as the old
            // sp_posts_per_day). 'week'/'month' switch to the interval-based
            // model in slot-finder.php (at most 1 post/day).
            $cadence_unit = get_option('sp_cadence_unit', 'day');
            if (!in_array($cadence_unit, array('day', 'week', 'month'), true)) {
                $cadence_unit = 'day';
            }
            $cadence_count = max(1, (int) get_option('sp_cadence_count', get_option('sp_posts_per_day', 3)));
            $is_cadence_mode = ($cadence_unit !== 'day');
            $interval_days = $is_cadence_mode ? sp_get_cadence_interval_days($cadence_unit, $cadence_count) : 1;
            $posts_per_day = $is_cadence_mode ? 1 : $cadence_count;

            $start_hour    = max(0, min(23, (int) get_option('sp_start_hour', 7)));
            $end_hour      = max(0, min(23, (int) get_option('sp_end_hour', 20)));
            $force_replan  = get_option('sp_force_replan', '0');

            // Categories entirely excluded from auto-scheduling (configured in Settings).
            $excluded_categories = array_map('intval', (array) get_option('sp_excluded_categories', array()));

            if ($start_hour >= $end_hour) {
                sp_log("⚠️ Invalid configuration: start_hour >= end_hour - auto-correcting", 'WARNING');
                $start_hour = 7;
                $end_hour = 20;
            }

            $cadence_description = $is_cadence_mode
                ? "{$cadence_count} posts/{$cadence_unit} (~1 every {$interval_days}d)"
                : "{$posts_per_day} posts/day";
            $skip_weekends_label = get_option('sp_skip_weekends', '0') === '1' ? 'YES' : 'NO';
            sp_log("Settings: {$cadence_description} | Range: {$start_hour}h-{$end_hour}h | Force: " . ($force_replan === '1' ? 'YES' : 'NO') . " | Skip weekends: {$skip_weekends_label}", 'INFO');

            if (!empty($excluded_categories)) {
                sp_log("🚫 " . count($excluded_categories) . " category(ies) excluded from auto-scheduling", 'INFO');
            }

            // === STEP 3: DETERMINE THE STARTING POINT ===
            if ($is_cadence_mode) {
                // Week/Month cadence: the anchor is stepped by
                // sp_advance_cadence_date() for every post in STEP 6 below,
                // so it never needs a per-day post count.
                $count_in_day = 0;

                if ($force_replan === '1') {
                    $current_date = date('Y-m-d', current_time('timestamp'));
                    sp_log("🧹 'Full Reset' mode enabled: Full reorganization at a {$cadence_count}/{$cadence_unit} cadence", 'INFO');
                } else {
                    $current_date = sp_get_cadence_anchor_date();
                    sp_log("📌 'Adhesive' mode enabled: Resuming from {$current_date} at a {$cadence_count}/{$cadence_unit} cadence", 'INFO');
                }
            } elseif ($force_replan === '1') {
                $tomorrow = sp_skip_weekend_if_needed(date('Y-m-d', strtotime('+1 day', current_time('timestamp'))));
                $current_date = $tomorrow;
                $count_in_day = 0;

                sp_log("🧹 'Full Reset' mode enabled: Full reorganization starting {$tomorrow}", 'INFO');

            } else {
                $slot = sp_get_next_available_slot($posts_per_day);
                $current_date = $slot['date'];
                $count_in_day = $slot['count'];

                sp_log("📌 'Adhesive' mode enabled: Resuming on {$current_date} with {$count_in_day} post(s) already present", 'INFO');
            }

            // === STEP 4: BUILD THE QUERY ===
            // IMPORTANT: ONLY post_status = 'future'
            $base_args = array(
                'post_status'    => 'future', // ✅ ONLY SCHEDULED POSTS
                'post_type'      => 'post',
                'order'          => 'ASC',
                'fields'         => 'ids',
            );

            if (!empty($excluded_categories)) {
                $base_args['category__not_in'] = $excluded_categories;
            }

            // Exclude locked posts directly in the query (in both modes): avoids
            // wasting a batch slot on a post that would be ignored anyway
            // (previously filtered afterward, in PHP, inside the loop).
            $lock_exclusion = array(
                'relation' => 'OR',
                array('key' => '_sp_lock_planning', 'compare' => 'NOT EXISTS'),
                array('key' => '_sp_lock_planning', 'value' => '1', 'compare' => '!='),
            );

            // IMPORTANT: in a REAL Adhesive run, every processed post drops out
            // of the next query's result set (its _is_smart_scheduled meta
            // becomes '1', which is excluded by the meta_query below): the
            // total number of matching posts therefore SHRINKS with every
            // batch. Paginating by offset in that case would skip an entire
            // block of posts (the offset advances faster than the result set
            // shrinks). So offset stays at 0 in that case: the query always
            // returns the "next" batch of still-eligible posts, and we stop
            // as soon as an incomplete (or empty) batch is returned.
            //
            // In Full Reset mode, the _is_smart_scheduled filter is NOT applied
            // (all non-locked future posts remain eligible even after being
            // processed): the result set does NOT shrink from one batch to the
            // next, so the offset is needed to move through the list without
            // looping back over the same posts. A dry run never writes
            // anything (no meta, no post_date), so its result set NEVER
            // shrinks either, regardless of mode - it always needs the offset
            // too, or it would re-preview the same first batch forever.
            $use_offset = ($force_replan === '1') || $dry_run;

            // A REAL Full Reset run paginates by offset WHILE the loop
            // rewrites post_date (via wp_update_post() below) - sorting by
            // date would make that pagination unstable: a post already
            // processed, once its new date is applied, can end up positioned
            // before or after posts not yet processed, shifting "position
            // 100" from one query to the next and causing posts to be
            // skipped or reprocessed. Sorting by ID (immutable during
            // execution) keeps that pagination reliable. This instability
            // only exists when post_date is actually being rewritten, so a
            // dry run (which never writes) always keeps the chronological
            // 'date' sort, even in Full Reset mode.
            $base_args['orderby'] = ($force_replan === '1' && !$dry_run) ? 'ID' : 'date';

            // Adhesive semantics (skip posts already smart-scheduled) apply
            // whenever the run - real or previewed - behaves like Adhesive
            // mode. Only a REAL Full Reset run intentionally reprocesses
            // everything.
            if ($force_replan !== '1') {
                $base_args['meta_query'] = array(
                    'relation' => 'AND',
                    array(
                        'relation' => 'OR',
                        array('key' => '_is_smart_scheduled', 'compare' => 'NOT EXISTS'),
                        array('key' => '_is_smart_scheduled', 'value' => '0', 'compare' => '='),
                    ),
                    $lock_exclusion,
                );
            } else {
                $base_args['meta_query'] = array($lock_exclusion);
            }

            // === STEP 5: BATCH PROCESSING ===
            $batch_size = 100;
            $offset = 0;
            $total_processed = 0;
            $batch_number = 1;
            $completed_fully = true;
            $preview = array(); // Only populated when $dry_run is true

            do {
                // Check memory
                if (!sp_check_memory_available()) {
                    sp_log("⚠️ Critical memory - pausing after {$total_processed} posts", 'WARNING');
                    $completed_fully = false;
                    break;
                }

                // Build the batch arguments
                $args = array_merge($base_args, array(
                    'posts_per_page' => $batch_size,
                    'offset'         => $use_offset ? $offset : 0,
                ));

                // Run the query
                $query = new WP_Query($args);
                $post_ids = $query->posts;

                if (empty($post_ids)) {
                    break;
                }

                sp_log("📦 Batch #{$batch_number}: " . count($post_ids) . " FUTURE posts to process", 'INFO');

                // Used after the loop to detect a fully-failed batch
                // (see anti-infinite-loop guard below)
                $total_processed_before_batch = $total_processed;

                // === STEP 6: PROCESS THE POSTS ===
                // (locked posts are already excluded by the query, see STEP 4)
                foreach ($post_ids as $post_id) {
                    // ✨ Generate an ultra-human date for the upcoming slot.
                    // In both branches, the date/counter is only committed
                    // after wp_update_post() succeeds (below), so a failed
                    // update doesn't "consume" a slot.
                    if ($is_cadence_mode) {
                        $candidate_date = sp_advance_cadence_date($current_date, $interval_days);
                        $new_date = SP_Human_Time_Generator::generate($candidate_date, 1, 1, $start_hour, $end_hour);
                    } else {
                        // Check whether the day is full
                        if ($count_in_day >= $posts_per_day) {
                            $next_slot = sp_find_next_available_day($current_date, $posts_per_day);
                            $current_date = $next_slot['date'];
                            $count_in_day = $next_slot['count'];

                            sp_log("📅 Moving to the next day: {$current_date} ({$count_in_day} post(s) already there)", 'INFO');
                        }

                        $new_date = SP_Human_Time_Generator::generate(
                            $current_date,
                            $count_in_day + 1,
                            $posts_per_day,
                            $start_hour,
                            $end_hour
                        );
                    }

                    if ($dry_run) {
                        // No write: nothing can fail, so the slot is always
                        // "confirmed" for a preview.
                        $preview[] = array(
                            'id'       => $post_id,
                            'title'    => get_the_title($post_id),
                            'old_date' => get_post_field('post_date', $post_id),
                            'new_date' => $new_date,
                        );
                    } else {
                        // Update the post
                        $updated_id = wp_update_post(array(
                            'ID'            => $post_id,
                            'post_date'     => $new_date,
                            'post_date_gmt' => get_gmt_from_date($new_date),
                            'edit_date'     => true
                        ), true);

                        if (is_wp_error($updated_id)) {
                            sp_log("❌ ERROR: Post ID {$post_id} - " . $updated_id->get_error_message(), 'ERROR');
                            continue;
                        }
                    }

                    // The slot is now confirmed occupied: commit only now
                    if ($is_cadence_mode) {
                        $current_date = $candidate_date;
                    } else {
                        $count_in_day++;
                    }

                    if (!$dry_run) {
                        // Mark as scheduled
                        update_post_meta($post_id, '_is_smart_scheduled', '1');
                    }

                    sp_log(($dry_run ? "👁️ [Preview] Post ID {$post_id} → {$new_date}" : "✅ Post ID {$post_id} → {$new_date}"), 'SUCCESS');
                    $total_processed++;

                    // Optional: add to the queue table
                    if (!$dry_run && class_exists('SP_Database_Manager')) {
                        SP_Database_Manager::add_task(
                            $post_id,
                            $new_date,
                            50, // Normal priority
                            array('batch' => $batch_number)
                        );
                    }
                }

                // Anti-infinite-loop guard: in Adhesive mode, the offset stays at 0
                // permanently (see above). If a COMPLETE batch (100 results)
                // produces NO success at all (e.g. another plugin blocks
                // wp_update_post() on all of these posts via its own save_post
                // hook), none of them receive _is_smart_scheduled: they would
                // reappear identically in the next batch, indefinitely.
                // sp_process_scheduling() runs synchronously within an HTTP
                // request ("Run Now" button, cron test, WP pseudo-cron): an
                // infinite loop would eventually hit max_execution_time, killing
                // the process WITHOUT going through the finally block that
                // releases the lock - blocking it until its own timeout. So we
                // stop explicitly instead of letting that situation happen.
                if (!$use_offset && count($post_ids) === $batch_size && $total_processed === $total_processed_before_batch) {
                    sp_log("❌ ERROR: batch #{$batch_number} failed entirely (0 successes out of {$batch_size}) - stopping to avoid an infinite loop. Check whether a third-party plugin is blocking wp_update_post() on these posts.", 'ERROR');
                    $completed_fully = false;
                    break;
                }

                $batch_number++;

                if ($use_offset) {
                    $offset += $batch_size;
                }

                // Small delay between batches, as long as more may remain
                $has_more = $use_offset ? ($query->found_posts > $offset) : (count($post_ids) === $batch_size);
                if ($has_more) {
                    usleep(100000); // 0.1 second
                }

            } while ($use_offset ? ($query->found_posts > $offset) : (count($post_ids) === $batch_size));

            // === STEP 7: WRAP-UP ===
            // A dry run never mutates plugin state: no Full Reset toggle
            // change, no heartbeat update - a preview must be a pure read.
            if (!$dry_run) {
                // Only disable Full Reset mode if processing ran to its natural
                // conclusion (result set exhausted). If it stopped prematurely
                // (critical memory), leave it enabled: otherwise the reorganization
                // would be declared complete while posts remain unprocessed.
                if ($force_replan === '1') {
                    if ($completed_fully) {
                        update_option('sp_force_replan', '0');
                        sp_log("🔄 'Full Reset' mode automatically disabled (processing complete)", 'INFO');
                    } else {
                        sp_log("⚠️ 'Full Reset' mode left enabled: processing stopped prematurely (memory). Re-run the scheduler to finish.", 'WARNING');
                    }
                }

                // Heartbeat
                update_option('sp_last_cron_run', time());
            }

            sp_log(($dry_run ? "=== PREVIEW FINISHED: {$total_processed} FUTURE posts would be processed ===" : "=== SCHEDULING FINISHED: {$total_processed} FUTURE posts processed ==="), 'SUCCESS');

            return array(
                'success' => true,
                'message' => $dry_run
                    ? "{$total_processed} posts would be scheduled"
                    : "{$total_processed} posts successfully scheduled",
                'processed' => $total_processed,
                'preview' => $preview,
            );

        } catch (Exception $e) {
            sp_log("❌ CRITICAL ERROR: " . $e->getMessage(), 'ERROR');
            sp_log("Stack trace: " . $e->getTraceAsString(), 'DEBUG');

            return array(
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'processed' => 0,
                'preview' => array(),
            );

        } finally {
            if (!$dry_run) {
                SP_Lock_Manager::release('main_scheduling');
            }
        }
    }
}
