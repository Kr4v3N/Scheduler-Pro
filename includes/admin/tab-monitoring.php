<?php
/**
 * ADMIN: MONITORING TAB - Scheduler Pro v2.5
 */

if (!defined('ABSPATH')) exit;

function sp_render_monitoring_tab() {
    $health = SP_Heartbeat_Monitor::check_wp_cron_health();
    $next = SP_Heartbeat_Monitor::get_next_scheduled();
    $issues = SP_Heartbeat_Monitor::diagnose_issues();

    // Queue stats
    $queue_stats = array();
    if (class_exists('SP_Database_Manager')) {
        $queue_stats = SP_Database_Manager::get_stats();
    }

    // WordPress stats
    $total_future = (int) wp_count_posts()->future;
    $total_publish = (int) wp_count_posts()->publish;

    ?>
    <div class="sp-monitoring-grid">
        <!-- Global Stats -->
        <div class="sp-stats-row">
            <div class="sp-stat-card">
                <div class="sp-stat-icon" style="background: #3b82f6;">
                    <span class="dashicons dashicons-calendar-alt"></span>
                </div>
                <div class="sp-stat-content">
                    <div class="sp-stat-value"><?php echo $total_future; ?></div>
                    <div class="sp-stat-label">Scheduled Posts</div>
                </div>
            </div>

            <div class="sp-stat-card">
                <div class="sp-stat-icon" style="background: #10b981;">
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <div class="sp-stat-content">
                    <div class="sp-stat-value"><?php echo $total_publish; ?></div>
                    <div class="sp-stat-label">Published Posts</div>
                </div>
            </div>

            <?php if (!empty($queue_stats)): ?>
            <div class="sp-stat-card">
                <div class="sp-stat-icon" style="background: #f59e0b;">
                    <span class="dashicons dashicons-backup"></span>
                </div>
                <div class="sp-stat-content">
                    <div class="sp-stat-value"><?php echo $queue_stats['pending'] ?? 0; ?></div>
                    <div class="sp-stat-label">Pending Tasks</div>
                </div>
            </div>

            <div class="sp-stat-card">
                <div class="sp-stat-icon" style="background: <?php echo $queue_stats['failed'] > 0 ? '#ef4444' : '#6b7280'; ?>;">
                    <span class="dashicons dashicons-warning"></span>
                </div>
                <div class="sp-stat-content">
                    <div class="sp-stat-value"><?php echo $queue_stats['failed'] ?? 0; ?></div>
                    <div class="sp-stat-label">Failed Tasks</div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Scheduler Status -->
        <div class="sp-card">
            <h2 class="sp-card-title">
                <span class="dashicons dashicons-heart"></span>
                Scheduler Status
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
                            <strong>Next run:</strong>
                            <?php echo esc_html($next['date']); ?>
                            (in <?php echo esc_html($next['human']); ?>)
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Issues Detected -->
        <?php if (!empty($issues)): ?>
        <div class="sp-card sp-card-warning">
            <h2 class="sp-card-title">
                <span class="dashicons dashicons-flag"></span>
                Issues Detected
            </h2>

            <?php foreach ($issues as $issue): ?>
                <div class="sp-issue-item sp-issue-<?php echo $issue['severity']; ?>">
                    <div class="sp-issue-icon">
                        <span class="dashicons dashicons-info"></span>
                    </div>
                    <div class="sp-issue-content">
                        <h4><?php echo esc_html($issue['message']); ?></h4>
                        <p><strong>Solution:</strong> <?php echo esc_html($issue['solution']); ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Post Distribution -->
        <?php sp_render_distribution_chart(); ?>
    </div>
    <?php
}

/**
 * Display the distribution chart
 */
function sp_render_distribution_chart() {
    global $wpdb;

    // Span the next 3 months. Grouped by week (rather than by day) so the
    // chart stays readable: ~13 bars instead of ~90 unreadable thin ones.
    // IMPORTANT: compare DATE(post_date), not raw post_date, against the
    // upper bound — comparing a datetime (e.g. "2026-10-03 14:23:07") to
    // "2026-10-03 00:00:00" would exclude every post on the last day
    // published after midnight.
    $range_days = 90;

    $distribution = $wpdb->get_results($wpdb->prepare("
        SELECT DATE_SUB(DATE(post_date), INTERVAL WEEKDAY(post_date) DAY) as week_start, COUNT(*) as count
        FROM {$wpdb->posts}
        WHERE post_status = 'future'
        AND post_type = 'post'
        AND DATE(post_date) >= CURDATE()
        AND DATE(post_date) <= DATE_ADD(CURDATE(), INTERVAL %d DAY)
        GROUP BY week_start
        ORDER BY week_start
    ", $range_days), ARRAY_A);

    if (empty($distribution)) {
        return;
    }

    $max_count = max(array_column($distribution, 'count'));

    ?>
    <div class="sp-card">
        <h2 class="sp-card-title">
            <span class="dashicons dashicons-chart-bar"></span>
            Distribution Over the Next 3 Months (by week)
        </h2>

        <div class="sp-distribution-chart">
            <?php foreach ($distribution as $week): ?>
                <?php
                $percentage = ($week['count'] / $max_count) * 100;
                $week_start = new DateTime($week['week_start']);
                $week_end = (clone $week_start)->modify('+6 days');
                ?>
                <div class="sp-chart-bar" title="<?php echo esc_attr($week['count']); ?> posts (<?php echo esc_attr($week_start->format('d/m')); ?> - <?php echo esc_attr($week_end->format('d/m')); ?>)">
                    <div class="sp-chart-bar-fill" style="height: <?php echo $percentage; ?>%;"></div>
                    <div class="sp-chart-bar-label">
                        <?php echo $week_start->format('d/m'); ?>
                    </div>
                    <div class="sp-chart-bar-value"><?php echo $week['count']; ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}
