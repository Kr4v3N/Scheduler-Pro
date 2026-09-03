<?php
/**
 * HEARTBEAT MONITOR - Scheduler Pro v2.5
 *
 * Scheduler health monitoring system
 *
 * Features:
 * - WP-Cron health check
 * - Stuck task detection
 * - Automatic alerts
 * - Configuration suggestions
 */

if (!defined('ABSPATH')) exit;

class SP_Heartbeat_Monitor {

    /**
     * Alert threshold (hours without a run)
     */
    const ALERT_THRESHOLD_HOURS = 25; // 25h = 1 day + margin

    /**
     * Record a heartbeat (called on every run)
     */
    public static function ping() {
        $timestamp = time();
        update_option('sp_last_cron_run', $timestamp);
        update_option('sp_last_cron_date', current_time('Y-m-d H:i:s'));

        sp_log("💓 Heartbeat recorded: " . current_time('Y-m-d H:i:s'), 'HEARTBEAT');
    }

    /**
     * Check WP-Cron health
     *
     * @return array Check result
     */
    public static function check_wp_cron_health() {
        $last_run = get_option('sp_last_cron_run');

        if (!$last_run) {
            return array(
                'status' => 'unknown',
                'level' => 'warning',
                'message' => 'No run recorded',
                'description' => 'The scheduler has never run yet, or was just installed.',
                'action' => null
            );
        }

        $hours_since_last_run = (time() - $last_run) / 3600;
        $last_run_date = get_option('sp_last_cron_date', 'Unknown');

        // OK status (< 25h)
        if ($hours_since_last_run < self::ALERT_THRESHOLD_HOURS) {
            return array(
                'status' => 'healthy',
                'level' => 'success',
                'message' => 'Scheduler running normally',
                'description' => sprintf(
                    'Last run: %s (%s ago)',
                    $last_run_date,
                    human_time_diff($last_run, time())
                ),
                'action' => null
            );
        }

        // CRITICAL status (> 25h)
        return array(
            'status' => 'critical',
            'level' => 'error',
            'message' => '⚠️ Scheduler inactive!',
            'description' => sprintf(
                'Last run: %s (%s ago). WP-Cron does not appear to be working correctly.',
                $last_run_date,
                human_time_diff($last_run, time())
            ),
            'action' => 'configure_real_cron'
        );
    }

    /**
     * Check whether WP-Cron is disabled
     *
     * @return bool
     */
    public static function is_wp_cron_disabled() {
        return defined('DISABLE_WP_CRON') && DISABLE_WP_CRON === true;
    }

    /**
     * Check the next scheduled run
     *
     * @return array|null Info about the next run
     */
    public static function get_next_scheduled() {
        $timestamp = wp_next_scheduled('sp_daily_schedule_event');

        if (!$timestamp) {
            return null;
        }

        return array(
            'timestamp' => $timestamp,
            'date' => date('Y-m-d H:i:s', $timestamp),
            'human' => human_time_diff(time(), $timestamp),
            'in_future' => $timestamp > time(),
        );
    }

    /**
     * Diagnose potential issues
     *
     * @return array List of detected issues
     */
    public static function diagnose_issues() {
        $issues = array();

        // 1. Check whether WP-Cron is disabled
        if (self::is_wp_cron_disabled()) {
            $issues[] = array(
                'type' => 'wp_cron_disabled',
                'severity' => 'critical',
                'message' => 'WP-Cron is disabled in wp-config.php',
                'solution' => 'Set up a real server cron or re-enable WP-Cron',
            );
        }

        // 2. Check whether the cron is scheduled
        $next_scheduled = self::get_next_scheduled();
        if (!$next_scheduled) {
            $issues[] = array(
                'type' => 'no_cron_scheduled',
                'severity' => 'critical',
                'message' => 'No scheduled task found',
                'solution' => 'Deactivate then reactivate the plugin',
            );
        }

        // 3. Check overall health
        $health = self::check_wp_cron_health();
        if ($health['status'] === 'critical') {
            $issues[] = array(
                'type' => 'cron_not_running',
                'severity' => 'critical',
                'message' => $health['message'],
                'solution' => 'Check that your site is receiving traffic, or set up a server cron',
            );
        }

        // 4. Check for stuck tasks (if the table exists)
        global $wpdb;
        $table_name = $wpdb->prefix . 'scheduler_queue';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
            $stuck_tasks = $wpdb->get_var("
                SELECT COUNT(*)
                FROM $table_name
                WHERE status = 'running'
                AND updated_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)
            ");

            if ($stuck_tasks > 0) {
                $issues[] = array(
                    'type' => 'stuck_tasks',
                    'severity' => 'warning',
                    'message' => "{$stuck_tasks} stuck task(s) detected",
                    'solution' => 'Run the automatic cleanup or restart the scheduler',
                );
            }
        }

        // 5. Check available memory
        // IMPORTANT: don't do a plain (int) cast, which reads "1G" as 1
        // (instead of 1024) — use sp_convert_to_bytes() (includes/memory-guard.php),
        // already used by the engine for the same conversion.
        $memory_limit = ini_get('memory_limit');
        $memory_limit_mb = sp_convert_to_bytes($memory_limit) / (1024 * 1024);

        if ($memory_limit_mb > 0 && $memory_limit_mb < 128) {
            $issues[] = array(
                'type' => 'low_memory',
                'severity' => 'warning',
                'message' => "Limited PHP memory: {$memory_limit}",
                'solution' => 'Increase memory_limit to at least 128M in php.ini',
            );
        }

        return $issues;
    }

    /**
     * Admin widget showing the status
     */
    public static function render_status_widget() {
        $health = self::check_wp_cron_health();
        $next = self::get_next_scheduled();
        $issues = self::diagnose_issues();

        $notice_class = 'notice-' . $health['level'];
        ?>
        <div class="notice <?php echo esc_attr($notice_class); ?> is-dismissible">
            <h3 style="margin: 10px 0;">💓 Scheduler Status</h3>

            <p><strong>Status:</strong> <?php echo esc_html($health['message']); ?></p>
            <p><?php echo esc_html($health['description']); ?></p>

            <?php if ($next): ?>
                <p>
                    <strong>Next run:</strong>
                    <?php echo esc_html($next['date']); ?>
                    (in <?php echo esc_html($next['human']); ?>)
                </p>
            <?php endif; ?>

            <?php if (!empty($issues)): ?>
                <hr style="margin: 15px 0;">
                <h4 style="margin: 10px 0;">⚠️ Issues Detected</h4>
                <ul style="margin: 10px 0; padding-left: 20px;">
                    <?php foreach ($issues as $issue): ?>
                        <li>
                            <strong><?php echo esc_html($issue['message']); ?></strong><br>
                            <em>Solution: <?php echo esc_html($issue['solution']); ?></em>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if ($health['action'] === 'configure_real_cron'): ?>
                <p style="margin-top: 15px;">
                    <a href="https://developer.wordpress.org/plugins/cron/hooking-wp-cron-into-the-system-task-scheduler/"
                       target="_blank"
                       class="button button-primary">
                        📖 Guide: Set Up a Real Server Cron
                    </a>

                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=scheduler-pro&action=test_cron'), 'sp_test_cron_nonce')); ?>"
                       class="button">
                        🔧 Test WP-Cron
                    </a>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Display the status on the plugin's admin page
     */
    public static function render_inline_status() {
        $health = self::check_wp_cron_health();
        $next = self::get_next_scheduled();

        $icon = $health['level'] === 'success' ? '✅' : ($health['level'] === 'warning' ? '⚠️' : '❌');
        $color = $health['level'] === 'success' ? '#10b981' : ($health['level'] === 'warning' ? '#f59e0b' : '#ef4444');
        ?>
        <div style="background: <?php echo esc_attr($color); ?>20; border-left: 4px solid <?php echo esc_attr($color); ?>; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <div style="display: flex; align-items: center; gap: 15px;">
                <div style="font-size: 32px;"><?php echo $icon; ?></div>
                <div style="flex: 1;">
                    <h3 style="margin: 0 0 5px 0; color: <?php echo esc_attr($color); ?>;">
                        <?php echo esc_html($health['message']); ?>
                    </h3>
                    <p style="margin: 0; color: #666;">
                        <?php echo esc_html($health['description']); ?>
                    </p>
                    <?php if ($next): ?>
                        <p style="margin: 5px 0 0 0; font-size: 13px; color: #666;">
                            <strong>Next run:</strong> <?php echo esc_html($next['date']); ?>
                        </p>
                    <?php endif; ?>
                </div>

                <?php if ($health['action'] === 'configure_real_cron'): ?>
                    <div>
                        <a href="https://developer.wordpress.org/plugins/cron/hooking-wp-cron-into-the-system-task-scheduler/"
                           target="_blank"
                           class="button button-small"
                           style="white-space: nowrap;">
                            📖 Cron Guide
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Manually test WP-Cron
     */
    public static function test_cron_execution() {
        sp_log("🧪 Manual WP-Cron test triggered", 'TEST');

        // Record the test time
        update_option('sp_last_manual_test', time());

        // Run the scheduling function immediately
        if (function_exists('sp_process_scheduling')) {
            $result = sp_process_scheduling();

            if ($result && $result['success']) {
                return array(
                    'success' => true,
                    'message' => "Test succeeded! {$result['processed']} posts scheduled."
                );
            } else {
                return array(
                    'success' => false,
                    'message' => "Test failed: " . ($result['message'] ?? 'Unknown error')
                );
            }
        }

        return array(
            'success' => false,
            'message' => 'Scheduling function unavailable'
        );
    }

    /**
     * WordPress Dashboard Widget
     */
    public static function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'sp_heartbeat_dashboard',
            '💓 Scheduler Pro - Monitoring',
            array('SP_Heartbeat_Monitor', 'render_dashboard_content')
        );
    }

    /**
     * Dashboard widget content
     */
    public static function render_dashboard_content() {
        $health = self::check_wp_cron_health();
        $stats = array();

        // Get the stats if the table exists
        if (class_exists('SP_Database_Manager')) {
            $stats = SP_Database_Manager::get_stats();
        }

        $total_scheduled = wp_count_posts()->future;

        ?>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 10px;">
            <div style="padding: 15px; background: #f0f6fb; border-radius: 8px; text-align: center;">
                <h4 style="margin: 0 0 5px 0; color: #2271b1;">Posts Awaiting Publication</h4>
                <p style="font-size: 32px; margin: 0; font-weight: bold;"><?php echo esc_html($total_scheduled); ?></p>
            </div>

            <div style="padding: 15px; background: <?php echo $health['level'] === 'success' ? '#d4edda' : '#f8d7da'; ?>; border-radius: 8px; text-align: center;">
                <h4 style="margin: 0 0 5px 0;">Cron Status</h4>
                <p style="font-size: 24px; margin: 0; font-weight: bold;">
                    <?php echo $health['level'] === 'success' ? '✅ OK' : '❌ Issue'; ?>
                </p>
            </div>

            <?php if (!empty($stats)): ?>
                <div style="grid-column: span 2; padding: 15px; background: #fff3cd; border-radius: 8px;">
                    <h4 style="margin: 0 0 10px 0;">📊 Task Queue</h4>
                    <div style="display: flex; justify-content: space-around;">
                        <div>
                            <strong>Pending:</strong> <?php echo esc_html($stats['pending']); ?>
                        </div>
                        <div>
                            <strong>Running:</strong> <?php echo esc_html($stats['running']); ?>
                        </div>
                        <div>
                            <strong>Completed:</strong> <?php echo esc_html($stats['completed']); ?>
                        </div>
                        <div>
                            <strong>Failed:</strong> <?php echo esc_html($stats['failed']); ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <p style="text-align: center; margin-top: 15px;">
            <a href="<?php echo admin_url('admin.php?page=scheduler-pro'); ?>" class="button button-primary">
                ⚙️ Go to Settings
            </a>
        </p>
        <?php
    }
}

/**
 * Hooks
 */

// Record the heartbeat on every cron run
add_action('sp_daily_schedule_event', array('SP_Heartbeat_Monitor', 'ping'));

// Add the widget to the WordPress dashboard
add_action('wp_dashboard_setup', array('SP_Heartbeat_Monitor', 'add_dashboard_widget'));

// Display the status widget in the admin
add_action('admin_notices', function() {
    $screen = get_current_screen();

    // Only display on the plugin's own page
    if ($screen && $screen->id === 'toplevel_page_scheduler-pro') {
        return; // The inline status is shown on that page instead
    }

    // Display on the dashboard and post screens
    if ($screen && in_array($screen->id, array('dashboard', 'edit-post', 'post'))) {
        $health = SP_Heartbeat_Monitor::check_wp_cron_health();

        // Only display if there's a critical issue
        if ($health['level'] === 'error') {
            SP_Heartbeat_Monitor::render_status_widget();
        }
    }
});

// Handle the manual test action
add_action('admin_init', function() {
    if (isset($_GET['action']) && $_GET['action'] === 'test_cron' && isset($_GET['page']) && $_GET['page'] === 'scheduler-pro') {
        if (!current_user_can('manage_options')) {
            wp_die('Access denied');
        }

        check_admin_referer('sp_test_cron_nonce');

        $result = SP_Heartbeat_Monitor::test_cron_execution();

        $redirect_url = add_query_arg(
            array(
                'page' => 'scheduler-pro',
                'test_result' => $result['success'] ? 'success' : 'error',
                'test_message' => urlencode($result['message'])
            ),
            admin_url('admin.php')
        );

        wp_redirect($redirect_url);
        exit;
    }
});
