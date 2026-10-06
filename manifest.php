<?php
declare(strict_types=1);

$settings = [
    'site_name' => 'DAG STUDIO',
    'theme_bg' => '#050505',
    'logo_mark' => '/assets/img/logo-mark-square.svg',
];

$file = __DIR__ . '/storage/settings.json';
if (is_file($file)) {
    $raw = @file_get_contents($file);
    $decoded = $raw !== false ? json_decode($raw, true) : null;
    if (is_array($decoded)) $settings = array_merge($settings, $decoded);
}

$bg = (string)$settings['theme_bg'];
if (!preg_match('/^#[0-9a-fA-F]{6}$/', $bg)) $bg = '#050505';

$mark = trim((string)$settings['logo_mark']);
if ($mark === '' || str_contains($mark, '..') || !str_starts_with($mark, '/')) $mark = '/assets/img/logo-mark-square.svg';
if (!str_starts_with($mark, '/assets/') && !str_starts_with($mark, '/uploads/branding/')) $mark = '/assets/img/logo-mark-square.svg';

$ext = strtolower(pathinfo(parse_url($mark, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
$type = match ($ext) {
    'png' => 'image/png',
    'jpg', 'jpeg' => 'image/jpeg',
    'webp' => 'image/webp',
    default => 'image/svg+xml',
};
$sizes = $type === 'image/svg+xml' ? 'any' : '512x512';
$local = __DIR__ . $mark;
if ($type !== 'image/svg+xml' && is_file($local)) {
    $info = @getimagesize($local);
    if (is_array($info) && !empty($info[0]) && !empty($info[1])) $sizes = $info[0] . 'x' . $info[1];
}

$manifest = [
    'name' => (string)$settings['site_name'],
    'short_name' => 'DAGSTUDIO',
    'start_url' => '/',
    'display' => 'standalone',
    'background_color' => $bg,
    'theme_color' => $bg,
    'icons' => [[
        'src' => $mark,
        'sizes' => $sizes,
        'type' => $type,
    ]],
];

header('Content-Type: application/manifest+json; charset=UTF-8');
header('Cache-Control: no-cache');
echo json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
