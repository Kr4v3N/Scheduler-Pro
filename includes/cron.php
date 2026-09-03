<?php
if (!defined('ABSPATH')) exit;

/**
 * Lien entre l'événement planifié et la fonction de traitement
 */
add_action('sp_daily_schedule_event', 'sp_run_automated_cron');

function sp_run_automated_cron() {
    // On vérifie si l'option d'automatisation est cochée dans l'admin
    $is_enabled = get_option('sp_auto_mode', '0');

    if ($is_enabled === '1') {
        // On appelle le moteur de scan défini dans engine.php
        if (function_exists('sp_process_scheduling')) {
            sp_process_scheduling();
        }
    }
}