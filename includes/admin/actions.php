<?php
/**
 * ADMIN: ACTION HANDLING - Scheduler Pro v2.5
 *
 * Processes form submissions (saving settings, manual run) and the
 * manual cron test result (includes/heartbeat-monitor.php redirects to
 * ?test_result=...) before any HTML is rendered on the admin page.
 */

if (!defined('ABSPATH')) exit;

function sp_handle_admin_actions() {
    $message = null;

    if (isset($_POST['sp_save_settings'])) {
        check_admin_referer('sp_settings_nonce');

        update_option('sp_posts_per_day', max(1, min(50, intval($_POST['sp_posts_per_day']))));
        update_option('sp_start_hour', max(0, min(23, intval($_POST['sp_start_hour']))));
        update_option('sp_end_hour', max(0, min(23, intval($_POST['sp_end_hour']))));
        update_option('sp_auto_mode', isset($_POST['sp_auto_mode']) ? '1' : '0');
        update_option('sp_force_replan', isset($_POST['sp_force_replan']) ? '1' : '0');

        $message = array('type' => 'success', 'text' => '✅ Settings saved successfully!');
    }

    if (isset($_POST['sp_run_now'])) {
        check_admin_referer('sp_run_nonce');

        $result = sp_process_scheduling();

        if ($result && $result['success']) {
            $message = array(
                'type' => 'success',
                'text' => "✅ Scheduling complete! {$result['processed']} posts processed."
            );
        } else {
            $message = array(
                'type' => 'error',
                'text' => "❌ Error while scheduling: " . ($result['message'] ?? 'Unknown error')
            );
        }
    }

    // Result of the manual WP-Cron test (see includes/heartbeat-monitor.php,
    // 'test_cron' action, which redirects here with these two parameters)
    if (isset($_GET['test_result']) && isset($_GET['page']) && $_GET['page'] === 'scheduler-pro') {
        $message = array(
            'type' => $_GET['test_result'] === 'success' ? 'success' : 'error',
            'text' => isset($_GET['test_message']) ? sanitize_text_field(wp_unslash($_GET['test_message'])) : 'Test complete.',
        );
    }

    return $message;
}
