<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';
admin_require_auth();

$settings = site_settings();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $clean = [
        'site_name' => mb_substr(trim((string)($_POST['site_name'] ?? '')), 0, 80),
        'email' => mb_substr(trim((string)($_POST['email'] ?? '')), 0, 120),
        'phone' => mb_substr(trim((string)($_POST['phone'] ?? '')), 0, 60),
        'location' => mb_substr(trim((string)($_POST['location'] ?? '')), 0, 250),
        'telegram' => mb_substr(trim((string)($_POST['telegram'] ?? '')), 0, 250),
        'whatsapp' => mb_substr(trim((string)($_POST['whatsapp'] ?? '')), 0, 250),
        'behance' => mb_substr(trim((string)($_POST['behance'] ?? '')), 0, 250),
    ];

    if ($clean['site_name'] === '' || !filter_var($clean['email'], FILTER_VALIDATE_EMAIL)) {
        flash('error', 'Укажите название сайта и корректный email.');
    } elseif (storage_write_json('settings.json', $clean)) {
        audit_log('settings.updated');
        flash('success', 'Настройки сохранены.');
    } else {
        flash('error', 'Не удалось сохранить настройки. Проверьте права записи каталога storage.');
    }
    admin_redirect('/admin/settings.php');
}

admin_header('Настройки', 'settings');
?>
<div class="page-head">
  <div>
    <h1>Настройки сайта</h1>
    <p>Контактные данные используются публичным сайтом, формой заявки и административной панелью.</p>
  </div>
</div>

<section class="panel form-panel">
  <form method="post">
    <?= csrf_field() ?>
    <div class="form-grid">
      <div class="field"><label for="site_name">Название</label><input id="site_name" name="site_name" value="<?= e($settings['site_name']) ?>" required></div>
      <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" value="<?= e($settings['email']) ?>" required></div>
      <div class="field"><label for="phone">Телефон</label><input id="phone" name="phone" value="<?= e($settings['phone']) ?>"></div>
      <div class="field"><label for="telegram">Telegram — ссылка</label><input id="telegram" name="telegram" value="<?= e($settings['telegram']) ?>" placeholder="https://t.me/..."></div>
      <div class="field"><label for="whatsapp">WhatsApp — ссылка</label><input id="whatsapp" name="whatsapp" value="<?= e($settings['whatsapp']) ?>" placeholder="https://wa.me/..."></div>
      <div class="field"><label for="behance">Behance — ссылка</label><input id="behance" name="behance" value="<?= e($settings['behance']) ?>" placeholder="https://behance.net/..."></div>
      <div class="field full"><label for="location">Адрес / местоположение</label><textarea id="location" name="location"><?= e($settings['location']) ?></textarea></div>
    </div>
    <div class="form-actions"><button class="btn" type="submit">Сохранить настройки</button></div>
  </form>
</section>
<?php admin_footer(); ?>