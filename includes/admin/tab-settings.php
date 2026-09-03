<?php
/**
 * ADMIN: SETTINGS TAB - Scheduler Pro v2.5
 */

if (!defined('ABSPATH')) exit;

function sp_render_settings_tab() {
    $cadence_unit = get_option('sp_cadence_unit', 'day');
    if (!in_array($cadence_unit, array('day', 'week', 'month'), true)) {
        $cadence_unit = 'day';
    }
    $cadence_count = (int) get_option('sp_cadence_count', get_option('sp_posts_per_day', 3));
    $start_hour = get_option('sp_start_hour', 7);
    $end_hour = get_option('sp_end_hour', 20);
    $auto_mode = get_option('sp_auto_mode', '1');
    $force_replan = get_option('sp_force_replan', '0');
    $total_future = (int) wp_count_posts()->future;

    // Average interval between two posts, in days (unifies all 3 units:
    // "day" with count=3 gives 1/3 day, same as the pre-cadence formula).
    $cadence_periods = array('day' => 1, 'week' => 7, 'month' => 30);
    $interval_days = max(1, $cadence_periods[$cadence_unit]) / max(1, $cadence_count);

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
                        Strategy Simulator
                    </h2>

                    <div class="sp-info-box">
                        <strong><?php echo $total_future; ?></strong> posts awaiting scheduling
                    </div>

                    <div class="sp-simulator">
                        <div class="sp-sim-input">
                            <label>Cadence</label>
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
                                    <option value="day" <?php selected($cadence_unit, 'day'); ?>>day</option>
                                    <option value="week" <?php selected($cadence_unit, 'week'); ?>>week</option>
                                    <option value="month" <?php selected($cadence_unit, 'month'); ?>>month</option>
                                </select>
                            </div>
                            <p class="sp-help-text">
                                Days/weeks are auto-distributed to keep publishing rhythm unpredictable.
                            </p>
                        </div>

                        <div class="sp-sim-arrow">
                            <span class="dashicons dashicons-arrow-right-alt2"></span>
                        </div>

                        <div class="sp-sim-output">
                            <label>Duration (Days)</label>
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
                        Schedule Configuration
                    </h2>

                    <div class="sp-form-row">
                        <div class="sp-form-col">
                            <label class="sp-label">Publishing time range</label>
                            <div class="sp-time-range">
                                <span>From</span>
                                <input type="number"
                                       name="sp_start_hour"
                                       value="<?php echo $start_hour; ?>"
                                       min="0"
                                       max="23"
                                       class="sp-input-time">
                                <span>h to</span>
                                <input type="number"
                                       name="sp_end_hour"
                                       value="<?php echo $end_hour; ?>"
                                       min="0"
                                       max="23"
                                       class="sp-input-time">
                                <span>h</span>
                            </div>
                            <p class="sp-help-text">
                                Recommended: 7am-8pm for natural-looking activity
                            </p>
                        </div>

                        <div class="sp-form-col">
                            <label class="sp-label">Automation</label>
                            <label class="sp-checkbox-label">
                                <input type="checkbox"
                                       name="sp_auto_mode"
                                       value="1"
                                       <?php checked($auto_mode, '1'); ?>>
                                <span>Enable automatic daily scheduling</span>
                            </label>
                            <p class="sp-help-text">
                                Runs daily at 00:30 via WP-Cron
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Card: Full Reset Mode -->
                <div class="sp-card sp-card-warning">
                    <h2 class="sp-card-title">
                        <span class="dashicons dashicons-image-rotate"></span>
                        "Full Reset" Mode
                    </h2>

                    <label class="sp-checkbox-label">
                        <input type="checkbox"
                               name="sp_force_replan"
                               value="1"
                               <?php checked($force_replan, '1'); ?>>
                        <strong>Force reorganization of ALL future posts</strong>
                    </label>

                    <p class="sp-help-text">
                        ⚠️ <strong>Warning:</strong> This option will reorganize ALL your future posts starting tomorrow.
                        Current dates will be overwritten. Automatically disables itself after running.
                    </p>
                </div>

                <div class="sp-actions">
                    <button type="submit"
                            name="sp_save_settings"
                            class="button button-primary button-hero">
                        <span class="dashicons dashicons-saved"></span>
                        Save Settings
                    </button>
                </div>
            </form>

            <!-- Manual Action -->
            <form method="post">
                <?php wp_nonce_field('sp_run_nonce'); ?>
                <div class="sp-card sp-card-action">
                    <h2 class="sp-card-title">
                        <span class="dashicons dashicons-controls-play"></span>
                        Manual Run
                    </h2>
                    <p>Run the post scheduling immediately (without waiting for the daily cron)</p>
                    <button type="submit"
                            name="sp_run_now"
                            class="button button-hero sp-btn-action">
                        🚀 Run Scheduling Now
                    </button>
                </div>
            </form>
        </div>

        <!-- Right Column: System Status -->
        <div class="sp-col-sidebar">
            <?php SP_Heartbeat_Monitor::render_inline_status(); ?>

            <!-- Quick Stats -->
            <div class="sp-card sp-card-stats">
                <h3>📊 Quick Stats</h3>
                <div class="sp-stat-item">
                    <span class="sp-stat-label">Future posts</span>
                    <span class="sp-stat-value"><?php echo $total_future; ?></span>
                </div>
                <div class="sp-stat-item">
                    <span class="sp-stat-label">Cadence</span>
                    <span class="sp-stat-value"><?php echo esc_html($cadence_count); ?>/<?php echo esc_html($cadence_unit); ?></span>
                </div>
                <div class="sp-stat-item">
                    <span class="sp-stat-label">Estimated duration</span>
                    <span class="sp-stat-value">
                        <?php echo ceil($total_future * $interval_days); ?> days
                    </span>
                </div>
            </div>

            <!-- Quick Help -->
            <div class="sp-card sp-card-help">
                <h3>💡 Quick Help</h3>
                <ul class="sp-help-list">
                    <li>
                        <strong>Adhesive Mode:</strong> New posts are added after the existing schedule
                    </li>
                    <li>
                        <strong>Full Reset Mode:</strong> Everything is reorganized starting tomorrow
                    </li>
                    <li>
                        <strong>Locking:</strong> Use the meta box on each post to lock its date
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

        function intervalDays() {
            const period = periods[$unit.val()] || 1;
            return period / (parseInt($count.val()) || 1);
        }

        function updateSim() {
            const days = parseInt($duration.val()) || 1;
            const date = new Date();
            date.setDate(date.getDate() + days);

            $summary.html(`
                <strong>Result:</strong> ${$count.val()}/${$unit.val()} until
                <strong>${date.toLocaleDateString('en-US', {day:'numeric', month:'long', year:'numeric'})}</strong>
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
