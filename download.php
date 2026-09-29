<?php

/**
 * BSL-World public download gateway.
 *
 * @copyright Copyright (C) 2026 Vasilyev Alexander (BSL-World.ru).
 * @license   GNU General Public License version 2 or later
 */

declare(strict_types=1);

function sendError(int $statusCode, string $message = '404 Not Found'): never
{
    http_response_code($statusCode);
    header('Content-Type: text/plain; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');

    echo $message;
    exit;
}

/*
 * Product directories for the current distribution infrastructure.
 *
 * Adding a new version of an existing product does not require changing
 * this file. A new product must be explicitly registered here.
 */
$products = [
    'bsl-timer' => '../distr/bsl-timer',
    'bsl-tag-cloud-aquarium' => '../distr/bsl-tag-cloud-aquarium',
    'bsl-daily-reflections' => '../distr/bsl-daily-reflections',
    'bsl-media-embed' => '../distr/bsl-media-embed',
];

/*
 * Legacy public keys.
 *
 * These entries preserve already published URLs. Do not add new product
 * versions here. New releases use the product + file route.
 */
$legacyFiles = [
    // Distributives
    'tor' => '../distr/tor-browser-windows-x86_64-portable-15.0.11.exe',
    'bsl-tor' => '../distr/bsl-tor_0.4.7_x64-setup.zip',
    'tor-bundle' => '../distr/tor-expert-bundle-windows-x86_64-15.0.11.tar.gz',
    '7zip' => '../distr/7z2601-x64.exe',
    'bsl-tagcloud-1.0.0' => '../distr/bsl-tag-cloud-aquarium/mod_bsl_tagcloud-1.0.0.zip',
    'bsl-tagcloud-1.0.1' => '../distr/bsl-tag-cloud-aquarium/mod_bsl_tagcloud-1.0.1.zip',
    'bsl-tagcloud-1.1.0' => '../distr/bsl-tag-cloud-aquarium/mod_bsl_tagcloud-1.1.0.zip',
    'bsl-tagcloud-1.2.0' => '../distr/bsl-tag-cloud-aquarium/mod_bsl_tagcloud-1.2.0.zip',
    'bsl-media-embed-0.1.1' => '../distr/bsl-media-embed/plg_content_bslmediaembed-0.1.1.zip',
    'bsl-media-embed-0.2.0' => '../distr/bsl-media-embed/plg_content_bslmediaembed-0.2.0.zip',
    'bsl-timer-0.3.0' => '../distr/bsl-timer/bsl-timer_0.3.0_free_x64-setup.zip',
    'bsl-timer-0.4.0' => '../distr/bsl-timer/BSL-Timer-0.4.0-Free-x64-setup.exe.zip',

    // Audio files
    'manifest-audio' => '../media/manifest.mp3',
    'karamazov-audio' => '../media/karamazovs-malovernaya-dama-nl.mp3',
    'forest-audio' => '../media/forest.mp3',
    'peskarev-audio' => '../media/peskarev-x15.mp3',

    // Video files
    'bsl-timer-tray' => '../media/bsl-timer-tray.mp4',
];

$allowedExtensions = [
    'exe',
    'zip',
    'gz',
];

$mimeTypes = [
    'mp3' => 'audio/mpeg',
    'mp4' => 'video/mp4',
    'webm' => 'video/webm',
    'ogg' => 'application/ogg',
    'exe' => 'application/octet-stream',
    'zip' => 'application/zip',
    'gz' => 'application/gzip',
];

$allowedSources = [
    'site',
    'jed',
    'joomla',
    'updater',
];

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (!in_array($requestMethod, ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD');
    sendError(405, '405 Method Not Allowed');
}

$product = isset($_GET['product']) && is_string($_GET['product'])
    ? $_GET['product']
    : '';

$fileName = isset($_GET['file']) && is_string($_GET['file'])
    ? $_GET['file']
    : '';

$file = false;
$logKey = '';

/*
 * New route:
 *
 * download.php?product=bsl-timer&file=BSL-Timer-0.4.0-Free-x64-setup.exe.zip
 */
if ($product !== '') {
    if (!array_key_exists($product, $products)) {
        sendError(404);
    }

    if (
        $fileName === ''
        || basename($fileName) !== $fileName
        || str_contains($fileName, '..')
        || str_contains($fileName, '/')
        || str_contains($fileName, '\\')
        || str_contains($fileName, "\0")
    ) {
        sendError(404);
    }

    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    if (!in_array($extension, $allowedExtensions, true)) {
        sendError(404);
    }

    $productDirectory = realpath(
        __DIR__ . DIRECTORY_SEPARATOR . $products[$product]
    );

    if ($productDirectory === false || !is_dir($productDirectory)) {
        sendError(404);
    }

    $candidate = realpath(
        $productDirectory . DIRECTORY_SEPARATOR . $fileName
    );

    if (
        $candidate === false
        || !is_file($candidate)
        || !is_readable($candidate)
    ) {
        sendError(404);
    }

    $productPrefix = rtrim($productDirectory, DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR;

    if (!str_starts_with($candidate, $productPrefix)) {
        sendError(404);
    }

    $file = $candidate;
    $logKey = $product . '/' . $fileName;
} else {
    /*
     * Legacy route:
     *
     * download.php?file=bsl-timer-0.4.0
     */
    if (!array_key_exists($fileName, $legacyFiles)) {
        sendError(404);
    }

    $candidate = realpath(
        __DIR__ . DIRECTORY_SEPARATOR . $legacyFiles[$fileName]
    );

    if (
        $candidate === false
        || !is_file($candidate)
        || !is_readable($candidate)
    ) {
        sendError(404);
    }

    $file = $candidate;
    $logKey = $fileName;
}

$extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$contentType = $mimeTypes[$extension] ?? 'application/octet-stream';

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
            $logKey,
            $source,
        ]) . PHP_EOL;

        @file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
    }
}

$fileSize = filesize($file);

if ($fileSize === false) {
    sendError(500, '500 Internal Server Error');
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
