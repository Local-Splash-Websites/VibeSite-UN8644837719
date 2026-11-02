<?php
// Assets under /_d/ requested with a query string. The copy keeps the live response for each exact
// query as <path>__<first 12 hex of md5(query)>; serve that, else the query-less file, else (images)
// the closest copied size via img-fallback.php.

declare(strict_types=1);

$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '');
$query = (string)($_SERVER['QUERY_STRING'] ?? '');
$root = realpath(__DIR__ . '/_d');
if (!$root || strpos($path, '/_d/') !== 0 || strpos($path, '..') !== false) {
    http_response_code(404);
    exit;
}
$file = $root . substr($path, 3);
$variant = $file . '__' . substr(md5($query), 0, 12);
$pick = is_file($variant) ? $variant : (is_file($file) ? $file : null);

if ($pick === null) {
    if (preg_match('#^/_d/irp\.cdn-website\.com/.+\.(png|jpe?g|webp|gif)$#i', $path)) {
        require __DIR__ . '/img-fallback.php';
        exit;
    }
    http_response_code(404);
    exit;
}

$types = [
    'css' => 'text/css; charset=utf-8', 'js' => 'text/javascript; charset=utf-8', 'json' => 'application/json',
    'svg' => 'image/svg+xml', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif',
    'webp' => 'image/webp', 'avif' => 'image/avif', 'woff2' => 'font/woff2', 'woff' => 'font/woff', 'ttf' => 'font/ttf',
    'mp4' => 'video/mp4', 'ico' => 'image/x-icon',
];
$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$head = (string)file_get_contents($pick, false, null, 0, 16);
if (substr($head, 8, 4) === 'WEBP') $type = 'image/webp';
elseif (substr($head, 4, 8) === 'ftypavif') $type = 'image/avif';
elseif (substr($head, 0, 8) === "\x89PNG\r\n\x1a\n") $type = 'image/png';
elseif (substr($head, 0, 3) === "\xFF\xD8\xFF") $type = 'image/jpeg';
elseif (basename($path) === 'css2') $type = 'text/css; charset=utf-8';
else $type = $types[$ext] ?? 'application/octet-stream';

header('Content-Type: ' . $type);
header('Content-Length: ' . filesize($pick));
header('Cache-Control: public, max-age=604800');
readfile($pick);
