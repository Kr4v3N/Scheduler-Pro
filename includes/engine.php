<?php
/**
 * ENGINE FINAL - Scheduler Pro v2.0
 * 
 * Moteur de planification intégrant :
 * - Générateur de temps ultra-humain (human-time-generator.php)
 * - Système de verrouillage (lock-manager.php)
 * - Table de queue persistante (database-manager.php)
 * - Monitoring (heartbeat-monitor.php)
 * 
 * IMPORTANT : Ce moteur ne traite QUE les articles avec post_status = 'future'
 */

if (!defined('ABSPATH')) exit;

/**
 * Charger le générateur de temps humain
 */
require_once SP_PATH . 'includes/human-time-generator.php';

/**
 * LOGS avec rotation automatique
 */
if (!function_exists('sp_log')) {
    function sp_log($message, $level = 'INFO') {
        $log_file = WP_CONTENT_DIR . '/scheduler-pro.log';
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

/**
 * GESTION DE LA MÉMOIRE
 */
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
        
        // 1. Trouver la dernière date avec des articles futurs
        // IMPORTANT : Requête filtrée sur post_status = 'future' uniquement
        $last_date = $wpdb->get_var("
            SELECT MAX(DATE(post_date)) 
            FROM $wpdb->posts 
            WHERE post_status = 'future'
            AND post_type = 'post'
        ");
        
        // 2. Si aucun article futur, commencer demain
        if (!$last_date) {
            $start_date = date('Y-m-d', strtotime('+1 day', current_time('timestamp')));
            sp_log("📅 Aucun article FUTUR trouvé → Démarrage le {$start_date}", 'INFO');
            
            return array(
                'date' => $start_date,
                'count' => 0
            );
        }
        
        // 3. Vérifier que la date n'est pas dans le passé
        $tomorrow = date('Y-m-d', strtotime('+1 day', current_time('timestamp')));
        
        if ($last_date < $tomorrow) {
            sp_log("📅 Dernière date ({$last_date}) <= aujourd'hui → Démarrage demain ({$tomorrow})", 'INFO');
            $last_date = $tomorrow;
        }
        
        // 4. Compter les articles NON-VERROUILLÉS sur cette date
        $count = sp_count_non_locked_posts_on_date($last_date);
        
        sp_log("📊 Date de reprise : {$last_date} ({$count}/{$posts_per_day} articles FUTURS non verrouillés)", 'INFO');
        
        // 5. Si la journée est déjà pleine, trouver le prochain jour disponible
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

/**
 * 🚀 MOTEUR DE PLANIFICATION PRINCIPAL
 * 
 * IMPORTANT : Ne traite QUE les articles avec post_status = 'future'
 */
if (!function_exists('sp_process_scheduling')) {
    function sp_process_scheduling() {
        // === ÉTAPE 1 : ACQUISITION DU VERROU ===
        if (!SP_Lock_Manager::acquire('main_scheduling', 600)) {
            sp_log("❌ Une planification est déjà en cours - abandon", 'ERROR');
            return array(
                'success' => false,
                'message' => 'Une planification est déjà en cours',
                'processed' => 0
            );
        }
        
        try {
            sp_log("=== DÉBUT DE LA PLANIFICATION (Articles FUTURS uniquement) ===", 'INFO');
            
            // === ÉTAPE 2 : RÉCUPÉRATION DES PARAMÈTRES ===
            $posts_per_day = max(1, (int) get_option('sp_posts_per_day', 3));
            $start_hour    = max(0, min(23, (int) get_option('sp_start_hour', 7)));
            $end_hour      = max(0, min(23, (int) get_option('sp_end_hour', 20)));
            $force_replan  = get_option('sp_force_replan', '0');
            
            if ($start_hour >= $end_hour) {
                sp_log("⚠️ Configuration invalide : start_hour >= end_hour - correction automatique", 'WARNING');
                $start_hour = 7;
                $end_hour = 20;
            }
            
            sp_log("Réglages : {$posts_per_day} art/jour | Plage : {$start_hour}h-{$end_hour}h | Force: " . ($force_replan === '1' ? 'OUI' : 'NON'), 'INFO');
            
            // === ÉTAPE 3 : DÉTERMINATION DU POINT DE DÉPART ===
            if ($force_replan === '1') {
                $tomorrow = date('Y-m-d', strtotime('+1 day', current_time('timestamp')));
                $current_date = $tomorrow;
                $count_in_day = 0;
                
                sp_log("🧹 Mode 'Grand Ménage' activé : Réorganisation totale depuis {$tomorrow}", 'INFO');
                
            } else {
                $slot = sp_get_next_available_slot($posts_per_day);
                $current_date = $slot['date'];
                $count_in_day = $slot['count'];
                
                sp_log("📌 Mode 'Adhésif' activé : Reprise sur {$current_date} avec {$count_in_day} article(s) déjà présent(s)", 'INFO');
            }
            
            // === ÉTAPE 4 : CONSTRUCTION DE LA REQUÊTE ===
            // IMPORTANT : SEULEMENT post_status = 'future'
            $base_args = array(
                'post_status'    => 'future', // ✅ UNIQUEMENT LES ARTICLES PLANIFIÉS
                'post_type'      => 'post',
                'orderby'        => 'date',
                'order'          => 'ASC',
                'fields'         => 'ids',
            );
            
            // Exclure les articles déjà planifiés (mode adhésif)
            if ($force_replan !== '1') {
                $base_args['meta_query'] = array(
                    'relation' => 'OR',
                    array(
                        'key' => '_is_smart_scheduled',
                        'compare' => 'NOT EXISTS',
                    ),
                    array(
                        'key' => '_is_smart_scheduled',
                        'value' => '0',
                        'compare' => '=',
                    ),
                );
            }
            
            // === ÉTAPE 5 : TRAITEMENT PAR LOTS ===
            $batch_size = 100;
            $offset = 0;
            $total_processed = 0;
            $batch_number = 1;
            
            do {
                // Vérifier la mémoire
                if (!sp_check_memory_available()) {
                    sp_log("⚠️ Mémoire critique - arrêt temporaire après {$total_processed} articles", 'WARNING');
                    break;
                }
                
                // Construire les arguments du lot
                $args = array_merge($base_args, array(
                    'posts_per_page' => $batch_size,
                    'offset'         => $offset,
                ));
                
                // Exécuter la requête
                $query = new WP_Query($args);
                $post_ids = $query->posts;
                
                if (empty($post_ids)) {
                    break;
                }
                
                sp_log("📦 Lot #{$batch_number} : " . count($post_ids) . " articles FUTURS à traiter", 'INFO');
                
                // === ÉTAPE 6 : TRAITEMENT DES ARTICLES ===
                foreach ($post_ids as $post_id) {
                    // Vérifier si l'article est verrouillé
                    $is_locked = get_post_meta($post_id, '_sp_lock_planning', true);
                    if ($is_locked === '1') {
                        sp_log("🔒 Article ID {$post_id} verrouillé - ignoré", 'INFO');
                        continue;
                    }
                    
                    // Vérifier si la journée est pleine
                    if ($count_in_day >= $posts_per_day) {
                        $next_slot = sp_find_next_available_day($current_date, $posts_per_day);
                        $current_date = $next_slot['date'];
                        $count_in_day = $next_slot['count'];
                        
                        sp_log("📅 Passage au jour suivant : {$current_date} (déjà {$count_in_day} article(s))", 'INFO');
                    }
                    
                    // Incrémenter le compteur
                    $count_in_day++;
                    
                    // ✨ Générer une date ultra-humaine
                    $new_date = SP_Human_Time_Generator::generate(
                        $current_date, 
                        $count_in_day, 
                        $posts_per_day, 
                        $start_hour, 
                        $end_hour
                    );
                    
                    // Mettre à jour l'article
                    $updated_id = wp_update_post(array(
                        'ID'            => $post_id,
                        'post_date'     => $new_date,
                        'post_date_gmt' => get_gmt_from_date($new_date),
                        'edit_date'     => true
                    ), true);
                    
                    if (is_wp_error($updated_id)) {
                        sp_log("❌ ERREUR : Article ID {$post_id} - " . $updated_id->get_error_message(), 'ERROR');
                        continue;
                    }
                    
                    // Marquer comme planifié
                    update_post_meta($post_id, '_is_smart_scheduled', '1');
                    
                    sp_log("✅ Article ID {$post_id} → {$new_date}", 'SUCCESS');
                    $total_processed++;
                    
                    // Optionnel : Ajouter à la table de queue
                    if (class_exists('SP_Database_Manager')) {
                        SP_Database_Manager::add_task(
                            $post_id, 
                            $new_date, 
                            50, // Priorité normale
                            array('batch' => $batch_number)
                        );
                    }
                }
                
                // Préparer le lot suivant
                $offset += $batch_size;
                $batch_number++;
                
                // Petit délai entre chaque lot
                if ($query->found_posts > $offset) {
                    usleep(100000); // 0.1 seconde
                }
                
            } while ($query->found_posts > $offset);
            
            // === ÉTAPE 7 : FINALISATION ===
            
            // Désactiver le mode force_replan
            if ($force_replan === '1') {
                update_option('sp_force_replan', '0');
                sp_log("🔄 Mode 'Grand Ménage' désactivé automatiquement", 'INFO');
            }
            
            // Heartbeat
            update_option('sp_last_cron_run', time());
            
            sp_log("=== FIN DE LA PLANIFICATION : {$total_processed} articles FUTURS traités ===", 'SUCCESS');
            
            return array(
                'success' => true,
                'message' => "{$total_processed} articles planifiés avec succès",
                'processed' => $total_processed
            );
            
        } catch (Exception $e) {
            sp_log("❌ ERREUR CRITIQUE : " . $e->getMessage(), 'ERROR');
            sp_log("Stack trace : " . $e->getTraceAsString(), 'DEBUG');
            
            return array(
                'success' => false,
                'message' => 'Erreur : ' . $e->getMessage(),
                'processed' => 0
            );
            
        } finally {
            SP_Lock_Manager::release('main_scheduling');
        }
    }
}