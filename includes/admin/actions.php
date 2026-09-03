<?php
/**
 * ADMIN : TRAITEMENT DES ACTIONS - Scheduler Pro v2.5
 *
 * Traite les soumissions de formulaire (sauvegarde des réglages,
 * exécution manuelle) et le résultat du test manuel du cron
 * (includes/heartbeat-monitor.php redirige vers ?test_result=...) avant
 * tout rendu HTML de la page admin.
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

        $message = array('type' => 'success', 'text' => '✅ Réglages sauvegardés avec succès !');
    }

    if (isset($_POST['sp_run_now'])) {
        check_admin_referer('sp_run_nonce');

        $result = sp_process_scheduling();

        if ($result && $result['success']) {
            $message = array(
                'type' => 'success',
                'text' => "✅ Planification terminée ! {$result['processed']} articles traités."
            );
        } else {
            $message = array(
                'type' => 'error',
                'text' => "❌ Erreur lors de la planification : " . ($result['message'] ?? 'Erreur inconnue')
            );
        }
    }

    // Résultat du test manuel du WP-Cron (voir includes/heartbeat-monitor.php,
    // action 'test_cron', qui redirige ici avec ces deux paramètres)
    if (isset($_GET['test_result']) && isset($_GET['page']) && $_GET['page'] === 'scheduler-pro') {
        $message = array(
            'type' => $_GET['test_result'] === 'success' ? 'success' : 'error',
            'text' => isset($_GET['test_message']) ? sanitize_text_field(wp_unslash($_GET['test_message'])) : 'Test terminé.',
        );
    }

    return $message;
}
