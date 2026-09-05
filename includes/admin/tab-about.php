<?php
/**
 * ADMIN: ABOUT TAB - Scheduler Pro v2.6
 */

if (!defined('ABSPATH')) exit;

function sp_render_about_tab() {
    ?>
    <div class="sp-about-grid">
        <div class="sp-card">
            <h2 class="sp-card-title">
                <span class="dashicons dashicons-info"></span>
                Scheduler Pro v<?php echo SP_VERSION; ?>
            </h2>

            <p class="sp-about-description">
                <?php esc_html_e('Advanced plugin for automatic, human-like WordPress post scheduling.', 'scheduler-pro'); ?>
                <?php esc_html_e('Designed to optimize your SEO publishing flow while keeping a natural appearance.', 'scheduler-pro'); ?>
            </p>

            <h3>✨ <?php esc_html_e("What's New in v2.6", 'scheduler-pro'); ?></h3>
            <ul class="sp-feature-list">
                <li>✅ <strong><?php esc_html_e('Flexible cadence', 'scheduler-pro'); ?></strong>: <?php esc_html_e('schedule by day, week, or month (e.g. "2/week", "6/month"), not just posts/day', 'scheduler-pro'); ?></li>
                <li>✅ <strong><?php esc_html_e('Category exclusion', 'scheduler-pro'); ?></strong>: <?php esc_html_e('keep entire categories out of auto-scheduling', 'scheduler-pro'); ?></li>
                <li>✅ <strong><?php esc_html_e('Preview before running', 'scheduler-pro'); ?></strong>: <?php esc_html_e('see the exact resulting dates before committing anything', 'scheduler-pro'); ?></li>
                <li>✅ <strong><?php esc_html_e('Email alerts', 'scheduler-pro'); ?></strong>: <?php esc_html_e('get notified if the scheduler stops running', 'scheduler-pro'); ?></li>
                <li>✅ <strong><?php esc_html_e('Calendar view', 'scheduler-pro'); ?></strong>: <?php esc_html_e('2-month day-by-day view of the upcoming schedule', 'scheduler-pro'); ?></li>
            </ul>

            <h3>🧱 <?php esc_html_e('Core Features', 'scheduler-pro'); ?></h3>
            <ul class="sp-feature-list">
                <li>✅ <strong><?php esc_html_e('Batch processing', 'scheduler-pro'); ?></strong>: <?php esc_html_e('handles thousands of posts without timing out', 'scheduler-pro'); ?></li>
                <li>✅ <strong><?php esc_html_e('Memory management', 'scheduler-pro'); ?></strong>: <?php esc_html_e('stops automatically if RAM usage gets too high', 'scheduler-pro'); ?></li>
                <li>✅ <strong><?php esc_html_e('Locking system', 'scheduler-pro'); ?></strong>: <?php esc_html_e('prevents duplicate runs', 'scheduler-pro'); ?></li>
                <li>✅ <strong><?php esc_html_e('Ultra-human generator', 'scheduler-pro'); ?></strong>: <?php esc_html_e('activity peaks at 9am, 2pm, 5pm', 'scheduler-pro'); ?></li>
                <li>✅ <strong><?php esc_html_e('Real-time monitoring', 'scheduler-pro'); ?></strong>: <?php esc_html_e('watches system health', 'scheduler-pro'); ?></li>
                <li>✅ <strong><?php esc_html_e('Task queue', 'scheduler-pro'); ?></strong>: <?php esc_html_e('full traceability of every scheduling run', 'scheduler-pro'); ?></li>
            </ul>

            <h3>🔧 <?php esc_html_e('Recommended Configuration', 'scheduler-pro'); ?></h3>
            <table class="sp-config-table">
                <tr>
                    <td><strong><?php esc_html_e('Cadence:', 'scheduler-pro'); ?></strong></td>
                    <td><?php esc_html_e('3-5/day (natural and SEO-friendly), or a week/month cadence for a lighter rhythm', 'scheduler-pro'); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Time range:', 'scheduler-pro'); ?></strong></td>
                    <td><?php esc_html_e('7am-8pm (human activity hours)', 'scheduler-pro'); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Auto mode:', 'scheduler-pro'); ?></strong></td>
                    <td><?php esc_html_e('Enabled (runs daily at 00:30, site time)', 'scheduler-pro'); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Server cron:', 'scheduler-pro'); ?></strong></td>
                    <td><?php esc_html_e('Recommended for maximum reliability', 'scheduler-pro'); ?></td>
                </tr>
            </table>
        </div>

        <div class="sp-card sp-card-help">
            <h2 class="sp-card-title">
                <span class="dashicons dashicons-sos"></span>
                <?php esc_html_e('Need Help?', 'scheduler-pro'); ?>
            </h2>

            <div class="sp-help-section">
                <h4>📖 <?php esc_html_e('Documentation', 'scheduler-pro'); ?></h4>
                <p><?php esc_html_e('Check the README file and guides bundled with the plugin.', 'scheduler-pro'); ?></p>
            </div>

            <div class="sp-help-section">
                <h4>🐛 <?php esc_html_e('Report a Bug', 'scheduler-pro'); ?></h4>
                <p><?php esc_html_e('Enable WordPress debug mode and check the logs.', 'scheduler-pro'); ?></p>
                <code>define('WP_DEBUG', true);</code>
            </div>

            <div class="sp-help-section">
                <h4>⚡ <?php esc_html_e('Performance', 'scheduler-pro'); ?></h4>
                <p>
                    <strong><?php esc_html_e('Before v2.0:', 'scheduler-pro'); ?></strong> <?php esc_html_e('1000 posts = 50s (frequent timeouts)', 'scheduler-pro'); ?><br>
                    <strong><?php esc_html_e('After v2.0:', 'scheduler-pro'); ?></strong> <?php esc_html_e('1000 posts = 5s', 'scheduler-pro'); ?> ✅
                </p>
            </div>
        </div>
    </div>

    <div class="sp-card sp-card-credits">
        <p style="text-align: center; opacity: 0.7;">
            <?php
            printf(
                /* translators: %1$s: developer name (bold), %2$s: plugin version number */
                esc_html__('Developed by %1$s | Version %2$s', 'scheduler-pro'),
                '<strong>Kr4v3n</strong>',
                esc_html(SP_VERSION)
            );
            ?>
        </p>
    </div>
    <?php
}
