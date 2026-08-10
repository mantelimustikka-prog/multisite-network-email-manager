=== Multisite Network Email Manager ===
Contributors: mantelimustikka-prog
Tags: smtp, multisite, email, network, mail
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Network-level SMTP configuration, per-site sender overrides, diagnostics, retry queue, and secure credential storage for WordPress Multisite.

== Description ==

**Multisite Network Email Manager** gives network administrators a single place to manage outbound email for every site in a WordPress Multisite network.

= Key features =

* **Network-level SMTP** — configure host, port, encryption (none / TLS / SSL), authentication, and sender defaults once for the whole network.
* **Per-site overrides** — individual site administrators can override the From address, From name, Reply-To address, and Reply-To name without touching SMTP credentials.
* **Credential encryption** — the SMTP password is encrypted at rest using `sodium_crypto_secretbox` (falls back to base64 when the sodium extension is unavailable).
* **Diagnostics** — test the SMTP connection and send a test email directly from the Network Admin settings page.
* **Retry queue** — failed sends are automatically queued and retried up to three times via WP-Cron (5-minute intervals).
* **Log viewer** — the most recent SMTP errors and warnings are displayed in the Network Admin settings page without requiring any external log storage.
* **WP-CLI support** — `wp mnem smtp test-connection`, `wp mnem smtp send-test`, and `wp mnem smtp status` for headless and CI environments.
* **Privacy-safe logging** — sensitive values (password, token, secret, credentials) are redacted before any log record is emitted.

= Intended use =

This plugin is intended for **network-activated** use on WordPress Multisite installations. A network administrator configures SMTP once; all sites use the same outbound mail server unless a site administrator has enabled a per-site sender override.

== Installation ==

1. Upload the `multisite-network-email-manager` folder to the `/wp-content/plugins/` directory.
2. Network-activate the plugin from **Network Admin → Plugins**.
3. Go to **Network Admin → Settings → SMTP Settings** and configure your SMTP server.
4. Optionally, site administrators can go to **Settings → Email Override** to set site-specific sender addresses.

== Frequently Asked Questions ==

= Does this plugin work on single-site WordPress installations? =

The SMTP and diagnostics features will work, but the plugin is optimised for Multisite. Some admin pages appear under Network Admin menus.

= Is the SMTP password stored securely? =

Yes. When PHP's `sodium` extension is available (PHP 7.2+, and the default on most hosts), the password is encrypted with `sodium_crypto_secretbox` using a randomly generated key stored in `wp_sitemeta`. On hosts without sodium, the password is stored as base64 (obfuscated but not encrypted) — it is recommended to ensure the sodium extension is enabled.

= Can individual site admins change SMTP credentials? =

No. SMTP server credentials are managed only by network administrators. Site admins can only override the sender name and email address for their own site.

= How does the retry queue work? =

When `wp_mail_failed` fires, the plugin pushes the message into a queue stored in `wp_sitemeta`. A WP-Cron event fires five minutes later to retry. Each message is retried up to three times; after that it is dropped and an error is logged.

= How do I disable SMTP on deactivation? =

Add the following to your `wp-config.php` or a mu-plugin:

`add_filter( 'mnem_smtp_disable_on_deactivate', '__return_true' );`

= Where are logs stored? =

No persistent log table is created. The plugin emits a `mnem_log` action for each record; an in-memory store captures the most recent 20 errors and warnings in a transient (24-hour TTL) for display in the Network Admin settings page. You can attach your own handler to `mnem_log` to forward records to any logging system.

== Screenshots ==

1. Network Admin SMTP Settings page with all options.
2. Network Admin SMTP Settings page showing the recent errors log panel.
3. Per-site Email Override settings page.

== Changelog ==

= 0.1.0 =
* Initial stable release.
* Network-level SMTP configuration.
* Per-site sender/reply-to override.
* SMTP credential encryption at rest (sodium).
* Diagnostics (test connection, send test email) in Network Admin UI and via WP-CLI.
* Retry queue for failed sends via WP-Cron.
* Recent error/warning log panel in Network Admin settings page.
* Safe debug logging with secret redaction.
* Lifecycle management: activation defaults, optional deactivation disable, full uninstall cleanup.

== Upgrade Notice ==

= 0.1.0 =
Initial release. No upgrade steps required.
