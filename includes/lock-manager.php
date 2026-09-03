<?php
/**
 * LOCK MANAGER - Scheduler Pro v2.5
 *
 * Système de verrouillage pour éviter les exécutions concurrentes.
 *
 * Utilise add_option() plutôt que les transients WordPress : la colonne
 * option_name de wp_options porte une contrainte UNIQUE, ce qui rend
 * add_option() réellement atomique au niveau base de données (l'INSERT
 * concurrent perdant échoue et add_option() retourne false), alors que
 * les transients (get_transient() + set_transient() séparés) exposaient
 * une fenêtre de course classique : deux appelants quasi simultanés
 * pouvaient tous deux lire "pas de verrou" avant que l'un des deux
 * n'écrive le sien.
 */

if (!defined('ABSPATH')) exit;

class SP_Lock_Manager {

    const DEFAULT_TIMEOUT = 300; // 5 minutes (timeout par défaut d'un verrou)

    /**
     * Seuil utilisé par le nettoyage quotidien pour considérer un verrou
     * comme abandonné. Volontairement plus large que DEFAULT_TIMEOUT :
     * le verrou 'main_scheduling' (voir includes/engine.php) est acquis
     * avec un timeout de 600s. Un seuil de nettoyage fixé à
     * DEFAULT_TIMEOUT (300s) supprimerait ce verrou alors qu'une
     * exécution légitime tourne encore. 1800s reste confortablement
     * au-dessus du plus grand timeout utilisé dans le plugin tout en
     * finissant par nettoyer les verrous réellement abandonnés (crash).
     */
    const STALE_CLEANUP_THRESHOLD = 1800; // 30 minutes

    const LOCK_PREFIX = 'sp_lock_';

    /**
     * Acquérir un verrou de façon atomique
     *
     * @param string $lock_name Nom du verrou (ex: 'main_scheduling')
     * @param int $timeout Durée maximale du verrou en secondes
     * @return bool True si verrou acquis, False si déjà actif
     */
    public static function acquire($lock_name, $timeout = self::DEFAULT_TIMEOUT) {
        $lock_key = self::LOCK_PREFIX . sanitize_key($lock_name);
        $now = time();

        // Tentative atomique : échoue si la clé existe déjà (contrainte UNIQUE en base)
        if (add_option($lock_key, $now, '', 'no')) {
            sp_log("🔒 Verrou acquis sur '{$lock_name}' (timeout: {$timeout}s)", 'INFO');
            return true;
        }

        // La clé existe déjà : vérifier si le verrou est périmé (crash, bug)
        $existing = (int) get_option($lock_key);
        $age = $now - $existing;

        if ($age > $timeout) {
            update_option($lock_key, $now, 'no');
            sp_log("🔓 Verrou périmé '{$lock_name}' remplacé (âge: {$age}s)", 'WARNING');
            return true;
        }

        sp_log("⚠️ Verrou actif sur '{$lock_name}' (âge: {$age}s) - abandon", 'WARNING');
        return false;
    }

    /**
     * Libérer un verrou
     */
    public static function release($lock_name) {
        $lock_key = self::LOCK_PREFIX . sanitize_key($lock_name);

        $existed = get_option($lock_key) !== false;

        delete_option($lock_key);

        if ($existed) {
            sp_log("🔓 Verrou libéré sur '{$lock_name}'", 'INFO');
        }

        return $existed;
    }

    /**
     * Vérifier si un verrou est actif
     */
    public static function is_locked($lock_name) {
        $lock_key = self::LOCK_PREFIX . sanitize_key($lock_name);
        return get_option($lock_key) !== false;
    }

    /**
     * Obtenir l'âge d'un verrou en secondes
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
     * Nettoyer tous les verrous périmés (appelé quotidiennement via cron)
     *
     * Utilise STALE_CLEANUP_THRESHOLD, pas DEFAULT_TIMEOUT : voir le
     * docblock de la constante plus haut.
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
            sp_log("🧹 {$deleted} verrou(s) périmé(s) nettoyé(s)", 'CLEANUP');
        }

        return $deleted;
    }

    /**
     * Nettoyer TOUS les verrous (forcé, utilisé à la désactivation)
     */
    public static function cleanup_all_locks() {
        global $wpdb;

        $deleted = $wpdb->query($wpdb->prepare("
            DELETE FROM {$wpdb->options}
            WHERE option_name LIKE %s
        ", $wpdb->esc_like(self::LOCK_PREFIX) . '%'));

        if ($deleted > 0) {
            sp_log("🧹 {$deleted} verrou(s) supprimé(s) lors du nettoyage forcé", 'CLEANUP');
        }

        return $deleted;
    }

    /**
     * Lister tous les verrous actifs
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
     * Exécuter une fonction avec verrouillage automatique
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
 * Hook de nettoyage quotidien
 */
add_action('sp_daily_cleanup', array('SP_Lock_Manager', 'cleanup_stale_locks'));

/**
 * Fonctions helper pour la compatibilité avec l'ancien code
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
