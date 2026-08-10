=== Multisite Network Email Manager ===
Contributors: mantelimustikka-prog
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Network: true

Centralized email management scaffold for WordPress multisite networks.

== Description ==

This initial v1 scaffold provides:

* Network-aware plugin bootstrap and activation hooks.
* Custom database tables for logs, queue, campaigns, suppressions, and advanced user management records.
* SMTP routing foundations built on top of `wp_mail` and PHPMailer.
* Queue, campaign, suppression, REST API, and advanced user management module skeletons.
* Placeholder network admin pages for future expansion.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/multisite-network-email-manager/`.
2. Network activate the plugin from the multisite network admin.
3. Open **Network Admin → Email Manager** to review the scaffolded settings and modules.
4. Configure SMTP details before enabling transactional delivery.
5. Keep **Allow user deletion** disabled unless you explicitly want destructive user actions available.

== Changelog ==

= 0.1.0 =
* Initial scaffold release.
