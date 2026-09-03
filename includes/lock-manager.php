<?php
/**
 * LOCK MANAGER - Scheduler Pro v2.0
 * 
 * Système de verrouillage pour éviter les exécutions concurrentes
 * Utilise les transients WordPress pour un verrouillage léger et efficace
 */

if (!defined('ABSPATH')) exit;

class SP_Lock_Manager {
    
    /**
     * Durée par défaut des verrous (secondes)
     */
    const DEFAULT_TIMEOUT = 300; // 5 minutes
    
    /**
     * Préfixe pour les clés de verrous
     */
    const LOCK_PREFIX = 'sp_lock_';
    
    /**
     * Acquérir un verrou
     * 
     * @param string $lock_name Nom du verrou (ex: 'main_scheduling')
     * @param int $timeout Durée maximale du verrou en secondes
     * @return bool True si verrou acquis, False si déjà actif
     */
    public static function acquire($lock_name, $timeout = self::DEFAULT_TIMEOUT) {
        $lock_key = self::LOCK_PREFIX . sanitize_key($lock_name);
        
        // Vérifier si un verrou existe déjà
        $existing_lock = get_transient($lock_key);
        
        if ($existing_lock !== false) {
            $age = time() - $existing_lock;
            
            // Si le verrou est trop vieux (bug ou crash), le supprimer
            if ($age > $timeout) {
                delete_transient($lock_key);
                sp_log("🔓 Verrou périmé '{$lock_name}' supprimé (âge: {$age}s)", 'WARNING');
            } else {
                sp_log("⚠️ Verrou actif sur '{$lock_name}' (âge: {$age}s) - abandon", 'WARNING');
                return false;
            }
        }
        
        // Créer le verrou avec timestamp actuel
        set_transient($lock_key, time(), $timeout);
        
        sp_log("🔒 Verrou acquis sur '{$lock_name}' (timeout: {$timeout}s)", 'INFO');
        
        return true;
    }
    
    /**
     * Libérer un verrou
     * 
     * @param string $lock_name Nom du verrou
     * @return bool True si libéré, False si n'existait pas
     */
    public static function release($lock_name) {
        $lock_key = self::LOCK_PREFIX . sanitize_key($lock_name);
        
        $existed = get_transient($lock_key) !== false;
        
        delete_transient($lock_key);
        
        if ($existed) {
            sp_log("🔓 Verrou libéré sur '{$lock_name}'", 'INFO');
        }
        
        return $existed;
    }
    
    /**
     * Vérifier si un verrou est actif
     * 
     * @param string $lock_name Nom du verrou
     * @return bool True si verrou actif
     */
    public static function is_locked($lock_name) {
        $lock_key = self::LOCK_PREFIX . sanitize_key($lock_name);
        return get_transient($lock_key) !== false;
    }
    
    /**
     * Obtenir l'âge d'un verrou en secondes
     * 
     * @param string $lock_name Nom du verrou
     * @return int|false Âge en secondes ou false si pas de verrou
     */
    public static function get_lock_age($lock_name) {
        $lock_key = self::LOCK_PREFIX . sanitize_key($lock_name);
        $lock_time = get_transient($lock_key);
        
        if ($lock_time === false) {
            return false;
        }
        
        return time() - $lock_time;
    }
    
    /**
     * Nettoyer tous les verrous périmés
     * 
     * Cette fonction est appelée quotidiennement via cron
     * 
     * @return int Nombre de verrous nettoyés
     */
    public static function cleanup_stale_locks() {
        global $wpdb;
        
        // Supprimer les verrous expirés (transients périmés)
        $deleted = $wpdb->query("
            DELETE FROM {$wpdb->options}
            WHERE option_name LIKE '_transient_" . self::LOCK_PREFIX . "%'
            AND option_value < UNIX_TIMESTAMP() - " . self::DEFAULT_TIMEOUT . "
        ");
        
        if ($deleted > 0) {
            sp_log("🧹 {$deleted} verrou(s) périmé(s) nettoyé(s)", 'CLEANUP');
        }
        
        return $deleted;
    }
    
    /**
     * Nettoyer TOUS les verrous (forcé)
     * 
     * Utilisé lors de la désactivation du plugin
     * 
     * @return int Nombre de verrous supprimés
     */
    public static function cleanup_all_locks() {
        global $wpdb;
        
        $deleted = $wpdb->query("
            DELETE FROM {$wpdb->options}
            WHERE option_name LIKE '_transient_" . self::LOCK_PREFIX . "%'
            OR option_name LIKE '_transient_timeout_" . self::LOCK_PREFIX . "%'
        ");
        
        if ($deleted > 0) {
            sp_log("🧹 {$deleted} verrou(s) supprimé(s) lors du nettoyage forcé", 'CLEANUP');
        }
        
        return $deleted;
    }
    
    /**
     * Lister tous les verrous actifs
     * 
     * @return array Liste des verrous avec leurs infos
     */
    public static function list_active_locks() {
        global $wpdb;
        
        $locks = $wpdb->get_results("
            SELECT 
                REPLACE(option_name, '_transient_" . self::LOCK_PREFIX . "', '') as lock_name,
                option_value as created_at
            FROM {$wpdb->options}
            WHERE option_name LIKE '_transient_" . self::LOCK_PREFIX . "%'
        ", ARRAY_A);
        
        // Calculer l'âge de chaque verrou
        foreach ($locks as &$lock) {
            $lock['age_seconds'] = time() - (int) $lock['created_at'];
            $lock['created_at_human'] = date('Y-m-d H:i:s', (int) $lock['created_at']);
        }
        
        return $locks;
    }
    
    /**
     * Exécuter une fonction avec verrouillage automatique
     * 
     * Cette méthode est un helper qui acquiert le verrou, exécute la fonction,
     * puis libère le verrou même en cas d'erreur.
     * 
     * @param string $lock_name Nom du verrou
     * @param callable $callback Fonction à exécuter
     * @param int $timeout Timeout du verrou
     * @return mixed Résultat de la fonction ou false si verrou non acquis
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