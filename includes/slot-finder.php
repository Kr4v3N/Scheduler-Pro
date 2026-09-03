<?php
/**
 * SLOT FINDER - Scheduler Pro v2.6
 *
 * Finds the next available slot (day + position) for the Adhesive mode
 * of sp_process_scheduling(). Only counts posts with post_status =
 * 'future', not locked (_sp_lock_planning != '1').
 */

if (!defined('ABSPATH')) exit;

/**
 * COUNT NON-LOCKED POSTS ON A GIVEN DATE
 *
 * IMPORTANT: Only counts posts with post_status = 'future'
 */
if (!function_exists('sp_count_non_locked_posts_on_date')) {
    function sp_count_non_locked_posts_on_date($date) {
        global $wpdb;

        $count = (int) $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT p.ID)
            FROM $wpdb->posts p
            LEFT JOIN $wpdb->postmeta m ON p.ID = m.post_id AND m.meta_key = '_sp_lock_planning'
            WHERE p.post_status = 'future'
            AND p.post_type = 'post'
            AND DATE(p.post_date) = %s
            AND (m.meta_value IS NULL OR m.meta_value != '1')
        ", $date));

        return $count;
    }
}

/**
 * SKIP WEEKENDS (optional, sp_skip_weekends = '1')
 *
 * Pushes a Saturday/Sunday candidate date forward to the following
 * Monday. No-op when the setting is off, or when $date isn't a weekend
 * day. Called everywhere a candidate publish date is finalized, in both
 * scheduling models (day-bucket and week/month cadence).
 */
if (!function_exists('sp_skip_weekend_if_needed')) {
    function sp_skip_weekend_if_needed($date) {
        if (get_option('sp_skip_weekends', '0') !== '1') {
            return $date;
        }

        $day_of_week = (int) date('N', strtotime($date)); // 1 (Mon) .. 7 (Sun)

        if ($day_of_week === 6) { // Saturday
            return date('Y-m-d', strtotime('+2 days', strtotime($date)));
        }
        if ($day_of_week === 7) { // Sunday
            return date('Y-m-d', strtotime('+1 day', strtotime($date)));
        }

        return $date;
    }
}

/**
 * FIND THE NEXT AVAILABLE SLOT
 */
if (!function_exists('sp_get_next_available_slot')) {
    function sp_get_next_available_slot($posts_per_day) {
        global $wpdb;

        $last_date = $wpdb->get_var("
            SELECT MAX(DATE(post_date))
            FROM $wpdb->posts
            WHERE post_status = 'future'
            AND post_type = 'post'
        ");

        if (!$last_date) {
            $start_date = sp_skip_weekend_if_needed(date('Y-m-d', strtotime('+1 day', current_time('timestamp'))));
            sp_log("📅 No FUTURE post found → Starting on {$start_date}", 'INFO');

            return array(
                'date' => $start_date,
                'count' => 0
            );
        }

        $tomorrow = date('Y-m-d', strtotime('+1 day', current_time('timestamp')));

        if ($last_date < $tomorrow) {
            sp_log("📅 Last date ({$last_date}) <= today → Starting tomorrow ({$tomorrow})", 'INFO');
            $last_date = $tomorrow;
        }

        $last_date = sp_skip_weekend_if_needed($last_date);
        $count = sp_count_non_locked_posts_on_date($last_date);

        sp_log("📊 Resume date: {$last_date} ({$count}/{$posts_per_day} non-locked FUTURE posts)", 'INFO');

        while ($count >= $posts_per_day) {
            $last_date = sp_skip_weekend_if_needed(date('Y-m-d', strtotime("+1 day", strtotime($last_date))));
            $count = sp_count_non_locked_posts_on_date($last_date);

            sp_log("📅 Day full → Moving to {$last_date} ({$count}/{$posts_per_day} posts)", 'INFO');
        }

        return array(
            'date' => $last_date,
            'count' => $count
        );
    }
}

/**
 * FIND THE NEXT AVAILABLE DAY (with automatic skipping)
 */
if (!function_exists('sp_find_next_available_day')) {
    function sp_find_next_available_day($current_date, $posts_per_day) {
        $next_date = sp_skip_weekend_if_needed(date('Y-m-d', strtotime("+1 day", strtotime($current_date))));
        $count = sp_count_non_locked_posts_on_date($next_date);

        $max_iterations = 365;
        $iterations = 0;

        while ($count >= $posts_per_day && $iterations < $max_iterations) {
            sp_log("⏭️ Day {$next_date} already full ({$count}/{$posts_per_day}) → Moving to the next one", 'INFO');
            $next_date = sp_skip_weekend_if_needed(date('Y-m-d', strtotime("+1 day", strtotime($next_date))));
            $count = sp_count_non_locked_posts_on_date($next_date);
            $iterations++;
        }

        if ($iterations >= $max_iterations) {
            sp_log("❌ ERROR: Could not find an available day after {$max_iterations} attempts", 'ERROR');
        }

        return array(
            'date' => $next_date,
            'count' => $count
        );
    }
}

/**
 * CADENCE HELPERS (Week / Month scheduling)
 *
 * Used when sp_cadence_unit is 'week' or 'month': at most one post per
 * day, placed at a rolling average interval derived from the period and
 * the desired count (e.g. "2/week" => roughly one post every 3.5 days).
 * The 'day' unit keeps the original one-or-more-per-day bucket model
 * above completely unchanged.
 */

/**
 * Number of days in a cadence period.
 */
if (!function_exists('sp_get_cadence_period_days')) {
    function sp_get_cadence_period_days($unit) {
        switch ($unit) {
            case 'week':
                return 7;
            case 'month':
                return 30;
            default:
                return 1;
        }
    }
}

/**
 * Average interval (in days) between two posts for a given cadence.
 * Always at least 1: week/month cadence never places more than one post
 * per day (a "day" unit cadence should be used for that instead).
 */
if (!function_exists('sp_get_cadence_interval_days')) {
    function sp_get_cadence_interval_days($unit, $count) {
        $count = max(1, (int) $count);
        $period = sp_get_cadence_period_days($unit);
        return max(1, $period / $count);
    }
}

/**
 * Step from $date by roughly $interval_days, with a ±20% random jitter
 * so the resulting dates don't fall on a rigid, detectable rhythm.
 * Never advances by less than 1 day.
 */
if (!function_exists('sp_advance_cadence_date')) {
    function sp_advance_cadence_date($date, $interval_days) {
        $jitter_factor = mt_rand(-20, 20) / 100; // ±20%
        $jittered_days = max(1, (int) round($interval_days * (1 + $jitter_factor)));

        $next_date = date('Y-m-d', strtotime("+{$jittered_days} days", strtotime($date)));

        return sp_skip_weekend_if_needed($next_date);
    }
}

/**
 * Starting anchor date for cadence-based scheduling in Adhesive mode:
 * the date of the last currently-scheduled FUTURE post, or today if none
 * exists or it's already in the past. sp_advance_cadence_date() is then
 * applied on top of this anchor for the first post, guaranteeing the
 * result never lands earlier than tomorrow.
 *
 * IMPORTANT: this anchor is never used directly as a publish date (only
 * as a base for sp_advance_cadence_date()), so it deliberately does NOT
 * apply sp_skip_weekend_if_needed() itself - that would shift the
 * interval math even though the anchor is never actually published on.
 */
if (!function_exists('sp_get_cadence_anchor_date')) {
    function sp_get_cadence_anchor_date() {
        global $wpdb;

        $last_date = $wpdb->get_var("
            SELECT MAX(DATE(post_date))
            FROM $wpdb->posts
            WHERE post_status = 'future'
            AND post_type = 'post'
        ");

        $today = date('Y-m-d', current_time('timestamp'));

        return ($last_date && $last_date > $today) ? $last_date : $today;
    }
}
