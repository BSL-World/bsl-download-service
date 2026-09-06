<?php

/**
 * BSL-World public download gateway.
 *
 * @copyright Copyright (C) 2026 Vasilyev Alexander (BSL-World.ru).
 * @license   GNU General Public License version 2 or later
 */

declare(strict_types=1);

function sendError(int $statusCode, string $message): never
{
    http_response_code($statusCode);
    header('Content-Type: text/plain; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');

    echo $message;
    exit;
}

$allowed = [
    // Distribution packages
    'tor' => '../distr/torbrowser-install.exe',
    'bsl-tor' => '../distr/bsl-tor_0.4.7_x64-setup.zip',
    'tor-bundle' => '../distr/tor-expert-bundle-windows-x86_64-15.0.11.tar.gz',
    '7zip' => '../distr/7z2601-x64.exe',
    'bsl-tagcloud-1.0.0' => '../distr/mod_bsl_tagcloud-1.0.0.zip',
    'bsl-tagcloud-1.0.1' => '../distr/mod_bsl_tagcloud-1.0.1.zip',
    'bsl-tagcloud-1.1.0' => '../distr/mod_bsl_tagcloud-1.1.0.zip',
    'bsl-tagcloud-1.2.0' => '../distr/mod_bsl_tagcloud-1.2.0.zip',
    'bsl-media-embed-0.1.1' => '../distr/plg_content_bslmediaembed-0.1.1.zip',
    'bsl-timer-0.3.0' => '../distr/bsl-timer_0.3.0_free_x64-setup.zip',
    'bsl-timer-0.4.0' => '../distr/BSL-Timer-0.4.0-Free-x64-setup.exe.zip',

    // Audio files
    'manifest-audio' => '../media/manifest.mp3',
    'karamazov-audio' => '../media/karamazovs-malovernaya-dama-nl.mp3',
    'forest-audio' => '../media/forest.mp3',
    'peskarev-audio' => '../media/peskarev-x15.mp3',

    // Video files
    'bsl-timer-tray' => '../media/bsl-timer-tray.mp4',
];

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (!in_array($requestMethod, ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD');
    sendError(405, 'Error: method not allowed');
}

$key = isset($_GET['file']) && is_string($_GET['file'])
    ? $_GET['file']
    : '';

if (!array_key_exists($key, $allowed)) {
    sendError(400, 'Error: invalid key');
}

$file = realpath(__DIR__ . DIRECTORY_SEPARATOR . $allowed[$key]);

if ($file === false || !is_file($file) || !is_readable($file)) {
    sendError(404, 'Error: file not found');
}

$mimeTypes = [
    'mp3'  => 'audio/mpeg',
    'mp4'  => 'video/mp4',
    'webm' => 'video/webm',
    'ogg'  => 'application/ogg',
    'exe'  => 'application/octet-stream',
    'zip'  => 'application/zip',
    'gz'   => 'application/gzip',
];

$extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$contentType = $mimeTypes[$extension] ?? 'application/octet-stream';

$allowedSources = ['site', 'jed', 'joomla'];
$source = isset($_GET['source']) && is_string($_GET['source'])
    ? strtolower($_GET['source'])
    : 'direct';

if (!in_array($source, $allowedSources, true)) {
    $source = 'direct';
}

if ($requestMethod === 'GET') {
    $logDirectory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'bsl-data';
    $logFile = $logDirectory . DIRECTORY_SEPARATOR . 'download.log';

    if (is_dir($logDirectory) && is_writable($logDirectory)) {
        $logLine = implode("\t", [
            date('c'),
            $key,
            $source,
        ]) . PHP_EOL;

        @file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
    }
}

$fileSize = filesize($file);

if ($fileSize === false) {
    sendError(500, 'Error: could not determine file size');
}

header('Content-Type: ' . $contentType);
header('Content-Length: ' . $fileSize);
header('X-Content-Type-Options: nosniff');

if (isset($_GET['download'])) {
    header(
        'Content-Disposition: attachment; filename="'
        . basename($file)
        . '"'
    );
}

if ($requestMethod === 'HEAD') {
    exit;
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

readfile($file);
exit;
