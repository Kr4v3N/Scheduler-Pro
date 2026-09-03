<?php
/**
 * TIME HELPERS - Scheduler Pro v2.5
 *
 * Convertit une expression de temps exprimée dans le fuseau horaire du
 * site (réglé dans WordPress > Réglages > Général) en timestamp UTC réel,
 * tel qu'exigé par wp_schedule_event().
 *
 * IMPORTANT : strtotime() seul utilise le fuseau horaire PHP par défaut
 * (souvent UTC sur l'hébergement, mais pas garanti), pas le fuseau réglé
 * dans WordPress. Sur un site dont les deux fuseaux diffèrent, ça décale
 * silencieusement l'heure réelle d'exécution du cron par rapport à
 * l'heure annoncée dans l'interface (ex: "00:30" affiché mais exécuté à
 * une autre heure). Cette fonction élimine l'écart en réutilisant
 * get_gmt_from_date(), déjà utilisé ailleurs dans le plugin pour la même
 * conversion locale → UTC (voir includes/engine.php).
 */

if (!defined('ABSPATH')) exit;

if (!function_exists('sp_get_site_gmt_timestamp')) {
    function sp_get_site_gmt_timestamp($time_string) {
        $site_now = current_time('timestamp');
        $local_datetime = date('Y-m-d H:i:s', strtotime($time_string, $site_now));
        $gmt_datetime = get_gmt_from_date($local_datetime);

        return strtotime($gmt_datetime . ' UTC');
    }
}
