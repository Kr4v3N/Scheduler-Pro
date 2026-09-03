<?php
/**
 * ADMIN: ABOUT TAB - Scheduler Pro v2.5
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
                Advanced plugin for automatic, human-like WordPress post scheduling.
                Designed to optimize your SEO publishing flow while keeping a natural appearance.
            </p>

            <h3>✨ What's New in v2.5</h3>
            <ul class="sp-feature-list">
                <li>✅ <strong>Atomic locking</strong>: eliminates the race window on the execution lock</li>
                <li>✅ <strong>Reliable scheduling</strong>: fixed a bug that could skip posts in Adhesive mode</li>
                <li>✅ <strong>Timezone respected</strong>: daily scheduling now honors WordPress's configured timezone</li>
                <li>✅ <strong>Protected log</strong>: the log file is no longer reachable via direct HTTP access</li>
                <li>✅ <strong>Modular architecture</strong>: code split by responsibility for easier maintenance</li>
            </ul>

            <h3>🧱 Core Features</h3>
            <ul class="sp-feature-list">
                <li>✅ <strong>Batch processing</strong>: handles thousands of posts without timing out</li>
                <li>✅ <strong>Memory management</strong>: stops automatically if RAM usage gets too high</li>
                <li>✅ <strong>Locking system</strong>: prevents duplicate runs</li>
                <li>✅ <strong>Ultra-human generator</strong>: activity peaks at 9am, 2pm, 5pm</li>
                <li>✅ <strong>Real-time monitoring</strong>: watches system health</li>
                <li>✅ <strong>Task queue</strong>: full traceability of every scheduling run</li>
            </ul>

            <h3>🔧 Recommended Configuration</h3>
            <table class="sp-config-table">
                <tr>
                    <td><strong>Posts/day:</strong></td>
                    <td>3-5 (natural and SEO-friendly)</td>
                </tr>
                <tr>
                    <td><strong>Time range:</strong></td>
                    <td>7am-8pm (human activity hours)</td>
                </tr>
                <tr>
                    <td><strong>Auto mode:</strong></td>
                    <td>Enabled (runs daily at 00:30, site time)</td>
                </tr>
                <tr>
                    <td><strong>Server cron:</strong></td>
                    <td>Recommended for maximum reliability</td>
                </tr>
            </table>
        </div>

        <div class="sp-card sp-card-help">
            <h2 class="sp-card-title">
                <span class="dashicons dashicons-sos"></span>
                Need Help?
            </h2>

            <div class="sp-help-section">
                <h4>📖 Documentation</h4>
                <p>Check the README file and guides bundled with the plugin.</p>
            </div>

            <div class="sp-help-section">
                <h4>🐛 Report a Bug</h4>
                <p>Enable WordPress debug mode and check the logs.</p>
                <code>define('WP_DEBUG', true);</code>
            </div>

            <div class="sp-help-section">
                <h4>⚡ Performance</h4>
                <p>
                    <strong>Before v2.0:</strong> 1000 posts = 50s (frequent timeouts)<br>
                    <strong>After v2.0:</strong> 1000 posts = 5s ✅
                </p>
            </div>
        </div>
    </div>

    <div class="sp-card sp-card-credits">
        <p style="text-align: center; opacity: 0.7;">
            Developed by <strong>Kr4v3n</strong> | Version <?php echo SP_VERSION; ?>
        </p>
    </div>
    <?php
}
