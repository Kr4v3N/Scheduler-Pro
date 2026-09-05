<?php
if (!defined('ABSPATH')) exit;

/**
 * Link between the scheduled event and the processing function
 */
add_action('sp_daily_schedule_event', 'sp_run_automated_cron');

if (!function_exists('sp_run_automated_cron')) {
    function sp_run_automated_cron() {
        // Check whether the automation option is enabled in the admin
        $is_enabled = get_option('sp_auto_mode', '0');

        if ($is_enabled === '1') {
            // Call the scan engine defined in engine.php
            if (function_exists('sp_process_scheduling')) {
                sp_process_scheduling();
            }
        }
    }
}
