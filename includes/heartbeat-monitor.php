<?php
/**
 * HEARTBEAT MONITOR - Scheduler Pro v2.0
 * 
 * Système de surveillance de la santé du scheduler
 * 
 * Fonctionnalités :
 * - Vérification du WP-Cron
 * - Détection des tâches bloquées
 * - Alertes automatiques
 * - Suggestions de configuration
 */

if (!defined('ABSPATH')) exit;

class SP_Heartbeat_Monitor {
    
    /**
     * Seuil d'alerte (heures sans exécution)
     */
    const ALERT_THRESHOLD_HOURS = 25; // 25h = 1 jour + marge
    
    /**
     * Enregistrer un heartbeat (appelé à chaque exécution)
     */
    public static function ping() {
        $timestamp = time();
        update_option('sp_last_cron_run', $timestamp);
        update_option('sp_last_cron_date', current_time('Y-m-d H:i:s'));
        
        sp_log("💓 Heartbeat enregistré : " . current_time('Y-m-d H:i:s'), 'HEARTBEAT');
    }
    
    /**
     * Vérifier la santé du WP-Cron
     * 
     * @return array Résultat du check
     */
    public static function check_wp_cron_health() {
        $last_run = get_option('sp_last_cron_run');
        
        if (!$last_run) {
            return array(
                'status' => 'unknown',
                'level' => 'warning',
                'message' => 'Aucune exécution enregistrée',
                'description' => 'Le scheduler n\'a jamais été exécuté ou vient d\'être installé.',
                'action' => null
            );
        }
        
        $hours_since_last_run = (time() - $last_run) / 3600;
        $last_run_date = get_option('sp_last_cron_date', 'Inconnue');
        
        // État OK (< 25h)
        if ($hours_since_last_run < self::ALERT_THRESHOLD_HOURS) {
            return array(
                'status' => 'healthy',
                'level' => 'success',
                'message' => 'Scheduler opérationnel',
                'description' => sprintf(
                    'Dernière exécution : %s (il y a %s)',
                    $last_run_date,
                    human_time_diff($last_run, time())
                ),
                'action' => null
            );
        }
        
        // État CRITIQUE (> 25h)
        return array(
            'status' => 'critical',
            'level' => 'error',
            'message' => '⚠️ Scheduler inactif !',
            'description' => sprintf(
                'Dernière exécution : %s (il y a %s). Le WP-Cron ne semble pas fonctionner correctement.',
                $last_run_date,
                human_time_diff($last_run, time())
            ),
            'action' => 'configure_real_cron'
        );
    }
    
    /**
     * Vérifier si WP-Cron est désactivé
     * 
     * @return bool
     */
    public static function is_wp_cron_disabled() {
        return defined('DISABLE_WP_CRON') && DISABLE_WP_CRON === true;
    }
    
    /**
     * Vérifier la prochaine exécution planifiée
     * 
     * @return array|null Infos sur la prochaine exécution
     */
    public static function get_next_scheduled() {
        $timestamp = wp_next_scheduled('sp_daily_schedule_event');
        
        if (!$timestamp) {
            return null;
        }
        
        return array(
            'timestamp' => $timestamp,
            'date' => date('Y-m-d H:i:s', $timestamp),
            'human' => human_time_diff(time(), $timestamp),
            'in_future' => $timestamp > time(),
        );
    }
    
    /**
     * Diagnostiquer les problèmes potentiels
     * 
     * @return array Liste des problèmes détectés
     */
    public static function diagnose_issues() {
        $issues = array();
        
        // 1. Vérifier si WP-Cron est désactivé
        if (self::is_wp_cron_disabled()) {
            $issues[] = array(
                'type' => 'wp_cron_disabled',
                'severity' => 'critical',
                'message' => 'WP-Cron est désactivé dans wp-config.php',
                'solution' => 'Configurez un vrai cron serveur ou réactivez WP-Cron',
            );
        }
        
        // 2. Vérifier si le cron est planifié
        $next_scheduled = self::get_next_scheduled();
        if (!$next_scheduled) {
            $issues[] = array(
                'type' => 'no_cron_scheduled',
                'severity' => 'critical',
                'message' => 'Aucune tâche planifiée trouvée',
                'solution' => 'Désactivez puis réactivez le plugin',
            );
        }
        
        // 3. Vérifier la santé globale
        $health = self::check_wp_cron_health();
        if ($health['status'] === 'critical') {
            $issues[] = array(
                'type' => 'cron_not_running',
                'severity' => 'critical',
                'message' => $health['message'],
                'solution' => 'Vérifiez que votre site reçoit du trafic ou configurez un cron serveur',
            );
        }
        
        // 4. Vérifier les tâches bloquées (si table existe)
        global $wpdb;
        $table_name = $wpdb->prefix . 'scheduler_queue';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
            $stuck_tasks = $wpdb->get_var("
                SELECT COUNT(*)
                FROM $table_name
                WHERE status = 'running'
                AND updated_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)
            ");
            
            if ($stuck_tasks > 0) {
                $issues[] = array(
                    'type' => 'stuck_tasks',
                    'severity' => 'warning',
                    'message' => "{$stuck_tasks} tâche(s) bloquée(s) détectée(s)",
                    'solution' => 'Exécutez le nettoyage automatique ou redémarrez le scheduler',
                );
            }
        }
        
        // 5. Vérifier la mémoire disponible
        // IMPORTANT : ne pas faire un simple (int) cast, qui lit "1G" comme 1
        // (au lieu de 1024) — utiliser sp_convert_to_bytes() (includes/memory-guard.php),
        // déjà utilisé par le moteur pour la même conversion.
        $memory_limit = ini_get('memory_limit');
        $memory_limit_mb = sp_convert_to_bytes($memory_limit) / (1024 * 1024);

        if ($memory_limit_mb > 0 && $memory_limit_mb < 128) {
            $issues[] = array(
                'type' => 'low_memory',
                'severity' => 'warning',
                'message' => "Mémoire PHP limitée : {$memory_limit}",
                'solution' => 'Augmentez memory_limit à au moins 128M dans php.ini',
            );
        }
        
        return $issues;
    }
    
    /**
     * Widget admin pour afficher le statut
     */
    public static function render_status_widget() {
        $health = self::check_wp_cron_health();
        $next = self::get_next_scheduled();
        $issues = self::diagnose_issues();
        
        $notice_class = 'notice-' . $health['level'];
        ?>
        <div class="notice <?php echo esc_attr($notice_class); ?> is-dismissible">
            <h3 style="margin: 10px 0;">💓 État du Scheduler</h3>
            
            <p><strong>Statut :</strong> <?php echo esc_html($health['message']); ?></p>
            <p><?php echo esc_html($health['description']); ?></p>
            
            <?php if ($next): ?>
                <p>
                    <strong>Prochaine exécution :</strong> 
                    <?php echo esc_html($next['date']); ?>
                    (dans <?php echo esc_html($next['human']); ?>)
                </p>
            <?php endif; ?>
            
            <?php if (!empty($issues)): ?>
                <hr style="margin: 15px 0;">
                <h4 style="margin: 10px 0;">⚠️ Problèmes Détectés</h4>
                <ul style="margin: 10px 0; padding-left: 20px;">
                    <?php foreach ($issues as $issue): ?>
                        <li>
                            <strong><?php echo esc_html($issue['message']); ?></strong><br>
                            <em>Solution : <?php echo esc_html($issue['solution']); ?></em>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            
            <?php if ($health['action'] === 'configure_real_cron'): ?>
                <p style="margin-top: 15px;">
                    <a href="https://developer.wordpress.org/plugins/cron/hooking-wp-cron-into-the-system-task-scheduler/" 
                       target="_blank" 
                       class="button button-primary">
                        📖 Guide : Configurer un vrai Cron serveur
                    </a>
                    
                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=scheduler-pro&action=test_cron'), 'sp_test_cron_nonce')); ?>"
                       class="button">
                        🔧 Tester le WP-Cron
                    </a>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Afficher le statut dans la page admin du plugin
     */
    public static function render_inline_status() {
        $health = self::check_wp_cron_health();
        $next = self::get_next_scheduled();
        
        $icon = $health['level'] === 'success' ? '✅' : ($health['level'] === 'warning' ? '⚠️' : '❌');
        $color = $health['level'] === 'success' ? '#10b981' : ($health['level'] === 'warning' ? '#f59e0b' : '#ef4444');
        ?>
        <div style="background: <?php echo esc_attr($color); ?>20; border-left: 4px solid <?php echo esc_attr($color); ?>; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <div style="display: flex; align-items: center; gap: 15px;">
                <div style="font-size: 32px;"><?php echo $icon; ?></div>
                <div style="flex: 1;">
                    <h3 style="margin: 0 0 5px 0; color: <?php echo esc_attr($color); ?>;">
                        <?php echo esc_html($health['message']); ?>
                    </h3>
                    <p style="margin: 0; color: #666;">
                        <?php echo esc_html($health['description']); ?>
                    </p>
                    <?php if ($next): ?>
                        <p style="margin: 5px 0 0 0; font-size: 13px; color: #666;">
                            <strong>Prochaine exécution :</strong> <?php echo esc_html($next['date']); ?>
                        </p>
                    <?php endif; ?>
                </div>
                
                <?php if ($health['action'] === 'configure_real_cron'): ?>
                    <div>
                        <a href="https://developer.wordpress.org/plugins/cron/hooking-wp-cron-into-the-system-task-scheduler/" 
                           target="_blank" 
                           class="button button-small"
                           style="white-space: nowrap;">
                            📖 Guide Cron
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Tester manuellement le WP-Cron
     */
    public static function test_cron_execution() {
        sp_log("🧪 Test manuel du WP-Cron déclenché", 'TEST');
        
        // Enregistrer l'heure du test
        update_option('sp_last_manual_test', time());
        
        // Exécuter immédiatement la fonction de planification
        if (function_exists('sp_process_scheduling')) {
            $result = sp_process_scheduling();
            
            if ($result && $result['success']) {
                return array(
                    'success' => true,
                    'message' => "Test réussi ! {$result['processed']} articles planifiés."
                );
            } else {
                return array(
                    'success' => false,
                    'message' => "Échec du test : " . ($result['message'] ?? 'Erreur inconnue')
                );
            }
        }
        
        return array(
            'success' => false,
            'message' => 'Fonction de planification non disponible'
        );
    }
    
    /**
     * Dashboard Widget WordPress
     */
    public static function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'sp_heartbeat_dashboard',
            '💓 Scheduler Pro - Monitoring',
            array('SP_Heartbeat_Monitor', 'render_dashboard_content')
        );
    }
    
    /**
     * Contenu du widget Dashboard
     */
    public static function render_dashboard_content() {
        $health = self::check_wp_cron_health();
        $stats = array();
        
        // Récupérer les stats si la table existe
        if (class_exists('SP_Database_Manager')) {
            $stats = SP_Database_Manager::get_stats();
        }
        
        $total_scheduled = wp_count_posts()->future;
        
        ?>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 10px;">
            <div style="padding: 15px; background: #f0f6fb; border-radius: 8px; text-align: center;">
                <h4 style="margin: 0 0 5px 0; color: #2271b1;">Articles en attente</h4>
                <p style="font-size: 32px; margin: 0; font-weight: bold;"><?php echo esc_html($total_scheduled); ?></p>
            </div>
            
            <div style="padding: 15px; background: <?php echo $health['level'] === 'success' ? '#d4edda' : '#f8d7da'; ?>; border-radius: 8px; text-align: center;">
                <h4 style="margin: 0 0 5px 0;">État du Cron</h4>
                <p style="font-size: 24px; margin: 0; font-weight: bold;">
                    <?php echo $health['level'] === 'success' ? '✅ OK' : '❌ Problème'; ?>
                </p>
            </div>
            
            <?php if (!empty($stats)): ?>
                <div style="grid-column: span 2; padding: 15px; background: #fff3cd; border-radius: 8px;">
                    <h4 style="margin: 0 0 10px 0;">📊 Queue de Tâches</h4>
                    <div style="display: flex; justify-content: space-around;">
                        <div>
                            <strong>En attente :</strong> <?php echo esc_html($stats['pending']); ?>
                        </div>
                        <div>
                            <strong>En cours :</strong> <?php echo esc_html($stats['running']); ?>
                        </div>
                        <div>
                            <strong>Terminées :</strong> <?php echo esc_html($stats['completed']); ?>
                        </div>
                        <div>
                            <strong>Échecs :</strong> <?php echo esc_html($stats['failed']); ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <p style="text-align: center; margin-top: 15px;">
            <a href="<?php echo admin_url('admin.php?page=scheduler-pro'); ?>" class="button button-primary">
                ⚙️ Accéder aux Réglages
            </a>
        </p>
        <?php
    }
}

/**
 * Hooks
 */

// Enregistrer le heartbeat à chaque exécution du cron
add_action('sp_daily_schedule_event', array('SP_Heartbeat_Monitor', 'ping'));

// Ajouter le widget au dashboard WordPress
add_action('wp_dashboard_setup', array('SP_Heartbeat_Monitor', 'add_dashboard_widget'));

// Afficher le widget d'état dans l'admin
add_action('admin_notices', function() {
    $screen = get_current_screen();
    
    // Afficher uniquement sur la page du plugin
    if ($screen && $screen->id === 'toplevel_page_scheduler-pro') {
        return; // On affiche le statut inline sur cette page
    }
    
    // Afficher sur le dashboard et les pages d'articles
    if ($screen && in_array($screen->id, array('dashboard', 'edit-post', 'post'))) {
        $health = SP_Heartbeat_Monitor::check_wp_cron_health();
        
        // Afficher uniquement si problème critique
        if ($health['level'] === 'error') {
            SP_Heartbeat_Monitor::render_status_widget();
        }
    }
});

// Gérer l'action de test manuel
add_action('admin_init', function() {
    if (isset($_GET['action']) && $_GET['action'] === 'test_cron' && isset($_GET['page']) && $_GET['page'] === 'scheduler-pro') {
        if (!current_user_can('manage_options')) {
            wp_die('Accès refusé');
        }

        check_admin_referer('sp_test_cron_nonce');

        $result = SP_Heartbeat_Monitor::test_cron_execution();
        
        $redirect_url = add_query_arg(
            array(
                'page' => 'scheduler-pro',
                'test_result' => $result['success'] ? 'success' : 'error',
                'test_message' => urlencode($result['message'])
            ),
            admin_url('admin.php')
        );
        
        wp_redirect($redirect_url);
        exit;
    }
});