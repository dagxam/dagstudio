<?php
declare(strict_types=1);

$enabled = true;
$stateFile = __DIR__ . '/storage/plugins.json';
if (is_file($stateFile)) {
    $raw = @file_get_contents($stateFile);
    $states = $raw !== false ? json_decode($raw, true) : null;
    if (is_array($states) && array_key_exists('audio', $states)) $enabled = (bool)$states['audio'];
}
if (!$enabled) { http_response_code(404); exit; }

$id = preg_replace('/[^a-z0-9-]/i', '', (string)($_GET['id'] ?? ''));
if ($id === '') { http_response_code(404); exit; }

$dataFile = __DIR__ . '/storage/audio.json';
$raw = is_file($dataFile) ? @file_get_contents($dataFile) : false;
$data = $raw !== false ? json_decode($raw, true) : null;
$items = is_array($data) && is_array($data['items'] ?? null) ? $data['items'] : [];

$match = null;
foreach ($items as $item) {
    if (!is_array($item) || (string)($item['id'] ?? '') !== $id) continue;
    if (empty($item['published']) || empty($item['allow_download'])) break;
    $match = $item;
    break;
}
if (!is_array($match)) { http_response_code(404); exit; }

$fileName = basename((string)($match['file'] ?? ''));
$path = __DIR__ . '/uploads/audio/' . $fileName;
if ($fileName === '' || !is_file($path) || !is_readable($path)) { http_response_code(404); exit; }

$downloadName = trim((string)($match['original_name'] ?? ''));
if ($downloadName === '') $downloadName = (string)($match['title'] ?? 'audio') . '.' . pathinfo($fileName, PATHINFO_EXTENSION);
$downloadName = preg_replace('/[\r\n]+/', '', $downloadName) ?? 'audio';

$mime = (string)($match['mime'] ?? 'application/octet-stream');
if (!preg_match('#^(audio/|application/ogg$|video/mp4$)#i', $mime)) $mime = 'application/octet-stream';

header('X-Content-Type-Options: nosniff');
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($path));
header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode($downloadName));
header('Cache-Control: private, max-age=0, must-revalidate');
readfile($path);
exit;
