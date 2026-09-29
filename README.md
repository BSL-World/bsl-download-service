# BSL Download Service

Shared download gateway and statistics page for BSL-World.

## Current components

- `download.php` — public gateway for distribution packages and media.
- `download-stats.php` — private report, available only to the configured Joomla users.
- `tests/download.integration.test.js` — gateway integration tests.

## Hosting layout

Deploy both PHP files in the root of the Joomla site (`www/bsl-world.ru/`). The files they serve and read are outside that root:

- `www/distr/<product>/` — versioned packages for registered products;
- `www/distr/` — existing Tor and 7-Zip packages served through legacy keys;
- `www/media/` — audio and video served through legacy keys;
- `www/bsl-data/download.log` — request log.

The paths above are relative to the `www/` parent of the Joomla directory. The statistics page requires the Joomla installation and an active Joomla session.

## Public URLs

New package releases use `download.php?product=<registered-product>&file=<versioned-filename>`. Register each new product in `$products` in `download.php`; releasing a new version of a registered product does not require editing the gateway. Allowed package extensions are `exe`, `zip` and `gz`.

Previously published `download.php?file=<key>` URLs use `$legacyFiles`. Preserve existing keys when files move; do not add new product versions to this list. The `tor` key currently delivers Tor Browser portable 15.0.11, which is a different artifact from the installer named in the old 0.2.0 JSON registry. Its URL is public to anyone who knows it.

The `source` query value can be `site`, `jed`, `joomla` or `updater`; other values are logged as `direct`. `GET` requests are counted; `HEAD` is not. A successful `GET` is recorded before the file is read, so a later transfer interruption may still appear in the report.

## Security and operations

The product route accepts only registered product directories, a single filename, and an allowed extension. It resolves the real file path and checks that the result remains inside the product directory. The legacy route resolves only fixed paths listed in `$legacyFiles`. Invalid or unavailable files return a generic 404.

The statistics page uses Joomla's session and checks usernames against its explicit allowlist. Update that allowlist if the site account names change. It reads `../bsl-data/download.log`; it does not modify the log.

Run `php -l download.php`, `php -l download-stats.php` and `node tests/download.integration.test.js <path-to-php>` before deployment. Then check package, legacy media, `GET`, `HEAD`, and statistics access on the target Joomla site. Keep site data, logs, secrets, and real packages out of Git.

The former `config/*.json` files belonged to the 0.2.0 registry gateway and are no longer read by this server implementation. Existing server copies under `www/bsl-data/` need no change for this migration.

## License and author

GNU General Public License version 2 or later. See `LICENSE.txt`.

Vasilyev Alexander — [BSL-World.ru](https://bsl-world.ru)
