<?php
declare(strict_types=1);

define('DS_ROOT', dirname(__DIR__, 2));
define('DS_STORAGE', DS_ROOT . '/storage');
define('DS_PLUGINS', DS_ROOT . '/plugins');

if (!is_dir(DS_STORAGE)) {
    @mkdir(DS_STORAGE, 0755, true);
}

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name('dagstudio_admin');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/admin',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function admin_redirect(string $path): never {
    header('Location: ' . $path);
    exit;
}

function admin_is_authenticated(): bool {
    return !empty($_SESSION['admin_authenticated']);
}

function admin_require_auth(): void {
    if (!admin_is_authenticated()) {
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? '/admin/';
        admin_redirect('/admin/login.php');
    }
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void {
    $posted = (string)($_POST['csrf_token'] ?? '');
    if ($posted === '' || !hash_equals(csrf_token(), $posted)) {
        http_response_code(419);
        exit('Сессия устарела. Обновите страницу и повторите действие.');
    }
}

function flash(string $type, string $message): void {
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array {
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return is_array($items) ? $items : [];
}

function storage_read_json(string $name, array $fallback = []): array {
    $path = DS_STORAGE . '/' . basename($name);
    if (!is_file($path)) return $fallback;
    $raw = @file_get_contents($path);
    if ($raw === false) return $fallback;
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : $fallback;
}

function storage_write_json(string $name, array $data): bool {
    $path = DS_STORAGE . '/' . basename($name);
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) return false;
    $tmp = $path . '.tmp-' . bin2hex(random_bytes(4));
    if (@file_put_contents($tmp, $json . PHP_EOL, LOCK_EX) === false) return false;
    @chmod($tmp, 0640);
    return @rename($tmp, $path);
}

function audit_log(string $action, array $context = []): void {
    $record = [
        'time' => gmdate('c'),
        'action' => $action,
        'context' => $context,
    ];
    $line = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($line !== false) {
        @file_put_contents(DS_STORAGE . '/audit.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

function audit_recent(int $limit = 8): array {
    $path = DS_STORAGE . '/audit.log';
    if (!is_file($path)) return [];
    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) return [];
    $lines = array_slice($lines, -$limit);
    $out = [];
    foreach (array_reverse($lines) as $line) {
        $row = json_decode($line, true);
        if (is_array($row)) $out[] = $row;
    }
    return $out;
}

function site_settings(): array {
    $defaults = [
        'site_name' => 'DAG STUDIO',
        'email' => 'admin@dagstudio.ru',
        'phone' => '+7 (928) 809-50-18',
        'location' => "Россия, Дагестан\nг. Махачкала",
        'telegram' => '',
        'whatsapp' => '',
        'behance' => '',
    ];
    return array_merge($defaults, storage_read_json('settings.json', []));
}

function plugin_definitions(): array {
    $plugins = [];
    if (!is_dir(DS_PLUGINS)) return $plugins;
    foreach (glob(DS_PLUGINS . '/*/plugin.php') ?: [] as $file) {
        $def = require $file;
        if (!is_array($def)) continue;
        $id = preg_replace('/[^a-z0-9_-]/i', '', (string)($def['id'] ?? ''));
        if ($id === '') continue;
        $def['id'] = $id;
        $def['path'] = dirname($file);
        $plugins[$id] = $def;
    }
    ksort($plugins);
    return $plugins;
}

function plugin_states(): array {
    return storage_read_json('plugins.json', []);
}

function plugin_enabled(array $plugin, ?array $states = null): bool {
    $states ??= plugin_states();
    $id = (string)$plugin['id'];
    if (array_key_exists($id, $states)) return (bool)$states[$id];
    return (bool)($plugin['default_enabled'] ?? false);
}

function set_plugin_enabled(string $id, bool $enabled): bool {
    $defs = plugin_definitions();
    if (!isset($defs[$id])) return false;
    $states = plugin_states();
    $states[$id] = $enabled;
    if (!storage_write_json('plugins.json', $states)) return false;
    audit_log($enabled ? 'plugin.enabled' : 'plugin.disabled', ['plugin' => $id]);
    return true;
}
