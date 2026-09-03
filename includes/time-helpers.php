<?php
/**
 * TIME HELPERS - Scheduler Pro v2.5
 *
 * Converts a time expression given in the site's timezone (as set in
 * WordPress > Settings > General) into a real UTC timestamp, as required
 * by wp_schedule_event().
 *
 * IMPORTANT: strtotime() alone uses PHP's default timezone (often UTC on
 * hosting, but not guaranteed), not the timezone configured in
 * WordPress. On a site where the two timezones differ, this silently
 * shifts the actual cron execution time away from the time shown in the
 * interface (e.g. "00:30" displayed but run at another time). This
 * function removes that gap by reusing get_gmt_from_date(), already used
 * elsewhere in the plugin for the same local → UTC conversion (see
 * includes/engine.php).
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
