<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/requests-data.php';

ds_public_security_headers();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /', true, 303);
    exit;
}

if (!ds_same_origin_request()) {
    ds_security_log(__DIR__, 'form.cross_origin_blocked');
    http_response_code(403);
    exit('Запрос отклонён.');
}

if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 32768) {
    ds_security_log(__DIR__, 'form.body_too_large');
    http_response_code(413);
    exit('Слишком большой запрос.');
}

function clean_line(string $value, int $max): string {
    $value = trim(strip_tags($value));
    $value = preg_replace('/[\r\n]+/', ' ', $value) ?? '';
    return mb_substr($value, 0, $max);
}

function clean_text(string $value, int $max): string {
    $value = trim(strip_tags($value));
    $value = preg_replace("/\r\n?|\n/", "\n", $value) ?? '';
    return mb_substr($value, 0, $max);
}

$source = (string)($_POST['source'] ?? 'contact');
$source = in_array($source, ['contact', 'modal'], true) ? $source : 'contact';
$anchor = $source === 'contact' ? '#contacts' : '#top';

$pluginStates = [];
$pluginStateFile = __DIR__ . '/storage/plugins.json';
if (is_file($pluginStateFile)) {
    $rawStates = @file_get_contents($pluginStateFile);
    $decodedStates = $rawStates !== false ? json_decode($rawStates, true) : null;
    if (is_array($decodedStates)) $pluginStates = $decodedStates;
}
$requestsEnabled = !array_key_exists('requests', $pluginStates) || !empty($pluginStates['requests']);
if (!$requestsEnabled) {
    ds_security_log(__DIR__, 'form.requests_plugin_disabled', ['source' => $source]);
    header('Location: /?sent=0&source=' . rawurlencode($source) . $anchor, true, 303);
    exit;
}

if (!empty($_POST['website'] ?? '')) {
    ds_security_log(__DIR__, 'form.honeypot');
    header('Location: /?sent=1&source=' . rawurlencode($source) . $anchor, true, 303);
    exit;
}

$formToken = (string)($_POST['form_token'] ?? '');
if (!ds_verify_form_token(__DIR__, $formToken, 'contact', 1, 7200)) {
    ds_security_log(__DIR__, 'form.invalid_token');
    header('Location: /?sent=0&source=' . rawurlencode($source) . $anchor, true, 303);
    exit;
}

$name = clean_line((string)($_POST['name'] ?? ''), 120);
$phone = clean_line((string)($_POST['phone'] ?? ''), 40);
$email = strtolower(clean_line((string)($_POST['email'] ?? ''), 120));
$message = clean_text((string)($_POST['message'] ?? ''), 2000);
$phoneDigits = preg_replace('/\D+/', '', $phone) ?? '';

$linkCount = preg_match_all('#https?://|www\.#iu', $message) ?: 0;
$tooManyLinks = $linkCount > 5;
$tooManyRepeats = preg_match('/(.)\1{11,}/u', $message) === 1;

if (
    $name === '' ||
    mb_strlen($name) < 2 ||
    strlen($phoneDigits) < 6 ||
    $message === '' ||
    mb_strlen($message) < 5 ||
    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    $tooManyLinks ||
    $tooManyRepeats
) {
    ds_security_log(__DIR__, 'form.invalid_or_spam_input');
    header('Location: /?sent=0&source=' . rawurlencode($source) . $anchor, true, 303);
    exit;
}

$shortWindow = ds_rate_limit(__DIR__, 'contact_form_short', 5, 900, 'contact', true);
$hourWindow = ds_rate_limit(__DIR__, 'contact_form_hour', 20, 3600, 'contact', true);
$globalWindow = ds_rate_limit(__DIR__, 'contact_form_global', 60, 3600, 'contact', false);
if (!$shortWindow || !$hourWindow || !$globalWindow) {
    ds_security_log(__DIR__, 'form.rate_limited');
    header('Location: /?sent=0&source=' . rawurlencode($source) . $anchor, true, 303);
    exit;
}

$items = ds_requests_read(__DIR__);
$fingerprint = ds_requests_fingerprint($email, $phone, $message);

if (ds_requests_is_recent_duplicate($items, $fingerprint, 600)) {
    ds_security_log(__DIR__, 'form.duplicate_suppressed');
    header('Location: /?sent=1&source=' . rawurlencode($source) . $anchor, true, 303);
    exit;
}

$request = [
    'id' => date('YmdHis') . '-' . bin2hex(random_bytes(4)),
    'name' => $name,
    'phone' => $phone,
    'email' => $email,
    'message' => $message,
    'source' => $source,
    'status' => 'pending',
    'fingerprint' => $fingerprint,
    'ip_hash' => ds_ip_hash(),
    'user_agent' => mb_substr(trim((string)($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 220),
    'created_at' => gmdate('c'),
    'updated_at' => gmdate('c'),
];

$ok = ds_requests_append(__DIR__, $request);
ds_security_log(__DIR__, $ok ? 'form.request_saved' : 'form.request_save_failed', ['source' => $source]);

header('Location: /?sent=' . ($ok ? '1' : '0') . '&source=' . rawurlencode($source) . $anchor, true, 303);
exit;
