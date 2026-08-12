# Multisite Network Email Manager

A clean baseline scaffold for a WordPress multisite network-only plugin that centralizes SMTP settings, queue handling, suppression management, campaign placeholders, REST endpoints, and a network admin UI.

## Password storage note

SMTP passwords are stored in a network option with **obfuscation only** (`base64_encode` plus a fixed prefix). This reduces accidental disclosure in routine UI and log handling, but it is **not encryption** and should not be treated as a secret vault.

## Development

```bash
composer install
composer lint
composer test
```
