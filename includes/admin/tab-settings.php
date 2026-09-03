<?php
/**
 * ADMIN: SETTINGS TAB - Scheduler Pro v2.5
 */

if (!defined('ABSPATH')) exit;

function sp_render_settings_tab() {
    $posts_per_day = get_option('sp_posts_per_day', 3);
    $start_hour = get_option('sp_start_hour', 7);
    $end_hour = get_option('sp_end_hour', 20);
    $auto_mode = get_option('sp_auto_mode', '1');
    $force_replan = get_option('sp_force_replan', '0');
    $total_future = (int) wp_count_posts()->future;

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
                            <label>Posts / day</label>
                            <input type="number"
                                   id="sp_posts_per_day"
                                   name="sp_posts_per_day"
                                   value="<?php echo $posts_per_day; ?>"
                                   min="1"
                                   max="50"
                                   class="sp-input-number">
                        </div>

                        <div class="sp-sim-arrow">
                            <span class="dashicons dashicons-arrow-right-alt2"></span>
                        </div>

                        <div class="sp-sim-output">
                            <label>Duration (Days)</label>
                            <input type="number"
                                   id="sp_duration_input"
                                   value="<?php echo ceil($total_future / max(1, $posts_per_day)); ?>"
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
                    <span class="sp-stat-label">Posts/day</span>
                    <span class="sp-stat-value"><?php echo $posts_per_day; ?></span>
                </div>
                <div class="sp-stat-item">
                    <span class="sp-stat-label">Estimated duration</span>
                    <span class="sp-stat-value">
                        <?php echo ceil($total_future / max(1, $posts_per_day)); ?> days
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
        const $perDay = $('#sp_posts_per_day');
        const $duration = $('#sp_duration_input');
        const $summary = $('#sp-summary-text');

        function updateSim() {
            const days = parseInt($duration.val()) || 1;
            const date = new Date();
            date.setDate(date.getDate() + days);

            $summary.html(`
                <strong>Result:</strong> ${$perDay.val()} posts/day until
                <strong>${date.toLocaleDateString('en-US', {day:'numeric', month:'long', year:'numeric'})}</strong>
            `);
        }

        $perDay.on('input', function() {
            $duration.val(Math.ceil(total / (parseInt($perDay.val()) || 1)));
            updateSim();
        });

        $duration.on('input', function() {
            $perDay.val(Math.ceil(total / (parseInt($duration.val()) || 1)));
            updateSim();
        });

        updateSim();
    });
    </script>
    <?php
}
