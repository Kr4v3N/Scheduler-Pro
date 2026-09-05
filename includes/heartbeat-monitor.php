<?php
/**
 * HEARTBEAT MONITOR - Scheduler Pro v2.6
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
     * Minimum delay between two alert emails, to avoid spamming the
     * recipient while the scheduler stays critical.
     */
    const ALERT_EMAIL_THROTTLE_HOURS = 24;

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
                'message' => __('No run recorded', 'scheduler-pro'),
                'description' => __('The scheduler has never run yet, or was just installed.', 'scheduler-pro'),
                'action' => null
            );
        }

        $hours_since_last_run = (time() - $last_run) / 3600;
        $last_run_date = get_option('sp_last_cron_date', __('Unknown', 'scheduler-pro'));

        // OK status (< 25h)
        if ($hours_since_last_run < self::ALERT_THRESHOLD_HOURS) {
            return array(
                'status' => 'healthy',
                'level' => 'success',
                'message' => __('Scheduler running normally', 'scheduler-pro'),
                'description' => sprintf(
                    /* translators: 1: last run date/time, 2: human-readable time elapsed since then */
                    __('Last run: %1$s (%2$s ago)', 'scheduler-pro'),
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
            'message' => '⚠️ ' . __('Scheduler inactive!', 'scheduler-pro'),
            'description' => sprintf(
                /* translators: 1: last run date/time, 2: human-readable time elapsed since then */
                __('Last run: %1$s (%2$s ago). WP-Cron does not appear to be working correctly.', 'scheduler-pro'),
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
                'message' => __('WP-Cron is disabled in wp-config.php', 'scheduler-pro'),
                'solution' => __('Set up a real server cron or re-enable WP-Cron', 'scheduler-pro'),
            );
        }

        // 2. Check whether the cron is scheduled
        $next_scheduled = self::get_next_scheduled();
        if (!$next_scheduled) {
            $issues[] = array(
                'type' => 'no_cron_scheduled',
                'severity' => 'critical',
                'message' => __('No scheduled task found', 'scheduler-pro'),
                'solution' => __('Deactivate then reactivate the plugin', 'scheduler-pro'),
            );
        }

        // 3. Check overall health
        $health = self::check_wp_cron_health();
        if ($health['status'] === 'critical') {
            $issues[] = array(
                'type' => 'cron_not_running',
                'severity' => 'critical',
                'message' => $health['message'],
                'solution' => __('Check that your site is receiving traffic, or set up a server cron', 'scheduler-pro'),
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
                    'message' => sprintf(
                        /* translators: %d: number of stuck tasks detected */
                        __('%d stuck task(s) detected', 'scheduler-pro'),
                        $stuck_tasks
                    ),
                    'solution' => __('Run the automatic cleanup or restart the scheduler', 'scheduler-pro'),
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
                'message' => sprintf(
                    /* translators: %s: PHP memory_limit value, e.g. "64M" */
                    __('Limited PHP memory: %s', 'scheduler-pro'),
                    $memory_limit
                ),
                'solution' => __('Increase memory_limit to at least 128M in php.ini', 'scheduler-pro'),
            );
        }

        return $issues;
    }

    /**
     * Send an email alert when the scheduler is critical (see
     * check_wp_cron_health()), throttled to at most one email every
     * ALERT_EMAIL_THROTTLE_HOURS.
     *
     * IMPORTANT: hooked on admin_init, not on the plugin's own cron
     * events - if WP-Cron itself is what's broken (the exact situation
     * this alert exists to report), a cron-triggered check would never
     * fire. Running on admin_init instead means the very next admin page
     * load after things break will send the alert.
     */
    public static function maybe_send_alert_email() {
        if (get_option('sp_email_alerts', '1') !== '1') {
            return;
        }

        $health = self::check_wp_cron_health();
        if ($health['status'] !== 'critical') {
            return;
        }

        $last_sent = (int) get_option('sp_last_alert_sent', 0);
        $throttle_seconds = self::ALERT_EMAIL_THROTTLE_HOURS * HOUR_IN_SECONDS;

        if ($last_sent && (time() - $last_sent) < $throttle_seconds) {
            return;
        }

        $to = get_option('sp_alert_email');
        if (empty($to)) {
            $to = get_option('admin_email');
        }

        $subject = sprintf(
            /* translators: %s: site name */
            __('[%s] Scheduler Pro: scheduler inactive', 'scheduler-pro'),
            get_bloginfo('name')
        );
        $body = sprintf(
            /* translators: 1: alert threshold in hours, 2: health description, 3: URL to the Monitoring tab */
            __("Scheduler Pro has not run in over %1\$d hours.\n\n%2\$s\n\nCheck the Monitoring tab: %3\$s", 'scheduler-pro'),
            self::ALERT_THRESHOLD_HOURS,
            $health['description'],
            admin_url('admin.php?page=scheduler-pro&tab=monitoring')
        );

        $sent = wp_mail($to, $subject, $body);

        if ($sent) {
            update_option('sp_last_alert_sent', time());
            sp_log("📧 Alert email sent to {$to} (scheduler critical)", 'WARNING');
        } else {
            sp_log("❌ Failed to send the alert email to {$to}", 'ERROR');
        }
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
            <h3 style="margin: 10px 0;">💓 <?php esc_html_e('Scheduler Status', 'scheduler-pro'); ?></h3>

            <p><strong><?php esc_html_e('Status:', 'scheduler-pro'); ?></strong> <?php echo esc_html($health['message']); ?></p>
            <p><?php echo esc_html($health['description']); ?></p>

            <?php if ($next): ?>
                <p>
                    <strong><?php esc_html_e('Next run:', 'scheduler-pro'); ?></strong>
                    <?php echo esc_html($next['date']); ?>
                    <?php
                    printf(
                        /* translators: %s: human-readable time until the next run, e.g. "3 hours" */
                        esc_html__('(in %s)', 'scheduler-pro'),
                        esc_html($next['human'])
                    );
                    ?>
                </p>
            <?php endif; ?>

            <?php if (!empty($issues)): ?>
                <hr style="margin: 15px 0;">
                <h4 style="margin: 10px 0;">⚠️ <?php esc_html_e('Issues Detected', 'scheduler-pro'); ?></h4>
                <ul style="margin: 10px 0; padding-left: 20px;">
                    <?php foreach ($issues as $issue): ?>
                        <li>
                            <strong><?php echo esc_html($issue['message']); ?></strong><br>
                            <em>
                                <?php
                                printf(
                                    /* translators: %s: suggested solution to the detected issue */
                                    esc_html__('Solution: %s', 'scheduler-pro'),
                                    esc_html($issue['solution'])
                                );
                                ?>
                            </em>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if ($health['action'] === 'configure_real_cron'): ?>
                <p style="margin-top: 15px;">
                    <a href="https://developer.wordpress.org/plugins/cron/hooking-wp-cron-into-the-system-task-scheduler/"
                       target="_blank"
                       class="button button-primary">
                        📖 <?php esc_html_e('Guide: Set Up a Real Server Cron', 'scheduler-pro'); ?>
                    </a>

                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=scheduler-pro&action=test_cron'), 'sp_test_cron_nonce')); ?>"
                       class="button">
                        🔧 <?php esc_html_e('Test WP-Cron', 'scheduler-pro'); ?>
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
                            <strong><?php esc_html_e('Next run:', 'scheduler-pro'); ?></strong> <?php echo esc_html($next['date']); ?>
                        </p>
                    <?php endif; ?>
                </div>

                <?php if ($health['action'] === 'configure_real_cron'): ?>
                    <div>
                        <a href="https://developer.wordpress.org/plugins/cron/hooking-wp-cron-into-the-system-task-scheduler/"
                           target="_blank"
                           class="button button-small"
                           style="white-space: nowrap;">
                            📖 <?php esc_html_e('Cron Guide', 'scheduler-pro'); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Manually test WP-Cron
     *
     * Read-only diagnostic: this NEVER runs the scheduling engine (it used
     * to, which meant a button labelled "Test" rewrote every future post's
     * date - use the "Run Scheduling Now" button in Settings for that).
     * What it checks:
     * 1. Both plugin cron events are actually scheduled.
     * 2. DISABLE_WP_CRON is not silently blocking automatic triggering.
     * 3. Pings wp-cron.php (non-blocking) so any overdue event fires now.
     */
    public static function test_cron_execution() {
        sp_log("🧪 Manual WP-Cron test triggered", 'TEST');

        // Record the test time
        update_option('sp_last_manual_test', time());

        // 1. Are the two plugin events scheduled?
        $missing = array();
        $schedule_next = wp_next_scheduled('sp_daily_schedule_event');
        $cleanup_next  = wp_next_scheduled('sp_daily_cleanup');

        if (!$schedule_next) {
            $missing[] = 'sp_daily_schedule_event';
        }
        if (!$cleanup_next) {
            $missing[] = 'sp_daily_cleanup';
        }

        if (!empty($missing)) {
            return array(
                'success' => false,
                'message' => sprintf(
                    /* translators: %s: comma-separated list of cron event names */
                    __('Cron event(s) not scheduled: %s. Deactivate then reactivate the plugin to recreate them.', 'scheduler-pro'),
                    implode(', ', $missing)
                )
            );
        }

        // 2. Is WP-Cron disabled? The events exist, but nothing will trigger
        // them on page loads: only a real server cron calling wp-cron.php
        // will. The plugin cannot verify that from the inside, so warn.
        if (self::is_wp_cron_disabled()) {
            return array(
                'success' => false,
                'message' => __('WP-Cron is disabled (DISABLE_WP_CRON): the events are scheduled, but WordPress will not trigger them automatically. Make sure a real server cron calls wp-cron.php regularly.', 'scheduler-pro')
            );
        }

        // 3. Nudge wp-cron.php so any overdue event fires immediately
        // (non-blocking: reachability issues would surface as a missed run,
        // which the health check above already reports).
        wp_remote_post(site_url('wp-cron.php?doing_wp_cron'), array(
            'timeout'   => 1,
            'blocking'  => false,
            'sslverify' => false,
        ));

        return array(
            'success' => true,
            'message' => sprintf(
                /* translators: 1: next scheduling run date, 2: next cleanup run date */
                __('WP-Cron OK. Next scheduling run: %1$s. Next cleanup: %2$s. A wp-cron.php request was triggered to run any overdue event now.', 'scheduler-pro'),
                wp_date('Y-m-d H:i:s', $schedule_next),
                wp_date('Y-m-d H:i:s', $cleanup_next)
            )
        );
    }

    /**
     * WordPress Dashboard Widget
     */
    public static function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'sp_heartbeat_dashboard',
            '💓 ' . __('Scheduler Pro - Monitoring', 'scheduler-pro'),
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
                <h4 style="margin: 0 0 5px 0; color: #2271b1;"><?php esc_html_e('Posts Awaiting Publication', 'scheduler-pro'); ?></h4>
                <p style="font-size: 32px; margin: 0; font-weight: bold;"><?php echo esc_html($total_scheduled); ?></p>
            </div>

            <div style="padding: 15px; background: <?php echo $health['level'] === 'success' ? '#d4edda' : '#f8d7da'; ?>; border-radius: 8px; text-align: center;">
                <h4 style="margin: 0 0 5px 0;"><?php esc_html_e('Cron Status', 'scheduler-pro'); ?></h4>
                <p style="font-size: 24px; margin: 0; font-weight: bold;">
                    <?php echo $health['level'] === 'success' ? '✅ ' . esc_html__('OK', 'scheduler-pro') : '❌ ' . esc_html__('Issue', 'scheduler-pro'); ?>
                </p>
            </div>

            <?php if (!empty($stats)): ?>
                <div style="grid-column: span 2; padding: 15px; background: #fff3cd; border-radius: 8px;">
                    <h4 style="margin: 0 0 10px 0;">📊 <?php esc_html_e('Task Queue', 'scheduler-pro'); ?></h4>
                    <div style="display: flex; justify-content: space-around;">
                        <div>
                            <strong><?php esc_html_e('Pending:', 'scheduler-pro'); ?></strong> <?php echo esc_html($stats['pending']); ?>
                        </div>
                        <div>
                            <strong><?php esc_html_e('Running:', 'scheduler-pro'); ?></strong> <?php echo esc_html($stats['running']); ?>
                        </div>
                        <div>
                            <strong><?php esc_html_e('Completed:', 'scheduler-pro'); ?></strong> <?php echo esc_html($stats['completed']); ?>
                        </div>
                        <div>
                            <strong><?php esc_html_e('Failed:', 'scheduler-pro'); ?></strong> <?php echo esc_html($stats['failed']); ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <p style="text-align: center; margin-top: 15px;">
            <a href="<?php echo admin_url('admin.php?page=scheduler-pro'); ?>" class="button button-primary">
                ⚙️ <?php esc_html_e('Go to Settings', 'scheduler-pro'); ?>
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

// Check whether a critical-status alert email needs sending on every admin
// page load (see maybe_send_alert_email() docblock for why not on cron)
add_action('admin_init', array('SP_Heartbeat_Monitor', 'maybe_send_alert_email'));

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
            wp_die(esc_html__('Access denied', 'scheduler-pro'));
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
