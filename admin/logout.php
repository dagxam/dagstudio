<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
if (admin_is_authenticated()) audit_log('auth.logout');
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => (string)($p['path'] ?? '/admin'),
        'domain' => (string)($p['domain'] ?? ''),
        'secure' => (bool)($p['secure'] ?? ds_is_https()),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}
session_destroy();

$return = (string)($_GET['return'] ?? '');
$target = $return === 'site' ? '/' : '/admin/login.php';
header('Location: ' . $target, true, 303);
exit;