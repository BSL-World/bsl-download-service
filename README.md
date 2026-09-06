# BSL Download Service

Shared server-side infrastructure for BSL-World websites and product delivery.

## Current components

- `download.php` - public gateway for controlled distribution and media delivery.
- `config/distributions-registry.json` - versioned registry of application and Joomla extension packages.
- `config/media-registry.seed.json` - initial media registry for a new installation.
- `tests/download.integration.test.js` - integration coverage for successful requests and configuration failures.

## Hosting layout

The gateway is deployed in the public root of `bsl-world.ru`. Data and downloadable files remain outside the site root:

- `../distr/` - application and Joomla extension packages;
- `../media/` - public audio and video files;
- `../bsl-data/distributions-registry.json` - deployed distribution registry;
- `../bsl-data/media-registry.json` - runtime media registry;
- `../bsl-data/download.log` - download request log.

## Registry model

Each registry is a JSON object that maps a public logical key to a filename. Registry values contain filenames only, never paths.

The distribution registry is maintained and versioned in this repository. The media seed provides initial data for a new installation. After deployment, `media-registry.json` is runtime data and must not be overwritten by the seed during routine updates. A future Joomla administration interface will manage that runtime registry.

Logical keys must be unique across both registries.

## Security model

`download.php` never accepts a filesystem path from a request. It selects a fixed storage directory, validates the registered filename, resolves the resulting path, and verifies that the file remains inside the permitted directory.

Missing, unreadable, malformed, oversized, or conflicting registries cause a safe configuration error without exposing internal details. Invalid filenames and duplicate logical keys are rejected.

Allowed request methods are `GET` and `HEAD`. Recognized traffic sources are `site`, `jed`, and `joomla`; other values are recorded as `direct`.

## Development and deployment

Changes must first pass PHP syntax checks and integration tests with PHP 8.1, 8.2, and 8.4. They must then be tested with the local Joomla 5 and Joomla 6 sites.

For deployment:

1. Deploy `download.php` to the public site root.
2. Deploy `config/distributions-registry.json` as `../bsl-data/distributions-registry.json`.
3. On a new installation only, deploy `config/media-registry.seed.json` as `../bsl-data/media-registry.json`.
4. Preserve the existing runtime `media-registry.json` during subsequent updates.
5. Verify controlled downloads and compare returned files with their expected source artifacts.

Runtime files, logs, credentials, and server-specific configuration must not be committed to this repository.

## License

GNU General Public License version 2 or later. See `LICENSE.txt`.

## Author

Vasilyev Alexander - [BSL-World.ru](https://bsl-world.ru)
