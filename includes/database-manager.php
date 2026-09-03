<?php
/**
 * DATABASE MANAGER - Scheduler Pro v2.6
 *
 * Manages the persistent queue table for scheduled tasks
 *
 * Features:
 * - wp_scheduler_queue table for traceability
 * - Status system (pending, running, completed, failed)
 * - Priority handling
 * - Automatic retry system
 */

if (!defined('ABSPATH')) exit;

class SP_Database_Manager {

    /**
     * Table name (without prefix)
     */
    const TABLE_NAME = 'scheduler_queue';

    /**
     * DB schema version
     */
    const DB_VERSION = '2.0';

    /**
     * Available statuses
     */
    const STATUS_PENDING = 'pending';
    const STATUS_RUNNING = 'running';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    /**
     * Create or update the tables on activation
     */
    public static function create_tables() {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL,
            scheduled_date datetime NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            priority int(3) unsigned NOT NULL DEFAULT 50,
            attempts int(3) unsigned NOT NULL DEFAULT 0,
            last_error text NULL,
            metadata longtext NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY status (status),
            KEY priority (priority),
            KEY scheduled_date (scheduled_date),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Record the schema version
        update_option('sp_db_version', self::DB_VERSION);

        sp_log("✅ Table {$table_name} created/updated (version " . self::DB_VERSION . ")", 'INSTALL');
    }

    /**
     * Add a task to the queue
     *
     * @param int $post_id Post ID
     * @param string $scheduled_date Scheduled date (Y-m-d H:i:s)
     * @param int $priority Priority (10=urgent, 90=background)
     * @param array $metadata Additional data
     * @return int|false Task ID or false on failure
     */
    public static function add_task($post_id, $scheduled_date, $priority = 50, $metadata = array()) {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;

        $inserted = $wpdb->insert(
            $table_name,
            array(
                'post_id' => $post_id,
                'scheduled_date' => $scheduled_date,
                'status' => self::STATUS_PENDING,
                'priority' => max(10, min(90, $priority)),
                'metadata' => !empty($metadata) ? json_encode($metadata) : null,
            ),
            array('%d', '%s', '%s', '%d', '%s')
        );

        if ($inserted) {
            $task_id = $wpdb->insert_id;
            sp_log("📝 Task #{$task_id} added: Post {$post_id} → {$scheduled_date}", 'QUEUE');
            return $task_id;
        }

        sp_log("❌ Failed to add task for Post {$post_id}", 'ERROR');
        return false;
    }

    /**
     * Get pending tasks (ordered by priority)
     *
     * @param int $limit Max number of tasks to return
     * @return array List of tasks
     */
    public static function get_pending_tasks($limit = 10) {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;

        $tasks = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM $table_name
            WHERE status = %s
            ORDER BY priority ASC, created_at ASC
            LIMIT %d
        ", self::STATUS_PENDING, $limit), ARRAY_A);

        return $tasks;
    }

    /**
     * Mark a task as "running"
     *
     * @param int $task_id Task ID
     * @return bool Success
     */
    public static function mark_as_running($task_id) {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;

        $updated = $wpdb->update(
            $table_name,
            array('status' => self::STATUS_RUNNING),
            array('id' => $task_id),
            array('%s'),
            array('%d')
        );

        if ($updated) {
            sp_log("▶️ Task #{$task_id} started", 'QUEUE');
            return true;
        }

        return false;
    }

    /**
     * Mark a task as "completed"
     *
     * @param int $task_id Task ID
     * @return bool Success
     */
    public static function mark_as_completed($task_id) {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;

        $updated = $wpdb->update(
            $table_name,
            array(
                'status' => self::STATUS_COMPLETED,
                'last_error' => null,
            ),
            array('id' => $task_id),
            array('%s', '%s'),
            array('%d')
        );

        if ($updated) {
            sp_log("✅ Task #{$task_id} completed", 'QUEUE');
            return true;
        }

        return false;
    }

    /**
     * Mark a task as "failed" with retry
     *
     * @param int $task_id Task ID
     * @param string $error_message Error message
     * @param int $max_attempts Max number of attempts
     * @return bool Success
     */
    public static function mark_as_failed($task_id, $error_message, $max_attempts = 3) {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;

        // Get the current attempt count
        $current_attempts = (int) $wpdb->get_var($wpdb->prepare("
            SELECT attempts FROM $table_name WHERE id = %d
        ", $task_id));

        $new_attempts = $current_attempts + 1;

        // If we've hit the max, mark as failed
        $new_status = ($new_attempts >= $max_attempts) ? self::STATUS_FAILED : self::STATUS_PENDING;

        $updated = $wpdb->update(
            $table_name,
            array(
                'status' => $new_status,
                'attempts' => $new_attempts,
                'last_error' => $error_message,
            ),
            array('id' => $task_id),
            array('%s', '%d', '%s'),
            array('%d')
        );

        if ($updated) {
            if ($new_status === self::STATUS_FAILED) {
                sp_log("❌ Task #{$task_id} FINAL FAILURE after {$new_attempts} attempts: {$error_message}", 'ERROR');
            } else {
                sp_log("⚠️ Task #{$task_id} attempt {$new_attempts}/{$max_attempts}: {$error_message}", 'WARNING');
            }
            return true;
        }

        return false;
    }

    /**
     * Clean up old completed tasks
     *
     * @param int $days Number of days to keep
     * @return int Number of tasks removed
     */
    public static function cleanup_old_tasks($days = 30) {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;

        $deleted = $wpdb->query($wpdb->prepare("
            DELETE FROM $table_name
            WHERE status IN (%s, %s)
            AND updated_at < DATE_SUB(NOW(), INTERVAL %d DAY)
        ", self::STATUS_COMPLETED, self::STATUS_FAILED, $days));

        if ($deleted > 0) {
            sp_log("🧹 {$deleted} old task(s) removed (> {$days} days)", 'CLEANUP');
        }

        return $deleted;
    }

    /**
     * Queue statistics
     *
     * @return array Stats
     */
    public static function get_stats() {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;

        $stats = $wpdb->get_row("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'running' THEN 1 ELSE 0 END) as running,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
            FROM $table_name
        ", ARRAY_A);

        return $stats;
    }

    /**
     * Get the history of a specific task
     *
     * @param int $post_id Post ID
     * @return array List of tasks for this post
     */
    public static function get_task_history($post_id) {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;

        $tasks = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM $table_name
            WHERE post_id = %d
            ORDER BY created_at DESC
        ", $post_id), ARRAY_A);

        return $tasks;
    }

    /**
     * Reset tasks stuck in "running" for too long
     *
     * @param int $timeout Timeout in minutes
     * @return int Number of tasks reset
     */
    public static function reset_stuck_tasks($timeout = 30) {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;

        $reset = $wpdb->query($wpdb->prepare("
            UPDATE $table_name
            SET status = %s,
                last_error = 'Task stuck - automatically reset'
            WHERE status = %s
            AND updated_at < DATE_SUB(NOW(), INTERVAL %d MINUTE)
        ", self::STATUS_PENDING, self::STATUS_RUNNING, $timeout));

        if ($reset > 0) {
            sp_log("🔄 {$reset} stuck task(s) reset (timeout: {$timeout}min)", 'CLEANUP');
        }

        return $reset;
    }

    /**
     * Drop the tables on uninstall
     */
    public static function drop_tables() {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;

        $wpdb->query("DROP TABLE IF EXISTS $table_name");

        delete_option('sp_db_version');

        sp_log("❌ Table {$table_name} removed", 'UNINSTALL');
    }
}

/**
 * Daily cleanup task
 */
add_action('sp_daily_cleanup', function() {
    SP_Database_Manager::cleanup_old_tasks(30); // Keep 30 days
    SP_Database_Manager::reset_stuck_tasks(30); // 30-minute timeout
});
