<?php
/**
 * ADMIN: MENU & MAIN PAGE - Scheduler Pro v2.5
 *
 * Menu registration, page shell (header, tabs) and routing to the right
 * tab. Each tab's rendering lives in its own file (tab-*.php in this
 * same directory).
 */

if (!defined('ABSPATH')) exit;

add_action('admin_menu', function() {
    add_menu_page(
        'Scheduler Pro',
        'Scheduler Pro',
        'manage_options',
        'scheduler-pro',
        'sp_admin_page_render',
        'dashicons-clock',
        80
    );
});

function sp_admin_page_render() {
    if (!current_user_can('manage_options')) {
        wp_die('Access denied');
    }

    $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'settings';

    $message = sp_handle_admin_actions();

    ?>
    <div class="wrap sp-admin-wrap">
        <div class="sp-header">
            <h1 class="sp-title">
                <span class="dashicons dashicons-clock"></span>
                Scheduler Pro
                <span class="sp-version">v<?php echo SP_VERSION; ?></span>
            </h1>

            <?php if (SP_Heartbeat_Monitor::check_wp_cron_health()['level'] === 'success'): ?>
                <div class="sp-status-badge sp-status-ok">
                    <span class="dashicons dashicons-yes-alt"></span>
                    Scheduler Running
                </div>
            <?php else: ?>
                <div class="sp-status-badge sp-status-error">
                    <span class="dashicons dashicons-warning"></span>
                    Issue Detected
                </div>
            <?php endif; ?>
        </div>

        <?php if ($message): ?>
            <div class="notice notice-<?php echo esc_attr($message['type']); ?> is-dismissible">
                <p><?php echo esc_html($message['text']); ?></p>
            </div>
        <?php endif; ?>

        <nav class="nav-tab-wrapper sp-nav-tabs">
            <a href="?page=scheduler-pro&tab=settings"
               class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                <span class="dashicons dashicons-admin-settings"></span>
                Settings
            </a>
            <a href="?page=scheduler-pro&tab=monitoring"
               class="nav-tab <?php echo $active_tab === 'monitoring' ? 'nav-tab-active' : ''; ?>">
                <span class="dashicons dashicons-chart-area"></span>
                Monitoring
            </a>
            <a href="?page=scheduler-pro&tab=logs"
               class="nav-tab <?php echo $active_tab === 'logs' ? 'nav-tab-active' : ''; ?>">
                <span class="dashicons dashicons-media-text"></span>
                Activity Log
            </a>
            <a href="?page=scheduler-pro&tab=about"
               class="nav-tab <?php echo $active_tab === 'about' ? 'nav-tab-active' : ''; ?>">
                <span class="dashicons dashicons-info"></span>
                About
            </a>
        </nav>

        <div class="sp-tab-content">
            <?php
            switch ($active_tab) {
                case 'monitoring':
                    sp_render_monitoring_tab();
                    break;

                case 'logs':
                    sp_render_logs_tab();
                    break;

                case 'about':
                    sp_render_about_tab();
                    break;

                case 'settings':
                default:
                    sp_render_settings_tab();
                    break;
            }
            ?>
        </div>
    </div>
    <?php
}
