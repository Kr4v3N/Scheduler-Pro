<?php
/**
 * LOCK MANAGER - Scheduler Pro v2.6
 *
 * Locking system to prevent concurrent executions.
 *
 * Stores locks in wp_options, using direct SQL (INSERT IGNORE /
 * conditional UPDATE), NOT via WordPress transients nor via add_option().
 *
 * IMPORTANT: add_option() is NOT a reliable atomic primitive for this use
 * case, contrary to what you'd expect from the UNIQUE constraint on
 * option_name. WordPress core actually runs an
 * INSERT ... ON DUPLICATE KEY UPDATE (verified in wp-includes/option.php):
 * on conflict, it OVERWRITES the existing row with the new values instead
 * of failing. Two concurrent callers can therefore both receive true,
 * each overwriting the other's lock - exactly the bug this file is
 * meant to eliminate. Hence the direct use of INSERT IGNORE (which never
 * touches an existing row) for acquisition, and an UPDATE conditioned on
 * the previous value (compare-and-swap) for replacing a stale lock.
 *
 * Transients (separate get_transient() + set_transient() calls) exposed
 * the same kind of race window even more visibly: two near-simultaneous
 * callers could both read "no lock" before either of them wrote theirs.
 */

if (!defined('ABSPATH')) exit;

class SP_Lock_Manager {

    const DEFAULT_TIMEOUT = 300; // 5 minutes (default timeout for a lock)

    /**
     * Threshold used by the daily cleanup to consider a lock abandoned.
     * Deliberately larger than DEFAULT_TIMEOUT: the 'main_scheduling'
     * lock (see includes/engine.php) is acquired with a 600s timeout. A
     * cleanup threshold set to DEFAULT_TIMEOUT (300s) would delete that
     * lock while a legitimate run is still in progress. 1800s stays
     * comfortably above the largest timeout used in the plugin while
     * still eventually cleaning up genuinely abandoned locks (crash).
     */
    const STALE_CLEANUP_THRESHOLD = 1800; // 30 minutes

    const LOCK_PREFIX = 'sp_lock_';

    /**
     * Atomically acquire a lock
     *
     * @param string $lock_name Lock name (e.g. 'main_scheduling')
     * @param int $timeout Maximum lock duration in seconds
     * @return bool True if the lock was acquired, False if already active
     */
    public static function acquire($lock_name, $timeout = self::DEFAULT_TIMEOUT) {
        global $wpdb;

        $lock_key = self::LOCK_PREFIX . sanitize_key($lock_name);
        $now = time();

        // Atomic acquisition attempt via INSERT IGNORE: unlike add_option(),
        // which actually runs an INSERT ... ON DUPLICATE KEY UPDATE on the
        // WordPress core side (verified in wp-includes/option.php),
        // INSERT IGNORE NEVER overwrites an existing row on conflict - it
        // fails silently (0 rows affected). This is the only one of the two
        // queries that guarantees only one concurrent caller can win.
        $inserted = $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
            $lock_key, $now
        ));

        if ($inserted === 1) {
            wp_cache_delete($lock_key, 'options');
            sp_log("🔒 Lock acquired on '{$lock_name}' (timeout: {$timeout}s)", 'INFO');
            return true;
        }

        // The key already exists: check whether the lock is stale (crash, bug).
        // Read via direct SQL (not get_option()) to never risk reading a
        // value cached before the INSERT IGNORE above.
        $existing = (string) $wpdb->get_var($wpdb->prepare(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
            $lock_key
        ));
        $age = $now - (int) $existing;

        if ($age > $timeout) {
            // Compare-and-swap replacement: the WHERE clause includes the
            // value just read, so the UPDATE only affects a row if no one
            // else has already replaced it between the read and the write.
            // If another caller won in the meantime, $updated is 0 and we
            // back off cleanly instead of overwriting their lock.
            $updated = $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s",
                $now, $lock_key, $existing
            ));

            if ($updated === 1) {
                wp_cache_delete($lock_key, 'options');
                sp_log("🔓 Stale lock '{$lock_name}' replaced (age: {$age}s)", 'WARNING');
                return true;
            }

            sp_log("⚠️ Lock '{$lock_name}' taken over by another caller in the meantime - backing off", 'WARNING');
            return false;
        }

        sp_log("⚠️ Lock active on '{$lock_name}' (age: {$age}s) - backing off", 'WARNING');
        return false;
    }

    /**
     * Release a lock
     */
    public static function release($lock_name) {
        $lock_key = self::LOCK_PREFIX . sanitize_key($lock_name);

        $existed = get_option($lock_key) !== false;

        delete_option($lock_key);

        if ($existed) {
            sp_log("🔓 Lock released on '{$lock_name}'", 'INFO');
        }

        return $existed;
    }

    /**
     * Check whether a lock is active
     */
    public static function is_locked($lock_name) {
        $lock_key = self::LOCK_PREFIX . sanitize_key($lock_name);
        return get_option($lock_key) !== false;
    }

    /**
     * Get the age of a lock in seconds
     */
    public static function get_lock_age($lock_name) {
        $lock_key = self::LOCK_PREFIX . sanitize_key($lock_name);
        $lock_time = get_option($lock_key);

        if ($lock_time === false) {
            return false;
        }

        return time() - (int) $lock_time;
    }

    /**
     * Clean up all stale locks (called daily via cron)
     *
     * Uses STALE_CLEANUP_THRESHOLD, not DEFAULT_TIMEOUT: see the
     * constant's docblock above.
     */
    public static function cleanup_stale_locks() {
        global $wpdb;

        $cutoff = time() - self::STALE_CLEANUP_THRESHOLD;

        $deleted = $wpdb->query($wpdb->prepare("
            DELETE FROM {$wpdb->options}
            WHERE option_name LIKE %s
            AND option_value < %d
        ", $wpdb->esc_like(self::LOCK_PREFIX) . '%', $cutoff));

        if ($deleted > 0) {
            sp_log("🧹 {$deleted} stale lock(s) cleaned up", 'CLEANUP');
        }

        return $deleted;
    }

    /**
     * Clean up ALL locks (forced, used on deactivation)
     */
    public static function cleanup_all_locks() {
        global $wpdb;

        $deleted = $wpdb->query($wpdb->prepare("
            DELETE FROM {$wpdb->options}
            WHERE option_name LIKE %s
        ", $wpdb->esc_like(self::LOCK_PREFIX) . '%'));

        if ($deleted > 0) {
            sp_log("🧹 {$deleted} lock(s) removed during forced cleanup", 'CLEANUP');
        }

        return $deleted;
    }

    /**
     * List all active locks
     */
    public static function list_active_locks() {
        global $wpdb;

        $locks = $wpdb->get_results($wpdb->prepare("
            SELECT
                REPLACE(option_name, %s, '') as lock_name,
                option_value as created_at
            FROM {$wpdb->options}
            WHERE option_name LIKE %s
        ", self::LOCK_PREFIX, $wpdb->esc_like(self::LOCK_PREFIX) . '%'), ARRAY_A);

        foreach ($locks as &$lock) {
            $lock['age_seconds'] = time() - (int) $lock['created_at'];
            $lock['created_at_human'] = date('Y-m-d H:i:s', (int) $lock['created_at']);
        }

        return $locks;
    }

    /**
     * Run a function with automatic locking
     */
    public static function execute_with_lock($lock_name, callable $callback, $timeout = self::DEFAULT_TIMEOUT) {
        if (!self::acquire($lock_name, $timeout)) {
            return false;
        }

        try {
            return $callback();
        } finally {
            self::release($lock_name);
        }
    }
}

/**
 * Daily cleanup hook
 */
add_action('sp_daily_cleanup', array('SP_Lock_Manager', 'cleanup_stale_locks'));

/**
 * Helper functions for backward compatibility
 */
if (!function_exists('sp_acquire_lock')) {
    function sp_acquire_lock($lock_name, $timeout = 300) {
        return SP_Lock_Manager::acquire($lock_name, $timeout);
    }
}

if (!function_exists('sp_release_lock')) {
    function sp_release_lock($lock_name) {
        return SP_Lock_Manager::release($lock_name);
    }
}
