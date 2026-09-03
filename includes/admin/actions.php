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
    $preview = null;

    if (isset($_POST['sp_save_settings'])) {
        check_admin_referer('sp_settings_nonce');

        $cadence_unit = isset($_POST['sp_cadence_unit']) ? sanitize_text_field(wp_unslash($_POST['sp_cadence_unit'])) : 'day';
        if (!in_array($cadence_unit, array('day', 'week', 'month'), true)) {
            $cadence_unit = 'day';
        }
        update_option('sp_cadence_unit', $cadence_unit);
        update_option('sp_cadence_count', max(1, min(500, intval($_POST['sp_cadence_count']))));
        update_option('sp_start_hour', max(0, min(23, intval($_POST['sp_start_hour']))));
        update_option('sp_end_hour', max(0, min(23, intval($_POST['sp_end_hour']))));
        update_option('sp_auto_mode', isset($_POST['sp_auto_mode']) ? '1' : '0');
        update_option('sp_force_replan', isset($_POST['sp_force_replan']) ? '1' : '0');

        $excluded_categories = isset($_POST['sp_excluded_categories']) && is_array($_POST['sp_excluded_categories'])
            ? array_map('intval', $_POST['sp_excluded_categories'])
            : array();
        update_option('sp_excluded_categories', $excluded_categories);

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

    if (isset($_POST['sp_preview_run'])) {
        check_admin_referer('sp_run_nonce');

        $result = sp_process_scheduling(true);

        if ($result && $result['success']) {
            $preview = $result['preview'];
            $message = array(
                'type' => 'success',
                'text' => "👁️ Preview: {$result['processed']} post(s) would be scheduled. Nothing has been changed yet."
            );
        } else {
            $message = array(
                'type' => 'error',
                'text' => "❌ Error while building the preview: " . ($result['message'] ?? 'Unknown error')
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

    return array('message' => $message, 'preview' => $preview);
}
