# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Added
- Scoped "definition of done" for the scaffold (MVP target).
- Plugin uninstall cleanup (`uninstall.php`) that removes network SMTP settings.
- Optional SMTP-disable-on-deactivate behavior via `mnem_smtp_disable_on_deactivate`.
- Pre-save SMTP validation with actionable admin error notices.
- Clearer diagnostics error messaging for connection and test-email failures.
- Composer-based tooling with PHPCS and PHPUnit scripts.
- Baseline PHPUnit suite for settings, service, and diagnostics validation branches.
- GitHub Actions CI workflow running lint and tests on push and pull request.
- Expanded README with setup, testing, observability, privacy, and release flow guidance.

### Milestone note
- First stable milestone is ready after CI passes and version/tag are cut.
