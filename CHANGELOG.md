# Changelog

All notable changes to BSL Download Service are documented in this file.

## [0.2.0] - 2026-09-06

### Added

- External JSON registries for distribution packages and media files.
- Strict validation of registry keys and filenames.
- Storage-boundary verification after filesystem path resolution.
- Safe handling of missing, unreadable, malformed, oversized, and conflicting registries.
- Integration coverage for registry-based delivery and configuration failures.

### Changed

- Moved the hardcoded file allowlist out of `download.php`.
- Separated versioned distribution configuration from runtime media configuration.

## [0.1.0] - 2026-09-06

### Added

- Initial repository baseline for the production download gateway.
- Controlled logical-key mapping for distributions, audio, and video files.
- `GET` and `HEAD` request handling.
- MIME type selection and optional attachment delivery.
- Download source logging to external BSL data storage.
