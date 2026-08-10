=== Multisite Network Email Manager ===
Contributors: mantelimustikka-prog
Tags: multisite, email, smtp, campaigns, network-admin
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Centralized email management for WordPress multisite networks — SMTP, campaigns, queue, suppression, logging, and advanced user management.

== Description ==

**Multisite Network Email Manager** provides a central hub for all outgoing email on a WordPress multisite network.

= Features (0.1.0 scaffold) =

* **SMTP configuration** — override WordPress's default mail transport with any SMTP server. No vendor lock-in: uses WordPress's built-in PHPMailer.
* **Send queue** — async email dispatch via WP-Cron with automatic retries and back-off.
* **Suppression list** — block specific addresses from ever receiving email through the plugin.
* **Campaigns** — placeholder for future bulk-email campaign management.
* **Logger** — structured event logging with level filtering and secret-scrubbing.
* **REST API** — `/wp-json/mnem/v1/` namespace with status, SMTP settings, queue, and suppression endpoints.
* **Advanced User Management** — hooks for user registration, deletion, and role changes (placeholder for rule engine).
* **Network Admin UI** — all settings live under the Network Admin menu, not the site admin.

== Installation ==

= Requirements =
* WordPress 6.0 or later
* PHP 7.4 or later
* WordPress Multisite enabled

= Steps =
1. Upload the `multisite-network-email-manager` folder to `/wp-content/plugins/`.
2. In the Network Admin, go to **Plugins → Network Activate** and activate **Multisite Network Email Manager**.
3. Navigate to **Network Admin → Email Manager → SMTP Settings** to configure your outgoing mail server.

= Network-only activation =
This plugin is designed for **network activation only**. The plugin header contains `Network: true`, so it will only appear in the network plugins list.

== Configuration ==

=== SMTP ===
1. Go to **Network Admin → Email Manager → SMTP Settings**.
2. Enable SMTP and enter your server details.
3. Use **Test Connection** to verify the server is reachable.
4. Use **Send Test Email** to verify end-to-end delivery.

=== Suppression list ===
Addresses on the suppression list are silently skipped by the send queue. Add entries via the **Suppression** admin page or via the REST API.

== Architecture ==

```
multisite-network-email-manager/
├── multisite-network-email-manager.php   ← Bootstrap, hooks, constants
├── includes/
│   ├── class-logger.php                  ← Structured logging API
│   ├── class-installer.php               ← DB table creation (dbDelta)
│   ├── class-settings.php                ← Network option wrapper
│   ├── class-rest-api.php                ← REST namespace + routes
│   ├── class-smtp-settings.php           ← SMTP config storage
│   ├── class-smtp-service.php            ← phpmailer_init integration
│   ├── class-smtp-diagnostics.php        ← Connection test + test email
│   ├── class-campaigns.php               ← Campaign CRUD (placeholder)
│   ├── class-queue.php                   ← Async send queue
│   ├── class-suppression.php             ← Suppression list
│   └── class-user-management.php         ← User event hooks (placeholder)
├── admin/
│   ├── class-admin.php                   ← Notices, form handlers
│   ├── class-admin-menu.php              ← Network Admin menu registration
│   ├── css/
│   │   └── admin.css                     ← Admin styles
│   └── views/
│       ├── dashboard.php
│       ├── smtp-settings.php             ← Full SMTP settings + test UI
│       ├── campaigns.php
│       ├── queue.php
│       ├── suppression.php
│       └── logs.php
└── languages/                            ← Translation files (empty)
```

== Frequently Asked Questions ==

= Does this replace wp_mail()? =
No — it hooks into `phpmailer_init` to configure PHPMailer when SMTP is enabled. `wp_mail()` is still the sending function.

= Is this safe to use on a large network? =
The queue is designed to batch sends (default 50 per cron run). Future releases will add per-site overrides and rate limiting.

= Where are credentials stored? =
SMTP credentials are stored as WordPress network options (site options). Passwords are never written to logs.

== Changelog ==

= 0.1.0 =
* Initial scaffold with SMTP, queue, suppression, logging, REST API, and network admin UI.

== Upgrade Notice ==

= 0.1.0 =
Initial release.
