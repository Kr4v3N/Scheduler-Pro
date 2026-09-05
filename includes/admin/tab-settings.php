<?php
/**
 * ADMIN: SETTINGS TAB - Scheduler Pro v2.6
 */

if (!defined('ABSPATH')) exit;

function sp_render_settings_tab($preview = null) {
    $cadence_unit = get_option('sp_cadence_unit', 'day');
    if (!in_array($cadence_unit, array('day', 'week', 'month'), true)) {
        $cadence_unit = 'day';
    }
    $cadence_unit_labels = array(
        'day' => __('day', 'scheduler-pro'),
        'week' => __('week', 'scheduler-pro'),
        'month' => __('month', 'scheduler-pro'),
    );
    $cadence_count = (int) get_option('sp_cadence_count', get_option('sp_posts_per_day', 3));
    $start_hour = get_option('sp_start_hour', 7);
    $end_hour = get_option('sp_end_hour', 20);
    $skip_weekends = get_option('sp_skip_weekends', '0');
    $auto_mode = get_option('sp_auto_mode', '1');
    $force_replan = get_option('sp_force_replan', '0');
    $excluded_categories = array_map('intval', (array) get_option('sp_excluded_categories', array()));
    $email_alerts = get_option('sp_email_alerts', '1');
    $alert_email = get_option('sp_alert_email', '');
    $total_future = (int) wp_count_posts()->future;

    // Average interval between two posts, in days (unifies all 3 units:
    // "day" with count=3 gives 1/3 day, same as the pre-cadence formula).
    // For week/month, reuse the engine's own sp_get_cadence_interval_days()
    // (includes/slot-finder.php) rather than recomputing it here: it clamps
    // the result to at least 1 day (week/month never place more than 1
    // post/day), and duplicating that formula without the clamp previously
    // made this simulator show a shorter duration than the engine actually
    // produces once sp_cadence_count exceeds the period (e.g. 10/week).
    $interval_days = ($cadence_unit === 'day')
        ? 1 / max(1, $cadence_count)
        : sp_get_cadence_interval_days($cadence_unit, $cadence_count);

    ?>
    <div class="sp-settings-grid">
        <!-- Left Column: Settings -->
        <div class="sp-col-main">
            <form method="post">
                <?php wp_nonce_field('sp_settings_nonce'); ?>

                <!-- Card: Simulator -->
                <div class="sp-card">
                    <h2 class="sp-card-title">
                        <span class="dashicons dashicons-calculator"></span>
                        <?php esc_html_e('Strategy Simulator', 'scheduler-pro'); ?>
                    </h2>

                    <div class="sp-info-box">
                        <?php
                        printf(
                            /* translators: %s: number of future posts, shown in bold */
                            esc_html__('%s posts awaiting scheduling', 'scheduler-pro'),
                            '<strong>' . esc_html($total_future) . '</strong>'
                        );
                        ?>
                    </div>

                    <div class="sp-simulator">
                        <div class="sp-sim-input">
                            <label><?php esc_html_e('Cadence', 'scheduler-pro'); ?></label>
                            <div class="sp-cadence-input">
                                <input type="number"
                                       id="sp_cadence_count"
                                       name="sp_cadence_count"
                                       value="<?php echo esc_attr($cadence_count); ?>"
                                       min="1"
                                       max="500"
                                       class="sp-input-number">
                                <span>/</span>
                                <select id="sp_cadence_unit" name="sp_cadence_unit" class="sp-input-select">
                                    <option value="day" <?php selected($cadence_unit, 'day'); ?>><?php esc_html_e('day', 'scheduler-pro'); ?></option>
                                    <option value="week" <?php selected($cadence_unit, 'week'); ?>><?php esc_html_e('week', 'scheduler-pro'); ?></option>
                                    <option value="month" <?php selected($cadence_unit, 'month'); ?>><?php esc_html_e('month', 'scheduler-pro'); ?></option>
                                </select>
                            </div>
                            <p class="sp-help-text">
                                <?php esc_html_e('Days/weeks are auto-distributed to keep publishing rhythm unpredictable.', 'scheduler-pro'); ?>
                            </p>
                        </div>

                        <div class="sp-sim-arrow">
                            <span class="dashicons dashicons-arrow-right-alt2"></span>
                        </div>

                        <div class="sp-sim-output">
                            <label><?php esc_html_e('Duration (Days)', 'scheduler-pro'); ?></label>
                            <input type="number"
                                   id="sp_duration_input"
                                   value="<?php echo ceil($total_future * $interval_days); ?>"
                                   min="1"
                                   class="sp-input-number">
                        </div>
                    </div>

                    <p class="sp-sim-result" id="sp-summary-text"></p>
                </div>

                <!-- Card: Schedule -->
                <div class="sp-card">
                    <h2 class="sp-card-title">
                        <span class="dashicons dashicons-clock"></span>
                        <?php esc_html_e('Schedule Configuration', 'scheduler-pro'); ?>
                    </h2>

                    <div class="sp-form-row">
                        <div class="sp-form-col">
                            <label class="sp-label"><?php esc_html_e('Publishing time range', 'scheduler-pro'); ?></label>
                            <div class="sp-time-range">
                                <span><?php esc_html_e('From', 'scheduler-pro'); ?></span>
                                <input type="number"
                                       name="sp_start_hour"
                                       value="<?php echo $start_hour; ?>"
                                       min="0"
                                       max="23"
                                       class="sp-input-time">
                                <span><?php esc_html_e('h to', 'scheduler-pro'); ?></span>
                                <input type="number"
                                       name="sp_end_hour"
                                       value="<?php echo $end_hour; ?>"
                                       min="0"
                                       max="23"
                                       class="sp-input-time">
                                <span>h</span>
                            </div>
                            <p class="sp-help-text">
                                <?php esc_html_e('Recommended: 7am-8pm for natural-looking activity', 'scheduler-pro'); ?>
                            </p>

                            <label class="sp-checkbox-label">
                                <input type="checkbox"
                                       name="sp_skip_weekends"
                                       value="1"
                                       <?php checked($skip_weekends, '1'); ?>>
                                <span><?php esc_html_e('Skip weekends (Saturday & Sunday)', 'scheduler-pro'); ?></span>
                            </label>
                        </div>

                        <div class="sp-form-col">
                            <label class="sp-label"><?php esc_html_e('Automation', 'scheduler-pro'); ?></label>
                            <label class="sp-checkbox-label">
                                <input type="checkbox"
                                       name="sp_auto_mode"
                                       value="1"
                                       <?php checked($auto_mode, '1'); ?>>
                                <span><?php esc_html_e('Enable automatic daily scheduling', 'scheduler-pro'); ?></span>
                            </label>
                            <p class="sp-help-text">
                                <?php esc_html_e('Runs daily at 00:30 via WP-Cron', 'scheduler-pro'); ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Card: Category Exclusion -->
                <div class="sp-card">
                    <h2 class="sp-card-title">
                        <span class="dashicons dashicons-hidden"></span>
                        <?php esc_html_e('Category Exclusion', 'scheduler-pro'); ?>
                    </h2>

                    <label class="sp-label" for="sp_excluded_categories"><?php esc_html_e('Categories never auto-scheduled', 'scheduler-pro'); ?></label>
                    <select id="sp_excluded_categories"
                            name="sp_excluded_categories[]"
                            class="sp-select-multiple"
                            multiple>
                        <?php foreach (get_categories(array('hide_empty' => false)) as $category): ?>
                            <option value="<?php echo esc_attr($category->term_id); ?>"
                                <?php echo in_array($category->term_id, $excluded_categories, true) ? 'selected' : ''; ?>>
                                <?php echo esc_html($category->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="sp-help-text">
                        <?php esc_html_e('Posts in a selected category are entirely skipped by the scheduler (Ctrl/Cmd-click to select several).', 'scheduler-pro'); ?>
                    </p>
                </div>

                <!-- Card: Email Alerts -->
                <div class="sp-card">
                    <h2 class="sp-card-title">
                        <span class="dashicons dashicons-email-alt"></span>
                        <?php esc_html_e('Email Alerts', 'scheduler-pro'); ?>
                    </h2>

                    <label class="sp-checkbox-label">
                        <input type="checkbox"
                               name="sp_email_alerts"
                               value="1"
                               <?php checked($email_alerts, '1'); ?>>
                        <span><?php esc_html_e('Email me if the scheduler stops running (no run for over 25h)', 'scheduler-pro'); ?></span>
                    </label>

                    <div class="sp-form-col">
                        <label class="sp-label" for="sp_alert_email"><?php esc_html_e('Recipient (optional)', 'scheduler-pro'); ?></label>
                        <input type="email"
                               id="sp_alert_email"
                               name="sp_alert_email"
                               value="<?php echo esc_attr($alert_email); ?>"
                               placeholder="<?php echo esc_attr(get_option('admin_email')); ?>"
                               class="sp-input-number sp-input-wide">
                    </div>
                    <p class="sp-help-text">
                        <?php esc_html_e("Leave empty to use the site's admin email. At most one alert every 24 hours.", 'scheduler-pro'); ?>
                    </p>
                </div>

                <!-- Card: Full Reset Mode -->
                <div class="sp-card sp-card-warning">
                    <h2 class="sp-card-title">
                        <span class="dashicons dashicons-image-rotate"></span>
                        <?php esc_html_e('"Full Reset" Mode', 'scheduler-pro'); ?>
                    </h2>

                    <label class="sp-checkbox-label">
                        <input type="checkbox"
                               name="sp_force_replan"
                               value="1"
                               <?php checked($force_replan, '1'); ?>>
                        <strong><?php esc_html_e('Force reorganization of ALL future posts', 'scheduler-pro'); ?></strong>
                    </label>

                    <p class="sp-help-text">
                        ⚠️ <strong><?php esc_html_e('Warning:', 'scheduler-pro'); ?></strong>
                        <?php esc_html_e('This option will reorganize ALL your future posts starting tomorrow. Current dates will be overwritten. Automatically disables itself after running.', 'scheduler-pro'); ?>
                    </p>
                </div>

                <div class="sp-actions">
                    <button type="submit"
                            name="sp_save_settings"
                            class="button button-primary button-hero">
                        <span class="dashicons dashicons-saved"></span>
                        <?php esc_html_e('Save Settings', 'scheduler-pro'); ?>
                    </button>
                </div>
            </form>

            <!-- Manual Action -->
            <form method="post">
                <?php wp_nonce_field('sp_run_nonce'); ?>
                <div class="sp-card sp-card-action">
                    <h2 class="sp-card-title">
                        <span class="dashicons dashicons-controls-play"></span>
                        <?php esc_html_e('Manual Run', 'scheduler-pro'); ?>
                    </h2>
                    <p><?php esc_html_e('Run the post scheduling immediately (without waiting for the daily cron), or preview the result first without changing anything.', 'scheduler-pro'); ?></p>
                    <div class="sp-actions">
                        <button type="submit"
                                name="sp_preview_run"
                                class="button button-hero">
                            👁️ <?php esc_html_e('Preview', 'scheduler-pro'); ?>
                        </button>
                        <button type="submit"
                                name="sp_run_now"
                                class="button button-hero sp-btn-action">
                            🚀 <?php esc_html_e('Run Scheduling Now', 'scheduler-pro'); ?>
                        </button>
                    </div>
                </div>
            </form>

            <?php if (is_array($preview)): ?>
            <div class="sp-card">
                <h2 class="sp-card-title">
                    <span class="dashicons dashicons-visibility"></span>
                    <?php esc_html_e('Preview Result (not applied)', 'scheduler-pro'); ?>
                </h2>

                <?php if (empty($preview)): ?>
                    <p class="sp-help-text"><?php esc_html_e('No post would be scheduled with the current settings.', 'scheduler-pro'); ?></p>
                <?php else: ?>
                    <table class="sp-config-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Post', 'scheduler-pro'); ?></th>
                                <th><?php esc_html_e('Current date', 'scheduler-pro'); ?></th>
                                <th></th>
                                <th><?php esc_html_e('New date', 'scheduler-pro'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($preview, 0, 50) as $row): ?>
                                <tr>
                                    <td><?php echo esc_html($row['title'] !== '' ? $row['title'] : __('(no title)', 'scheduler-pro')); ?></td>
                                    <td><?php echo esc_html($row['old_date']); ?></td>
                                    <td><span class="dashicons dashicons-arrow-right-alt2"></span></td>
                                    <td><strong><?php echo esc_html($row['new_date']); ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if (count($preview) > 50): ?>
                        <p class="sp-help-text">
                            <?php
                            printf(
                                /* translators: %d: number of additional posts not shown in the preview table */
                                esc_html__('%d more post(s) not shown here, but included in the total above.', 'scheduler-pro'),
                                count($preview) - 50
                            );
                            ?>
                        </p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right Column: System Status -->
        <div class="sp-col-sidebar">
            <?php SP_Heartbeat_Monitor::render_inline_status(); ?>

            <!-- Quick Stats -->
            <div class="sp-card sp-card-stats">
                <h3>📊 <?php esc_html_e('Quick Stats', 'scheduler-pro'); ?></h3>
                <div class="sp-stat-item">
                    <span class="sp-stat-label"><?php esc_html_e('Future posts', 'scheduler-pro'); ?></span>
                    <span class="sp-stat-value"><?php echo $total_future; ?></span>
                </div>
                <div class="sp-stat-item">
                    <span class="sp-stat-label"><?php esc_html_e('Cadence', 'scheduler-pro'); ?></span>
                    <span class="sp-stat-value"><?php echo esc_html($cadence_count); ?>/<?php echo esc_html($cadence_unit_labels[$cadence_unit]); ?></span>
                </div>
                <div class="sp-stat-item">
                    <span class="sp-stat-label"><?php esc_html_e('Estimated duration', 'scheduler-pro'); ?></span>
                    <span class="sp-stat-value">
                        <?php
                        printf(
                            /* translators: %d: estimated duration in days */
                            esc_html__('%d days', 'scheduler-pro'),
                            ceil($total_future * $interval_days)
                        );
                        ?>
                    </span>
                </div>
            </div>

            <!-- Quick Help -->
            <div class="sp-card sp-card-help">
                <h3>💡 <?php esc_html_e('Quick Help', 'scheduler-pro'); ?></h3>
                <ul class="sp-help-list">
                    <li>
                        <strong><?php esc_html_e('Adhesive Mode:', 'scheduler-pro'); ?></strong> <?php esc_html_e('New posts are added after the existing schedule', 'scheduler-pro'); ?>
                    </li>
                    <li>
                        <strong><?php esc_html_e('Full Reset Mode:', 'scheduler-pro'); ?></strong> <?php esc_html_e('Everything is reorganized starting tomorrow', 'scheduler-pro'); ?>
                    </li>
                    <li>
                        <strong><?php esc_html_e('Locking:', 'scheduler-pro'); ?></strong> <?php esc_html_e('Use the meta box on each post to lock its date', 'scheduler-pro'); ?>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        const total = <?php echo $total_future; ?>;
        const periods = { day: 1, week: 7, month: 30 };
        const $count = $('#sp_cadence_count');
        const $unit = $('#sp_cadence_unit');
        const $duration = $('#sp_duration_input');
        const $summary = $('#sp-summary-text');
        const spSimI18n = {
            result: <?php echo wp_json_encode(__('Result:', 'scheduler-pro')); ?>,
            until: <?php echo wp_json_encode(__('until', 'scheduler-pro')); ?>,
            locale: <?php echo wp_json_encode(str_replace('_', '-', get_locale())); ?>,
            units: <?php echo wp_json_encode($cadence_unit_labels); ?>
        };

        function intervalDays() {
            const unit = $unit.val();
            const period = periods[unit] || 1;
            const raw = period / (parseInt($count.val()) || 1);
            // Week/month never place more than 1 post/day (mirrors
            // sp_get_cadence_interval_days() in includes/slot-finder.php) -
            // "day" alone can go below 1 (several posts per day).
            return unit === 'day' ? raw : Math.max(1, raw);
        }

        function updateSim() {
            const days = parseInt($duration.val()) || 1;
            const date = new Date();
            date.setDate(date.getDate() + days);

            $summary.html(`
                <strong>${spSimI18n.result}</strong> ${$count.val()}/${spSimI18n.units[$unit.val()]} ${spSimI18n.until}
                <strong>${date.toLocaleDateString(spSimI18n.locale, {day:'numeric', month:'long', year:'numeric'})}</strong>
            `);
        }

        $count.on('input', function() {
            $duration.val(Math.max(1, Math.ceil(total * intervalDays())));
            updateSim();
        });

        $unit.on('change', function() {
            $duration.val(Math.max(1, Math.ceil(total * intervalDays())));
            updateSim();
        });

        $duration.on('input', function() {
            const period = periods[$unit.val()] || 1;
            const days = parseInt($duration.val()) || 1;
            $count.val(Math.max(1, Math.round((period * total) / days)));
            updateSim();
        });

        updateSim();
    });
    </script>
    <?php
}
