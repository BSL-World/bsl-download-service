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

function loadRegistry(string $registryFile, string $baseDirectory): array
{
    if (!is_file($registryFile) || !is_readable($registryFile)) {
        throw new RuntimeException('Registry file is unavailable');
    }

    $json = file_get_contents($registryFile);

    if ($json === false || strlen($json) > 1048576) {
        throw new RuntimeException('Registry file cannot be read');
    }

    try {
        $decoded = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        throw new RuntimeException('Registry file contains invalid JSON', 0, $exception);
    }

    if (!$decoded instanceof stdClass) {
        throw new RuntimeException('Registry root must be a JSON object');
    }

    $resolvedBase = realpath($baseDirectory);

    if ($resolvedBase === false || !is_dir($resolvedBase)) {
        throw new RuntimeException('Registry base directory is unavailable');
    }

    $registry = [];

    foreach (get_object_vars($decoded) as $key => $filename) {
        if (!preg_match('~^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$~', $key)) {
            throw new RuntimeException('Registry contains an invalid key');
        }

        if (
            !is_string($filename)
            || !preg_match('~^[A-Za-z0-9][A-Za-z0-9._-]{0,255}$~', $filename)
        ) {
            throw new RuntimeException('Registry contains an invalid filename');
        }

        $registry[$key] = [
            'base' => $resolvedBase,
            'filename' => $filename,
        ];
    }

    return $registry;
}

function isPathWithinDirectory(string $path, string $directory): bool
{
    $path = rtrim(str_replace('\\', '/', $path), '/');
    $directory = rtrim(str_replace('\\', '/', $directory), '/');

    if (DIRECTORY_SEPARATOR === '\\') {
        $path = strtolower($path);
        $directory = strtolower($directory);
    }

    return str_starts_with($path, $directory . '/');
}

$storageRoot = dirname(__DIR__);
$dataDirectory = $storageRoot . DIRECTORY_SEPARATOR . 'bsl-data';

try {
    $distributionFiles = loadRegistry(
        $dataDirectory . DIRECTORY_SEPARATOR . 'distributions-registry.json',
        $storageRoot . DIRECTORY_SEPARATOR . 'distr'
    );
    $mediaFiles = loadRegistry(
        $dataDirectory . DIRECTORY_SEPARATOR . 'media-registry.json',
        $storageRoot . DIRECTORY_SEPARATOR . 'media'
    );

    if (array_intersect_key($distributionFiles, $mediaFiles) !== []) {
        throw new RuntimeException('Registry keys must be unique');
    }

    $allowed = $distributionFiles + $mediaFiles;
} catch (Throwable $exception) {
    sendError(500, 'Error: service configuration unavailable');
}

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

$entry = $allowed[$key];
$file = realpath(
    $entry['base'] . DIRECTORY_SEPARATOR . $entry['filename']
);

if (
    $file === false
    || !isPathWithinDirectory($file, $entry['base'])
    || !is_file($file)
    || !is_readable($file)
) {
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
