# Multisite Network Email Manager

A WordPress multisite plugin scaffold for network-level SMTP configuration and diagnostics.

## Definition of done for this scaffold

This scaffold is considered done at **MVP level** when:

- network admins can configure SMTP safely from network settings
- SMTP diagnostics can test connection and send a test email
- logs are safe (secrets redacted) and can be consumed via hooks
- lifecycle behavior is explicit (activation/deactivation/uninstall)
- quality gates (lint + tests + CI) run successfully
- developer and release documentation is complete

Production hardening (advanced retries, queueing, UI polish, and integrations) is explicitly out of current scope.

## Network activation expectations

- Plugin is intended for **WordPress multisite** with **network activation**.
- SMTP settings are stored as a **network option** (`mnem_smtp_settings`).
- Optional deactivation behavior is available via filter:
  - `mnem_smtp_disable_on_deactivate` (default `false`)
  - if set to `true`, SMTP is disabled on plugin deactivation.

## SMTP module capabilities

- enable/disable SMTP for outbound mail
- host, port, encryption, and authentication settings
- sender and reply-to defaults
- saved test recipient
- safe debug logging without storing secrets
- test connection and test email actions

## Observability and privacy

- Plugin emits sanitized logs through `do_action( 'mnem_log', $record )`.
- Sensitive values (`password`, `token`, `secret`, credentials-like keys) are redacted.
- No persistent log storage is created by default; retention is controlled by whatever listener consumes `mnem_log`.
- Recommended: only enable debug logging temporarily for diagnostics, then disable it.

## Development setup

1. Install dependencies:
   - `composer install`
2. Run lint:
   - `composer lint`
3. Run tests:
   - `composer test`

## Testing workflow

- Automated:
  - PHPUnit tests for settings sanitization, service configuration checks, and diagnostics validation branches.
  - PHPCS lint pass for plugin PHP files.
- Manual multisite checks:
  1. Save SMTP settings with valid and invalid input.
  2. Test connection with valid and invalid SMTP host/port/auth.
  3. Send test email and verify delivery/failure behavior.
  4. Confirm disabled-mode behavior.
  5. Confirm notices and logs are clear and secrets are not exposed.

## Release flow

1. Ensure CI is green (lint + tests).
2. Update changelog.
3. Bump plugin version.
4. Tag release.
5. Publish release notes.

## Project files

- `multisite-network-email-manager.php`
- `uninstall.php`
- `includes/class-logger.php`
- `includes/class-smtp-settings.php`
- `includes/class-smtp-service.php`
- `includes/class-smtp-diagnostics.php`
- `includes/class-mailer-adapter.php`
- `tests/`
- `.github/workflows/ci.yml`
