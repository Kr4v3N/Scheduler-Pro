<?php
/**
 * ADMIN : ONGLET RÉGLAGES - Scheduler Pro v2.5
 */

if (!defined('ABSPATH')) exit;

function sp_render_settings_tab() {
    $posts_per_day = get_option('sp_posts_per_day', 3);
    $start_hour = get_option('sp_start_hour', 7);
    $end_hour = get_option('sp_end_hour', 20);
    $auto_mode = get_option('sp_auto_mode', '1');
    $force_replan = get_option('sp_force_replan', '0');
    $total_future = (int) wp_count_posts()->future;

    ?>
    <div class="sp-settings-grid">
        <!-- Colonne Gauche : Réglages -->
        <div class="sp-col-main">
            <form method="post">
                <?php wp_nonce_field('sp_settings_nonce'); ?>

                <!-- Card : Simulateur -->
                <div class="sp-card">
                    <h2 class="sp-card-title">
                        <span class="dashicons dashicons-calculator"></span>
                        Simulateur de Stratégie
                    </h2>

                    <div class="sp-info-box">
                        <strong><?php echo $total_future; ?></strong> articles en attente de planification
                    </div>

                    <div class="sp-simulator">
                        <div class="sp-sim-input">
                            <label>Articles / jour</label>
                            <input type="number"
                                   id="sp_posts_per_day"
                                   name="sp_posts_per_day"
                                   value="<?php echo $posts_per_day; ?>"
                                   min="1"
                                   max="50"
                                   class="sp-input-number">
                        </div>

                        <div class="sp-sim-arrow">
                            <span class="dashicons dashicons-arrow-right-alt2"></span>
                        </div>

                        <div class="sp-sim-output">
                            <label>Durée (Jours)</label>
                            <input type="number"
                                   id="sp_duration_input"
                                   value="<?php echo ceil($total_future / max(1, $posts_per_day)); ?>"
                                   min="1"
                                   class="sp-input-number">
                        </div>
                    </div>

                    <p class="sp-sim-result" id="sp-summary-text"></p>
                </div>

                <!-- Card : Horaires -->
                <div class="sp-card">
                    <h2 class="sp-card-title">
                        <span class="dashicons dashicons-clock"></span>
                        Configuration des Horaires
                    </h2>

                    <div class="sp-form-row">
                        <div class="sp-form-col">
                            <label class="sp-label">Plage horaire de publication</label>
                            <div class="sp-time-range">
                                <span>De</span>
                                <input type="number"
                                       name="sp_start_hour"
                                       value="<?php echo $start_hour; ?>"
                                       min="0"
                                       max="23"
                                       class="sp-input-time">
                                <span>h à</span>
                                <input type="number"
                                       name="sp_end_hour"
                                       value="<?php echo $end_hour; ?>"
                                       min="0"
                                       max="23"
                                       class="sp-input-time">
                                <span>h</span>
                            </div>
                            <p class="sp-help-text">
                                Recommandé : 7h-20h pour une activité naturelle
                            </p>
                        </div>

                        <div class="sp-form-col">
                            <label class="sp-label">Automatisation</label>
                            <label class="sp-checkbox-label">
                                <input type="checkbox"
                                       name="sp_auto_mode"
                                       value="1"
                                       <?php checked($auto_mode, '1'); ?>>
                                <span>Activer la planification quotidienne automatique</span>
                            </label>
                            <p class="sp-help-text">
                                Exécution quotidienne à 00:30 via WP-Cron
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Card : Mode Grand Ménage -->
                <div class="sp-card sp-card-warning">
                    <h2 class="sp-card-title">
                        <span class="dashicons dashicons-image-rotate"></span>
                        Mode "Grand Ménage"
                    </h2>

                    <label class="sp-checkbox-label">
                        <input type="checkbox"
                               name="sp_force_replan"
                               value="1"
                               <?php checked($force_replan, '1'); ?>>
                        <strong>Forcer la réorganisation de TOUS les articles futurs</strong>
                    </label>

                    <p class="sp-help-text">
                        ⚠️ <strong>Attention :</strong> Cette option réorganisera TOUS vos articles futurs depuis demain.
                        Les dates actuelles seront écrasées. Se désactive automatiquement après exécution.
                    </p>
                </div>

                <div class="sp-actions">
                    <button type="submit"
                            name="sp_save_settings"
                            class="button button-primary button-hero">
                        <span class="dashicons dashicons-saved"></span>
                        Sauvegarder les Réglages
                    </button>
                </div>
            </form>

            <!-- Action Manuelle -->
            <form method="post">
                <?php wp_nonce_field('sp_run_nonce'); ?>
                <div class="sp-card sp-card-action">
                    <h2 class="sp-card-title">
                        <span class="dashicons dashicons-controls-play"></span>
                        Exécution Manuelle
                    </h2>
                    <p>Lancer immédiatement la planification des articles (ne pas attendre le cron quotidien)</p>
                    <button type="submit"
                            name="sp_run_now"
                            class="button button-hero sp-btn-action">
                        🚀 Lancer la Planification Maintenant
                    </button>
                </div>
            </form>
        </div>

        <!-- Colonne Droite : État du Système -->
        <div class="sp-col-sidebar">
            <?php SP_Heartbeat_Monitor::render_inline_status(); ?>

            <!-- Quick Stats -->
            <div class="sp-card sp-card-stats">
                <h3>📊 Statistiques Rapides</h3>
                <div class="sp-stat-item">
                    <span class="sp-stat-label">Articles futurs</span>
                    <span class="sp-stat-value"><?php echo $total_future; ?></span>
                </div>
                <div class="sp-stat-item">
                    <span class="sp-stat-label">Articles/jour</span>
                    <span class="sp-stat-value"><?php echo $posts_per_day; ?></span>
                </div>
                <div class="sp-stat-item">
                    <span class="sp-stat-label">Durée estimée</span>
                    <span class="sp-stat-value">
                        <?php echo ceil($total_future / max(1, $posts_per_day)); ?> jours
                    </span>
                </div>
            </div>

            <!-- Aide Rapide -->
            <div class="sp-card sp-card-help">
                <h3>💡 Aide Rapide</h3>
                <ul class="sp-help-list">
                    <li>
                        <strong>Mode Adhésif :</strong> Les nouveaux articles sont ajoutés à la suite
                    </li>
                    <li>
                        <strong>Mode Grand Ménage :</strong> Tout est réorganisé depuis demain
                    </li>
                    <li>
                        <strong>Verrouillage :</strong> Utilisez la meta box sur chaque article pour bloquer sa date
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        const total = <?php echo $total_future; ?>;
        const $perDay = $('#sp_posts_per_day');
        const $duration = $('#sp_duration_input');
        const $summary = $('#sp-summary-text');

        function updateSim() {
            const days = parseInt($duration.val()) || 1;
            const date = new Date();
            date.setDate(date.getDate() + days);

            $summary.html(`
                <strong>Action :</strong> ${$perDay.val()} articles/jour jusqu'au
                <strong>${date.toLocaleDateString('fr-FR', {day:'numeric', month:'long', year:'numeric'})}</strong>
            `);
        }

        $perDay.on('input', function() {
            $duration.val(Math.ceil(total / (parseInt($perDay.val()) || 1)));
            updateSim();
        });

        $duration.on('input', function() {
            $perDay.val(Math.ceil(total / (parseInt($duration.val()) || 1)));
            updateSim();
        });

        updateSim();
    });
    </script>
    <?php
}
