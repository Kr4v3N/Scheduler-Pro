<?php
/**
 * ENGINE - Scheduler Pro v2.5
 *
 * Moteur de planification. Dépend de :
 * - includes/memory-guard.php (vérification mémoire)
 * - includes/logger.php (sp_log)
 * - includes/lock-manager.php (verrouillage anti-concurrence)
 * - includes/slot-finder.php (recherche de créneau, mode Adhésif)
 * - includes/human-time-generator.php (génération d'heure humaine)
 * - includes/database-manager.php (traçabilité, optionnelle)
 *
 * Ces fichiers doivent être chargés AVANT celui-ci (voir
 * sp_load_core_files() dans scheduler-pro.php, qui définit l'ordre).
 *
 * IMPORTANT : Ce moteur ne traite QUE les articles avec post_status = 'future'
 */

if (!defined('ABSPATH')) exit;

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

            // Exclusion des articles verrouillés directement dans la requête (dans les
            // deux modes) : évite de gaspiller une place de lot sur un article qui sera
            // de toute façon ignoré (auparavant filtré après coup, en PHP, dans la boucle).
            $lock_exclusion = array(
                'relation' => 'OR',
                array('key' => '_sp_lock_planning', 'compare' => 'NOT EXISTS'),
                array('key' => '_sp_lock_planning', 'value' => '1', 'compare' => '!='),
            );

            // IMPORTANT : en mode Adhésif, chaque article traité sort du résultat de la
            // requête suivante (sa meta _is_smart_scheduled passe à '1', qui est exclue
            // par le meta_query ci-dessous) : le nombre total d'articles correspondants
            // RÉTRÉCIT donc à chaque lot. Paginer par offset dans ce cas sauterait un bloc
            // entier d'articles (l'offset avance plus vite que le jeu de résultats ne
            // rétrécit). On garde donc systématiquement offset=0 en mode Adhésif : la
            // requête ramène toujours le "prochain" lot d'articles encore éligibles, et on
            // s'arrête dès qu'un lot incomplet (ou vide) est retourné.
            //
            // En mode Grand Ménage, le filtre _is_smart_scheduled n'est PAS appliqué (tous
            // les articles futurs non verrouillés restent éligibles même après traitement) :
            // le jeu de résultats NE rétrécit PAS d'un lot à l'autre, l'offset reste donc
            // nécessaire pour avancer dans la liste sans reboucler sur les mêmes articles.
            $use_offset = ($force_replan === '1');

            if (!$use_offset) {
                $base_args['meta_query'] = array(
                    'relation' => 'AND',
                    array(
                        'relation' => 'OR',
                        array('key' => '_is_smart_scheduled', 'compare' => 'NOT EXISTS'),
                        array('key' => '_is_smart_scheduled', 'value' => '0', 'compare' => '='),
                    ),
                    $lock_exclusion,
                );
            } else {
                $base_args['meta_query'] = array($lock_exclusion);
            }

            // === ÉTAPE 5 : TRAITEMENT PAR LOTS ===
            $batch_size = 100;
            $offset = 0;
            $total_processed = 0;
            $batch_number = 1;
            $completed_fully = true;

            do {
                // Vérifier la mémoire
                if (!sp_check_memory_available()) {
                    sp_log("⚠️ Mémoire critique - arrêt temporaire après {$total_processed} articles", 'WARNING');
                    $completed_fully = false;
                    break;
                }

                // Construire les arguments du lot
                $args = array_merge($base_args, array(
                    'posts_per_page' => $batch_size,
                    'offset'         => $use_offset ? $offset : 0,
                ));

                // Exécuter la requête
                $query = new WP_Query($args);
                $post_ids = $query->posts;

                if (empty($post_ids)) {
                    break;
                }

                sp_log("📦 Lot #{$batch_number} : " . count($post_ids) . " articles FUTURS à traiter", 'INFO');

                // === ÉTAPE 6 : TRAITEMENT DES ARTICLES ===
                // (les articles verrouillés sont déjà exclus par la requête, cf. ÉTAPE 4)
                foreach ($post_ids as $post_id) {
                    // Vérifier si la journée est pleine
                    if ($count_in_day >= $posts_per_day) {
                        $next_slot = sp_find_next_available_day($current_date, $posts_per_day);
                        $current_date = $next_slot['date'];
                        $count_in_day = $next_slot['count'];

                        sp_log("📅 Passage au jour suivant : {$current_date} (déjà {$count_in_day} article(s))", 'INFO');
                    }

                    // ✨ Générer une date ultra-humaine pour la position à venir.
                    // Le compteur n'est incrémenté qu'après confirmation du succès de
                    // wp_update_post() (plus bas), pour ne pas "consommer" un créneau
                    // en cas d'échec de mise à jour.
                    $new_date = SP_Human_Time_Generator::generate(
                        $current_date,
                        $count_in_day + 1,
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

                    // Le créneau est confirmé occupé : on incrémente maintenant seulement
                    $count_in_day++;

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

                $batch_number++;

                if ($use_offset) {
                    $offset += $batch_size;
                }

                // Petit délai entre chaque lot, tant qu'il en reste potentiellement d'autres
                $has_more = $use_offset ? ($query->found_posts > $offset) : (count($post_ids) === $batch_size);
                if ($has_more) {
                    usleep(100000); // 0.1 seconde
                }

            } while ($use_offset ? ($query->found_posts > $offset) : (count($post_ids) === $batch_size));

            // === ÉTAPE 7 : FINALISATION ===

            // Désactiver le mode force_replan UNIQUEMENT si le traitement est allé à son
            // terme naturel (jeu de résultats épuisé). S'il s'est arrêté prématurément
            // (mémoire critique), on le laisse actif : sinon la réorganisation serait
            // déclarée terminée alors qu'il reste des articles non traités.
            if ($force_replan === '1') {
                if ($completed_fully) {
                    update_option('sp_force_replan', '0');
                    sp_log("🔄 Mode 'Grand Ménage' désactivé automatiquement (traitement complet)", 'INFO');
                } else {
                    sp_log("⚠️ Mode 'Grand Ménage' laissé actif : le traitement s'est arrêté prématurément (mémoire). Relancez la planification pour terminer.", 'WARNING');
                }
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
