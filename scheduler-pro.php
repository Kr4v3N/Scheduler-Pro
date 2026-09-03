<?php
/**
 * Plugin Name: Scheduler Pro
 * Description: Optimisation humaine et automatique de la planification des articles avec système avancé de monitoring.
 * Version: 2.0
 * Author: Kr4v3n
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Text Domain: scheduler-pro
 */

if (!defined('ABSPATH')) exit;

// Vérification de la version PHP
if (version_compare(PHP_VERSION, '7.4', '<')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>Scheduler Pro</strong> nécessite PHP 7.4 ou supérieur. ';
        echo 'Votre version actuelle : ' . PHP_VERSION;
        echo '</p></div>';
    });
    return;
}

// Constantes de chemin
define('SP_VERSION', '2.0.0');
define('SP_PATH', plugin_dir_path(__FILE__));
define('SP_URL', plugin_dir_url(__FILE__));
define('SP_BASENAME', plugin_basename(__FILE__));

/**
 * Chargement des composants principaux
 */
function sp_init_plugin() {
    // Core
    require_once SP_PATH . 'includes/lock-manager.php';
    require_once SP_PATH . 'includes/database-manager.php';
    require_once SP_PATH . 'includes/engine.php';
    require_once SP_PATH . 'includes/cron.php';
    require_once SP_PATH . 'includes/heartbeat-monitor.php';
    
    // Interface Admin
    if (is_admin()) {
        require_once SP_PATH . 'includes/admin.php';
    }
    
    // Charger la traduction
    load_plugin_textdomain('scheduler-pro', false, dirname(SP_BASENAME) . '/languages');
}
add_action('plugins_loaded', 'sp_init_plugin');

/**
 * Activation : Configuration initiale
 */
register_activation_hook(__FILE__, 'sp_activate_plugin');
function sp_activate_plugin() {
    // Log de l'activation
    if (function_exists('sp_log')) {
        sp_log("🎉 Scheduler Pro v" . SP_VERSION . " activé", 'INSTALL');
    }
    
    // Créer les tables de base de données
    if (class_exists('SP_Database_Manager')) {
        SP_Database_Manager::create_tables();
    }
    
    // Nettoyer d'abord toute vieille planification pour éviter les doublons
    wp_clear_scheduled_hook('sp_daily_schedule_event');
    wp_clear_scheduled_hook('sp_daily_cleanup');
    
    // Planifier la tâche quotidienne de scheduling à 00:30
    if (!wp_next_scheduled('sp_daily_schedule_event')) {
        $start_time = strtotime('tomorrow 00:30:00');
        wp_schedule_event($start_time, 'daily', 'sp_daily_schedule_event');
    }
    
    // Planifier le nettoyage quotidien à 03:00
    if (!wp_next_scheduled('sp_daily_cleanup')) {
        $cleanup_time = strtotime('tomorrow 03:00:00');
        wp_schedule_event($cleanup_time, 'daily', 'sp_daily_cleanup');
    }
    
    // Options par défaut
    add_option('sp_posts_per_day', 3);
    add_option('sp_start_hour', 7);
    add_option('sp_end_hour', 20);
    add_option('sp_auto_mode', '1'); // Activé par défaut
    add_option('sp_force_replan', '0');
    add_option('sp_excluded_ids', '');
    
    // Marquer la version installée
    update_option('sp_version', SP_VERSION);
    
    // Message de succès
    set_transient('sp_activation_notice', true, 30);
}

/**
 * Désactivation : Nettoyage
 */
register_deactivation_hook(__FILE__, 'sp_deactivate_plugin');
function sp_deactivate_plugin() {
    // Log de la désactivation
    if (function_exists('sp_log')) {
        sp_log("👋 Scheduler Pro v" . SP_VERSION . " désactivé", 'UNINSTALL');
    }
    
    // Nettoyer les tâches planifiées
    wp_clear_scheduled_hook('sp_daily_schedule_event');
    wp_clear_scheduled_hook('sp_daily_cleanup');
    
    // Nettoyer les verrous actifs
    if (class_exists('SP_Lock_Manager')) {
        SP_Lock_Manager::cleanup_all_locks();
    }
}

/**
 * Notice d'activation
 */
add_action('admin_notices', function() {
    if (get_transient('sp_activation_notice')) {
        ?>
        <div class="notice notice-success is-dismissible">
            <h3>🎉 Scheduler Pro v<?php echo SP_VERSION; ?> activé avec succès !</h3>
            <p>
                <strong>Nouveautés v2.0 :</strong>
                ✅ Système de verrouillage anti-doublon
                ✅ Gestion intelligente de la mémoire
                ✅ Traitement par lots (batching)
                ✅ Générateur de dates ultra-humain
                ✅ Monitoring en temps réel
            </p>
            <p>
                <a href="<?php echo admin_url('admin.php?page=scheduler-pro'); ?>" class="button button-primary">
                    ⚙️ Configurer maintenant
                </a>
                <a href="<?php echo admin_url('admin.php?page=scheduler-pro&tab=monitoring'); ?>" class="button">
                    📊 Voir le Monitoring
                </a>
            </p>
        </div>
        <?php
        delete_transient('sp_activation_notice');
    }
});

/**
 * Lien vers les réglages dans la liste des plugins
 */
add_filter('plugin_action_links_' . SP_BASENAME, function($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=scheduler-pro') . '">⚙️ Réglages</a>';
    $monitoring_link = '<a href="' . admin_url('admin.php?page=scheduler-pro&tab=monitoring') . '" style="color: #10b981; font-weight: bold;">📊 Monitoring</a>';
    
    array_unshift($links, $monitoring_link, $settings_link);
    return $links;
});

/**
 * Enregistrer les assets
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
    
    // Passer des variables JS
    wp_localize_script('sp-admin-script', 'spData', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('sp_ajax_nonce'),
        'version' => SP_VERSION,
    ));
});

/**
 * Vérifier les mises à jour de schéma DB
 */
add_action('plugins_loaded', function() {
    $installed_version = get_option('sp_version', '0');
    
    if (version_compare($installed_version, SP_VERSION, '<')) {
        // Mise à jour nécessaire
        sp_log("🔄 Mise à jour de v{$installed_version} vers v" . SP_VERSION, 'UPDATE');
        
        // Recréer les tables si nécessaire
        if (class_exists('SP_Database_Manager')) {
            SP_Database_Manager::create_tables();
        }
        
        // Mettre à jour la version
        update_option('sp_version', SP_VERSION);
        
        sp_log("✅ Mise à jour terminée vers v" . SP_VERSION, 'UPDATE');
    }
});

/**
 * Action AJAX pour les opérations en temps réel
 */
add_action('wp_ajax_sp_get_stats', function() {
    check_ajax_referer('sp_ajax_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Accès refusé');
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