<?php
/**
 * MEMORY GUARD - Scheduler Pro v2.5
 *
 * Utilitaires de gestion mémoire, partagés entre le moteur de
 * planification (engine.php) et le diagnostic (heartbeat-monitor.php),
 * qui avait auparavant sa propre conversion d'unité incorrecte (ne gérait
 * pas le suffixe "G").
 */

if (!defined('ABSPATH')) exit;

if (!function_exists('sp_check_memory_available')) {
    function sp_check_memory_available() {
        $memory_limit = ini_get('memory_limit');
        $memory_limit_bytes = sp_convert_to_bytes($memory_limit);
        $current_usage = memory_get_usage(true);
        $usage_percent = ($current_usage / $memory_limit_bytes) * 100;

        if ($usage_percent > 80) {
            sp_log("⚠️ ALERTE MÉMOIRE : {$usage_percent}% utilisée", 'WARNING');
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
