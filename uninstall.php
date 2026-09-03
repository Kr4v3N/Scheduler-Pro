<?php
/**
 * Fichier de désinstallation de Scheduler Pro
 * Nettoie entièrement la base de données lors de la suppression du plugin.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// 1. Suppression de toutes les options de configuration et d'état
$options = array(
    'sp_posts_per_day',
    'sp_auto_mode',
    'sp_start_hour',
    'sp_end_hour',
    'sp_force_replan',
    'sp_version',
    'sp_db_version',
    'sp_last_cron_run',
    'sp_last_cron_date',
    'sp_last_manual_test',
);

foreach ($options as $option) {
    delete_option($option);
}

// 2. Suppression des tâches planifiées (Cron) - les DEUX événements
wp_clear_scheduled_hook('sp_daily_schedule_event');
wp_clear_scheduled_hook('sp_daily_cleanup');

// 3. Nettoyage des verrous actifs (options sp_lock_*)
$wpdb->query($wpdb->prepare(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
    $wpdb->esc_like('sp_lock_') . '%'
));

// 4. Nettoyage des métadonnées sur les articles
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_is_smart_scheduled'");
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_sp_lock_planning'");

// 5. Suppression de la table de queue
$table_name = $wpdb->prefix . 'scheduler_queue';
$wpdb->query("DROP TABLE IF EXISTS {$table_name}");

// 6. Suppression du répertoire de logs protégé (uploads/scheduler-pro-logs/)
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
