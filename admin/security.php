<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

admin_require_auth();

$settings = site_settings();
$adminEmail = strtolower(trim((string)($settings['admin_email'] ?? 'admin@dagstudio.ru')));
$config = admin_auth_config();
$enabled = !empty($config['totp_enabled']);
$setupSecret = (string)($_SESSION['totp_setup_secret'] ?? '');
$recoveryCodes = is_array($_SESSION['new_recovery_codes'] ?? null) ? $_SESSION['new_recovery_codes'] : [];
unset($_SESSION['new_recovery_codes']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'begin_totp' && !$enabled) {
        if (!function_exists('openssl_encrypt') || !function_exists('openssl_decrypt')) {
            flash('error', 'На сервере недоступен OpenSSL. Подключение приложения-аутентификатора невозможно.');
        } else {
            $_SESSION['totp_setup_secret'] = admin_totp_generate_secret();
            audit_log('auth.totp_setup_started');
        }
        admin_redirect('/admin/security.php');
    }

    if ($action === 'cancel_totp' && !$enabled) {
        unset($_SESSION['totp_setup_secret']);
        flash('success', 'Подключение отменено.');
        admin_redirect('/admin/security.php');
    }

    if ($action === 'confirm_totp' && !$enabled) {
        $secret = (string)($_SESSION['totp_setup_secret'] ?? '');
        $code = (string)($_POST['code'] ?? '');

        if ($secret === '' || !admin_totp_verify($secret, $code, 1)) {
            ds_security_log(DS_ROOT, 'auth.totp_setup_invalid');
            flash('error', 'Код не подошёл. Проверьте время на телефоне и введите новый шестизначный код.');
            admin_redirect('/admin/security.php');
        }

        $encrypted = admin_encrypt_totp_secret($secret);
        if ($encrypted === '') {
            flash('error', 'Не удалось безопасно зашифровать ключ аутентификатора.');
            admin_redirect('/admin/security.php');
        }

        $codes = admin_generate_recovery_codes(8);
        $next = [
            'totp_enabled' => true,
            'totp_secret' => $encrypted,
            'recovery_hashes' => array_map('admin_recovery_hash', $codes),
            'enabled_at' => gmdate('c'),
        ];

        if (!storage_write_json('admin-auth.json', $next)) {
            flash('error', 'Не удалось сохранить настройки двухфакторной защиты.');
            admin_redirect('/admin/security.php');
        }

        unset($_SESSION['totp_setup_secret']);
        $_SESSION['new_recovery_codes'] = $codes;
        audit_log('auth.totp_enabled');
        flash('success', 'Приложение-аутентификатор подключено. Теперь вход выполняется без письма на почту.');
        admin_redirect('/admin/security.php');
    }

    if ($action === 'regenerate_recovery' && $enabled) {
        $secret = admin_decrypt_totp_secret((string)($config['totp_secret'] ?? ''));
        $code = (string)($_POST['code'] ?? '');
        if ($secret === '' || !admin_totp_verify($secret, $code, 1)) {
            flash('error', 'Введите актуальный код из приложения-аутентификатора.');
            admin_redirect('/admin/security.php');
        }

        $codes = admin_generate_recovery_codes(8);
        $config['recovery_hashes'] = array_map('admin_recovery_hash', $codes);
        if (storage_write_json('admin-auth.json', $config)) {
            $_SESSION['new_recovery_codes'] = $codes;
            audit_log('auth.recovery_codes_regenerated');
            flash('success', 'Новые резервные коды созданы. Старые коды больше не действуют.');
        } else {
            flash('error', 'Не удалось сохранить резервные коды.');
        }
        admin_redirect('/admin/security.php');
    }

    if ($action === 'disable_totp' && $enabled) {
        $secret = admin_decrypt_totp_secret((string)($config['totp_secret'] ?? ''));
        $code = (string)($_POST['code'] ?? '');
        if ($secret === '' || !admin_totp_verify($secret, $code, 1)) {
            flash('error', 'Для отключения защиты нужен актуальный код из приложения.');
            admin_redirect('/admin/security.php');
        }

        if (storage_write_json('admin-auth.json', [
            'totp_enabled' => false,
            'totp_secret' => '',
            'recovery_hashes' => [],
            'enabled_at' => '',
        ])) {
            audit_log('auth.totp_disabled');
            flash('success', 'Приложение-аутентификатор отключено. До повторного подключения вход снова будет подтверждаться по email.');
        } else {
            flash('error', 'Не удалось отключить приложение-аутентификатор.');
        }
        admin_redirect('/admin/security.php');
    }
}

$config = admin_auth_config();
$enabled = !empty($config['totp_enabled']);
$setupSecret = (string)($_SESSION['totp_setup_secret'] ?? '');
$uri = $setupSecret !== '' ? admin_totp_uri($setupSecret, $adminEmail) : '';
$groupedSecret = $setupSecret !== '' ? trim(chunk_split($setupSecret, 4, ' ')) : '';
$remaining = is_array($config['recovery_hashes'] ?? null) ? count($config['recovery_hashes']) : 0;

admin_header('Безопасность', 'security');
?>
<div class="page-head security-page-head">
  <div class="security-title-wrap">
    <div class="security-title-icon" aria-hidden="true"><span></span></div>
    <div>
      <p class="requests-kicker">Защита администратора</p>
      <h1>Безопасность</h1>
      <p>Подключите приложение-аутентификатор. Поддерживаются Google Authenticator, Microsoft Authenticator, 2FAS, Aegis и другие приложения стандарта TOTP.</p>
    </div>
  </div>
</div>

<?php if ($recoveryCodes): ?>
<section class="panel recovery-panel">
  <div class="security-panel-head">
    <div>
      <span class="security-status-dot on"></span>
      <div><h2>Сохраните резервные коды</h2><p>Они показываются только сейчас. Каждый код можно использовать один раз, если телефон недоступен.</p></div>
    </div>
    <button class="toggle-btn" type="button" data-copy-recovery>Скопировать все</button>
  </div>
  <div class="recovery-grid" data-recovery-codes>
    <?php foreach ($recoveryCodes as $code): ?><code><?= e((string)$code) ?></code><?php endforeach; ?>
  </div>
  <p class="security-warning">Не храните эти коды на общедоступном устройстве и не отправляйте их другим людям.</p>
</section>
<?php endif; ?>

<div class="security-grid">
  <section class="panel security-main-panel">
    <div class="security-panel-head">
      <div>
        <span class="security-status-dot <?= $enabled ? 'on' : 'off' ?>"></span>
        <div>
          <h2>Приложение-аутентификатор</h2>
          <p><?= $enabled ? 'Подключено. При входе используется одноразовый код из приложения.' : 'Не подключено. Сейчас вход подтверждается кодом из письма.' ?></p>
        </div>
      </div>
      <span class="security-state <?= $enabled ? 'on' : 'off' ?>"><?= $enabled ? 'Активно' : 'Не подключено' ?></span>
    </div>

    <?php if (!$enabled && $setupSecret === ''): ?>
      <div class="auth-apps">
        <div><strong>G</strong><span>Google<br>Authenticator</span></div>
        <div><strong>M</strong><span>Microsoft<br>Authenticator</span></div>
        <div><strong>2F</strong><span>2FAS</span></div>
        <div><strong>A</strong><span>Aegis</span></div>
      </div>
      <p class="security-description">После подключения почта больше не понадобится для обычного входа. Приложение генерирует новый шестизначный код каждые 30 секунд.</p>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="begin_totp">
        <button class="btn" type="submit">Подключить аутентификатор</button>
      </form>
    <?php elseif (!$enabled): ?>
      <div class="totp-setup">
        <div class="totp-step"><span>01</span><div><strong>Добавьте аккаунт в приложении</strong><p>Нажмите кнопку на телефоне или выберите в приложении ручной ввод ключа.</p></div></div>
        <a class="btn secondary totp-open-app" href="<?= e($uri) ?>">Открыть в приложении</a>

        <div class="totp-secret-box">
          <small>Ключ настройки</small>
          <code><?= e($groupedSecret) ?></code>
          <button type="button" class="toggle-btn" data-copy-value="<?= e($setupSecret) ?>">Скопировать ключ</button>
        </div>

        <div class="totp-step"><span>02</span><div><strong>Подтвердите подключение</strong><p>Введите шестизначный код, который сейчас показывает приложение.</p></div></div>
        <form method="post" class="totp-confirm-form">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="confirm_totp">
          <div class="field"><label for="totp-code">Код из приложения</label><input id="totp-code" class="totp-code-input" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" placeholder="000000" required autofocus></div>
          <div class="form-actions"><button class="btn" type="submit">Подтвердить и включить</button></div>
        </form>
        <form method="post" class="security-cancel-form">
          <?= csrf_field() ?><input type="hidden" name="action" value="cancel_totp">
          <button class="toggle-btn" type="submit">Отменить подключение</button>
        </form>
      </div>
    <?php else: ?>
      <div class="security-active-summary">
        <div><small>Метод входа</small><strong>TOTP · 6 цифр</strong></div>
        <div><small>Резервные коды</small><strong><?= $remaining ?> из 8</strong></div>
        <div><small>Подключено</small><strong><?= !empty($config['enabled_at']) ? e(date('d.m.Y', strtotime((string)$config['enabled_at']))) : '—' ?></strong></div>
      </div>
      <div class="security-note"><span>✓</span><p>Ключ аутентификатора хранится на сервере в зашифрованном виде AES-256-GCM. При обычном входе email не используется.</p></div>
    <?php endif; ?>
  </section>

  <?php if ($enabled): ?>
  <aside class="panel security-side-panel">
    <div>
      <h2>Резервные коды</h2>
      <p>Создайте новый комплект, если старые коды потеряны или часть уже использована.</p>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="regenerate_recovery">
        <div class="field"><label>Код из приложения</label><input class="totp-code-input" name="code" inputmode="numeric" maxlength="6" pattern="[0-9]{6}" placeholder="000000" required></div>
        <button class="toggle-btn" type="submit">Создать новые коды</button>
      </form>
    </div>
    <div class="security-danger-zone">
      <h2>Отключить защиту</h2>
      <p>После отключения вход снова будет зависеть от доставки кода на административную почту.</p>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="disable_totp">
        <div class="field"><label>Код из приложения</label><input class="totp-code-input" name="code" inputmode="numeric" maxlength="6" pattern="[0-9]{6}" placeholder="000000" required></div>
        <button class="toggle-btn danger" type="submit" data-confirm="Отключить вход через приложение-аутентификатор?">Отключить аутентификатор</button>
      </form>
    </div>
  </aside>
  <?php endif; ?>
</div>
<?php admin_footer(); ?>
