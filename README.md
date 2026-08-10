# Multisite Network Email Manager

A minimal WordPress multisite plugin scaffold with a provider-agnostic SMTP module.

## SMTP module

The plugin now includes a network-admin SMTP foundation that supports:

- enabling or disabling SMTP for outbound mail
- host, port, encryption, and authentication settings
- sender and reply-to defaults
- a saved test recipient
- safe debug logging without logging secrets
- test connection and test email actions

## Files

- `multisite-network-email-manager.php`
- `includes/class-logger.php`
- `includes/class-smtp-settings.php`
- `includes/class-smtp-service.php`
- `includes/class-smtp-diagnostics.php`
- `includes/class-mailer-adapter.php`
