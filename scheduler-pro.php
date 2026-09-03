<?php
/**
 * Plugin Name: Scheduler Pro
 * Description: Automatic, human-like post scheduling with an advanced monitoring system.
 * Version: 2.6.1
 * Author: Kr4v3n
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Text Domain: scheduler-pro
 */

if (!defined('ABSPATH')) exit;

// Check the PHP version
if (version_compare(PHP_VERSION, '7.4', '<')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>Scheduler Pro</strong> requires PHP 7.4 or higher. ';
        echo 'Your current version: ' . PHP_VERSION;
        echo '</p></div>';
    });
    return;
}

// Path constants
define('SP_VERSION', '2.6.1');
define('SP_PATH', plugin_dir_path(__FILE__));
define('SP_URL', plugin_dir_url(__FILE__));
define('SP_BASENAME', plugin_basename(__FILE__));

/**
 * Load the "core" files, independent from the admin.
 *
 * IMPORTANT: this function must stay idempotent (require_once) and must
 * be called both from sp_init_plugin() (plugins_loaded hook, the normal
 * case) AND explicitly from sp_activate_plugin(). By the time the
 * activation hook fires, plugins_loaded has already happened for this
 * request WITHOUT this plugin having been considered active yet (it is
 * in the process of becoming active): sp_init_plugin() therefore never
 * ran on this request, and the classes below wouldn't exist without
 * this explicit call — this was the cause of a bug where the queue
 * table was never created on activation.
 */
function sp_load_core_files() {
    require_once SP_PATH . 'includes/memory-guard.php';
    require_once SP_PATH . 'includes/logger.php';
    require_once SP_PATH . 'includes/time-helpers.php';
    require_once SP_PATH . 'includes/lock-manager.php';
    require_once SP_PATH . 'includes/database-manager.php';
    require_once SP_PATH . 'includes/human-time-generator.php';
    require_once SP_PATH . 'includes/slot-finder.php';
    require_once SP_PATH . 'includes/engine.php';
    require_once SP_PATH . 'includes/cron.php';
    require_once SP_PATH . 'includes/heartbeat-monitor.php';
}

/**
 * Load the main components
 */
function sp_init_plugin() {
    sp_load_core_files();

    // Admin interface
    if (is_admin()) {
        require_once SP_PATH . 'includes/admin/menu.php';
        require_once SP_PATH . 'includes/admin/actions.php';
        require_once SP_PATH . 'includes/admin/tab-settings.php';
        require_once SP_PATH . 'includes/admin/tab-monitoring.php';
        require_once SP_PATH . 'includes/admin/tab-logs.php';
        require_once SP_PATH . 'includes/admin/tab-about.php';
        require_once SP_PATH . 'includes/admin/meta-box.php';
    }

    // Load the translation
    load_plugin_textdomain('scheduler-pro', false, dirname(SP_BASENAME) . '/languages');
}
add_action('plugins_loaded', 'sp_init_plugin');

/**
 * Activation: initial setup
 */
register_activation_hook(__FILE__, 'sp_activate_plugin');
function sp_activate_plugin() {
    // See the docblock of sp_load_core_files() above.
    sp_load_core_files();

    sp_log("🎉 Scheduler Pro v" . SP_VERSION . " activated", 'INSTALL');

    SP_Database_Manager::create_tables();

    // First clear any old schedule to avoid duplicates
    wp_clear_scheduled_hook('sp_daily_schedule_event');
    wp_clear_scheduled_hook('sp_daily_cleanup');

    // Schedule the daily scheduling task at 00:30, SITE time
    // (not the PHP server's — see includes/time-helpers.php)
    if (!wp_next_scheduled('sp_daily_schedule_event')) {
        $start_time = sp_get_site_gmt_timestamp('tomorrow 00:30:00');
        wp_schedule_event($start_time, 'daily', 'sp_daily_schedule_event');
    }

    // Schedule the daily cleanup at 03:00, SITE time
    if (!wp_next_scheduled('sp_daily_cleanup')) {
        $cleanup_time = sp_get_site_gmt_timestamp('tomorrow 03:00:00');
        wp_schedule_event($cleanup_time, 'daily', 'sp_daily_cleanup');
    }

    // Default options
    add_option('sp_cadence_unit', 'day');
    add_option('sp_cadence_count', 3);
    add_option('sp_start_hour', 7);
    add_option('sp_end_hour', 20);
    add_option('sp_auto_mode', '1'); // Enabled by default
    add_option('sp_force_replan', '0');
    add_option('sp_excluded_categories', array());
    add_option('sp_email_alerts', '1'); // Enabled by default
    add_option('sp_alert_email', ''); // Empty = use the site's admin email

    // Record the installed version
    update_option('sp_version', SP_VERSION);

    // Success message
    set_transient('sp_activation_notice', true, 30);
}

/**
 * Deactivation: cleanup
 */
register_deactivation_hook(__FILE__, 'sp_deactivate_plugin');
function sp_deactivate_plugin() {
    // Unlike activation, deactivating an already-active plugin happens on
    // a request where plugins_loaded already loaded all of the plugin's
    // classes normally: no need for sp_load_core_files() here.
    if (function_exists('sp_log')) {
        sp_log("👋 Scheduler Pro v" . SP_VERSION . " deactivated", 'UNINSTALL');
    }

    // Clear the scheduled tasks
    wp_clear_scheduled_hook('sp_daily_schedule_event');
    wp_clear_scheduled_hook('sp_daily_cleanup');

    // Clear active locks
    if (class_exists('SP_Lock_Manager')) {
        SP_Lock_Manager::cleanup_all_locks();
    }
}

/**
 * Activation notice
 */
add_action('admin_notices', function() {
    if (get_transient('sp_activation_notice')) {
        ?>
        <div class="notice notice-success is-dismissible">
            <h3>🎉 Scheduler Pro v<?php echo SP_VERSION; ?> activated successfully!</h3>
            <p>
                <strong>What's new in this version:</strong>
                ✅ Flexible cadence (day/week/month)
                ✅ Category exclusion
                ✅ Preview before running
                ✅ Email alerts
                ✅ Calendar view
            </p>
            <p>
                <a href="<?php echo admin_url('admin.php?page=scheduler-pro'); ?>" class="button button-primary">
                    ⚙️ Configure Now
                </a>
                <a href="<?php echo admin_url('admin.php?page=scheduler-pro&tab=monitoring'); ?>" class="button">
                    📊 View Monitoring
                </a>
            </p>
        </div>
        <?php
        delete_transient('sp_activation_notice');
    }
});

/**
 * Link to the settings from the plugins list
 */
add_filter('plugin_action_links_' . SP_BASENAME, function($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=scheduler-pro') . '">⚙️ Settings</a>';
    $monitoring_link = '<a href="' . admin_url('admin.php?page=scheduler-pro&tab=monitoring') . '" style="color: #10b981; font-weight: bold;">📊 Monitoring</a>';

    array_unshift($links, $monitoring_link, $settings_link);
    return $links;
});

/**
 * Register the assets
 */
add_action('admin_enqueue_scripts', function($hook) {
    if (strpos($hook, 'scheduler-pro') === false) {
        return;
    }

    wp_enqueue_style(
        'sp-admin-style',
        SP_URL . 'assets/style.css',
        array(),
        SP_VERSION
    );

    wp_enqueue_script(
        'sp-admin-script',
        SP_URL . 'assets/script.js',
        array('jquery'),
        SP_VERSION,
        true
    );

    // Pass JS variables
    wp_localize_script('sp-admin-script', 'spData', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('sp_ajax_nonce'),
        'version' => SP_VERSION,
    ));
});

/**
 * Check for DB schema updates
 */
add_action('plugins_loaded', function() {
    $installed_version = get_option('sp_version', '0');

    if (version_compare($installed_version, SP_VERSION, '<')) {
        // Update needed
        sp_log("🔄 Updating from v{$installed_version} to v" . SP_VERSION, 'UPDATE');

        // Recreate the tables if needed
        if (class_exists('SP_Database_Manager')) {
            SP_Database_Manager::create_tables();
        }

        // Migrate the old flat "posts/day" setting to the new cadence
        // model (day/week/month + count), so existing installs keep their
        // exact current behavior after the upgrade.
        if (get_option('sp_cadence_unit', false) === false) {
            add_option('sp_cadence_unit', 'day');
            add_option('sp_cadence_count', (int) get_option('sp_posts_per_day', 3));
        }

        // Update the version
        update_option('sp_version', SP_VERSION);

        sp_log("✅ Update to v" . SP_VERSION . " complete", 'UPDATE');
    }
});

/**
 * AJAX action for real-time operations
 */
add_action('wp_ajax_sp_get_stats', function() {
    check_ajax_referer('sp_ajax_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Access denied');
    }

    $stats = array(
        'total_future' => (int) wp_count_posts()->future,
        'health' => SP_Heartbeat_Monitor::check_wp_cron_health(),
    );

    if (class_exists('SP_Database_Manager')) {
        $stats['queue'] = SP_Database_Manager::get_stats();
    }

    wp_send_json_success($stats);
});
