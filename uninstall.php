<?php
/**
 * Fichier de désinstallation de Scheduler Pro
 * Nettoie la base de données lors de la suppression du plugin.
 */

// Si la suppression n'est pas déclenchée par WordPress, on sort.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// 1. Suppression des options de configuration
delete_option('sp_posts_per_day');
delete_option('sp_excluded_ids');
delete_option('sp_auto_mode');
delete_option('sp_start_hour');
delete_option('sp_end_hour');

// 2. Suppression de la tâche planifiée (Cron)
wp_clear_scheduled_hook('sp_daily_schedule_event');

// 3. Nettoyage des métadonnées sur les articles (Marqueur de planification)
global $wpdb;
$wpdb->query(
    "DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_is_smart_scheduled'"
);