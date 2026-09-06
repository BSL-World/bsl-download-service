# BSL Download Service

Shared server-side infrastructure for BSL-World websites and product delivery.

## Current components

- `download.php` — public gateway for controlled distribution and media delivery.

## Hosting layout

The gateway is deployed in the public root of `bsl-world.ru`. Data and downloadable files remain outside the site root:

- `../distr/` — application and Joomla extension packages;
- `../media/` — public audio and video files;
- `../bsl-data/download.log` — download request log.

## Security model

`download.php` accepts only logical keys explicitly registered in its allowlist. A request cannot provide an arbitrary filesystem path. The resolved file must exist, be a regular file, and be readable.

Allowed request methods are `GET` and `HEAD`. Recognized traffic sources are `site`, `jed`, and `joomla`; other values are recorded as `direct`.

## Development and deployment

Changes must first be tested with the local Joomla 5 and Joomla 6 sites. Production deployment is performed only after syntax checks, controlled download tests, and comparison of the returned file with the expected source artifact.

Runtime files, logs, credentials, and server-specific configuration must not be committed to this repository.

## License

GNU General Public License version 2 or later. See `LICENSE.txt`.

## Author

Vasilyev Alexander — [BSL-World.ru](https://bsl-world.ru)
