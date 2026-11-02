<?php
// Duda's font stylesheet URL differs per page only by its query (?family=...). Serve the copy saved
// for this exact query, or the merged stylesheet of every query when this one was not seen.

$dir = __DIR__ . '/_d/irp.cdn-website.com/fonts';
$q = (string)($_SERVER['QUERY_STRING'] ?? '');
$file = $dir . '/css2__' . substr(md5($q), 0, 12);
if ($q === '' || !is_file($file)) $file = $dir . '/css2';
if (!is_file($file)) {
    http_response_code(404);
    exit;
}
header('Content-Type: text/css; charset=utf-8');
header('Cache-Control: public, max-age=604800');
readfile($file);
