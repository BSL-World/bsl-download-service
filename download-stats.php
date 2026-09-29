<?php

/**
 * BSL-World download statistics.
 *
 * Place this file in the Joomla site root, next to configuration.php.
 * The download log is expected at ../bsl-data/download.log.
 *
 * @copyright Copyright (C) 2026 Vasilyev Alexander (BSL-World.ru).
 * @license   GNU General Public License version 2 or later
 */

declare(strict_types=1);

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session as CmsSession;
use Joomla\CMS\Uri\Uri;
use Joomla\Session\Session as FrameworkSession;
use Joomla\Session\SessionInterface;

define('_JEXEC', 1);
define('JPATH_BASE', __DIR__);

require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

$container = Factory::getContainer();
$container->alias('session.web', 'session.web.site')
    ->alias('session', 'session.web.site')
    ->alias('JSession', 'session.web.site')
    ->alias(CmsSession::class, 'session.web.site')
    ->alias(FrameworkSession::class, 'session.web.site')
    ->alias(SessionInterface::class, 'session.web.site');

/** @var SiteApplication $app */
$app = $container->get(SiteApplication::class);
Factory::$application = $app;
$app->createExtensionNamespaceMap();

$sessionUser = $app->getSession()->get('user');
$identity = $app->getIdentity();

$userId = 0;
$username = '';

if (is_object($sessionUser)) {
    $userId = (int) ($sessionUser->id ?? 0);
    $username = trim((string) ($sessionUser->username ?? ''));
} elseif (is_array($sessionUser)) {
    $userId = (int) ($sessionUser['id'] ?? 0);
    $username = trim((string) ($sessionUser['username'] ?? ''));
}

if ($userId === 0 && !$identity->guest) {
    $userId = (int) $identity->id;
    $username = trim((string) $identity->username);
}

$allowedUsernames = ['BSL-AlexV', 'Alex.V'];

if ($userId === 0 || $username === '') {
    $returnUrl = base64_encode(Uri::getInstance()->toString());
    $loginUrl = Route::_('index.php?option=com_users&view=login&return=' . $returnUrl, false);

    $app->redirect($loginUrl);
    $app->close();
}

$normalizedUsername = strtolower($username);
$normalizedAllowedUsernames = array_map('strtolower', $allowedUsernames);

if (!in_array($normalizedUsername, $normalizedAllowedUsernames, true)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');

    echo "403 Forbidden\n\n";
    echo 'Joomla detected the signed-in username as: ' . $username;
    exit;
}

header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$logFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'bsl-data'
    . DIRECTORY_SEPARATOR . 'download.log';

$records = [];
$invalidLines = 0;

if (is_file($logFile) && is_readable($logFile)) {
    $handle = fopen($logFile, 'rb');

    if ($handle !== false) {
        while (($line = fgets($handle)) !== false) {
            $columns = explode("\t", trim($line));

            if (count($columns) !== 3) {
                $invalidLines++;
                continue;
            }

            [$timestamp, $key, $source] = $columns;

            $date = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $timestamp);

            if ($date === false) {
                $invalidLines++;
                continue;
            }

            $records[] = [
                'date' => $date,
                'key' => $key,
                'source' => $source,
            ];
        }

        fclose($handle);
    }
}

$sourceLabels = [
    'jed' => 'JED',
    'site' => 'BSL-World',
    'joomla' => 'Joomla Update System',
    'direct' => 'Direct',
    'updater' => 'Desktop Updater',
];

$fileLabels = [
    'tor' => 'Tor Browser Installer',
    'bsl-tor' => 'BSL-Tor',
    'tor-bundle' => 'Tor Expert Bundle',
    '7zip' => '7-Zip',
    'manifest-audio' => 'Manifest — audio',
    'karamazov-audio' => 'The Brothers Karamazov — audio',
    'forest-audio' => 'Forest — audio',
    'peskarev-audio' => 'Peskarev — audio',
];

$bySource = [];
$byDay = [];
$byFile = [];

foreach ($records as $record) {
    $source = $record['source'];
    $key = $record['key'];
    $day = $record['date']->format('Y-m-d');

    $bySource[$source] = ($bySource[$source] ?? 0) + 1;
    $byDay[$day] = ($byDay[$day] ?? 0) + 1;
    $byFile[$key]['total'] = ($byFile[$key]['total'] ?? 0) + 1;
    $byFile[$key]['sources'][$source] = ($byFile[$key]['sources'][$source] ?? 0) + 1;

}

uasort($byFile, static fn (array $a, array $b): int => $b['total'] <=> $a['total']);
krsort($byDay);

$latestRecords = array_reverse(array_slice($records, -50));
$total = count($records);
$firstDate = $total > 0 ? $records[0]['date'] : null;
$lastDate = $total > 0 ? $records[$total - 1]['date'] : null;

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function sourceLabel(string $source, array $labels): string
{
    return $labels[$source] ?? ucfirst($source);
}

function fileLabel(string $key, array $labels): string
{
    if (str_starts_with($key, 'bsl-tagcloud-')) {
        return 'BSL Tag Cloud Aquarium ' . substr($key, strlen('bsl-tagcloud-'));
    }

    if (str_contains($key, '/')) {
        [$product, $fileName] = explode('/', $key, 2);

        $productLabels = [
            'bsl-timer' => 'BSL-Timer',
            'bsl-tag-cloud-aquarium' => 'BSL Tag Cloud Aquarium',
            'bsl-daily-reflections' => 'BSL Daily Reflections',
            'bsl-media-embed' => 'BSL Media Embed',
        ];

        $productLabel = $productLabels[$product] ?? $product;

        return $productLabel . ' — ' . $fileName;
    }

    return $labels[$key] ?? $key;
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title>BSL-World — Download Statistics</title>
    <style>
        :root {
            color-scheme: light dark;
            --accent: #0d6efd;
            --background: #f4f6f8;
            --border: #d8dee4;
            --card: #fff;
            --muted: #667085;
            --text: #1f2937;
        }

        * { box-sizing: border-box; }
        body {
            background: var(--background);
            color: var(--text);
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            line-height: 1.45;
            margin: 0;
        }
        main { margin: 0 auto; max-width: 1180px; padding: 32px 20px 60px; }
        header { align-items: flex-start; display: flex; gap: 20px; justify-content: space-between; }
        h1 { font-size: clamp(1.7rem, 4vw, 2.4rem); margin: 0; }
        h2 { font-size: 1.25rem; margin: 0 0 16px; }
        p { margin: 8px 0; }
        .muted { color: var(--muted); }
        .button {
            background: var(--accent);
            border-radius: 8px;
            color: #fff;
            display: inline-block;
            padding: 10px 16px;
            text-decoration: none;
            white-space: nowrap;
        }
        .summary {
            display: grid;
            gap: 16px;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            margin: 26px 0;
        }
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            box-shadow: 0 2px 10px rgb(0 0 0 / 5%);
            padding: 20px;
        }
        .number { color: var(--accent); font-size: 2rem; font-weight: 700; }
        .tables {
            display: grid;
            gap: 20px;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        }
        .wide { margin-top: 20px; overflow-x: auto; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border-bottom: 1px solid var(--border); padding: 10px 8px; text-align: left; }
        th { color: var(--muted); font-size: .85rem; text-transform: uppercase; }
        td:last-child, th:last-child { text-align: right; }
        .recent td:last-child, .recent th:last-child { text-align: left; }
        .empty { padding: 40px 20px; text-align: center; }
        footer { color: var(--muted); font-size: .9rem; margin-top: 24px; }

        @media (prefers-color-scheme: dark) {
            :root {
                --background: #111827;
                --border: #374151;
                --card: #1f2937;
                --muted: #a7b0bf;
                --text: #f3f4f6;
            }
        }
        @media (max-width: 600px) {
            header { display: block; }
            .button { margin-top: 16px; }
            main { padding-top: 22px; }
        }
    </style>
</head>
<body>
<main>
    <header>
        <div>
            <h1>Download Statistics</h1>
            <p>BSL-World files</p>
            <p class="muted">Signed in as <?= escape($username) ?></p>
        </div>
        <a class="button" href="<?= escape(Uri::getInstance()->toString()) ?>">Refresh</a>
    </header>

    <?php if ($total === 0): ?>
        <section class="card empty">
            <h2>No download records found</h2>
            <p class="muted">The report could not find readable entries in download.log.</p>
        </section>
    <?php else: ?>
        <section class="summary">
            <div class="card">
                <div class="number"><?= $total ?></div>
                <div>Total requests</div>
            </div>
            <div class="card">
                <div class="number"><?= $bySource['jed'] ?? 0 ?></div>
                <div>From JED</div>
            </div>
            <div class="card">
                <div class="number"><?= $bySource['site'] ?? 0 ?></div>
                <div>From BSL-World</div>
            </div>
            <div class="card">
                <div class="number"><?= $bySource['joomla'] ?? 0 ?></div>
                <div>Joomla updates</div>
            </div>
            <div class="card">
                <div class="number"><?= $bySource['updater'] ?? 0 ?></div>
                <div>Desktop updates</div>
            </div>
        </section>

        <section class="card wide">
            <h2>By file</h2>
            <table>
                <thead>
                    <tr>
                        <th>File</th>
                        <th>Total</th>
                        <th>Site</th>
                        <th>JED</th>
                        <th>Joomla</th>
                        <th>Updater</th>
                        <th>Direct</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($byFile as $key => $statistics): ?>
                    <tr>
                        <td><?= escape(fileLabel($key, $fileLabels)) ?></td>
                        <td><?= $statistics['total'] ?></td>
                        <td><?= $statistics['sources']['site'] ?? 0 ?></td>
                        <td><?= $statistics['sources']['jed'] ?? 0 ?></td>
                        <td><?= $statistics['sources']['joomla'] ?? 0 ?></td>
                        <td><?= $statistics['sources']['updater'] ?? 0 ?></td>
                        <td><?= $statistics['sources']['direct'] ?? 0 ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section class="tables">
            <div class="card">
                <h2>By source</h2>
                <table>
                    <thead><tr><th>Source</th><th>Requests</th></tr></thead>
                    <tbody>
                    <?php foreach ($bySource as $source => $count): ?>
                        <tr>
                            <td><?= escape(sourceLabel($source, $sourceLabels)) ?></td>
                            <td><?= $count ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="card">
                <h2>By day</h2>
                <table>
                    <thead><tr><th>Date</th><th>Requests</th></tr></thead>
                    <tbody>
                    <?php foreach (array_slice($byDay, 0, 30, true) as $day => $count): ?>
                        <tr><td><?= escape($day) ?></td><td><?= $count ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card wide">
            <h2>Latest requests</h2>
            <table class="recent">
                <thead><tr><th>Date and time</th><th>File</th><th>Source</th></tr></thead>
                <tbody>
                <?php foreach ($latestRecords as $record): ?>
                    <tr>
                        <td><?= escape($record['date']->format('Y-m-d H:i:s P')) ?></td>
                        <td><?= escape(fileLabel($record['key'], $fileLabels)) ?></td>
                        <td><?= escape(sourceLabel($record['source'], $sourceLabels)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    <?php endif; ?>

    <footer>
        <p>
            This report counts successful GET requests recorded by download.php.
            Repeated requests, automated checks and the author's own tests may be included.
        </p>
        <?php if ($firstDate !== null && $lastDate !== null): ?>
            <p>
                Period: <?= escape($firstDate->format('Y-m-d H:i:s P')) ?> —
                <?= escape($lastDate->format('Y-m-d H:i:s P')) ?>.
            </p>
        <?php endif; ?>
        <?php if ($invalidLines > 0): ?>
            <p>Skipped malformed log lines: <?= $invalidLines ?>.</p>
        <?php endif; ?>
    </footer>
</main>
</body>
</html>
