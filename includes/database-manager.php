<?php
/**
 * DATABASE MANAGER - Scheduler Pro v2.0
 * 
 * Gestion de la table de queue persistante pour les tâches planifiées
 * 
 * Fonctionnalités :
 * - Table wp_scheduler_queue pour traçabilité
 * - Système de statuts (pending, running, completed, failed)
 * - Gestion des priorités
 * - Système de retry automatique
 */

if (!defined('ABSPATH')) exit;

class SP_Database_Manager {
    
    /**
     * Nom de la table (sans préfixe)
     */
    const TABLE_NAME = 'scheduler_queue';
    
    /**
     * Version du schéma DB
     */
    const DB_VERSION = '2.0';
    
    /**
     * Statuts disponibles
     */
    const STATUS_PENDING = 'pending';
    const STATUS_RUNNING = 'running';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    
    /**
     * Créer ou mettre à jour les tables lors de l'activation
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
        
        // Enregistrer la version du schéma
        update_option('sp_db_version', self::DB_VERSION);
        
        sp_log("✅ Table {$table_name} créée/mise à jour (version " . self::DB_VERSION . ")", 'INSTALL');
    }
    
    /**
     * Ajouter une tâche à la queue
     * 
     * @param int $post_id ID de l'article
     * @param string $scheduled_date Date de planification (Y-m-d H:i:s)
     * @param int $priority Priorité (10=urgent, 90=background)
     * @param array $metadata Données supplémentaires
     * @return int|false ID de la tâche ou false si échec
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
            sp_log("📝 Tâche #{$task_id} ajoutée : Post {$post_id} → {$scheduled_date}", 'QUEUE');
            return $task_id;
        }
        
        sp_log("❌ Échec ajout tâche pour Post {$post_id}", 'ERROR');
        return false;
    }
    
    /**
     * Récupérer les tâches en attente (par ordre de priorité)
     * 
     * @param int $limit Nombre max de tâches à retourner
     * @return array Liste des tâches
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
     * Marquer une tâche comme "en cours"
     * 
     * @param int $task_id ID de la tâche
     * @return bool Succès
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
            sp_log("▶️ Tâche #{$task_id} démarrée", 'QUEUE');
            return true;
        }
        
        return false;
    }
    
    /**
     * Marquer une tâche comme "terminée"
     * 
     * @param int $task_id ID de la tâche
     * @return bool Succès
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
            sp_log("✅ Tâche #{$task_id} terminée", 'QUEUE');
            return true;
        }
        
        return false;
    }
    
    /**
     * Marquer une tâche comme "échouée" avec retry
     * 
     * @param int $task_id ID de la tâche
     * @param string $error_message Message d'erreur
     * @param int $max_attempts Nombre max de tentatives
     * @return bool Succès
     */
    public static function mark_as_failed($task_id, $error_message, $max_attempts = 3) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        
        // Récupérer le nombre actuel de tentatives
        $current_attempts = (int) $wpdb->get_var($wpdb->prepare("
            SELECT attempts FROM $table_name WHERE id = %d
        ", $task_id));
        
        $new_attempts = $current_attempts + 1;
        
        // Si on a atteint le max, marquer comme failed
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
                sp_log("❌ Tâche #{$task_id} ÉCHEC DÉFINITIF après {$new_attempts} tentatives : {$error_message}", 'ERROR');
            } else {
                sp_log("⚠️ Tâche #{$task_id} tentative {$new_attempts}/{$max_attempts} : {$error_message}", 'WARNING');
            }
            return true;
        }
        
        return false;
    }
    
    /**
     * Nettoyer les vieilles tâches terminées
     * 
     * @param int $days Nombre de jours à conserver
     * @return int Nombre de tâches supprimées
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
            sp_log("🧹 {$deleted} ancienne(s) tâche(s) supprimée(s) (> {$days} jours)", 'CLEANUP');
        }
        
        return $deleted;
    }
    
    /**
     * Statistiques de la queue
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
     * Récupérer l'historique d'une tâche spécifique
     * 
     * @param int $post_id ID de l'article
     * @return array Liste des tâches pour cet article
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
     * Réinitialiser les tâches bloquées en "running" depuis trop longtemps
     * 
     * @param int $timeout Minutes de timeout
     * @return int Nombre de tâches réinitialisées
     */
    public static function reset_stuck_tasks($timeout = 30) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        
        $reset = $wpdb->query($wpdb->prepare("
            UPDATE $table_name
            SET status = %s,
                last_error = 'Tâche bloquée - réinitialisée automatiquement'
            WHERE status = %s
            AND updated_at < DATE_SUB(NOW(), INTERVAL %d MINUTE)
        ", self::STATUS_PENDING, self::STATUS_RUNNING, $timeout));
        
        if ($reset > 0) {
            sp_log("🔄 {$reset} tâche(s) bloquée(s) réinitialisée(s) (timeout: {$timeout}min)", 'CLEANUP');
        }
        
        return $reset;
    }
    
    /**
     * Supprimer les tables lors de la désinstallation
     */
    public static function drop_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        
        $wpdb->query("DROP TABLE IF EXISTS $table_name");
        
        delete_option('sp_db_version');
        
        sp_log("❌ Table {$table_name} supprimée", 'UNINSTALL');
    }
}

/**
 * Tâche quotidienne de nettoyage
 */
add_action('sp_daily_cleanup', function() {
    SP_Database_Manager::cleanup_old_tasks(30); // Garder 30 jours
    SP_Database_Manager::reset_stuck_tasks(30); // Timeout 30 minutes
});