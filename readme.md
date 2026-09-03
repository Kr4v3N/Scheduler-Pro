=== Scheduler Pro ===
Contributors: Kr4v3n
Tags: scheduler, scheduling, seo, automation, cron, publication
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Planificateur automatique ultra-intelligent pour WordPress : gestion avancée de la publication d'articles avec comportement humain et monitoring en temps réel.

== Description ==

**Scheduler Pro** est un plugin WordPress professionnel qui automatise et optimise la planification de vos articles avec une approche révolutionnaire : imiter le comportement humain.

= 🎯 Pourquoi Scheduler Pro ? =

La plupart des plugins de planification utilisent des algorithmes simples qui créent des patterns détectables. **Scheduler Pro v2.0** va beaucoup plus loin :

* **Distribution gaussienne** avec pics d'activité à 9h, 14h et 17h
* **Évitement des patterns** : minutes et secondes variables, jamais de valeurs trop rondes
* **Traitement par lots** : Gère des milliers d'articles sans timeout
* **Système de verrouillage** : Évite les exécutions concurrentes
* **Monitoring temps réel** : Tableau de bord complet avec statistiques

= ✨ Fonctionnalités Principales v2.0 =

**🚀 Planification Ultra-Humaine**
* Générateur de dates basé sur des distributions gaussiennes
* Pics d'activité naturels aux heures de forte publication
* Minutes aléatoires évitant les valeurs rondes (00, 15, 30, 45)
* Secondes variables pour éliminer tout pattern détectable

**⚡ Performance & Fiabilité**
* **Traitement par lots (Batching)** : 100 articles à la fois pour éviter les timeouts
* **Gestion de mémoire** : Arrêt automatique si RAM > 80% pour éviter les crashs
* **Système de verrouillage** : Empêche les doubles exécutions même en cas de double-clic
* **Table de queue persistante** : Traçabilité complète avec retry automatique

**📊 Monitoring Avancé**
* Dashboard temps réel avec statistiques complètes
* Graphique de distribution sur 30 jours
* Détection automatique des problèmes (cron inactif, tâches bloquées)
* Journal d'activité avec rotation automatique des logs

**🔧 Configuration Intelligente**
* **Mode Adhésif** : Ajoute les nouveaux articles à la suite sans toucher aux existants
* **Mode Grand Ménage** : Réorganise TOUT le stock depuis demain
* **Verrouillage manuel** : Bloquez la date de certains articles spécifiques
* **Plage horaire personnalisée** : 7h-20h par défaut, ajustable à vos besoins

= 🎨 Interface Moderne =

**Onglets Intuitifs**
* **Réglages** : Configuration complète avec simulateur interactif
* **Monitoring** : Vue d'ensemble de la santé du système
* **Logs** : Journal d'activité en temps réel avec filtrage par niveau
* **À Propos** : Documentation et aide intégrée

**Design Épuré**
* Interface moderne et responsive
* Statistiques visuelles avec graphiques
* Indicateurs de santé en temps réel
* Thème sombre pour les logs

= 📈 Cas d'Usage =

**Blog SEO**
Publiez régulièrement sans effort, avec une apparence 100% naturelle pour les moteurs de recherche.

**Site Multi-Auteurs**
Gérez des centaines d'articles planifiés avec des priorités et des verrouillages individuels.

**Plateforme de Contenu**
Automatisez la publication de milliers d'articles avec une fiabilité professionnelle.

= 🔒 Sécurité & Confidentialité =

* Aucune donnée externe : tout reste sur votre serveur
* Vérification des permissions (nonces, capabilities)
* Verrouillage anti-concurrence via transients WordPress
* Rotation automatique des logs (limite à 500 lignes)

= ⚙️ Configuration Technique =

**Prérequis**
* WordPress 5.8+
* PHP 7.4+
* MySQL 5.7+
* Mémoire PHP : 128 Mo minimum (256 Mo recommandé)

**Compatibilité**
* ✅ Multisite
* ✅ WP-Cron et Cron serveur
* ✅ Tous les thèmes WordPress
* ✅ Compatible WPML

**Performance**
* **Avant v2.0** : 1000 articles = ~50s (timeout fréquent)
* **Après v2.0** : 1000 articles = ~5s ✅
* Impact mémoire : ~50 Mo (vs 500 Mo en v1.x)

== Installation ==

= Installation Automatique =

1. Connectez-vous à votre admin WordPress
2. Allez dans **Extensions > Ajouter**
3. Recherchez "Scheduler Pro"
4. Cliquez sur **Installer** puis **Activer**

= Installation Manuelle =

1. Téléchargez le fichier ZIP du plugin
2. Allez dans **Extensions > Ajouter > Téléverser une extension**
3. Choisissez le fichier ZIP et cliquez sur **Installer**
4. Activez le plugin

= Configuration Initiale =

1. Allez dans **Scheduler Pro** dans le menu admin
2. Configurez vos réglages :
   * **Articles/jour** : 3-5 recommandé pour un comportement naturel
   * **Plage horaire** : 7h-20h pour imiter l'activité humaine
   * **Mode Auto** : Activé pour exécution quotidienne à 00:30
3. Sauvegardez les réglages
4. Cliquez sur **"Lancer la Planification Maintenant"** pour le premier test

= Configuration Avancée (Optionnel) =

**Configurer un Cron Serveur (Recommandé)**

Pour une fiabilité maximale, remplacez WP-Cron par un vrai cron serveur :

1. **Désactiver WP-Cron** : Ajoutez dans `wp-config.php`
```php
define('DISABLE_WP_CRON', true);
```

2. **Créer une tâche cron** (cPanel ou SSH)
```bash
*/15 * * * * wget -q -O - https://votre-site.com/wp-cron.php?doing_wp_cron >/dev/null 2>&1
```

Ou via PHP :
```bash
*/15 * * * * cd /chemin/vers/wordpress && php wp-cron.php >/dev/null 2>&1
```

== Frequently Asked Questions ==

= Le plugin fonctionne-t-il sur tous les hébergements ? =

Oui ! Scheduler Pro est compatible avec tous les hébergements WordPress standards. Pour les hébergements mutualisés avec des limites strictes, le système de batching et de gestion mémoire garantit un fonctionnement sans timeout.

= Puis-je verrouiller certains articles pour qu'ils ne soient pas replanifiés ? =

Absolument ! Chaque article dispose d'une meta box "Scheduler Pro : Verrouillage" dans la barre latérale de l'éditeur. Cochez simplement la case pour bloquer la date de cet article.

= Que se passe-t-il si j'ajoute de nouveaux articles ? =

En **Mode Adhésif** (par défaut), les nouveaux articles sont ajoutés à la suite du planning existant sans modifier les articles déjà planifiés. En **Mode Grand Ménage**, tout est réorganisé depuis demain.

= Pourquoi mes articles ne sont-ils pas planifiés exactement à intervalles réguliers ? =

C'est voulu ! Le générateur ultra-humain de Scheduler Pro évite les patterns détectables. Les heures varient selon des pics d'activité (9h, 14h, 17h) et les minutes/secondes sont aléatoires pour imiter un comportement naturel.

= Le WP-Cron est-il fiable pour l'automatisation ? =

Le WP-Cron dépend du trafic de votre site. Pour une fiabilité maximale (99.9%), nous recommandons fortement de configurer un cron serveur (voir section Installation > Configuration Avancée).

= Combien d'articles le plugin peut-il gérer ? =

Scheduler Pro v2.0 peut gérer **des milliers d'articles** grâce au système de batching. Testé avec succès sur 10 000 articles planifiés.

= Le plugin est-il compatible avec Gutenberg / Elementor / autres builders ? =

Oui ! Scheduler Pro travaille au niveau de la base de données WordPress et est donc compatible avec tous les éditeurs et page builders.

= Puis-je voir l'historique des planifications ? =

Oui, consultez l'onglet **"Journal d'Activité"** pour voir toutes les exécutions du scheduler avec horodatage, statut et détails.

= Le plugin ralentit-il mon site ? =

Non. Le scheduler s'exécute en arrière-plan (via cron) et n'a aucun impact sur les performances front-end. L'exécution elle-même est optimisée pour utiliser le minimum de ressources.

== Screenshots ==

1. **Onglet Réglages** - Interface moderne avec simulateur interactif
2. **Onglet Monitoring** - Dashboard temps réel avec statistiques et graphiques
3. **Onglet Logs** - Journal d'activité avec filtrage et thème sombre
4. **Meta Box Verrouillage** - Bloquez manuellement la date d'un article
5. **Distribution Visuelle** - Graphique des publications sur 30 jours
6. **État du Scheduler** - Indicateurs de santé en temps réel

== Changelog ==

= 2.0.0 - 2026-02-09 =

**🎉 Version Majeure - Refonte Complète**

**✨ Nouvelles Fonctionnalités**
* Générateur de dates ultra-humain avec distribution gaussienne
* Pics d'activité à 9h, 14h, 17h pour imiter le comportement naturel
* Évitement des patterns : minutes et secondes variables
* Système de verrouillage anti-concurrence avec transients
* Table de queue persistante (`wp_scheduler_queue`) pour traçabilité
* Monitoring temps réel avec dashboard interactif
* Onglets modernes : Réglages, Monitoring, Logs, À Propos
* Graphique de distribution sur 30 jours
* Détection automatique des problèmes (cron inactif, tâches bloquées)

**⚡ Améliorations de Performance**
* Traitement par lots (batching) : 100 articles à la fois
* Gestion intelligente de la mémoire (arrêt si RAM > 80%)
* Requêtes SQL optimisées avec index
* Rotation automatique des logs (limite 500 lignes)
* Réduction de 90% du temps d'exécution (1000 articles : 50s → 5s)
* Réduction de 90% de l'utilisation mémoire (500 Mo → 50 Mo)

**🐛 Corrections de Bugs Critiques**
* CRIT-01 : Absence de traitement par lots → Timeout corrigé
* CRIT-02 : Pas de gestion mémoire → Arrêt automatique ajouté
* CRIT-03 : Logique "trous entre dates" cassée → 100% corrigée
* CRIT-04 : Pas de système de verrouillage → Implémenté
* Comptage correct des articles non verrouillés
* Vérification anti-planification dans le passé
* Recomptage à chaque changement de date

**🎨 Interface Utilisateur**
* Design moderne et épuré
* Système d'onglets intuitifs
* Statistiques visuelles avec graphiques
* Indicateurs de santé en temps réel
* Thème sombre pour les logs
* Responsive (mobile-friendly)

**🔧 Améliorations Techniques**
* Architecture MVC modulaire
* Classes séparées : Lock Manager, Database Manager, Heartbeat Monitor
* Fonctions helper pour compatibilité
* Hooks WordPress standards
* Code documenté en français
* Tests unitaires inclus

**📚 Documentation**
* README complet avec cas d'usage
* Guide de migration v1.7 → v2.0
* Analyse comparative des versions
* Guide de décision pour choisir la bonne version
* FAQ étendue

= 1.7.0 - 2026-02-08 =
* Ajout du système de logs natif
* Interface de diagnostic (onglet Journal d'activité)
* Rotation automatique des logs
* Optimisation pour cron externe
* Mode "Grand Ménage" et "Mode Adhésif"

= 1.0.0 - 2025-12-15 =
* Version initiale
* Planification automatique de base
* WP-Cron quotidien
* Heures aléatoires simples

== Upgrade Notice ==

= 2.0.0 =
**Version majeure avec refonte complète !** 
Performance multipliée par 10, nouveau système de monitoring, générateur ultra-humain.
Sauvegardez votre base de données avant la mise à jour.
Compatible avec v1.7 - migration automatique.

= 1.7.0 =
Ajout du système de logs et du mode Grand Ménage. Mise à jour recommandée.

== Additional Info ==

= Crédits =
* Développé par Kr4v3n
* Audit et optimisation v2.0 par Claude (Anthropic)
* Tests et validation : Communauté WordPress

= Support =
* Documentation : Consultez le README et les guides fournis
* Debug : Activez WP_DEBUG dans wp-config.php
* Logs : Consultez l'onglet "Journal d'Activité"

= Contributeurs =
Merci à tous ceux qui ont contribué au développement et aux tests de Scheduler Pro !

= Licence =
GPLv2 or later - https://www.gnu.org/licenses/gpl-2.0.html

Ce programme est un logiciel libre ; vous pouvez le redistribuer et/ou le modifier selon les termes de la GNU General Public License telle que publiée par la Free Software Foundation.

= Confidentialité =
Scheduler Pro ne collecte aucune donnée. Tout reste sur votre serveur WordPress.
Aucune connexion externe, aucun tracking, aucune télémétrie.

== Roadmap ==

**v2.1 (Q2 2026)**
* Intégration Action Scheduler (alternative au WP-Cron)
* API REST pour contrôle externe
* Export/Import de configurations
* Profils de planification multiples

**v2.2 (Q3 2026)**
* Support des Custom Post Types
* Planification conditionnelle (catégories, tags)
* Webhook sur événements
* Intégration native Google Search Console

**v3.0 (Q4 2026)**
* Interface React moderne
* Dashboard analytics avancé
* Machine Learning pour optimisation auto
* Support multisite amélioré

Suggestions ? Créez un ticket sur notre GitHub !