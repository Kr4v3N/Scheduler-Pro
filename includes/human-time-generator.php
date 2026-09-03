<?php
/**
 * HUMAN TIME GENERATOR - Scheduler Pro v2.6
 *
 * Ultra-human date/time generator featuring:
 * - Non-uniform distribution (activity peaks at 9am, 2pm, 5pm)
 * - Pattern avoidance (variable minutes and seconds)
 * - Natural behavior with no detectable regularity
 */

if (!defined('ABSPATH')) exit;

class SP_Human_Time_Generator {

    /**
     * Peak activity hours (gaussian distribution)
     */
    const PEAK_HOURS = array(9, 14, 17);

    /**
     * Standard deviation for the gaussian distribution (in hours)
     */
    const GAUSSIAN_STDDEV = 1.5;

    /**
     * Minutes to avoid (too round = suspicious)
     */
    const AVOID_MINUTES = array(0, 15, 30, 45);

    /**
     * Generate a human publication time
     *
     * @param string $date Date in Y-m-d format
     * @param int $index Position of the post within the day (1, 2, 3...)
     * @param int $total_per_day Total number of posts for that day
     * @param int $start_hour Start hour (e.g. 7)
     * @param int $end_hour End hour (e.g. 20)
     * @return string Full date in Y-m-d H:i:s format
     */
    public static function generate($date, $index, $total_per_day, $start_hour, $end_hour) {
        // Safety
        $total_per_day = max(1, $total_per_day);
        $start_hour = max(0, min(23, $start_hour));
        $end_hour = max(0, min(23, $end_hour));

        if ($start_hour >= $end_hour) {
            $start_hour = 7;
            $end_hour = 20;
        }

        // Generate the hour following a human distribution
        $hour = self::generate_human_hour($index, $total_per_day, $start_hour, $end_hour);

        // Generate human minutes (avoid round minutes)
        $minute = self::generate_human_minute();

        // Generate variable seconds
        $second = self::generate_human_second();

        return sprintf("%s %02d:%02d:%02d", $date, $hour, $minute, $second);
    }

    /**
     * Generate an hour following a human distribution
     *
     * Combines:
     * - A base uniform distribution
     * - Gaussian peaks at high-activity hours
     * - Random noise to break patterns
     *
     * @param int $index Position within the day
     * @param int $total Total number of posts
     * @param int $start_hour Start hour
     * @param int $end_hour End hour
     * @return int Hour (0-23)
     */
    private static function generate_human_hour($index, $total, $start_hour, $end_hour) {
        $range = $end_hour - $start_hour;

        // 1. Base distribution (uniform with slight noise)
        $base_position = ($index - 1) / max(1, $total);
        $noise = (mt_rand(-100, 100) / 1000); // ±10% noise
        $position = max(0, min(1, $base_position + $noise));

        // 2. Compute the base hour
        $base_hour = $start_hour + ($position * $range);

        // 3. Apply activity peaks (gaussian distribution)
        $activity_weight = self::calculate_activity_weight($base_hour);

        // 4. Adjust based on the activity weight
        // The higher the weight, the more it tends toward that hour
        $adjustment = (mt_rand(-60, 60) / 100) * (1 - $activity_weight);
        $final_hour = $base_hour + $adjustment;

        // 5. Round and clamp
        $final_hour = max($start_hour, min($end_hour, round($final_hour)));

        return (int) $final_hour;
    }

    /**
     * Compute the activity weight for a given hour
     *
     * Uses a gaussian distribution with peaks at 9am, 2pm, 5pm
     *
     * @param float $hour Hour (can be a decimal)
     * @return float Weight between 0 and 1
     */
    private static function calculate_activity_weight($hour) {
        $max_weight = 0;

        // Compute each peak's contribution
        foreach (self::PEAK_HOURS as $peak) {
            $distance = abs($hour - $peak);

            // Gaussian function: e^(-(distance^2) / (2 * σ^2))
            $weight = exp(-pow($distance, 2) / (2 * pow(self::GAUSSIAN_STDDEV, 2)));

            $max_weight = max($max_weight, $weight);
        }

        return $max_weight;
    }

    /**
     * Generate "human" minutes
     *
     * Avoids overly round minutes (0, 15, 30, 45)
     * Favors "odd" minutes like 7, 23, 41, 58
     *
     * @return int Minute (0-59)
     */
    private static function generate_human_minute() {
        // 70% of the time: standard random minutes
        // 30% of the time: favored "odd" minutes

        if (mt_rand(1, 100) <= 70) {
            // Standard generation
            $minute = mt_rand(0, 59);
        } else {
            // Favor odd minutes
            $favorite_minutes = array(7, 13, 23, 27, 33, 37, 41, 47, 53, 58);
            $minute = $favorite_minutes[array_rand($favorite_minutes)];
        }

        // Avoid overly round minutes
        while (in_array($minute, self::AVOID_MINUTES)) {
            $minute = mt_rand(0, 59);
        }

        return $minute;
    }

    /**
     * Generate variable seconds
     *
     * @return int Second (0-59)
     */
    private static function generate_human_second() {
        // Distribution slightly biased toward the middle (more natural)
        $random1 = mt_rand(0, 59);
        $random2 = mt_rand(0, 59);

        // Average of the two for a "smoother" distribution
        return (int) (($random1 + $random2) / 2);
    }

    /**
     * Generate a full time range for a given day
     *
     * Pre-generates all dates to optimize performance
     *
     * @param string $date Base date (Y-m-d)
     * @param int $count Number of posts to generate
     * @param int $start_hour Start hour
     * @param int $end_hour End hour
     * @return array List of generated dates
     */
    public static function generate_day_schedule($date, $count, $start_hour, $end_hour) {
        $schedule = array();

        for ($i = 1; $i <= $count; $i++) {
            $schedule[] = self::generate($date, $i, $count, $start_hour, $end_hour);
        }

        // Sort to make sure the dates are chronological
        sort($schedule);

        return $schedule;
    }

    /**
     * Analyze the distribution of generated hours (for debugging)
     *
     * @param array $dates List of dates in Y-m-d H:i:s format
     * @return array Distribution statistics
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
     * Detect peaks in the distribution
     *
     * @param array $distribution Hour distribution
     * @return array Detected peak hours
     */
    private static function detect_peaks($distribution) {
        $peaks = array();
        $avg = array_sum($distribution) / max(1, count($distribution));

        foreach ($distribution as $hour => $count) {
            if ($count > $avg * 1.2) { // 20% above the average
                $peaks[] = $hour;
            }
        }

        return $peaks;
    }
}

/**
 * Helper function for backward compatibility
 */
if (!function_exists('sp_calculate_human_time')) {
    function sp_calculate_human_time($date, $index, $total_per_day, $start_h, $end_h) {
        return SP_Human_Time_Generator::generate($date, $index, $total_per_day, $start_h, $end_h);
    }
}

/**
 * Test and validation (debug mode)
 */
if (defined('WP_DEBUG') && WP_DEBUG && isset($_GET['sp_test_time_gen'])) {
    add_action('admin_init', function() {
        if (!current_user_can('manage_options')) return;

        echo "<h2>Human Time Generator Test</h2>";

        // Generate 100 dates over a single day
        $dates = SP_Human_Time_Generator::generate_day_schedule('2026-02-15', 100, 7, 20);

        echo "<h3>Sample of 20 generated dates:</h3><pre>";
        for ($i = 0; $i < 20; $i++) {
            echo $dates[$i] . "\n";
        }
        echo "</pre>";

        // Analyze the distribution
        $analysis = SP_Human_Time_Generator::analyze_distribution($dates);

        echo "<h3>Distribution analysis (100 dates):</h3>";
        echo "<h4>Distribution by hour:</h4><pre>";
        print_r($analysis['hours']);
        echo "</pre>";

        echo "<h4>Detected peaks:</h4><pre>";
        print_r($analysis['peaks_detected']);
        echo "</pre>";

        echo "<p><em>Expected peaks: 9am, 2pm, 5pm</em></p>";

        exit;
    });
}
