<?php
/**
 * Scheduler Pro uninstall file
 * Fully cleans up the database when the plugin is removed.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// 1. Remove all configuration and state options
$options = array(
    'sp_posts_per_day', // legacy, pre-2.6.0
    'sp_cadence_unit',
    'sp_cadence_count',
    'sp_auto_mode',
    'sp_start_hour',
    'sp_end_hour',
    'sp_force_replan',
    'sp_excluded_categories',
    'sp_email_alerts',
    'sp_alert_email',
    'sp_last_alert_sent',
    'sp_version',
    'sp_db_version',
    'sp_last_cron_run',
    'sp_last_cron_date',
    'sp_last_manual_test',
);

foreach ($options as $option) {
    delete_option($option);
}

// 2. Remove the scheduled tasks (Cron) - BOTH events
wp_clear_scheduled_hook('sp_daily_schedule_event');
wp_clear_scheduled_hook('sp_daily_cleanup');

// 3. Clean up active locks (sp_lock_* options)
$wpdb->query($wpdb->prepare(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
    $wpdb->esc_like('sp_lock_') . '%'
));

// 4. Clean up post metadata
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_is_smart_scheduled'");
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_sp_lock_planning'");

// 5. Drop the queue table
$table_name = $wpdb->prefix . 'scheduler_queue';
$wpdb->query("DROP TABLE IF EXISTS {$table_name}");

// 6. Remove the protected log directory (uploads/scheduler-pro-logs/)
$upload_dir = wp_upload_dir();
$log_dir = trailingslashit($upload_dir['basedir']) . 'scheduler-pro-logs/';

if (is_dir($log_dir)) {
    $files = glob($log_dir . '*');
    if ($files) {
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }
    @rmdir($log_dir);
}
