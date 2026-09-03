<?php
/**
 * LOGGER - Scheduler Pro v2.5
 *
 * Journal d'activité du plugin, avec rotation automatique. Le fichier
 * est stocké dans un répertoire dédié sous uploads/ (pas directement
 * dans wp-content/), protégé par un .htaccess + un index.php vide pour
 * empêcher l'accès HTTP direct (auparavant le log vivait en clair à la
 * racine de wp-content/, potentiellement accessible en HTTP).
 */

if (!defined('ABSPATH')) exit;

if (!function_exists('sp_get_log_dir')) {
    function sp_get_log_dir() {
        $upload_dir = wp_upload_dir();
        $log_dir = trailingslashit($upload_dir['basedir']) . 'scheduler-pro-logs/';

        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }

        $htaccess = $log_dir . '.htaccess';
        if (!file_exists($htaccess)) {
            @file_put_contents($htaccess, "Deny from all\nRequire all denied\n");
        }

        $index = $log_dir . 'index.php';
        if (!file_exists($index)) {
            @file_put_contents($index, "<?php\n// Silence is golden.\n");
        }

        return $log_dir;
    }
}

if (!function_exists('sp_log')) {
    function sp_log($message, $level = 'INFO') {
        $log_file = sp_get_log_dir() . 'scheduler-pro.log';
        $timestamp = current_time('Y-m-d H:i:s');
        $formatted_message = "[$timestamp] [$level] $message" . PHP_EOL;

        @file_put_contents($log_file, $formatted_message, FILE_APPEND | LOCK_EX);
        sp_rotate_log_file($log_file);
    }
}

if (!function_exists('sp_rotate_log_file')) {
    function sp_rotate_log_file($log_file) {
        if (!file_exists($log_file)) return;

        $file_size = filesize($log_file);
        if ($file_size > 1048576) { // 1 Mo
            $lines = file($log_file);
            if (count($lines) > 500) {
                $lines = array_slice($lines, -500);
                @file_put_contents($log_file, implode('', $lines), LOCK_EX);
                sp_log("🔄 Rotation du fichier log effectuée", 'SYSTEM');
            }
        }
    }
}
