<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

if (admin_is_authenticated()) {
    admin_redirect('/admin/');
}

$settings = site_settings();
$adminEmail = strtolower(trim((string)($settings['admin_email'] ?? 'admin@dagstudio.ru')));
$accent = setting_color($settings, 'theme_accent', '#c96f41');
$bg = setting_color($settings, 'theme_bg', '#050505');
$panel = setting_color($settings, 'theme_panel', '#1c1c1c');
$text = setting_color($settings, 'theme_text', '#f7f7f5');
$adminLogo = setting_asset($settings, 'logo_admin', '/assets/img/logo-horizontal-dark.svg');
$markLogo = setting_asset($settings, 'logo_mark', '/assets/img/logo-mark-square.svg');
$error = '';
$stage = !empty($_SESSION['otp_hash']) ? 'verify' : 'request';

function login_mask_email(string $email): string {
    [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
    if ($local === '' || $domain === '') return '••••••';
    $visible = mb_substr($local, 0, min(2, mb_strlen($local)));
    return $visible . str_repeat('•', max(4, mb_strlen($local) - mb_strlen($visible))) . '@' . $domain;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'send') {
        $last = (int)($_SESSION['otp_sent_at'] ?? 0);
        $ipAllowed = ds_rate_limit(DS_ROOT, 'admin_otp_send_ip', 5, 900, 'send', true);
        $globalAllowed = ds_rate_limit(DS_ROOT, 'admin_otp_send_global', 30, 3600, $adminEmail, false);

        if (!$ipAllowed || !$globalAllowed) {
            http_response_code(429);
            ds_security_log(DS_ROOT, 'auth.otp_rate_limited');
            $error = 'Слишком много запросов кода. Попробуйте позже.';
        } elseif ($last > 0 && time() - $last < 60) {
            $error = 'Подождите минуту перед повторной отправкой кода.';
        } else {
            $code = (string)random_int(100000, 999999);
            $_SESSION['otp_hash'] = password_hash($code, PASSWORD_DEFAULT);
            $_SESSION['otp_expires'] = time() + 600;
            $_SESSION['otp_attempts'] = 0;
            $_SESSION['otp_sent_at'] = time();

            $subject = 'Код входа в DAG STUDIO';
            $body = "Код входа в панель DAG STUDIO: {$code}\n\nКод действует 10 минут. Если вы не запрашивали вход, проигнорируйте письмо.";
            $headers = [
                'From: DAG STUDIO <no-reply@dagstudio.ru>',
                'Content-Type: text/plain; charset=UTF-8',
            ];
            $sent = @mail($adminEmail, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers));
            if ($sent) {
                audit_log('auth.code_sent');
                $stage = 'verify';
            } else {
                unset($_SESSION['otp_hash'], $_SESSION['otp_expires'], $_SESSION['otp_attempts'], $_SESSION['otp_sent_at']);
                ds_security_log(DS_ROOT, 'auth.otp_mail_failed');
                $error = 'Сервер не смог отправить код на почту. Проверьте почтовую функцию хостинга.';
                $stage = 'request';
            }
        }
    }

    if ($action === 'verify') {
        $stage = 'verify';

        if (!ds_rate_limit(DS_ROOT, 'admin_otp_verify_ip', 15, 900, 'verify', true)) {
            http_response_code(429);
            ds_security_log(DS_ROOT, 'auth.verify_rate_limited');
            $error = 'Слишком много попыток входа. Попробуйте позже.';
        }

        $expires = (int)($_SESSION['otp_expires'] ?? 0);
        $attempts = (int)($_SESSION['otp_attempts'] ?? 0);
        $code = preg_replace('/\D+/', '', (string)($_POST['code'] ?? ''));

        if ($error !== '') {
            // Persistent rate limit already blocked this attempt.
        } elseif ($expires < time()) {
            $error = 'Код истёк. Запросите новый.';
            unset($_SESSION['otp_hash'], $_SESSION['otp_expires'], $_SESSION['otp_attempts']);
            $stage = 'request';
        } elseif ($attempts >= 5) {
            $error = 'Слишком много попыток. Запросите новый код.';
            unset($_SESSION['otp_hash'], $_SESSION['otp_expires'], $_SESSION['otp_attempts']);
            $stage = 'request';
        } elseif (strlen($code) !== 6 || empty($_SESSION['otp_hash']) || !password_verify($code, (string)$_SESSION['otp_hash'])) {
            $_SESSION['otp_attempts'] = $attempts + 1;
            ds_security_log(DS_ROOT, 'auth.invalid_otp', ['attempt' => (string)($attempts + 1)]);
            $error = 'Неверный код.';
        } else {
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
    }

    if ($action === 'reset') {
        unset($_SESSION['otp_hash'], $_SESSION['otp_expires'], $_SESSION['otp_attempts'], $_SESSION['otp_sent_at']);
        $stage = 'request';
    }
}
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>Вход — DAG STUDIO</title>
  <link rel="icon" href="<?= e($markLogo) ?>">
  <link rel="stylesheet" href="/admin/assets/admin.css">
  <style>:root{--admin-accent:<?= e($accent) ?>;--admin-bg:<?= e($bg) ?>;--admin-panel:<?= e($panel) ?>;--admin-text:<?= e($text) ?>}</style>
</head>
<body class="login-body">
  <section class="login-card">
    <img class="login-logo" src="<?= e($adminLogo) ?>" alt="DAG STUDIO">
    <h1>Вход в админку</h1>
    <p>Доступ подтверждается одноразовым кодом, который отправляется на административную почту сайта.</p>

    <?php if ($error !== ''): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>

    <?php if ($stage === 'request'): ?>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="send">
        <div class="field">
          <label>Административная почта</label>
          <input type="text" value="<?= e(login_mask_email($adminEmail)) ?>" readonly aria-label="Скрытый адрес административной почты">
        </div>
        <button class="btn" type="submit">Получить код</button>
      </form>
    <?php else: ?>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="verify">
        <div class="field">
          <label for="code">Код из письма</label>
          <div class="otp-row"><input id="code" type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="000000" required autofocus></div>
        </div>
        <button class="btn" type="submit">Войти</button>
      </form>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="reset">
        <button class="small-link" type="submit" style="background:none;border:0;cursor:pointer">Запросить другой код</button>
      </form>
    <?php endif; ?>

    <div class="login-help">Код действует 10 минут. После 5 неверных попыток он сбрасывается.</div>
  </section>
</body>
</html>