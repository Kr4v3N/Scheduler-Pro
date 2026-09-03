<?php
/**
 * SLOT FINDER - Scheduler Pro v2.5
 *
 * Recherche du prochain créneau disponible (jour + position) pour le
 * mode Adhésif de sp_process_scheduling(). Ne compte que les articles
 * post_status = 'future', non verrouillés (_sp_lock_planning != '1').
 */

if (!defined('ABSPATH')) exit;

/**
 * COMPTER LES ARTICLES NON-VERROUILLÉS SUR UNE DATE
 *
 * IMPORTANT : Ne compte QUE les articles avec post_status = 'future'
 */
if (!function_exists('sp_count_non_locked_posts_on_date')) {
    function sp_count_non_locked_posts_on_date($date) {
        global $wpdb;

        $count = (int) $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT p.ID)
            FROM $wpdb->posts p
            LEFT JOIN $wpdb->postmeta m ON p.ID = m.post_id AND m.meta_key = '_sp_lock_planning'
            WHERE p.post_status = 'future'
            AND p.post_type = 'post'
            AND DATE(p.post_date) = %s
            AND (m.meta_value IS NULL OR m.meta_value != '1')
        ", $date));

        return $count;
    }
}

/**
 * TROUVER LE PROCHAIN CRÉNEAU DISPONIBLE
 */
if (!function_exists('sp_get_next_available_slot')) {
    function sp_get_next_available_slot($posts_per_day) {
        global $wpdb;

        $last_date = $wpdb->get_var("
            SELECT MAX(DATE(post_date))
            FROM $wpdb->posts
            WHERE post_status = 'future'
            AND post_type = 'post'
        ");

        if (!$last_date) {
            $start_date = date('Y-m-d', strtotime('+1 day', current_time('timestamp')));
            sp_log("📅 Aucun article FUTUR trouvé → Démarrage le {$start_date}", 'INFO');

            return array(
                'date' => $start_date,
                'count' => 0
            );
        }

        $tomorrow = date('Y-m-d', strtotime('+1 day', current_time('timestamp')));

        if ($last_date < $tomorrow) {
            sp_log("📅 Dernière date ({$last_date}) <= aujourd'hui → Démarrage demain ({$tomorrow})", 'INFO');
            $last_date = $tomorrow;
        }

        $count = sp_count_non_locked_posts_on_date($last_date);

        sp_log("📊 Date de reprise : {$last_date} ({$count}/{$posts_per_day} articles FUTURS non verrouillés)", 'INFO');

        while ($count >= $posts_per_day) {
            $last_date = date('Y-m-d', strtotime("+1 day", strtotime($last_date)));
            $count = sp_count_non_locked_posts_on_date($last_date);

            sp_log("📅 Journée pleine → Passage au {$last_date} ({$count}/{$posts_per_day} articles)", 'INFO');
        }

        return array(
            'date' => $last_date,
            'count' => $count
        );
    }
}

/**
 * TROUVER LE PROCHAIN JOUR DISPONIBLE (avec saut automatique)
 */
if (!function_exists('sp_find_next_available_day')) {
    function sp_find_next_available_day($current_date, $posts_per_day) {
        $next_date = date('Y-m-d', strtotime("+1 day", strtotime($current_date)));
        $count = sp_count_non_locked_posts_on_date($next_date);

        $max_iterations = 365;
        $iterations = 0;

        while ($count >= $posts_per_day && $iterations < $max_iterations) {
            sp_log("⏭️ Jour {$next_date} déjà plein ({$count}/{$posts_per_day}) → Passage au suivant", 'INFO');
            $next_date = date('Y-m-d', strtotime("+1 day", strtotime($next_date)));
            $count = sp_count_non_locked_posts_on_date($next_date);
            $iterations++;
        }

        if ($iterations >= $max_iterations) {
            sp_log("❌ ERREUR : Impossible de trouver un jour disponible après {$max_iterations} tentatives", 'ERROR');
        }

        return array(
            'date' => $next_date,
            'count' => $count
        );
    }
}
