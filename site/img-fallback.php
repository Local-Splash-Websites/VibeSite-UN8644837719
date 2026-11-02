<?php
// Duda's runtime asks irp.cdn-website.com for image sizes it picks at run time (name-1000w.png,
// name-1200h.jpg, ...). When a requested size was not copied, serve the closest copied size of the
// same image (the smallest one at least as large, else the largest), or the original.

declare(strict_types=1);

$uri = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '');
$root = realpath(__DIR__ . '/_d/irp.cdn-website.com');
if (!$root || !preg_match('#^/_d/irp\.cdn-website\.com(/.+)$#', $uri, $m) || strpos($m[1], '..') !== false) {
    http_response_code(404);
    exit;
}
$want = $root . $m[1];
$dir = dirname($want);
$base = basename($want);
if (!preg_match('/^(.+?)(?:-(\d+)([wh]))?\.([a-z0-9]+)$/i', $base, $p)) {
    http_response_code(404);
    exit;
}
[$stem, $size, $ext] = [$p[1], (int)($p[2] ?? 0), $p[4]];

$cands = [];
foreach ([$dir, basename($dir) === 'opt' ? dirname($dir) : null] as $d) {
    if (!$d || !is_dir($d)) continue;
    foreach (glob($d . '/' . glob_escape($stem) . '*.' . $ext) ?: [] as $f) {
        if (!preg_match('/^' . preg_quote($stem, '/') . '(?:-(\d+)[wh])?\.' . preg_quote($ext, '/') . '$/i', basename($f), $q)) continue;
        $cands[$f] = isset($q[1]) ? (int)$q[1] : PHP_INT_MAX;  // the original counts as largest
    }
}
if (!$cands) {
    http_response_code(404);
    exit;
}
asort($cands);
$pick = array_key_last($cands);
foreach ($cands as $f => $w) {
    if ($w >= $size) { $pick = $f; break; }
}
$types = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'gif' => 'image/gif', 'svg' => 'image/svg+xml'];
$head = (string)file_get_contents($pick, false, null, 0, 16);
$type = substr($head, 8, 4) === 'WEBP' ? 'image/webp' : ($types[strtolower($ext)] ?? 'application/octet-stream');
header('Content-Type: ' . $type);
header('Content-Length: ' . filesize($pick));
header('Cache-Control: public, max-age=604800');
readfile($pick);

function glob_escape(string $s): string {
    return preg_replace('/[\[\]\*\?]/', '[$0]', $s);
}
