<?php
/**
 * ADMIN: ACTIVITY LOG TAB - Scheduler Pro v2.6
 */

if (!defined('ABSPATH')) exit;

function sp_render_logs_tab() {
    $log_path = sp_get_log_dir() . 'scheduler-pro.log';
    $logs = array();

    if (file_exists($log_path)) {
        $logs = array_reverse(file($log_path));
        $logs = array_slice($logs, 0, 200); // Limit to 200 lines
    }

    ?>
    <div class="sp-card">
        <div class="sp-logs-header">
            <h2 class="sp-card-title">
                <span class="dashicons dashicons-media-text"></span>
                <?php esc_html_e('Activity Log', 'scheduler-pro'); ?>
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
                <p class="sp-no-logs"><?php esc_html_e('No activity recorded yet.', 'scheduler-pro'); ?></p>
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
