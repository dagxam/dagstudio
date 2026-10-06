<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';
admin_require_auth();

$settings = site_settings();

$logoDefaults = [
    'logo_dark' => '/assets/img/logo-horizontal-dark.svg',
    'logo_light' => '/assets/img/logo-horizontal.svg',
    'logo_admin' => '/assets/img/logo-horizontal-dark.svg',
    'logo_mark' => '/assets/img/logo-mark-square.svg',
];

function settings_hex(string $value, string $fallback): string {
    $value = strtolower(trim($value));
    return preg_match('/^#[0-9a-f]{6}$/', $value) ? $value : $fallback;
}

function settings_custom_logo_path(string $path): ?string {
    if (!str_starts_with($path, '/uploads/branding/')) return null;
    $name = basename($path);
    if ($name === '' || $name !== substr($path, strlen('/uploads/branding/'))) return null;
    return DS_ROOT . '/uploads/branding/' . $name;
}

function settings_upload_logo(string $field, string $role, array &$newFiles, array &$errors): ?string {
    $file = $_FILES[$field] ?? null;
    if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;

    if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $errors[] = 'Не удалось загрузить файл «' . $role . '».';
        return null;
    }

    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > 5 * 1024 * 1024) {
        $errors[] = 'Логотип «' . $role . '» должен быть не больше 5 МБ.';
        return null;
    }

    $image = @getimagesize((string)$file['tmp_name']);
    $mime = is_array($image) ? (string)($image['mime'] ?? '') : '';
    $types = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];
    if (!isset($types[$mime])) {
        $errors[] = 'Для «' . $role . '» разрешены PNG, JPG и WebP.';
        return null;
    }

    $dir = DS_ROOT . '/uploads/branding';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        $errors[] = 'Не удалось создать каталог для логотипов.';
        return null;
    }

    $name = preg_replace('/[^a-z0-9_-]/i', '-', $field) . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(3)) . '.' . $types[$mime];
    $destination = $dir . '/' . $name;
    if (!move_uploaded_file((string)$file['tmp_name'], $destination)) {
        $errors[] = 'Не удалось сохранить логотип «' . $role . '» на сервере.';
        return null;
    }

    @chmod($destination, 0644);
    $newFiles[] = $destination;
    return '/uploads/branding/' . $name;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $next = $settings;
    $next['site_name'] = mb_substr(trim((string)($_POST['site_name'] ?? '')), 0, 80);
    $next['email'] = mb_substr(trim((string)($_POST['email'] ?? '')), 0, 120);
    $next['admin_email'] = mb_substr(trim((string)($_POST['admin_email'] ?? '')), 0, 120);
    $next['phone'] = mb_substr(trim((string)($_POST['phone'] ?? '')), 0, 60);
    $next['location'] = mb_substr(trim((string)($_POST['location'] ?? '')), 0, 250);
    $next['telegram'] = mb_substr(trim((string)($_POST['telegram'] ?? '')), 0, 250);
    $next['whatsapp'] = mb_substr(trim((string)($_POST['whatsapp'] ?? '')), 0, 250);
    $next['behance'] = mb_substr(trim((string)($_POST['behance'] ?? '')), 0, 250);

    $schemes = ['copper', 'gold', 'emerald', 'sapphire', 'crimson', 'custom'];
    $scheme = (string)($_POST['theme_scheme'] ?? 'copper');
    $next['theme_scheme'] = in_array($scheme, $schemes, true) ? $scheme : 'custom';
    $next['theme_accent'] = settings_hex((string)($_POST['theme_accent'] ?? ''), '#c96f41');
    $next['theme_bg'] = settings_hex((string)($_POST['theme_bg'] ?? ''), '#050505');
    $next['theme_panel'] = settings_hex((string)($_POST['theme_panel'] ?? ''), '#1c1c1c');
    $next['theme_text'] = settings_hex((string)($_POST['theme_text'] ?? ''), '#f7f7f5');

    $errors = [];
    if ($next['site_name'] === '') $errors[] = 'Укажите название сайта.';
    if (!filter_var($next['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Укажите корректный публичный email.';
    if (!filter_var($next['admin_email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Укажите корректный email администратора.';

    $newFiles = [];
    $oldFilesToDelete = [];
    $logoSlots = [
        'logo_dark' => 'логотип для тёмного фона',
        'logo_light' => 'логотип для светлого фона',
        'logo_admin' => 'логотип админки',
        'logo_mark' => 'знак / favicon',
    ];

    foreach ($logoSlots as $field => $label) {
        $current = setting_asset($settings, $field, $logoDefaults[$field]);

        if (!empty($_POST['reset_' . $field])) {
            $custom = settings_custom_logo_path($current);
            if ($custom !== null) $oldFilesToDelete[] = $custom;
            $next[$field] = $logoDefaults[$field];
            continue;
        }

        $uploaded = settings_upload_logo($field, $label, $newFiles, $errors);
        if ($uploaded !== null) {
            $custom = settings_custom_logo_path($current);
            if ($custom !== null) $oldFilesToDelete[] = $custom;
            $next[$field] = $uploaded;
        } else {
            $next[$field] = $current;
        }
    }

    if ($errors) {
        foreach ($newFiles as $path) @unlink($path);
        flash('error', implode(' ', $errors));
    } elseif (storage_write_json('settings.json', $next)) {
        foreach ($oldFilesToDelete as $path) @unlink($path);
        audit_log('settings.updated', ['section' => 'site-theme-branding']);
        flash('success', 'Настройки сохранены.');
    } else {
        foreach ($newFiles as $path) @unlink($path);
        flash('error', 'Не удалось сохранить настройки. Проверьте права записи каталога storage.');
    }

    admin_redirect('/admin/settings.php');
}

$settings = site_settings();
$logoSlots = [
    'logo_dark' => ['title' => 'Главная · тёмный фон', 'hint' => 'Шапка, футер и другие тёмные блоки сайта.', 'fallback' => $logoDefaults['logo_dark'], 'light' => false],
    'logo_light' => ['title' => 'Светлый фон', 'hint' => 'Версия логотипа для будущих светлых блоков и страниц.', 'fallback' => $logoDefaults['logo_light'], 'light' => true],
    'logo_admin' => ['title' => 'Админка', 'hint' => 'Боковое меню и экран входа в панель управления.', 'fallback' => $logoDefaults['logo_admin'], 'light' => false],
    'logo_mark' => ['title' => 'Знак / favicon', 'hint' => 'Квадратный знак для favicon, PWA и декоративных блоков.', 'fallback' => $logoDefaults['logo_mark'], 'light' => false],
];

admin_header('Настройки', 'settings');
?>
<div class="page-head settings-page-head">
  <div>
    <h1>Настройки сайта</h1>
    <p>Разделы можно раскрывать и сворачивать. Здесь управляются данные сайта, контакты, цветовая схема и фирменные логотипы.</p>
  </div>
</div>

<form class="settings-form" method="post" enctype="multipart/form-data">
  <?= csrf_field() ?>

  <div class="settings-stack">
    <details class="settings-section" data-settings-section="general" open>
      <summary>
        <span class="settings-index">01</span>
        <span class="settings-summary-copy"><strong>Основные настройки</strong><small>Название сайта и административный доступ</small></span>
        <span class="settings-chevron">⌄</span>
      </summary>
      <div class="settings-section-body">
        <div class="form-grid">
          <div class="field"><label for="site_name">Название</label><input id="site_name" name="site_name" value="<?= e((string)$settings['site_name']) ?>" required></div>
          <div class="field"><label for="admin_email">Email администратора для входа</label><input id="admin_email" type="email" name="admin_email" value="<?= e((string)$settings['admin_email']) ?>" required></div>
        </div>
      </div>
    </details>

    <details class="settings-section" data-settings-section="contacts">
      <summary>
        <span class="settings-index">02</span>
        <span class="settings-summary-copy"><strong>Контакты</strong><small>Телефон, email, адрес и социальные сети</small></span>
        <span class="settings-chevron">⌄</span>
      </summary>
      <div class="settings-section-body">
        <div class="form-grid">
          <div class="field"><label for="email">Публичный email</label><input id="email" type="email" name="email" value="<?= e((string)$settings['email']) ?>" required></div>
          <div class="field"><label for="phone">Телефон</label><input id="phone" name="phone" value="<?= e((string)$settings['phone']) ?>"></div>
          <div class="field"><label for="telegram">Telegram — ссылка</label><input id="telegram" name="telegram" value="<?= e((string)$settings['telegram']) ?>" placeholder="https://t.me/..."></div>
          <div class="field"><label for="whatsapp">WhatsApp — ссылка</label><input id="whatsapp" name="whatsapp" value="<?= e((string)$settings['whatsapp']) ?>" placeholder="https://wa.me/..."></div>
          <div class="field"><label for="behance">Behance — ссылка</label><input id="behance" name="behance" value="<?= e((string)$settings['behance']) ?>" placeholder="https://behance.net/..."></div>
          <div class="field full"><label for="location">Адрес / местоположение</label><textarea id="location" name="location"><?= e((string)$settings['location']) ?></textarea></div>
        </div>
      </div>
    </details>

    <details class="settings-section" data-settings-section="colors">
      <summary>
        <span class="settings-index">03</span>
        <span class="settings-summary-copy"><strong>Цветовые схемы</strong><small>Готовые варианты и точная ручная настройка цветов</small></span>
        <span class="settings-chevron">⌄</span>
      </summary>
      <div class="settings-section-body">
        <div class="scheme-grid">
          <?php
          $schemes = [
              'copper' => ['Медь', '#c96f41', '#050505', '#1c1c1c', '#f7f7f5'],
              'gold' => ['Золото', '#c89b4b', '#050505', '#1b1811', '#f5f1e8'],
              'emerald' => ['Изумруд', '#3f9b78', '#050706', '#111a17', '#eef7f2'],
              'sapphire' => ['Сапфир', '#477fc7', '#050608', '#111722', '#f0f4fa'],
              'crimson' => ['Бордо', '#b74c4c', '#070505', '#1c1212', '#f8f0f0'],
          ];
          foreach ($schemes as $id => $scheme):
          ?>
            <label class="scheme-card" data-theme-preset data-accent="<?= e($scheme[1]) ?>" data-bg="<?= e($scheme[2]) ?>" data-panel="<?= e($scheme[3]) ?>" data-text="<?= e($scheme[4]) ?>">
              <input type="radio" name="theme_scheme" value="<?= e($id) ?>" <?= ($settings['theme_scheme'] ?? 'copper') === $id ? 'checked' : '' ?>>
              <span class="scheme-preview" style="--scheme-accent:<?= e($scheme[1]) ?>;--scheme-bg:<?= e($scheme[2]) ?>;--scheme-panel:<?= e($scheme[3]) ?>;--scheme-text:<?= e($scheme[4]) ?>"><i></i><b></b><em></em></span>
              <strong><?= e($scheme[0]) ?></strong>
            </label>
          <?php endforeach; ?>
          <label class="scheme-card custom-scheme">
            <input type="radio" name="theme_scheme" value="custom" <?= ($settings['theme_scheme'] ?? '') === 'custom' ? 'checked' : '' ?>>
            <span class="scheme-custom-icon">＋</span>
            <strong>Своя схема</strong>
          </label>
        </div>

        <div class="color-fields">
          <label class="color-field"><span>Акцент</span><div><input type="color" id="theme_accent_picker" value="<?= e(setting_color($settings, 'theme_accent', '#c96f41')) ?>"><input data-theme-color id="theme_accent" name="theme_accent" value="<?= e(setting_color($settings, 'theme_accent', '#c96f41')) ?>"></div></label>
          <label class="color-field"><span>Основной фон</span><div><input type="color" id="theme_bg_picker" value="<?= e(setting_color($settings, 'theme_bg', '#050505')) ?>"><input data-theme-color id="theme_bg" name="theme_bg" value="<?= e(setting_color($settings, 'theme_bg', '#050505')) ?>"></div></label>
          <label class="color-field"><span>Карточки / панели</span><div><input type="color" id="theme_panel_picker" value="<?= e(setting_color($settings, 'theme_panel', '#1c1c1c')) ?>"><input data-theme-color id="theme_panel" name="theme_panel" value="<?= e(setting_color($settings, 'theme_panel', '#1c1c1c')) ?>"></div></label>
          <label class="color-field"><span>Основной текст</span><div><input type="color" id="theme_text_picker" value="<?= e(setting_color($settings, 'theme_text', '#f7f7f5')) ?>"><input data-theme-color id="theme_text" name="theme_text" value="<?= e(setting_color($settings, 'theme_text', '#f7f7f5')) ?>"></div></label>
        </div>
        <p class="settings-note">Цветовая схема применяется к публичному сайту и панели управления. Выбранные цвета можно дополнительно подправить вручную.</p>
      </div>
    </details>

    <details class="settings-section" data-settings-section="logos">
      <summary>
        <span class="settings-index">04</span>
        <span class="settings-summary-copy"><strong>Логотипы</strong><small>Главная страница, светлая версия, админка и favicon</small></span>
        <span class="settings-chevron">⌄</span>
      </summary>
      <div class="settings-section-body">
        <div class="logo-settings-grid">
          <?php foreach ($logoSlots as $field => $slot):
            $src = setting_asset($settings, $field, $slot['fallback']);
          ?>
            <article class="logo-setting-card">
              <div class="logo-preview <?= $slot['light'] ? 'is-light' : '' ?>">
                <img id="preview_<?= e($field) ?>" src="<?= e($src) ?>" alt="<?= e($slot['title']) ?>">
              </div>
              <div class="logo-setting-copy">
                <strong><?= e($slot['title']) ?></strong>
                <p><?= e($slot['hint']) ?></p>
              </div>
              <label class="logo-upload-btn">
                <input type="file" name="<?= e($field) ?>" accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp" data-logo-input data-preview="preview_<?= e($field) ?>">
                <span>Выбрать файл</span>
              </label>
              <label class="logo-reset"><input type="checkbox" name="reset_<?= e($field) ?>" value="1"><span>Вернуть стандартный</span></label>
            </article>
          <?php endforeach; ?>
        </div>
        <p class="settings-note">Для загружаемых логотипов используйте PNG, JPG или WebP до 5 МБ. Прозрачный PNG/WebP лучше всего подходит для тёмного дизайна.</p>
      </div>
    </details>
  </div>

  <div class="settings-savebar">
    <div><strong>Настройки DAG STUDIO</strong><span>Изменения применятся сразу после сохранения.</span></div>
    <button class="btn" type="submit">Сохранить настройки</button>
  </div>
</form>
<?php admin_footer(); ?>
