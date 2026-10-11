<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/scripts-data.php';

ds_public_security_headers();

$states = [];
$stateFile = __DIR__ . '/storage/plugins.json';
if (is_file($stateFile)) {
    $raw = @file_get_contents($stateFile);
    $decoded = $raw !== false ? json_decode($raw, true) : null;
    if (is_array($decoded)) $states = $decoded;
}
if (array_key_exists('scripts', $states) && empty($states['scripts'])) {
    http_response_code(404);
    exit('Раздел отключён.');
}

$id = preg_replace('/[^a-z0-9_-]/i', '', (string)($_GET['id'] ?? ''));
$item = ds_script_find(ds_scripts_read(__DIR__), $id);
if (!is_array($item) || empty($item['published'])) {
    http_response_code(404);
    exit('Файл не найден.');
}

$path = ds_script_file_path(__DIR__, (string)($item['file'] ?? ''));
if ($path === '') {
    http_response_code(404);
    exit('Архив временно недоступен.');
}

$name = trim((string)($item['original_name'] ?? basename($path)));
$name = preg_replace('/[\r\n"\\\/]+/u', '-', $name) ?: 'download.zip';

header_remove('Content-Security-Policy');
header('Content-Type: application/octet-stream');
header('Content-Length: ' . (string)filesize($path));
header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode($name));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');
readfile($path);
exit;
