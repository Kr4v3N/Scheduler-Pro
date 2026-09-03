<?php
/**
 * ADMIN : ONGLET À PROPOS - Scheduler Pro v2.5
 */

if (!defined('ABSPATH')) exit;

function sp_render_about_tab() {
    ?>
    <div class="sp-about-grid">
        <div class="sp-card">
            <h2 class="sp-card-title">
                <span class="dashicons dashicons-info"></span>
                Scheduler Pro v<?php echo SP_VERSION; ?>
            </h2>

            <p class="sp-about-description">
                Plugin avancé de planification automatique et humaine des articles WordPress.
                Conçu pour optimiser votre flux de publication SEO tout en conservant une apparence naturelle.
            </p>

            <h3>✨ Nouveautés v2.5</h3>
            <ul class="sp-feature-list">
                <li>✅ <strong>Verrouillage atomique</strong> : élimination de la fenêtre de course sur le verrou d'exécution</li>
                <li>✅ <strong>Planification fiable</strong> : correction d'un bug qui pouvait sauter des articles en mode Adhésif</li>
                <li>✅ <strong>Fuseau horaire respecté</strong> : la planification quotidienne respecte le fuseau réglé dans WordPress</li>
                <li>✅ <strong>Journal protégé</strong> : le fichier de log n'est plus accessible en HTTP direct</li>
                <li>✅ <strong>Architecture modulaire</strong> : code découpé par responsabilité pour faciliter la maintenance</li>
            </ul>

            <h3>🧱 Fonctionnalités Principales</h3>
            <ul class="sp-feature-list">
                <li>✅ <strong>Traitement par lots</strong> : Gestion de milliers d'articles sans timeout</li>
                <li>✅ <strong>Gestion mémoire</strong> : Arrêt automatique si RAM saturée</li>
                <li>✅ <strong>Système de verrouillage</strong> : Évite les doubles exécutions</li>
                <li>✅ <strong>Générateur ultra-humain</strong> : Pics d'activité à 9h, 14h, 17h</li>
                <li>✅ <strong>Monitoring temps réel</strong> : Surveillance de la santé du système</li>
                <li>✅ <strong>Table de queue</strong> : Traçabilité complète de chaque planification</li>
            </ul>

            <h3>🔧 Configuration Recommandée</h3>
            <table class="sp-config-table">
                <tr>
                    <td><strong>Articles/jour :</strong></td>
                    <td>3-5 (naturel et SEO-friendly)</td>
                </tr>
                <tr>
                    <td><strong>Plage horaire :</strong></td>
                    <td>7h-20h (heures d'activité humaine)</td>
                </tr>
                <tr>
                    <td><strong>Mode Auto :</strong></td>
                    <td>Activé (exécution quotidienne à 00:30, heure du site)</td>
                </tr>
                <tr>
                    <td><strong>Cron Serveur :</strong></td>
                    <td>Recommandé pour fiabilité maximale</td>
                </tr>
            </table>
        </div>

        <div class="sp-card sp-card-help">
            <h2 class="sp-card-title">
                <span class="dashicons dashicons-sos"></span>
                Besoin d'Aide ?
            </h2>

            <div class="sp-help-section">
                <h4>📖 Documentation</h4>
                <p>Consultez les fichiers README et guides fournis avec le plugin.</p>
            </div>

            <div class="sp-help-section">
                <h4>🐛 Signaler un Bug</h4>
                <p>Activez le mode debug WordPress et consultez les logs.</p>
                <code>define('WP_DEBUG', true);</code>
            </div>

            <div class="sp-help-section">
                <h4>⚡ Performance</h4>
                <p>
                    <strong>Avant v2.0 :</strong> 1000 articles = 50s (timeout)<br>
                    <strong>Après v2.0 :</strong> 1000 articles = 5s ✅
                </p>
            </div>
        </div>
    </div>

    <div class="sp-card sp-card-credits">
        <p style="text-align: center; opacity: 0.7;">
            Développé par <strong>Kr4v3n</strong> | Version <?php echo SP_VERSION; ?> |
            Audit et Optimisation par Claude (Anthropic)
        </p>
    </div>
    <?php
}
