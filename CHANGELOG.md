# Changelog

All notable changes to BSL Download Service are documented in this file.

## Unreleased

### Added

- Product and versioned filename route from the confirmed server gateway.
- `updater` download source and generic 404 responses.
- Current Joomla-session-based download statistics page.
- Legacy routes for existing Tor, 7-Zip and BSL Media Embed links.

### Changed

- The `tor` legacy key now delivers Tor Browser portable 15.0.11, as the old installer is not present in the server distribution directory.
- Replaced the 0.2.0 JSON-registry gateway and its obsolete test fixtures with the confirmed server routing model.
- Removed the former registry files from the repository; the server's existing data files are unaffected.

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
