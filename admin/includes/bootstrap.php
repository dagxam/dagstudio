<?php
declare(strict_types=1);

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    http_response_code(404);
    exit;
}

define('DS_ROOT', dirname(__DIR__, 2));
define('DS_STORAGE', DS_ROOT . '/storage');
define('DS_PLUGINS', DS_ROOT . '/plugins');
require_once DS_ROOT . '/includes/security.php';

if (!is_dir(DS_STORAGE)) {
    @mkdir(DS_STORAGE, 0750, true);
}
@chmod(DS_STORAGE, 0750);

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name('dagstudio_admin');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/admin',
    'secure' => ds_is_https(),
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

ds_admin_security_headers();

const DS_ADMIN_IDLE_TIMEOUT = 1800;
const DS_ADMIN_ABSOLUTE_TIMEOUT = 28800;
const DS_ADMIN_ROTATE_INTERVAL = 900;

function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function admin_redirect(string $path): never {
    header('Location: ' . $path);
    exit;
}

function admin_is_authenticated(): bool {
    if (empty($_SESSION['admin_authenticated'])) return false;

    $now = time();
    $started = (int)($_SESSION['admin_session_started'] ?? $_SESSION['admin_login_at'] ?? 0);
    $last = (int)($_SESSION['admin_last_activity'] ?? $started);

    if ($started <= 0 || $now - $started > DS_ADMIN_ABSOLUTE_TIMEOUT || ($last > 0 && $now - $last > DS_ADMIN_IDLE_TIMEOUT)) {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) @session_regenerate_id(true);
        return false;
    }

    $rotated = (int)($_SESSION['admin_session_rotated'] ?? $started);
    if ($rotated <= 0 || $now - $rotated > DS_ADMIN_ROTATE_INTERVAL) {
        @session_regenerate_id(true);
        $_SESSION['admin_session_rotated'] = $now;
    }

    $_SESSION['admin_last_activity'] = $now;
    return true;
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
    if (!ds_same_origin_request()) {
        ds_security_log(DS_ROOT, 'csrf.cross_origin_blocked', ['path' => (string)($_SERVER['REQUEST_URI'] ?? '')]);
        http_response_code(403);
        exit('Запрос отклонён.');
    }

    $posted = (string)($_POST['csrf_token'] ?? '');
    if ($posted === '' || !hash_equals(csrf_token(), $posted)) {
        ds_security_log(DS_ROOT, 'csrf.invalid', ['path' => (string)($_SERVER['REQUEST_URI'] ?? '')]);
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
        'ip_hash' => ds_ip_hash(),
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

function ds_portfolio_menu(array $menu): array {
    foreach ($menu as &$item) {
        if (!is_array($item)) continue;
        $label = mb_strtolower(trim((string)($item['label'] ?? '')));
        if ($label === 'блог') {
            $item['label'] = 'Наши работы';
            $item['url'] = '/works.php';
            $item['visible'] = true;
            $item['new_tab'] = false;
        }
    }
    unset($item);
    return $menu;
}

function site_settings(): array {
    $defaults = [
        'site_name' => 'DAG STUDIO',
        'email' => 'admin@dagstudio.ru',
        'admin_email' => 'admin@dagstudio.ru',
        'phone' => '+7 (928) 809-50-18',
        'location' => "Россия, Дагестан\nг. Махачкала",
        'telegram' => '',
        'whatsapp' => '',
        'behance' => '',
        'theme_scheme' => 'copper',
        'theme_accent' => '#c96f41',
        'theme_bg' => '#050505',
        'theme_panel' => '#1c1c1c',
        'theme_text' => '#f7f7f5',
        'logo_dark' => '/assets/img/logo-horizontal-dark.svg',
        'logo_light' => '/assets/img/logo-horizontal.svg',
        'logo_admin' => '/assets/img/logo-horizontal-dark.svg',
        'logo_mark' => '/assets/img/logo-mark-square.svg',
        'hero_ornament' => '/assets/img/hero-ornament-main-v2.webp',
        'main_menu' => [
            ['label' => 'Главная', 'url' => '/#top', 'visible' => true, 'new_tab' => false],
            ['label' => 'Наши работы', 'url' => '/works.php', 'visible' => true, 'new_tab' => false],
            ['label' => 'Наши аудио', 'url' => '/audio.php', 'visible' => true, 'new_tab' => false],
            ['label' => 'Скрипты', 'url' => '/#services', 'visible' => true, 'new_tab' => false],
            ['label' => 'О студии', 'url' => '/#about', 'visible' => true, 'new_tab' => false],
        ],
    ];
    $settings = array_merge($defaults, storage_read_json('settings.json', []));
    $settings['main_menu'] = ds_portfolio_menu(is_array($settings['main_menu'] ?? null) ? $settings['main_menu'] : $defaults['main_menu']);
    return $settings;
}

function setting_color(array $settings, string $key, string $fallback): string {
    $value = (string)($settings[$key] ?? '');
    return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : $fallback;
}

function setting_asset(array $settings, string $key, string $fallback): string {
    $value = trim((string)($settings[$key] ?? ''));
    if ($value === '' || str_contains($value, '..') || !str_starts_with($value, '/')) return $fallback;
    if (!str_starts_with($value, '/assets/') && !str_starts_with($value, '/uploads/branding/')) return $fallback;
    return $value;
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

function admin_auth_config(): array {
    return array_merge([
        'totp_enabled' => false,
        'totp_secret' => '',
        'recovery_hashes' => [],
        'enabled_at' => '',
    ], storage_read_json('admin-auth.json', []));
}

function ds_base32_encode(string $binary): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($binary) as $char) {
        $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
    }
    $encoded = '';
    for ($i = 0, $len = strlen($bits); $i < $len; $i += 5) {
        $chunk = substr($bits, $i, 5);
        if (strlen($chunk) < 5) $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
        $encoded .= $alphabet[bindec($chunk)];
    }
    return $encoded;
}

function ds_base32_decode(string $value): string|false {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $value = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $value) ?? '');
    if ($value === '') return false;
    $bits = '';
    foreach (str_split($value) as $char) {
        $pos = strpos($alphabet, $char);
        if ($pos === false) return false;
        $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
    }
    $binary = '';
    for ($i = 0, $len = strlen($bits); $i + 8 <= $len; $i += 8) {
        $binary .= chr(bindec(substr($bits, $i, 8)));
    }
    return $binary;
}

function admin_totp_generate_secret(): string {
    return ds_base32_encode(random_bytes(20));
}

function admin_totp_code(string $secret, ?int $timeSlice = null): string {
    $key = ds_base32_decode($secret);
    if ($key === false) return '';
    $timeSlice ??= intdiv(time(), 30);
    $counter = pack('N2', 0, $timeSlice);
    $hash = hash_hmac('sha1', $counter, $key, true);
    $offset = ord($hash[19]) & 0x0f;
    $binary = ((ord($hash[$offset]) & 0x7f) << 24)
        | ((ord($hash[$offset + 1]) & 0xff) << 16)
        | ((ord($hash[$offset + 2]) & 0xff) << 8)
        | (ord($hash[$offset + 3]) & 0xff);
    return str_pad((string)($binary % 1000000), 6, '0', STR_PAD_LEFT);
}

function admin_totp_verify(string $secret, string $code, int $window = 1): bool {
    $code = preg_replace('/\D+/', '', $code) ?? '';
    if (strlen($code) !== 6) return false;
    $slice = intdiv(time(), 30);
    for ($offset = -$window; $offset <= $window; $offset++) {
        if (hash_equals(admin_totp_code($secret, $slice + $offset), $code)) return true;
    }
    return false;
}

function admin_auth_key(): string {
    return hash('sha256', ds_security_secret(DS_ROOT) . '|dagstudio-admin-auth', true);
}

function admin_encrypt_totp_secret(string $secret): string {
    if (!function_exists('openssl_encrypt')) return '';
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($secret, 'aes-256-gcm', admin_auth_key(), OPENSSL_RAW_DATA, $iv, $tag);
    if (!is_string($cipher) || strlen($tag) !== 16) return '';
    return base64_encode($iv . $tag . $cipher);
}

function admin_decrypt_totp_secret(string $encrypted): string {
    if (!function_exists('openssl_decrypt')) return '';
    $raw = base64_decode($encrypted, true);
    if ($raw === false || strlen($raw) < 29) return '';
    $iv = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $cipher = substr($raw, 28);
    $secret = openssl_decrypt($cipher, 'aes-256-gcm', admin_auth_key(), OPENSSL_RAW_DATA, $iv, $tag);
    return is_string($secret) ? $secret : '';
}

function admin_totp_uri(string $secret, string $account): string {
    $issuer = 'DAG STUDIO';
    $label = $issuer . ':' . $account;
    return 'otpauth://totp/' . rawurlencode($label)
        . '?secret=' . rawurlencode($secret)
        . '&issuer=' . rawurlencode($issuer)
        . '&algorithm=SHA1&digits=6&period=30';
}

function admin_generate_recovery_codes(int $count = 8): array {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $codes = [];
    for ($n = 0; $n < $count; $n++) {
        $raw = '';
        for ($i = 0; $i < 10; $i++) $raw .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        $codes[] = substr($raw, 0, 5) . '-' . substr($raw, 5);
    }
    return $codes;
}

function admin_recovery_hash(string $code): string {
    $normalized = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $code) ?? '');
    return hash_hmac('sha256', $normalized, admin_auth_key());
}

function admin_recovery_verify_and_consume(string $code): bool {
    $config = admin_auth_config();
    $hashes = is_array($config['recovery_hashes'] ?? null) ? $config['recovery_hashes'] : [];
    $candidate = admin_recovery_hash($code);
    foreach ($hashes as $index => $hash) {
        if (is_string($hash) && hash_equals($hash, $candidate)) {
            unset($hashes[$index]);
            $config['recovery_hashes'] = array_values($hashes);
            if (!storage_write_json('admin-auth.json', $config)) return false;
            audit_log('auth.recovery_code_used', ['remaining' => count($hashes)]);
            return true;
        }
    }
    return false;
}

function admin_complete_login(): never {
    session_regenerate_id(true);
    $now = time();
    $_SESSION['admin_authenticated'] = true;
    $_SESSION['admin_login_at'] = $now;
    $_SESSION['admin_session_started'] = $now;
    $_SESSION['admin_last_activity'] = $now;
    $_SESSION['admin_session_rotated'] = $now;
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    unset($_SESSION['otp_hash'], $_SESSION['otp_expires'], $_SESSION['otp_attempts'], $_SESSION['otp_sent_at']);
    audit_log('auth.login');
    $target = (string)($_SESSION['after_login'] ?? '/admin/');
    unset($_SESSION['after_login']);
    if (!str_starts_with($target, '/admin')) $target = '/admin/';
    admin_redirect($target);
}

function admin_sidebar_available_items(array $plugins, array $states): array {
    $items = [
        'dashboard' => ['id' => 'dashboard', 'label' => 'Обзор', 'href' => '/admin/', 'icon' => '◫', 'counter' => ''],
        'settings' => ['id' => 'settings', 'label' => 'Настройки', 'href' => '/admin/settings.php', 'icon' => '⚙', 'counter' => ''],
        'security' => ['id' => 'security', 'label' => 'Безопасность', 'href' => '/admin/security.php', 'icon' => '⌾', 'counter' => ''],
        'plugins' => ['id' => 'plugins', 'label' => 'Функции и плагины', 'href' => '/admin/plugins.php', 'icon' => '◆', 'counter' => ''],
    ];

    foreach ($plugins as $plugin) {
        if (!is_array($plugin) || empty($plugin['menu']) || !plugin_enabled($plugin, $states)) continue;
        $pluginId = (string)($plugin['id'] ?? '');
        if ($pluginId === '') continue;

        $counter = '';
        if (isset($plugin['counter'])) {
            if (is_callable($plugin['counter'])) {
                try {
                    $counter = (string)$plugin['counter']();
                } catch (Throwable) {
                    $counter = '';
                }
            } elseif (is_scalar($plugin['counter'])) {
                $counter = (string)$plugin['counter'];
            }
        }

        $id = 'plugin-' . $pluginId;
        $items[$id] = [
            'id' => $id,
            'label' => (string)$plugin['menu'],
            'href' => '/admin/plugin.php?id=' . rawurlencode($pluginId),
            'icon' => (string)($plugin['icon'] ?? '+'),
            'counter' => $counter,
        ];
    }

    return $items;
}

function admin_sidebar_order(array $available): array {
    $saved = storage_read_json('admin-menu.json', ['order' => []]);
    $order = is_array($saved['order'] ?? null) ? $saved['order'] : [];
    $result = [];

    foreach ($order as $id) {
        $id = (string)$id;
        if ($id === 'requests' && isset($available['plugin-requests'])) $id = 'plugin-requests';
        if (isset($available[$id]) && !in_array($id, $result, true)) $result[] = $id;
    }
    foreach (array_keys($available) as $id) {
        if (!in_array($id, $result, true)) $result[] = $id;
    }

    return $result;
}

function save_admin_sidebar_order(array $order, array $available): bool {
    $clean = [];
    foreach ($order as $id) {
        $id = preg_replace('/[^a-z0-9_-]/i', '', (string)$id);
        if ($id !== '' && isset($available[$id]) && !in_array($id, $clean, true)) $clean[] = $id;
    }
    foreach (array_keys($available) as $id) {
        if (!in_array($id, $clean, true)) $clean[] = $id;
    }

    if (!storage_write_json('admin-menu.json', ['order' => $clean])) return false;
    audit_log('admin.sidebar_order_updated', ['count' => count($clean)]);
    return true;
}
