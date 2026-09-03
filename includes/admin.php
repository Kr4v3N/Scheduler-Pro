<?php
/**
 * ADMIN INTERFACE - Scheduler Pro v2.0
 * 
 * Interface d'administration moderne avec :
 * - Système d'onglets (Réglages, Monitoring, Logs)
 * - Design épuré et responsive
 * - Statistiques en temps réel
 * - Visualisation de la distribution
 */

if (!defined('ABSPATH')) exit;

/**
 * Créer le menu principal
 */
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

/**
 * Rendu de la page principale
 */
function sp_admin_page_render() {
    // Vérifier les permissions
    if (!current_user_can('manage_options')) {
        wp_die('Accès refusé');
    }
    
    // Récupérer l'onglet actif
    $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'settings';
    
    // Traiter les actions
    $message = sp_handle_admin_actions();
    
    ?>
    <div class="wrap sp-admin-wrap">
        <!-- Header -->
        <div class="sp-header">
            <h1 class="sp-title">
                <span class="dashicons dashicons-clock"></span>
                Scheduler Pro
                <span class="sp-version">v<?php echo SP_VERSION; ?></span>
            </h1>
            
            <?php if (SP_Heartbeat_Monitor::check_wp_cron_health()['level'] === 'success'): ?>
                <div class="sp-status-badge sp-status-ok">
                    <span class="dashicons dashicons-yes-alt"></span>
                    Scheduler Opérationnel
                </div>
            <?php else: ?>
                <div class="sp-status-badge sp-status-error">
                    <span class="dashicons dashicons-warning"></span>
                    Problème Détecté
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Messages -->
        <?php if ($message): ?>
            <div class="notice notice-<?php echo esc_attr($message['type']); ?> is-dismissible">
                <p><?php echo $message['text']; ?></p>
            </div>
        <?php endif; ?>
        
        <!-- Tabs Navigation -->
        <nav class="nav-tab-wrapper sp-nav-tabs">
            <a href="?page=scheduler-pro&tab=settings" 
               class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                <span class="dashicons dashicons-admin-settings"></span>
                Réglages
            </a>
            <a href="?page=scheduler-pro&tab=monitoring" 
               class="nav-tab <?php echo $active_tab === 'monitoring' ? 'nav-tab-active' : ''; ?>">
                <span class="dashicons dashicons-chart-area"></span>
                Monitoring
            </a>
            <a href="?page=scheduler-pro&tab=logs" 
               class="nav-tab <?php echo $active_tab === 'logs' ? 'nav-tab-active' : ''; ?>">
                <span class="dashicons dashicons-media-text"></span>
                Journal d'Activité
            </a>
            <a href="?page=scheduler-pro&tab=about" 
               class="nav-tab <?php echo $active_tab === 'about' ? 'nav-tab-active' : ''; ?>">
                <span class="dashicons dashicons-info"></span>
                À Propos
            </a>
        </nav>
        
        <!-- Tab Content -->
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

/**
 * Gérer les actions (sauvegarde, exécution manuelle, etc.)
 */
function sp_handle_admin_actions() {
    $message = null;
    
    // Sauvegarde des réglages
    if (isset($_POST['sp_save_settings'])) {
        check_admin_referer('sp_settings_nonce');
        
        update_option('sp_posts_per_day', max(1, min(50, intval($_POST['sp_posts_per_day']))));
        update_option('sp_start_hour', max(0, min(23, intval($_POST['sp_start_hour']))));
        update_option('sp_end_hour', max(0, min(23, intval($_POST['sp_end_hour']))));
        update_option('sp_auto_mode', isset($_POST['sp_auto_mode']) ? '1' : '0');
        update_option('sp_force_replan', isset($_POST['sp_force_replan']) ? '1' : '0');
        
        $message = array('type' => 'success', 'text' => '✅ Réglages sauvegardés avec succès !');
    }
    
    // Exécution manuelle
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
    
    return $message;
}

/**
 * ONGLET : Réglages
 */
function sp_render_settings_tab() {
    $posts_per_day = get_option('sp_posts_per_day', 3);
    $start_hour = get_option('sp_start_hour', 7);
    $end_hour = get_option('sp_end_hour', 20);
    $auto_mode = get_option('sp_auto_mode', '1');
    $force_replan = get_option('sp_force_replan', '0');
    $total_future = (int) wp_count_posts()->future;
    
    ?>
    <div class="sp-settings-grid">
        <!-- Colonne Gauche : Réglages -->
        <div class="sp-col-main">
            <form method="post">
                <?php wp_nonce_field('sp_settings_nonce'); ?>
                
                <!-- Card : Simulateur -->
                <div class="sp-card">
                    <h2 class="sp-card-title">
                        <span class="dashicons dashicons-calculator"></span>
                        Simulateur de Stratégie
                    </h2>
                    
                    <div class="sp-info-box">
                        <strong><?php echo $total_future; ?></strong> articles en attente de planification
                    </div>
                    
                    <div class="sp-simulator">
                        <div class="sp-sim-input">
                            <label>Articles / jour</label>
                            <input type="number" 
                                   id="sp_posts_per_day" 
                                   name="sp_posts_per_day" 
                                   value="<?php echo $posts_per_day; ?>" 
                                   min="1" 
                                   max="50"
                                   class="sp-input-number">
                        </div>
                        
                        <div class="sp-sim-arrow">
                            <span class="dashicons dashicons-arrow-right-alt2"></span>
                        </div>
                        
                        <div class="sp-sim-output">
                            <label>Durée (Jours)</label>
                            <input type="number" 
                                   id="sp_duration_input" 
                                   value="<?php echo ceil($total_future / max(1, $posts_per_day)); ?>" 
                                   min="1"
                                   class="sp-input-number">
                        </div>
                    </div>
                    
                    <p class="sp-sim-result" id="sp-summary-text"></p>
                </div>
                
                <!-- Card : Horaires -->
                <div class="sp-card">
                    <h2 class="sp-card-title">
                        <span class="dashicons dashicons-clock"></span>
                        Configuration des Horaires
                    </h2>
                    
                    <div class="sp-form-row">
                        <div class="sp-form-col">
                            <label class="sp-label">Plage horaire de publication</label>
                            <div class="sp-time-range">
                                <span>De</span>
                                <input type="number" 
                                       name="sp_start_hour" 
                                       value="<?php echo $start_hour; ?>" 
                                       min="0" 
                                       max="23" 
                                       class="sp-input-time">
                                <span>h à</span>
                                <input type="number" 
                                       name="sp_end_hour" 
                                       value="<?php echo $end_hour; ?>" 
                                       min="0" 
                                       max="23" 
                                       class="sp-input-time">
                                <span>h</span>
                            </div>
                            <p class="sp-help-text">
                                Recommandé : 7h-20h pour une activité naturelle
                            </p>
                        </div>
                        
                        <div class="sp-form-col">
                            <label class="sp-label">Automatisation</label>
                            <label class="sp-checkbox-label">
                                <input type="checkbox" 
                                       name="sp_auto_mode" 
                                       value="1" 
                                       <?php checked($auto_mode, '1'); ?>>
                                <span>Activer la planification quotidienne automatique</span>
                            </label>
                            <p class="sp-help-text">
                                Exécution quotidienne à 00:30 via WP-Cron
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Card : Mode Grand Ménage -->
                <div class="sp-card sp-card-warning">
                    <h2 class="sp-card-title">
                        <span class="dashicons dashicons-image-rotate"></span>
                        Mode "Grand Ménage"
                    </h2>
                    
                    <label class="sp-checkbox-label">
                        <input type="checkbox" 
                               name="sp_force_replan" 
                               value="1" 
                               <?php checked($force_replan, '1'); ?>>
                        <strong>Forcer la réorganisation de TOUS les articles futurs</strong>
                    </label>
                    
                    <p class="sp-help-text">
                        ⚠️ <strong>Attention :</strong> Cette option réorganisera TOUS vos articles futurs depuis demain.
                        Les dates actuelles seront écrasées. Se désactive automatiquement après exécution.
                    </p>
                </div>
                
                <div class="sp-actions">
                    <button type="submit" 
                            name="sp_save_settings" 
                            class="button button-primary button-hero">
                        <span class="dashicons dashicons-saved"></span>
                        Sauvegarder les Réglages
                    </button>
                </div>
            </form>
            
            <!-- Action Manuelle -->
            <form method="post">
                <?php wp_nonce_field('sp_run_nonce'); ?>
                <div class="sp-card sp-card-action">
                    <h2 class="sp-card-title">
                        <span class="dashicons dashicons-controls-play"></span>
                        Exécution Manuelle
                    </h2>
                    <p>Lancer immédiatement la planification des articles (ne pas attendre le cron quotidien)</p>
                    <button type="submit" 
                            name="sp_run_now" 
                            class="button button-hero sp-btn-action">
                        🚀 Lancer la Planification Maintenant
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Colonne Droite : État du Système -->
        <div class="sp-col-sidebar">
            <?php SP_Heartbeat_Monitor::render_inline_status(); ?>
            
            <!-- Quick Stats -->
            <div class="sp-card sp-card-stats">
                <h3>📊 Statistiques Rapides</h3>
                <div class="sp-stat-item">
                    <span class="sp-stat-label">Articles futurs</span>
                    <span class="sp-stat-value"><?php echo $total_future; ?></span>
                </div>
                <div class="sp-stat-item">
                    <span class="sp-stat-label">Articles/jour</span>
                    <span class="sp-stat-value"><?php echo $posts_per_day; ?></span>
                </div>
                <div class="sp-stat-item">
                    <span class="sp-stat-label">Durée estimée</span>
                    <span class="sp-stat-value">
                        <?php echo ceil($total_future / max(1, $posts_per_day)); ?> jours
                    </span>
                </div>
            </div>
            
            <!-- Aide Rapide -->
            <div class="sp-card sp-card-help">
                <h3>💡 Aide Rapide</h3>
                <ul class="sp-help-list">
                    <li>
                        <strong>Mode Adhésif :</strong> Les nouveaux articles sont ajoutés à la suite
                    </li>
                    <li>
                        <strong>Mode Grand Ménage :</strong> Tout est réorganisé depuis demain
                    </li>
                    <li>
                        <strong>Verrouillage :</strong> Utilisez la meta box sur chaque article pour bloquer sa date
                    </li>
                </ul>
            </div>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        const total = <?php echo $total_future; ?>;
        const $perDay = $('#sp_posts_per_day');
        const $duration = $('#sp_duration_input');
        const $summary = $('#sp-summary-text');
        
        function updateSim() {
            const days = parseInt($duration.val()) || 1;
            const date = new Date();
            date.setDate(date.getDate() + days);
            
            $summary.html(`
                <strong>Action :</strong> ${$perDay.val()} articles/jour jusqu'au 
                <strong>${date.toLocaleDateString('fr-FR', {day:'numeric', month:'long', year:'numeric'})}</strong>
            `);
        }
        
        $perDay.on('input', function() {
            $duration.val(Math.ceil(total / (parseInt($perDay.val()) || 1)));
            updateSim();
        });
        
        $duration.on('input', function() {
            $perDay.val(Math.ceil(total / (parseInt($duration.val()) || 1)));
            updateSim();
        });
        
        updateSim();
    });
    </script>
    <?php
}

/**
 * ONGLET : Monitoring
 */
function sp_render_monitoring_tab() {
    $health = SP_Heartbeat_Monitor::check_wp_cron_health();
    $next = SP_Heartbeat_Monitor::get_next_scheduled();
    $issues = SP_Heartbeat_Monitor::diagnose_issues();
    
    // Stats de la queue
    $queue_stats = array();
    if (class_exists('SP_Database_Manager')) {
        $queue_stats = SP_Database_Manager::get_stats();
    }
    
    // Stats WordPress
    $total_future = (int) wp_count_posts()->future;
    $total_publish = (int) wp_count_posts()->publish;
    
    ?>
    <div class="sp-monitoring-grid">
        <!-- Stats Globales -->
        <div class="sp-stats-row">
            <div class="sp-stat-card">
                <div class="sp-stat-icon" style="background: #3b82f6;">
                    <span class="dashicons dashicons-calendar-alt"></span>
                </div>
                <div class="sp-stat-content">
                    <div class="sp-stat-value"><?php echo $total_future; ?></div>
                    <div class="sp-stat-label">Articles Planifiés</div>
                </div>
            </div>
            
            <div class="sp-stat-card">
                <div class="sp-stat-icon" style="background: #10b981;">
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <div class="sp-stat-content">
                    <div class="sp-stat-value"><?php echo $total_publish; ?></div>
                    <div class="sp-stat-label">Articles Publiés</div>
                </div>
            </div>
            
            <?php if (!empty($queue_stats)): ?>
            <div class="sp-stat-card">
                <div class="sp-stat-icon" style="background: #f59e0b;">
                    <span class="dashicons dashicons-backup"></span>
                </div>
                <div class="sp-stat-content">
                    <div class="sp-stat-value"><?php echo $queue_stats['pending'] ?? 0; ?></div>
                    <div class="sp-stat-label">Tâches en Attente</div>
                </div>
            </div>
            
            <div class="sp-stat-card">
                <div class="sp-stat-icon" style="background: <?php echo $queue_stats['failed'] > 0 ? '#ef4444' : '#6b7280'; ?>;">
                    <span class="dashicons dashicons-warning"></span>
                </div>
                <div class="sp-stat-content">
                    <div class="sp-stat-value"><?php echo $queue_stats['failed'] ?? 0; ?></div>
                    <div class="sp-stat-label">Tâches Échouées</div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- État du Scheduler -->
        <div class="sp-card">
            <h2 class="sp-card-title">
                <span class="dashicons dashicons-heart"></span>
                État du Scheduler
            </h2>
            
            <div class="sp-health-status sp-health-<?php echo $health['level']; ?>">
                <div class="sp-health-icon">
                    <?php if ($health['level'] === 'success'): ?>
                        <span class="dashicons dashicons-yes-alt"></span>
                    <?php else: ?>
                        <span class="dashicons dashicons-warning"></span>
                    <?php endif; ?>
                </div>
                <div class="sp-health-content">
                    <h3><?php echo esc_html($health['message']); ?></h3>
                    <p><?php echo esc_html($health['description']); ?></p>
                    
                    <?php if ($next): ?>
                        <p class="sp-next-run">
                            <strong>Prochaine exécution :</strong> 
                            <?php echo esc_html($next['date']); ?>
                            (dans <?php echo esc_html($next['human']); ?>)
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Problèmes Détectés -->
        <?php if (!empty($issues)): ?>
        <div class="sp-card sp-card-warning">
            <h2 class="sp-card-title">
                <span class="dashicons dashicons-flag"></span>
                Problèmes Détectés
            </h2>
            
            <?php foreach ($issues as $issue): ?>
                <div class="sp-issue-item sp-issue-<?php echo $issue['severity']; ?>">
                    <div class="sp-issue-icon">
                        <span class="dashicons dashicons-info"></span>
                    </div>
                    <div class="sp-issue-content">
                        <h4><?php echo esc_html($issue['message']); ?></h4>
                        <p><strong>Solution :</strong> <?php echo esc_html($issue['solution']); ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- Distribution Articles -->
        <?php sp_render_distribution_chart(); ?>
    </div>
    <?php
}

/**
 * Afficher le graphique de distribution
 */
function sp_render_distribution_chart() {
    global $wpdb;
    
    // Récupérer la distribution des 30 prochains jours
    $distribution = $wpdb->get_results("
        SELECT DATE(post_date) as date, COUNT(*) as count
        FROM {$wpdb->posts}
        WHERE post_status = 'future'
        AND post_type = 'post'
        AND post_date >= CURDATE()
        AND post_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        GROUP BY DATE(post_date)
        ORDER BY post_date
        LIMIT 30
    ", ARRAY_A);
    
    if (empty($distribution)) {
        return;
    }
    
    $max_count = max(array_column($distribution, 'count'));
    
    ?>
    <div class="sp-card">
        <h2 class="sp-card-title">
            <span class="dashicons dashicons-chart-bar"></span>
            Distribution des 30 Prochains Jours
        </h2>
        
        <div class="sp-distribution-chart">
            <?php foreach ($distribution as $day): ?>
                <?php
                $percentage = ($day['count'] / $max_count) * 100;
                $date_obj = new DateTime($day['date']);
                ?>
                <div class="sp-chart-bar" title="<?php echo $day['count']; ?> articles">
                    <div class="sp-chart-bar-fill" style="height: <?php echo $percentage; ?>%;"></div>
                    <div class="sp-chart-bar-label">
                        <?php echo $date_obj->format('d/m'); ?>
                    </div>
                    <div class="sp-chart-bar-value"><?php echo $day['count']; ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}

/**
 * ONGLET : Logs
 */
function sp_render_logs_tab() {
    $log_path = WP_CONTENT_DIR . '/scheduler-pro.log';
    $logs = array();
    
    if (file_exists($log_path)) {
        $logs = array_reverse(file($log_path));
        $logs = array_slice($logs, 0, 200); // Limiter à 200 lignes
    }
    
    ?>
    <div class="sp-card">
        <div class="sp-logs-header">
            <h2 class="sp-card-title">
                <span class="dashicons dashicons-media-text"></span>
                Journal d'Activité
            </h2>
            <div class="sp-logs-info">
                <span class="sp-badge">scheduler-pro.log</span>
                <?php if (file_exists($log_path)): ?>
                    <span class="sp-badge">
                        <?php echo size_format(filesize($log_path)); ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="sp-logs-container">
            <?php if (empty($logs)): ?>
                <p class="sp-no-logs">Aucune activité enregistrée.</p>
            <?php else: ?>
                <?php foreach ($logs as $line): ?>
                    <?php
                    $line = trim($line);
                    $class = 'sp-log-line';
                    
                    if (strpos($line, '[ERROR]') !== false || strpos($line, '❌') !== false) {
                        $class .= ' sp-log-error';
                    } elseif (strpos($line, '[WARNING]') !== false || strpos($line, '⚠️') !== false) {
                        $class .= ' sp-log-warning';
                    } elseif (strpos($line, '[SUCCESS]') !== false || strpos($line, '✅') !== false) {
                        $class .= ' sp-log-success';
                    } elseif (strpos($line, '[INFO]') !== false || strpos($line, '📦') !== false) {
                        $class .= ' sp-log-info';
                    }
                    ?>
                    <div class="<?php echo $class; ?>"><?php echo esc_html($line); ?></div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/**
 * ONGLET : À Propos
 */
function sp_render_about_tab() {
    ?>
    <div class="sp-about-grid">
        <div class="sp-card">
            <h2 class="sp-card-title">
                <span class="dashicons dashicons-info"></span>
                Scheduler Pro v<?php echo SP_VERSION; ?>
            </h2>
            
            <p class="sp-about-description">
                Plugin avancé de planification automatique et humaine des articles WordPress.
                Conçu pour optimiser votre flux de publication SEO tout en conservant une apparence naturelle.
            </p>
            
            <h3>✨ Nouvelles Fonctionnalités v2.0</h3>
            <ul class="sp-feature-list">
                <li>✅ <strong>Traitement par lots</strong> : Gestion de milliers d'articles sans timeout</li>
                <li>✅ <strong>Gestion mémoire</strong> : Arrêt automatique si RAM saturée</li>
                <li>✅ <strong>Système de verrouillage</strong> : Évite les doubles exécutions</li>
                <li>✅ <strong>Générateur ultra-humain</strong> : Pics d'activité à 9h, 14h, 17h</li>
                <li>✅ <strong>Monitoring temps réel</strong> : Surveillance de la santé du système</li>
                <li>✅ <strong>Table de queue</strong> : Traçabilité complète avec retry automatique</li>
            </ul>
            
            <h3>🔧 Configuration Recommandée</h3>
            <table class="sp-config-table">
                <tr>
                    <td><strong>Articles/jour :</strong></td>
                    <td>3-5 (naturel et SEO-friendly)</td>
                </tr>
                <tr>
                    <td><strong>Plage horaire :</strong></td>
                    <td>7h-20h (heures d'activité humaine)</td>
                </tr>
                <tr>
                    <td><strong>Mode Auto :</strong></td>
                    <td>Activé (exécution quotidienne à 00:30)</td>
                </tr>
                <tr>
                    <td><strong>Cron Serveur :</strong></td>
                    <td>Recommandé pour fiabilité maximale</td>
                </tr>
            </table>
        </div>
        
        <div class="sp-card sp-card-help">
            <h2 class="sp-card-title">
                <span class="dashicons dashicons-sos"></span>
                Besoin d'Aide ?
            </h2>
            
            <div class="sp-help-section">
                <h4>📖 Documentation</h4>
                <p>Consultez les fichiers README et guides fournis avec le plugin.</p>
            </div>
            
            <div class="sp-help-section">
                <h4>🐛 Signaler un Bug</h4>
                <p>Activez le mode debug WordPress et consultez les logs.</p>
                <code>define('WP_DEBUG', true);</code>
            </div>
            
            <div class="sp-help-section">
                <h4>⚡ Performance</h4>
                <p>
                    <strong>Avant v2.0 :</strong> 1000 articles = 50s (timeout)<br>
                    <strong>Après v2.0 :</strong> 1000 articles = 5s ✅
                </p>
            </div>
        </div>
    </div>
    
    <div class="sp-card sp-card-credits">
        <p style="text-align: center; opacity: 0.7;">
            Développé par <strong>Kr4v3n</strong> | Version <?php echo SP_VERSION; ?> | 
            Audit et Optimisation par Claude (Anthropic)
        </p>
    </div>
    <?php
}

/**
 * Meta Box : Verrouillage d'Article
 */
add_action('add_meta_boxes', function() {
    add_meta_box(
        'sp_lock_date_box',
        '🔒 Scheduler Pro : Verrouillage',
        'sp_render_lock_meta_box',
        'post',
        'side',
        'high'
    );
});

function sp_render_lock_meta_box($post) {
    $value = get_post_meta($post->ID, '_sp_lock_planning', true);
    wp_nonce_field('sp_lock_nonce', 'sp_lock_nonce_field');
    ?>
    <label class="sp-meta-box-label">
        <input type="checkbox" 
               name="sp_lock_planning" 
               value="1" 
               <?php checked($value, '1'); ?>>
        <strong>Verrouiller la date de publication</strong>
    </label>
    <p class="description">
        Empêche le Scheduler de modifier automatiquement la date de cet article.
    </p>
    <?php
}

add_action('save_post', function($post_id) {
    if (!isset($_POST['sp_lock_nonce_field']) || !wp_verify_nonce($_POST['sp_lock_nonce_field'], 'sp_lock_nonce')) {
        return;
    }
    
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    update_post_meta($post_id, '_sp_lock_planning', isset($_POST['sp_lock_planning']) ? '1' : '0');
});