<?php
/**
 * HUMAN TIME GENERATOR - Scheduler Pro v2.0
 * 
 * Générateur de dates/heures ultra-humain avec :
 * - Distribution non-uniforme (pics d'activité à 9h, 14h, 17h)
 * - Évitement des patterns (minutes et secondes variables)
 * - Comportement naturel sans régularité détectable
 */

if (!defined('ABSPATH')) exit;

class SP_Human_Time_Generator {
    
    /**
     * Heures de pics d'activité (distribution gaussienne)
     */
    const PEAK_HOURS = array(9, 14, 17);
    
    /**
     * Écart-type pour la distribution gaussienne (en heures)
     */
    const GAUSSIAN_STDDEV = 1.5;
    
    /**
     * Minutes à éviter (trop rondes = suspect)
     */
    const AVOID_MINUTES = array(0, 15, 30, 45);
    
    /**
     * Générer une heure de publication humaine
     * 
     * @param string $date Date au format Y-m-d
     * @param int $index Position de l'article dans la journée (1, 2, 3...)
     * @param int $total_per_day Nombre total d'articles pour cette journée
     * @param int $start_hour Heure de début (ex: 7)
     * @param int $end_hour Heure de fin (ex: 20)
     * @return string Date complète au format Y-m-d H:i:s
     */
    public static function generate($date, $index, $total_per_day, $start_hour, $end_hour) {
        // Sécurité
        $total_per_day = max(1, $total_per_day);
        $start_hour = max(0, min(23, $start_hour));
        $end_hour = max(0, min(23, $end_hour));
        
        if ($start_hour >= $end_hour) {
            $start_hour = 7;
            $end_hour = 20;
        }
        
        // Générer l'heure selon une distribution humaine
        $hour = self::generate_human_hour($index, $total_per_day, $start_hour, $end_hour);
        
        // Générer des minutes humaines (éviter les minutes rondes)
        $minute = self::generate_human_minute();
        
        // Générer des secondes variables
        $second = self::generate_human_second();
        
        return sprintf("%s %02d:%02d:%02d", $date, $hour, $minute, $second);
    }
    
    /**
     * Générer une heure selon une distribution humaine
     * 
     * Utilise une combinaison de :
     * - Distribution uniforme de base
     * - Pics gaussiens aux heures de forte activité
     * - Bruit aléatoire pour casser les patterns
     * 
     * @param int $index Position dans la journée
     * @param int $total Nombre total d'articles
     * @param int $start_hour Heure de début
     * @param int $end_hour Heure de fin
     * @return int Heure (0-23)
     */
    private static function generate_human_hour($index, $total, $start_hour, $end_hour) {
        $range = $end_hour - $start_hour;
        
        // 1. Distribution de base (uniforme avec léger bruit)
        $base_position = ($index - 1) / max(1, $total);
        $noise = (mt_rand(-100, 100) / 1000); // ±10% de bruit
        $position = max(0, min(1, $base_position + $noise));
        
        // 2. Calculer l'heure de base
        $base_hour = $start_hour + ($position * $range);
        
        // 3. Appliquer les pics d'activité (distribution gaussienne)
        $activity_weight = self::calculate_activity_weight($base_hour);
        
        // 4. Ajuster selon le poids d'activité
        // Plus le poids est élevé, plus on tend vers cette heure
        $adjustment = (mt_rand(-60, 60) / 100) * (1 - $activity_weight);
        $final_hour = $base_hour + $adjustment;
        
        // 5. Arrondir et borner
        $final_hour = max($start_hour, min($end_hour, round($final_hour)));
        
        return (int) $final_hour;
    }
    
    /**
     * Calculer le poids d'activité pour une heure donnée
     * 
     * Utilise une distribution gaussienne avec pics à 9h, 14h, 17h
     * 
     * @param float $hour Heure (peut être décimale)
     * @return float Poids entre 0 et 1
     */
    private static function calculate_activity_weight($hour) {
        $max_weight = 0;
        
        // Calculer la contribution de chaque pic
        foreach (self::PEAK_HOURS as $peak) {
            $distance = abs($hour - $peak);
            
            // Fonction gaussienne : e^(-(distance^2) / (2 * σ^2))
            $weight = exp(-pow($distance, 2) / (2 * pow(self::GAUSSIAN_STDDEV, 2)));
            
            $max_weight = max($max_weight, $weight);
        }
        
        return $max_weight;
    }
    
    /**
     * Générer des minutes "humaines"
     * 
     * Évite les minutes trop rondes (0, 15, 30, 45)
     * Favorise les minutes "impaires" comme 7, 23, 41, 58
     * 
     * @return int Minute (0-59)
     */
    private static function generate_human_minute() {
        // 70% du temps : minutes aléatoires standard
        // 30% du temps : minutes "impaires" favorisées
        
        if (mt_rand(1, 100) <= 70) {
            // Génération standard
            $minute = mt_rand(0, 59);
        } else {
            // Favoriser les minutes impaires
            $favorite_minutes = array(7, 13, 23, 27, 33, 37, 41, 47, 53, 58);
            $minute = $favorite_minutes[array_rand($favorite_minutes)];
        }
        
        // Éviter les minutes trop rondes
        while (in_array($minute, self::AVOID_MINUTES)) {
            $minute = mt_rand(0, 59);
        }
        
        return $minute;
    }
    
    /**
     * Générer des secondes variables
     * 
     * @return int Seconde (0-59)
     */
    private static function generate_human_second() {
        // Distribution légèrement biaisée vers le milieu (plus naturel)
        $random1 = mt_rand(0, 59);
        $random2 = mt_rand(0, 59);
        
        // Moyenne des deux pour distribution plus "douce"
        return (int) (($random1 + $random2) / 2);
    }
    
    /**
     * Générer une plage horaire complète pour une journée
     * 
     * Pré-génère toutes les dates pour optimiser les performances
     * 
     * @param string $date Date de base (Y-m-d)
     * @param int $count Nombre d'articles à générer
     * @param int $start_hour Heure de début
     * @param int $end_hour Heure de fin
     * @return array Liste des dates générées
     */
    public static function generate_day_schedule($date, $count, $start_hour, $end_hour) {
        $schedule = array();
        
        for ($i = 1; $i <= $count; $i++) {
            $schedule[] = self::generate($date, $i, $count, $start_hour, $end_hour);
        }
        
        // Trier pour s'assurer que les dates sont bien chronologiques
        sort($schedule);
        
        return $schedule;
    }
    
    /**
     * Analyser la distribution des heures générées (pour debug)
     * 
     * @param array $dates Liste de dates au format Y-m-d H:i:s
     * @return array Statistiques de distribution
     */
    public static function analyze_distribution($dates) {
        $hours_count = array();
        $minutes_count = array();
        
        foreach ($dates as $date) {
            $time = strtotime($date);
            $hour = (int) date('H', $time);
            $minute = (int) date('i', $time);
            
            $hours_count[$hour] = ($hours_count[$hour] ?? 0) + 1;
            $minutes_count[$minute] = ($minutes_count[$minute] ?? 0) + 1;
        }
        
        return array(
            'hours' => $hours_count,
            'minutes' => $minutes_count,
            'total' => count($dates),
            'peaks_detected' => self::detect_peaks($hours_count),
        );
    }
    
    /**
     * Détecter les pics dans la distribution
     * 
     * @param array $distribution Distribution des heures
     * @return array Heures de pic détectées
     */
    private static function detect_peaks($distribution) {
        $peaks = array();
        $avg = array_sum($distribution) / max(1, count($distribution));
        
        foreach ($distribution as $hour => $count) {
            if ($count > $avg * 1.2) { // 20% au-dessus de la moyenne
                $peaks[] = $hour;
            }
        }
        
        return $peaks;
    }
}

/**
 * Fonction helper pour compatibilité avec l'ancien code
 */
if (!function_exists('sp_calculate_human_time')) {
    function sp_calculate_human_time($date, $index, $total_per_day, $start_h, $end_h) {
        return SP_Human_Time_Generator::generate($date, $index, $total_per_day, $start_h, $end_h);
    }
}

/**
 * Test et validation (en mode debug)
 */
if (defined('WP_DEBUG') && WP_DEBUG && isset($_GET['sp_test_time_gen'])) {
    add_action('admin_init', function() {
        if (!current_user_can('manage_options')) return;
        
        echo "<h2>Test du Générateur de Temps Humain</h2>";
        
        // Générer 100 dates sur une journée
        $dates = SP_Human_Time_Generator::generate_day_schedule('2026-02-15', 100, 7, 20);
        
        echo "<h3>Échantillon de 20 dates générées :</h3><pre>";
        for ($i = 0; $i < 20; $i++) {
            echo $dates[$i] . "\n";
        }
        echo "</pre>";
        
        // Analyser la distribution
        $analysis = SP_Human_Time_Generator::analyze_distribution($dates);
        
        echo "<h3>Analyse de distribution (100 dates) :</h3>";
        echo "<h4>Distribution par heure :</h4><pre>";
        print_r($analysis['hours']);
        echo "</pre>";
        
        echo "<h4>Pics détectés :</h4><pre>";
        print_r($analysis['peaks_detected']);
        echo "</pre>";
        
        echo "<p><em>Pics attendus : 9h, 14h, 17h</em></p>";
        
        exit;
    });
}