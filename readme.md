=== Scheduler Pro ===
Contributors: Kr4v3n
Tags: scheduler, scheduling, seo, automation, cron, publication
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 2.6.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Ultra-smart automatic scheduler for WordPress: advanced post publishing management with human-like behavior and real-time monitoring.

== Description ==

**Scheduler Pro** is a professional WordPress plugin that automates and optimizes your post scheduling with a revolutionary approach: mimicking human behavior.

= 🎯 Why Scheduler Pro? =

Most scheduling plugins use simple algorithms that create detectable patterns. **Scheduler Pro v2.5** goes much further:

* **Gaussian distribution** with activity peaks at 9am, 2pm and 5pm
* **Pattern avoidance**: variable minutes and seconds, never overly round values
* **Batch processing**: handles thousands of posts without timing out
* **Locking system**: prevents concurrent runs
* **Real-time monitoring**: full dashboard with statistics

= ✨ Core Features =

**🚀 Ultra-Human Scheduling**
* Date generator based on gaussian distributions
* Natural activity peaks at high-publishing hours
* Random minutes avoiding round values (00, 15, 30, 45)
* Variable seconds to eliminate any detectable pattern

**⚡ Performance & Reliability**
* **Batch processing**: 100 posts at a time to avoid timeouts
* **Memory management**: stops automatically if RAM usage exceeds 80% to avoid crashes
* **Locking system**: prevents duplicate runs, even on a double-click, via atomic direct SQL queries
* **Persistent task queue**: full traceability and history of every scheduling run

**📊 Advanced Monitoring**
* Real-time dashboard with full statistics
* 3-month distribution chart (by week) plus a 2-month day-by-day calendar view
* Automatic issue detection (inactive cron, stuck tasks)
* Email alert if the scheduler stops running (throttled to 1/24h)
* Activity log with automatic rotation

**🔧 Smart Configuration**
* **Flexible cadence**: schedule by day, week, or month (e.g. "2/week", "6/month"), with automatic day distribution
* **Category exclusion**: keep entire categories out of auto-scheduling
* **Skip weekends**: optionally never publish on Saturday or Sunday
* **Preview before running**: see the exact resulting dates before committing anything
* **Adhesive Mode**: adds new posts after the existing ones without touching them
* **Full Reset Mode**: reorganizes the ENTIRE backlog starting tomorrow
* **Manual locking**: lock the date of specific posts
* **Custom time range**: 7am-8pm by default, adjustable to your needs

= 🎨 Modern Interface =

**Intuitive Tabs**
* **Settings**: full configuration with an interactive simulator
* **Monitoring**: overview of system health
* **Logs**: real-time activity log with level filtering
* **About**: built-in documentation and help

**Clean Design**
* Modern, responsive interface
* Visual statistics with charts
* Real-time health indicators
* Dark theme for the logs

= 📈 Use Cases =

**SEO Blog**
Publish regularly with no effort, with a 100% natural appearance for search engines.

**Multi-Author Site**
Manage hundreds of scheduled posts with individual priorities and locks.

**Content Platform**
Automate the publication of thousands of posts with professional reliability.

= 🔒 Security & Privacy =

* No external data: everything stays on your server
* Permission checks (nonces, capabilities)
* Anti-concurrency locking via atomic direct SQL queries
* Automatic log rotation (500-line limit)

= ⚙️ Technical Requirements =

**Requirements**
* WordPress 5.8+
* PHP 7.4+
* MySQL 5.7+
* PHP memory: 128 MB minimum (256 MB recommended)

**Compatibility**
* ✅ Multisite
* ✅ WP-Cron and server cron
* ✅ All WordPress themes
* ✅ WPML compatible

**Performance**
* **Before v2.0**: 1000 posts = ~50s (frequent timeouts)
* **After v2.0**: 1000 posts = ~5s ✅
* Memory footprint: ~50 MB (vs 500 MB in v1.x)

== Installation ==

= Automatic Installation =

1. Log in to your WordPress admin
2. Go to **Plugins > Add New**
3. Search for "Scheduler Pro"
4. Click **Install** then **Activate**

= Manual Installation =

1. Download the plugin's ZIP file
2. Go to **Plugins > Add New > Upload Plugin**
3. Choose the ZIP file and click **Install**
4. Activate the plugin

= Initial Configuration =

1. Go to **Scheduler Pro** in the admin menu
2. Configure your settings:
   * **Cadence**: 3-5/day recommended for natural-looking behavior, or a week/month cadence (e.g. "2/week") for a lighter rhythm
   * **Time range**: 7am-8pm to mimic human activity
   * **Auto mode**: enabled for a daily run at 00:30
3. Save your settings
4. Click **"Run Scheduling Now"** for the first test

= Advanced Configuration (Optional) =

**Set Up a Server Cron (Recommended)**

For maximum reliability, replace WP-Cron with a real server cron:

1. **Disable WP-Cron**: add this to `wp-config.php`
```php
define('DISABLE_WP_CRON', true);
```

2. **Create a cron job** (cPanel or SSH)
```bash
*/15 * * * * wget -q -O - https://your-site.com/wp-cron.php?doing_wp_cron >/dev/null 2>&1
```

Or via PHP:
```bash
*/15 * * * * cd /path/to/wordpress && php wp-cron.php >/dev/null 2>&1
```

== Frequently Asked Questions ==

= Does the plugin work on all hosting providers? =

Yes! Scheduler Pro is compatible with all standard WordPress hosting. On shared hosting with strict limits, the batching and memory management system guarantees timeout-free operation.

= Can I lock certain posts so they don't get rescheduled? =

Absolutely! Every post has a "Scheduler Pro: Locking" meta box in the editor's sidebar. Just check the box to lock that post's date.

= What happens if I add new posts? =

In **Adhesive Mode** (default), new posts are added after the existing schedule without modifying already-scheduled posts. In **Full Reset Mode**, everything is reorganized starting tomorrow.

= Why aren't my posts scheduled at exactly regular intervals? =

That's intentional! Scheduler Pro's ultra-human generator avoids detectable patterns. Hours vary according to activity peaks (9am, 2pm, 5pm) and minutes/seconds are randomized to mimic natural behavior.

= Is WP-Cron reliable for automation? =

WP-Cron depends on your site's traffic. For maximum reliability (99.9%), we strongly recommend setting up a server cron (see the Installation > Advanced Configuration section).

= How many posts can the plugin handle? =

Scheduler Pro v2.5 can handle **thousands of posts** thanks to its batching system. Successfully tested with 10,000 scheduled posts.

= Is the plugin compatible with Gutenberg / Elementor / other builders? =

Yes! Scheduler Pro operates at the WordPress database level, so it's compatible with every editor and page builder.

= Can I see the scheduling history? =

Yes, check the **"Activity Log"** tab to see every scheduler run with timestamp, status and details.

= Does the plugin slow down my site? =

No. The scheduler runs in the background (via cron) and has no impact on front-end performance. The run itself is optimized to use minimal resources.

== Screenshots ==

1. **Settings Tab** - Modern interface with an interactive simulator
2. **Monitoring Tab** - Real-time dashboard with statistics and charts
3. **Logs Tab** - Activity log with filtering and a dark theme
4. **Locking Meta Box** - Manually lock a post's date
5. **Visual Distribution** - 3-month weekly chart plus a 2-month calendar view of publications
6. **Scheduler Status** - Real-time health indicators

== Changelog ==

= 2.6.2 - 2026-09-03 =

**✨ New Feature**
* **Skip weekends**: new checkbox in Settings (`sp_skip_weekends`, off by default). When enabled, the scheduler never picks a Saturday or Sunday as a publish date - it pushes the candidate date forward to the following Monday. Works identically across the day, week, and month cadences.

= 2.6.1 - 2026-09-03 =

**🔧 Fix**
* Strategy Simulator: the estimated duration shown for a week/month cadence was too short once the count exceeded the period (e.g. more than 7/week or 30/month), because the simulator's interval calculation didn't cap at 1 post/day like the actual scheduling engine does. The simulator now reuses the engine's own calculation.

= 2.6.0 - 2026-09-03 =

**✨ New Features**
* **Flexible cadence**: replaced the flat "posts/day" setting with a unified cadence (count + day/week/month unit). "Day" keeps the exact original behavior; "week"/"month" use a new rolling-average-interval model (at most 1 post/day, with ±20% jitter to avoid a detectable rhythm). Existing installs are migrated automatically with unchanged behavior.
* **Category exclusion**: posts in selected categories are entirely skipped by the scheduler, in both Adhesive and Full Reset modes. Replaces the old, never-functional `sp_excluded_ids` option.
* **Preview before running**: a new "Preview" button next to "Run Scheduling Now" simulates a full run (identical selection and date logic) and shows the resulting dates in a table, without writing anything.
* **Email alerts**: an email is sent to the site admin (or a custom address) as soon as the scheduler status turns critical (no run in over 25h), throttled to at most one email every 24h. Checked on every admin page load rather than on the plugin's own cron, so it still fires when WP-Cron itself is what's broken.
* **Calendar view**: a new 2-month, pure-CSS calendar grid in the Monitoring tab, showing the post count per day with titles on hover, alongside the existing chart.

**⚙️ Improvements**
* The distribution chart now spans 3 months (grouped by week) instead of 30 daily bars.

= 2.5.0 - 2026-09-03 =

**🔧 Critical Fixes**
* Scheduling in Adhesive Mode could skip an entire block of posts once the number of remaining posts dropped below a batch's size (pagination was incompatible with a filter that shrinks as batches progress)
* The task queue table (`wp_scheduler_queue`) was never created on plugin activation (WordPress hook ordering): fixed by explicitly loading the required classes on activation

**🔒 Security**
* Added a nonce check on the manual WP-Cron test (CSRF)
* The log file is no longer reachable via direct HTTP access (moved under `uploads/`, protected by `.htaccess` + `index.php`)
* Output consistently escaped in admin messages

**⚙️ Reliability**
* Anti-concurrency locking rewritten to be atomic (eliminates a race window on concurrent runs)
* The daily cleanup of stale locks now uses a threshold consistent with the main lock's actual timeout
* Daily scheduling (00:30) and cleanup (03:00) now honor the timezone configured in WordPress instead of the PHP server's
* "Full Reset" mode no longer disables itself automatically if processing stopped prematurely (memory): it resumes on the next run
* Locked posts are now excluded directly by the scheduling query (instead of being filtered afterward)
* Fixed a counter that could waste a time slot when a post update failed
* Fixed the distribution chart, which used to exclude day-30 posts published after midnight
* The manual WP-Cron test result is now displayed in the interface (previously computed but never shown)
* Fixed reading `memory_limit` when expressed in gigabytes (e.g. "1G")

**🧹 Cleanup**
* Removed a duplicate activation hook (table creation used to be triggered twice)
* Removed the `sp_excluded_ids` option, created but never used by any feature
* Completed uninstallation: now also cleans up the daily cleanup cron, the per-post lock meta, all plugin options, and the queue table (previously only partially cleaned up)
* Corrected the documentation: the queue table provides traceability/history, it does not (yet) drive automatic retries

**🧱 Architecture**
* Split the plugin into modular files by responsibility (`includes/logger.php`, `includes/memory-guard.php`, `includes/time-helpers.php`, `includes/slot-finder.php`, `includes/admin/*.php`) to make maintenance easier — no functional behavior change beyond the fixes listed above

= 2.0.0 - 2026-02-09 =

**🎉 Major Version - Complete Overhaul**

**✨ New Features**
* Ultra-human date generator with gaussian distribution
* Activity peaks at 9am, 2pm, 5pm to mimic natural behavior
* Pattern avoidance: variable minutes and seconds
* Anti-concurrency locking system with transients
* Persistent task queue (`wp_scheduler_queue`) for traceability
* Real-time monitoring with an interactive dashboard
* Modern tabs: Settings, Monitoring, Logs, About
* 30-day distribution chart
* Automatic issue detection (inactive cron, stuck tasks)

**⚡ Performance Improvements**
* Batch processing: 100 posts at a time
* Smart memory management (stops if RAM > 80%)
* Optimized SQL queries with indexes
* Automatic log rotation (500-line limit)
* 90% reduction in execution time (1000 posts: 50s → 5s)
* 90% reduction in memory usage (500 MB → 50 MB)

**🐛 Critical Bug Fixes**
* CRIT-01: Missing batch processing → timeout fixed
* CRIT-02: No memory management → automatic stop added
* CRIT-03: Broken "gaps between dates" logic → 100% fixed
* CRIT-04: No locking system → implemented
* Correct counting of non-locked posts
* Anti-past-scheduling check
* Recount on every date change

**🎨 User Interface**
* Modern, clean design
* Intuitive tab system
* Visual statistics with charts
* Real-time health indicators
* Dark theme for the logs
* Responsive (mobile-friendly)

**🔧 Technical Improvements**
* Modular MVC architecture
* Separate classes: Lock Manager, Database Manager, Heartbeat Monitor
* Helper functions for compatibility
* Standard WordPress hooks
* Documented codebase
* Unit tests included

**📚 Documentation**
* Full README with use cases
* v1.7 → v2.0 migration guide
* Version comparison analysis
* Decision guide for choosing the right version
* Extended FAQ

= 1.7.0 - 2026-02-08 =
* Added native logging system
* Diagnostics interface (Activity Log tab)
* Automatic log rotation
* Optimized for external cron
* "Full Reset" and "Adhesive" modes

= 1.0.0 - 2025-12-15 =
* Initial version
* Basic automatic scheduling
* Daily WP-Cron
* Simple random hours

== Upgrade Notice ==

= 2.0.0 =
**Major version with a complete overhaul!**
10x performance, new monitoring system, ultra-human generator.
Back up your database before updating.
Compatible with v1.7 - automatic migration.

= 1.7.0 =
Added the logging system and "Full Reset" mode. Update recommended.

== Additional Info ==

= Credits =
* Developed by Kr4v3n
* Testing and validation: WordPress community

= Support =
* Documentation: check the README and the bundled guides
* Debug: enable WP_DEBUG in wp-config.php
* Logs: check the "Activity Log" tab

= Contributors =
Thanks to everyone who contributed to developing and testing Scheduler Pro!

= License =
GPLv2 or later - https://www.gnu.org/licenses/gpl-2.0.html

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation.

= Privacy =
Scheduler Pro collects no data. Everything stays on your WordPress server.
No external connections, no tracking, no telemetry.

== Roadmap ==

**v2.1 (Q2 2026)**
* Action Scheduler integration (WP-Cron alternative)
* REST API for external control
* Configuration export/import
* Multiple scheduling profiles

**v2.2 (Q3 2026)**
* Custom Post Type support
* Conditional scheduling (categories, tags)
* Event webhooks
* Native Google Search Console integration

**v3.0 (Q4 2026)**
* Modern React interface
* Advanced analytics dashboard
* Machine learning for automatic optimization
* Improved multisite support

Suggestions? Open a ticket on our GitHub!
