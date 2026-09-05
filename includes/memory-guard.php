<?php
/**
 * MEMORY GUARD - Scheduler Pro v2.6
 *
 * Memory management utilities, shared between the scheduling engine
 * (engine.php) and the diagnostics (heartbeat-monitor.php), which used
 * to have its own incorrect unit conversion (didn't handle the "G"
 * suffix).
 */

if (!defined('ABSPATH')) exit;

if (!function_exists('sp_check_memory_available')) {
    function sp_check_memory_available() {
        $memory_limit = ini_get('memory_limit');
        $memory_limit_bytes = sp_convert_to_bytes($memory_limit);

        // <= 0 covers both an empty/unreadable memory_limit (would divide
        // by zero below, a fatal error in PHP 8) and -1 (unlimited, the
        // common value on CLI / server cron): in both cases there is no
        // meaningful limit to check against.
        if ($memory_limit_bytes <= 0) {
            return true;
        }

        $current_usage = memory_get_usage(true);
        $usage_percent = ($current_usage / $memory_limit_bytes) * 100;

        if ($usage_percent > 80) {
            sp_log("⚠️ MEMORY ALERT: {$usage_percent}% used", 'WARNING');
            return false;
        }
        return true;
    }
}

/**
 * Time guard, twin of the memory guard above: the scheduling loop checks
 * it between batches and stops at 80% of max_execution_time instead of
 * being killed mid-batch by PHP. A timeout kill never runs the engine's
 * finally block, so the execution lock would survive until its own
 * expiry (600s) - stopping cleanly avoids that and lets the next run
 * resume where this one left off.
 */
if (!function_exists('sp_check_time_available')) {
    function sp_check_time_available($started_at) {
        $max_execution_time = (int) ini_get('max_execution_time');

        // 0 = unlimited (CLI, some server cron setups)
        if ($max_execution_time <= 0) {
            return true;
        }

        $elapsed = time() - $started_at;

        if ($elapsed > ($max_execution_time * 0.8)) {
            sp_log("⚠️ TIME ALERT: {$elapsed}s elapsed of {$max_execution_time}s max - pausing", 'WARNING');
            return false;
        }
        return true;
    }
}

if (!function_exists('sp_convert_to_bytes')) {
    function sp_convert_to_bytes($val) {
        $val = trim($val);
        if (empty($val)) return 0;

        $last = strtolower($val[strlen($val) - 1]);
        $val = (int) $val;

        switch($last) {
            case 'g': $val *= 1024;
            case 'm': $val *= 1024;
            case 'k': $val *= 1024;
        }
        return $val;
    }
}
