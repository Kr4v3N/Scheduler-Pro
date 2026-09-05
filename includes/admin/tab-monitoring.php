<?php
/**
 * ADMIN: MONITORING TAB - Scheduler Pro v2.6
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
                    <div class="sp-stat-label"><?php esc_html_e('Scheduled Posts', 'scheduler-pro'); ?></div>
                </div>
            </div>

            <div class="sp-stat-card">
                <div class="sp-stat-icon" style="background: #10b981;">
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <div class="sp-stat-content">
                    <div class="sp-stat-value"><?php echo $total_publish; ?></div>
                    <div class="sp-stat-label"><?php esc_html_e('Published Posts', 'scheduler-pro'); ?></div>
                </div>
            </div>

            <?php if (!empty($queue_stats)): ?>
            <div class="sp-stat-card">
                <div class="sp-stat-icon" style="background: #f59e0b;">
                    <span class="dashicons dashicons-backup"></span>
                </div>
                <div class="sp-stat-content">
                    <div class="sp-stat-value"><?php echo $queue_stats['pending'] ?? 0; ?></div>
                    <div class="sp-stat-label"><?php esc_html_e('Pending Tasks', 'scheduler-pro'); ?></div>
                </div>
            </div>

            <div class="sp-stat-card">
                <div class="sp-stat-icon" style="background: <?php echo $queue_stats['failed'] > 0 ? '#ef4444' : '#6b7280'; ?>;">
                    <span class="dashicons dashicons-warning"></span>
                </div>
                <div class="sp-stat-content">
                    <div class="sp-stat-value"><?php echo $queue_stats['failed'] ?? 0; ?></div>
                    <div class="sp-stat-label"><?php esc_html_e('Failed Tasks', 'scheduler-pro'); ?></div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Scheduler Status -->
        <div class="sp-card">
            <h2 class="sp-card-title">
                <span class="dashicons dashicons-heart"></span>
                <?php esc_html_e('Scheduler Status', 'scheduler-pro'); ?>
            </h2>

            <div class="sp-health-status sp-health-<?php echo esc_attr($health['level']); ?>">
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
                            <strong><?php esc_html_e('Next run:', 'scheduler-pro'); ?></strong>
                            <?php echo esc_html($next['date']); ?>
                            <?php
                            printf(
                                /* translators: %s: human-readable time until the next run, e.g. "3 hours" */
                                esc_html__('(in %s)', 'scheduler-pro'),
                                esc_html($next['human'])
                            );
                            ?>
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
                <?php esc_html_e('Issues Detected', 'scheduler-pro'); ?>
            </h2>

            <?php foreach ($issues as $issue): ?>
                <div class="sp-issue-item sp-issue-<?php echo esc_attr($issue['severity']); ?>">
                    <div class="sp-issue-icon">
                        <span class="dashicons dashicons-info"></span>
                    </div>
                    <div class="sp-issue-content">
                        <h4><?php echo esc_html($issue['message']); ?></h4>
                        <p><strong><?php esc_html_e('Solution:', 'scheduler-pro'); ?></strong> <?php echo esc_html($issue['solution']); ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Post Distribution -->
        <?php sp_render_distribution_chart(); ?>

        <!-- Calendar View -->
        <?php sp_render_calendar_view(); ?>
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
            <?php esc_html_e('Distribution Over the Next 3 Months (by week)', 'scheduler-pro'); ?>
        </h2>

        <div class="sp-distribution-chart">
            <?php foreach ($distribution as $week): ?>
                <?php
                $percentage = ($week['count'] / $max_count) * 100;
                $week_start = new DateTime($week['week_start']);
                $week_end = (clone $week_start)->modify('+6 days');
                ?>
                <?php
                $bar_title = sprintf(
                    /* translators: 1: post count, 2: week start date (d/m), 3: week end date (d/m) */
                    __('%1$d posts (%2$s - %3$s)', 'scheduler-pro'),
                    $week['count'],
                    $week_start->format('d/m'),
                    $week_end->format('d/m')
                );
                ?>
                <div class="sp-chart-bar" title="<?php echo esc_attr($bar_title); ?>">
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

/**
 * Display a 2-month calendar grid of upcoming FUTURE posts (current month
 * + next month), complementing the weekly bar chart above with day-level
 * detail: post count per day, with titles shown on hover. Pure CSS, no
 * JS library.
 */
function sp_render_calendar_view() {
    global $wpdb;

    $today = new DateTime(current_time('Y-m-d'));
    $range_start = new DateTime($today->format('Y-m-01'));
    $range_end = (clone $range_start)->modify('+2 months')->modify('-1 day');

    $rows = $wpdb->get_results($wpdb->prepare("
        SELECT post_title, post_date
        FROM {$wpdb->posts}
        WHERE post_status = 'future'
        AND post_type = 'post'
        AND DATE(post_date) >= %s
        AND DATE(post_date) <= %s
        ORDER BY post_date ASC
    ", $range_start->format('Y-m-d'), $range_end->format('Y-m-d')), ARRAY_A);

    $titles_by_date = array();
    foreach ($rows as $row) {
        $date_key = substr($row['post_date'], 0, 10);
        $titles_by_date[$date_key][] = $row['post_title'];
    }

    ?>
    <div class="sp-card">
        <h2 class="sp-card-title">
            <span class="dashicons dashicons-calendar"></span>
            <?php esc_html_e('Calendar View', 'scheduler-pro'); ?>
        </h2>

        <div class="sp-calendar">
            <?php
            $month_cursor = clone $range_start;
            for ($m = 0; $m < 2; $m++) {
                sp_render_calendar_month($month_cursor, $titles_by_date, $today);
                $month_cursor->modify('+1 month');
            }
            ?>
        </div>
    </div>
    <?php
}

/**
 * Render a single month grid for sp_render_calendar_view().
 *
 * @param DateTime $month_start First day of the month to render
 * @param array    $titles_by_date Map of 'Y-m-d' => array of post titles
 * @param DateTime $today
 */
function sp_render_calendar_month($month_start, $titles_by_date, $today) {
    $first_weekday = (int) $month_start->format('N'); // 1 (Mon) .. 7 (Sun)
    $days_in_month = (int) $month_start->format('t');
    $today_key = $today->format('Y-m-d');

    ?>
    <div class="sp-calendar-month">
        <h3 class="sp-calendar-month-title"><?php echo esc_html(date_i18n('F Y', strtotime($month_start->format('Y-m-d')))); ?></h3>
        <div class="sp-calendar-grid">
            <?php
            $weekday_labels = array(
                __('Mon', 'scheduler-pro'),
                __('Tue', 'scheduler-pro'),
                __('Wed', 'scheduler-pro'),
                __('Thu', 'scheduler-pro'),
                __('Fri', 'scheduler-pro'),
                __('Sat', 'scheduler-pro'),
                __('Sun', 'scheduler-pro'),
            );
            ?>
            <?php foreach ($weekday_labels as $weekday_label): ?>
                <div class="sp-calendar-weekday"><?php echo esc_html($weekday_label); ?></div>
            <?php endforeach; ?>

            <?php for ($i = 1; $i < $first_weekday; $i++): ?>
                <div class="sp-calendar-day sp-calendar-day-empty"></div>
            <?php endfor; ?>

            <?php for ($day = 1; $day <= $days_in_month; $day++): ?>
                <?php
                $date_key = $month_start->format('Y-m-') . str_pad($day, 2, '0', STR_PAD_LEFT);
                $titles = $titles_by_date[$date_key] ?? array();
                $count = count($titles);

                $classes = array('sp-calendar-day');
                if ($count > 0) $classes[] = 'sp-calendar-day-has-posts';
                if ($date_key === $today_key) $classes[] = 'sp-calendar-day-today';
                if ($date_key < $today_key) $classes[] = 'sp-calendar-day-past';
                ?>
                <div class="<?php echo esc_attr(implode(' ', $classes)); ?>">
                    <span class="sp-calendar-daynum"><?php echo $day; ?></span>
                    <?php if ($count > 0): ?>
                        <span class="sp-calendar-count"><?php echo $count; ?></span>
                        <div class="sp-calendar-tooltip">
                            <strong><?php echo esc_html(date_i18n('D d M', strtotime($date_key))); ?></strong>
                            <ul>
                                <?php foreach (array_slice($titles, 0, 8) as $title): ?>
                                    <li><?php echo esc_html($title !== '' ? $title : __('(no title)', 'scheduler-pro')); ?></li>
                                <?php endforeach; ?>
                                <?php if ($count > 8): ?>
                                    <li>
                                        <?php
                                        printf(
                                            /* translators: %d: number of additional posts on that day not shown in the tooltip */
                                            esc_html__('%d more…', 'scheduler-pro'),
                                            $count - 8
                                        );
                                        ?>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endfor; ?>
        </div>
    </div>
    <?php
}
