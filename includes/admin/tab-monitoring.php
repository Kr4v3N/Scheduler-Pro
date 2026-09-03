<?php
/**
 * ADMIN : ONGLET MONITORING - Scheduler Pro v2.5
 */

if (!defined('ABSPATH')) exit;

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
    // IMPORTANT : comparer DATE(post_date) et non post_date brut à la borne
    // supérieure — comparer un datetime (ex: "2026-10-03 14:23:07") à
    // "2026-10-03 00:00:00" exclurait tous les articles du 30e jour publiés
    // après minuit.
    $distribution = $wpdb->get_results("
        SELECT DATE(post_date) as date, COUNT(*) as count
        FROM {$wpdb->posts}
        WHERE post_status = 'future'
        AND post_type = 'post'
        AND DATE(post_date) >= CURDATE()
        AND DATE(post_date) <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
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
