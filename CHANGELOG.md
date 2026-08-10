# Changelog

All notable changes to this project will be documented in this file.

## [0.1.0] — 2026-08-10

### Added
- Network-level SMTP configuration (host, port, encryption, auth, sender, reply-to, test recipient, debug mode).
- Network Admin settings page with nonce-protected save, pre-save validation, and actionable admin notices.
- Test connection and send test email actions from the Network Admin UI.
- Per-site sender/reply-to override page under **Settings → Email Override**.
- SMTP credential encryption at rest using `sodium_crypto_secretbox` (base64 fallback when sodium is unavailable).
- Retry queue for failed sends via WP-Cron (up to 3 attempts, 5-minute intervals).
- Recent error and warning log panel in the Network Admin settings page (transient-backed, 24-hour TTL).
- WP-CLI commands: `wp mnem smtp test-connection`, `wp mnem smtp send-test`, `wp mnem smtp status`.
- Safe debug logging with automatic redaction of sensitive context keys.
- Plugin uninstall cleanup removing network SMTP settings, encryption key, and mail queue.
- Optional SMTP-disable-on-deactivate behavior via `mnem_smtp_disable_on_deactivate` filter.
- Composer-based tooling with PHPCS and PHPUnit scripts.
- PHPUnit test suite for settings sanitization, service configuration, diagnostics validation, site settings, crypto, mail queue, and log store.
- GitHub Actions CI workflow running lint and tests on push and pull request.
- WordPress.org `readme.txt` with full plugin description, FAQ, and changelog.
