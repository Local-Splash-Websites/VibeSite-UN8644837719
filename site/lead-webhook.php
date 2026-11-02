<?php
// Receives the site's lead forms and forwards them to the leads API.
// The copied Duda forms post JSON to /_dm/s/rt/actions/form/<id> (rewritten here by .htaccess)
// and expect Duda's JSON reply; plain HTML form posts are accepted too.
// Settings come from the environment or from a .env file one level above the web root.

declare(strict_types=1);

function env_value(string $key, string $default = ''): string {
    static $file = null;
    $v = getenv($key);
    if ($v !== false && $v !== '') return $v;
    if ($file === null) {
        $file = [];
        $path = dirname(__DIR__) . '/.env';
        if (is_readable($path)) {
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                if ($line[0] === '#' || strpos($line, '=') === false) continue;
                [$k, $val] = explode('=', $line, 2);
                $file[trim($k)] = trim($val, " \t\"'");
            }
        }
    }
    return $file[$key] ?? $default;
}

$isDuda = stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false;

function respond(bool $ok, int $code, string $error = '', array $submitted = []): void {
    global $isDuda;
    if ($isDuda) {
        // Duda's runtime shows the form's error step unless renderedElements is non-empty
        // and actionResult is "submitted"; then it follows the form's thank-you link.
        header('Content-Type: application/json');
        echo json_encode($ok
            ? ['renderedElements' => [''], 'actionResult' => 'submitted', 'submittedFields' => $submitted]
            : ['renderedElements' => [], 'actionResult' => 'error']);
        exit;
    }
    http_response_code($code);
    if (stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
        header('Content-Type: application/json');
        echo json_encode($ok ? ['ok' => true] : ['ok' => false, 'error' => $error]);
    } else {
        header('Location: ' . ($ok ? '/thank-you' : '/contact-us?error=1'), true, 303);
    }
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit;
}

$clip = function ($v, int $max): string {
    $v = trim(is_array($v) ? implode(', ', $v) : (string)$v);
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '';
    return mb_substr($v, 0, $max);
};

$lead = ['name' => '', 'phone' => '', 'email' => '', 'address' => '', 'message' => '', 'extra' => []];
$submitted = [];

if ($isDuda) {
    $body = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($body) || ($body['action'] ?? 'submit') !== 'submit') respond(false, 400);
    $labels = [];
    $map = dirname(__DIR__) . '/form-fields.json';
    if (is_readable($map)) $labels = json_decode((string)file_get_contents($map), true) ?: [];
    foreach ((array)($body['fieldValues'] ?? []) as $id => $values) {
        $label = strtolower((string)($labels[$id] ?? $id));
        $value = $clip($values, 4000);
        $submitted[] = ['label' => $label, 'values' => (array)$values];
        if (array_key_exists($label, $lead) && $label !== 'extra') $lead[$label] = $value;
        else $lead['extra'][$label] = $value;
    }
    $lead['form_id'] = $clip(basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: ''), 60);
} else {
    foreach (['name', 'phone', 'email', 'address', 'message'] as $k) $lead[$k] = $clip($_POST[$k] ?? '', 4000);
    $lead['form_id'] = $clip($_POST['form_id'] ?? '', 60);
}

foreach (['name' => 120, 'phone' => 40, 'email' => 160, 'address' => 300] as $k => $max) $lead[$k] = mb_substr($lead[$k], 0, $max);
if ($lead['name'] === '' || ($lead['phone'] === '' && $lead['email'] === '')) respond(false, 422, 'Enter your name and a phone number or email.');

$apiUrl = env_value('LEADS_API_URL');
$payload = $lead + [
    'site_name'    => env_value('LEADS_SITE_NAME'),
    'page'         => mb_substr((string)($_SERVER['HTTP_REFERER'] ?? ''), 0, 500),
    'ip'           => $_SERVER['REMOTE_ADDR'] ?? '',
    'user_agent'   => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 300),
    'submitted_at' => gmdate('c'),
];

if ($apiUrl === '') {
    error_log('lead-webhook: LEADS_API_URL is not set; lead not forwarded: ' . json_encode($payload));
    respond(false, 503, "That didn't work. The form wasn't sent. Please call us instead.");
}

$headers = ['Content-Type: application/json', 'Accept: application/json'];
$key = env_value('LEADS_API_KEY');
if ($key !== '') $headers[] = 'Authorization: Bearer ' . $key;

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => $headers,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
]);
$resp = curl_exec($ch);
$status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

if ($resp === false || $status < 200 || $status >= 300) {
    error_log("lead-webhook: leads API failed ($status) $err " . substr((string)$resp, 0, 500));
    respond(false, 502, "That didn't work. The form wasn't sent. Please try again or call us.");
}

respond(true, 200, '', $submitted);
