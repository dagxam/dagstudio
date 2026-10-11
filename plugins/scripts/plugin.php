<?php
declare(strict_types=1);

if (!defined('DS_ROOT')) {
    http_response_code(404);
    exit;
}

require_once DS_ROOT . '/includes/scripts-data.php';

if (!function_exists('ds_script_clean')) {
    function ds_script_clean(string $value, int $max): string {
        return mb_substr(trim(strip_tags($value)), 0, $max);
    }

    function ds_script_slug(string $value): string {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9а-яё_-]+/ui', '-', $value) ?? '';
        $value = trim($value, '-_');
        return mb_substr($value !== '' ? $value : ('script-' . time()), 0, 90);
    }

    function ds_script_upload_archive(array $file, string $id): array {
        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) return ['ok' => true, 'file' => '', 'name' => '', 'size' => 0, 'error' => ''];
        if ($error !== UPLOAD_ERR_OK) return ['ok' => false, 'error' => 'Ошибка загрузки архива.'];

        $size = (int)($file['size'] ?? 0);
        if ($size <= 0 || $size > 104857600) return ['ok' => false, 'error' => 'Размер архива должен быть не более 100 МБ.'];

        $original = basename((string)($file['name'] ?? 'archive.zip'));
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $allowed = ['zip', 'rar', '7z', 'gz', 'tgz'];
        if (!in_array($ext, $allowed, true)) return ['ok' => false, 'error' => 'Разрешены архивы ZIP, RAR, 7Z, GZ и TGZ.'];

        $tmp = (string)($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) return ['ok' => false, 'error' => 'Не удалось проверить загруженный файл.'];

        $head = @file_get_contents($tmp, false, null, 0, 12);
        if (!is_string($head)) return ['ok' => false, 'error' => 'Не удалось прочитать архив.'];
        $magicOk = match ($ext) {
            'zip' => str_starts_with($head, "PK\x03\x04") || str_starts_with($head, "PK\x05\x06") || str_starts_with($head, "PK\x07\x08"),
            'rar' => str_starts_with($head, "Rar!\x1A\x07"),
            '7z' => str_starts_with($head, "7z\xBC\xAF\x27\x1C"),
            'gz', 'tgz' => str_starts_with($head, "\x1F\x8B"),
            default => false,
        };
        if (!$magicOk) return ['ok' => false, 'error' => 'Содержимое файла не соответствует типу архива.'];

        $dir = DS_ROOT . '/uploads/scripts';
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) return ['ok' => false, 'error' => 'Не удалось создать каталог для архивов.'];
        $guard = $dir . '/.htaccess';
        if (!is_file($guard)) {
            @file_put_contents($guard, "Options -Indexes\n<FilesMatch \"\\.(php|phtml|phar|cgi|pl|py|sh)$\">\n  Require all denied\n</FilesMatch>\n<IfModule mod_php.c>\n  php_flag engine off\n</IfModule>\n", LOCK_EX);
            @chmod($guard, 0640);
        }

        $safeId = preg_replace('/[^a-z0-9_-]/i', '', $id) ?: 'script';
        $stored = $safeId . '-' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
        $target = $dir . '/' . $stored;
        if (!@move_uploaded_file($tmp, $target)) return ['ok' => false, 'error' => 'Не удалось сохранить архив на сервере.'];
        @chmod($target, 0640);

        return [
            'ok' => true,
            'file' => '/uploads/scripts/' . $stored,
            'name' => $original,
            'size' => $size,
            'error' => '',
        ];
    }

    function ds_script_delete_upload(string $file): void {
        if (!str_starts_with($file, '/uploads/scripts/')) return;
        $path = ds_script_file_path(DS_ROOT, $file);
        if ($path !== '') @unlink($path);
    }
}

return [
    'id' => 'scripts',
    'name' => 'Скрипты',
    'menu' => 'Скрипты',
    'icon' => '⌘',
    'version' => '1.1.0',
    'author' => 'DAG STUDIO',
    'default_enabled' => true,
    'custom_page_head' => true,
    'description' => 'Каталог готовых скриптов, модулей, тем и инструментов с загрузкой архивов.',
    'handle' => static function (): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
        csrf_verify();

        $items = ds_scripts_read(DS_ROOT);
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'save') {
            $existingId = preg_replace('/[^a-z0-9_-]/i', '', (string)($_POST['id'] ?? ''));
            $existing = $existingId !== '' ? ds_script_find($items, $existingId) : null;

            $title = ds_script_clean((string)($_POST['title'] ?? ''), 160);
            $category = ds_script_clean((string)($_POST['category'] ?? ''), 80);
            $compatibility = ds_script_clean((string)($_POST['compatibility'] ?? ''), 100);
            $version = ds_script_clean((string)($_POST['version'] ?? ''), 40);
            $description = ds_script_clean((string)($_POST['description'] ?? ''), 900);
            $price = ds_script_price_value($_POST['price'] ?? 0);
            $paymentRaw = trim((string)($_POST['payment_url'] ?? ''));
            $paymentUrl = ds_script_payment_url($paymentRaw);

            if ($title === '') {
                flash('error', 'Укажите название скрипта.');
                admin_redirect('/admin/plugin.php?id=scripts' . ($existingId !== '' ? '&edit=' . rawurlencode($existingId) : '&new=1'));
            }
            if ($paymentRaw !== '' && $paymentUrl === '') {
                flash('error', 'Ссылка оплаты должна быть официальной HTTPS-ссылкой банка, СБП или платёжной страницы.');
                admin_redirect('/admin/plugin.php?id=scripts' . ($existingId !== '' ? '&edit=' . rawurlencode($existingId) : '&new=1'));
            }
            if ($price > 0 && $paymentUrl === '') {
                flash('error', 'Для платного скрипта укажите ссылку оплаты.');
                admin_redirect('/admin/plugin.php?id=scripts' . ($existingId !== '' ? '&edit=' . rawurlencode($existingId) : '&new=1'));
            }

            $id = is_array($existing) ? (string)$existing['id'] : ds_script_slug($title);
            if (!is_array($existing)) {
                $base = $id;
                $suffix = 2;
                while (ds_script_find($items, $id)) $id = $base . '-' . $suffix++;
            }

            $upload = ds_script_upload_archive(is_array($_FILES['archive'] ?? null) ? $_FILES['archive'] : [], $id);
            if (empty($upload['ok'])) {
                flash('error', (string)($upload['error'] ?? 'Не удалось загрузить архив.'));
                admin_redirect('/admin/plugin.php?id=scripts' . ($existingId !== '' ? '&edit=' . rawurlencode($existingId) : '&new=1'));
            }

            $file = is_array($existing) ? (string)($existing['file'] ?? '') : '';
            $originalName = is_array($existing) ? (string)($existing['original_name'] ?? '') : '';
            $size = is_array($existing) ? (int)($existing['size'] ?? 0) : 0;

            if ((string)($upload['file'] ?? '') !== '') {
                if (is_array($existing)) ds_script_delete_upload($file);
                $file = (string)$upload['file'];
                $originalName = (string)$upload['name'];
                $size = (int)$upload['size'];
            }

            if ($file === '') {
                flash('error', 'Для нового скрипта загрузите архив.');
                admin_redirect('/admin/plugin.php?id=scripts&new=1');
            }

            $row = [
                'id' => $id,
                'title' => $title,
                'category' => $category,
                'compatibility' => $compatibility,
                'version' => $version,
                'description' => $description,
                'file' => $file,
                'original_name' => $originalName,
                'size' => $size,
                'price' => $price,
                'payment_url' => $paymentUrl,
                'published' => !empty($_POST['published']),
                'featured' => !empty($_POST['featured']),
                'created_at' => is_array($existing) ? (string)($existing['created_at'] ?? gmdate('c')) : gmdate('c'),
                'updated_at' => gmdate('c'),
            ];

            if (is_array($existing)) {
                foreach ($items as $key => $item) {
                    if (is_array($item) && (string)($item['id'] ?? '') === $id) {
                        $items[$key] = $row;
                        break;
                    }
                }
                audit_log('scripts.updated', ['id' => $id]);
            } else {
                array_unshift($items, $row);
                audit_log('scripts.created', ['id' => $id]);
            }

            if (ds_scripts_save(DS_ROOT, $items)) {
                flash('success', is_array($existing) ? 'Скрипт обновлён.' : 'Скрипт добавлен.');
                admin_redirect('/admin/plugin.php?id=scripts');
            }

            flash('error', 'Не удалось сохранить каталог скриптов.');
            admin_redirect('/admin/plugin.php?id=scripts');
        }

        $id = preg_replace('/[^a-z0-9_-]/i', '', (string)($_POST['id'] ?? ''));
        if ($id === '') admin_redirect('/admin/plugin.php?id=scripts');

        if ($action === 'toggle') {
            foreach ($items as &$item) {
                if (is_array($item) && (string)($item['id'] ?? '') === $id) {
                    $item['published'] = empty($item['published']);
                    $item['updated_at'] = gmdate('c');
                    audit_log('scripts.visibility', ['id' => $id, 'published' => !empty($item['published'])]);
                    break;
                }
            }
            unset($item);
            ds_scripts_save(DS_ROOT, $items);
            admin_redirect('/admin/plugin.php?id=scripts');
        }

        if ($action === 'delete') {
            foreach ($items as $key => $item) {
                if (!is_array($item) || (string)($item['id'] ?? '') !== $id) continue;
                ds_script_delete_upload((string)($item['file'] ?? ''));
                unset($items[$key]);
                audit_log('scripts.deleted', ['id' => $id]);
                break;
            }
            ds_scripts_save(DS_ROOT, array_values($items));
            flash('success', 'Скрипт удалён из каталога.');
            admin_redirect('/admin/plugin.php?id=scripts');
        }
    },
    'render' => static function (): void {
        $items = ds_scripts_read(DS_ROOT);
        $editId = preg_replace('/[^a-z0-9_-]/i', '', (string)($_GET['edit'] ?? ''));
        $edit = $editId !== '' ? ds_script_find($items, $editId) : null;
        $isNew = isset($_GET['new']);
        $published = count(array_filter($items, static fn($item) => !empty($item['published'])));
        $featured = count(array_filter($items, static fn($item) => !empty($item['featured'])));

        if ($isNew || is_array($edit)):
            $item = is_array($edit) ? $edit : [
                'id' => '', 'title' => '', 'category' => '', 'compatibility' => '', 'version' => '',
                'description' => '', 'file' => '', 'original_name' => '', 'size' => 0,
                'price' => 0, 'payment_url' => '',
                'published' => true, 'featured' => false,
            ];
        ?>
          <div class="page-head scripts-admin-head">
            <div class="scripts-admin-title-wrap">
              <div class="scripts-admin-title-icon" aria-hidden="true"><i></i><i></i><i></i></div>
              <div><p class="requests-kicker">Каталог DAG STUDIO</p><h1><?= is_array($edit) ? 'Редактирование скрипта' : 'Новый скрипт' ?></h1><p>Добавьте архив и описание. Архивы хранятся без распаковки и выдаются только как скачиваемые файлы.</p></div>
            </div>
            <a class="works-back-btn" href="/admin/plugin.php?id=scripts"><span></span>К списку</a>
          </div>

          <section class="panel scripts-editor-panel">
            <form method="post" enctype="multipart/form-data" class="scripts-editor-form">
              <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= e((string)$item['id']) ?>">
              <div class="form-grid">
                <div class="field"><label>Название</label><input name="title" maxlength="160" value="<?= e((string)$item['title']) ?>" required></div>
                <div class="field"><label>Категория</label><input name="category" maxlength="80" value="<?= e((string)$item['category']) ?>" placeholder="WordPress, DLE, доступность..."></div>
                <div class="field"><label>Совместимость</label><input name="compatibility" maxlength="100" value="<?= e((string)$item['compatibility']) ?>" placeholder="WordPress / DLE / Web"></div>
                <div class="field"><label>Версия</label><input name="version" maxlength="40" value="<?= e((string)$item['version']) ?>" placeholder="1.0"></div>
                <div class="field"><label>Цена, ₽</label><input name="price" inputmode="decimal" maxlength="15" value="<?= e((string)($item['price'] ?? 0)) ?>" placeholder="0 = бесплатно"><small>0 — бесплатное скачивание. Для платного скрипта архив не выдаётся напрямую.</small></div>
                <div class="field"><label>Ссылка оплаты</label><input name="payment_url" type="url" maxlength="1000" value="<?= e((string)($item['payment_url'] ?? '')) ?>" placeholder="https://..."><small>Официальная HTTPS-ссылка СберБанка, СБП или другого банка. На телефоне банк может открыть своё приложение через universal link.</small></div>
                <div class="field full"><label>Описание</label><textarea name="description" maxlength="900" rows="5"><?= e((string)$item['description']) ?></textarea></div>
                <div class="field full">
                  <label><?= is_array($edit) ? 'Заменить архив' : 'Архив' ?></label>
                  <label class="script-upload-zone">
                    <input type="file" name="archive" accept=".zip,.rar,.7z,.gz,.tgz" <?= is_array($edit) ? '' : 'required' ?>>
                    <span class="script-upload-icon"></span>
                    <span><strong><?= is_array($edit) && !empty($item['original_name']) ? e((string)$item['original_name']) : 'Выберите архив' ?></strong><small>ZIP · RAR · 7Z · GZ · TGZ · до 100 МБ</small></span>
                    <b>Выбрать</b>
                  </label>
                </div>
              </div>
              <div class="script-options">
                <label class="audio-option-card"><input type="checkbox" name="published" value="1" <?= !empty($item['published']) ? 'checked' : '' ?>><span class="audio-option-toggle"></span><span><strong>Опубликован</strong><small>Показывать на странице «Скрипты»</small></span></label>
                <label class="audio-option-card"><input type="checkbox" name="featured" value="1" <?= !empty($item['featured']) ? 'checked' : '' ?>><span class="audio-option-toggle"></span><span><strong>Рекомендуемый</strong><small>Выделить в каталоге</small></span></label>
              </div>
              <div class="form-actions"><button class="btn" type="submit"><?= is_array($edit) ? 'Сохранить изменения' : 'Добавить скрипт' ?></button><a class="btn secondary" href="/admin/plugin.php?id=scripts">Отмена</a></div>
            </form>
          </section>
        <?php else: ?>
          <div class="page-head scripts-admin-head">
            <div class="scripts-admin-title-wrap">
              <div class="scripts-admin-title-icon" aria-hidden="true"><i></i><i></i><i></i></div>
              <div><p class="requests-kicker">Каталог DAG STUDIO</p><h1>Скрипты</h1><p>Управляйте готовыми модулями, темами и инструментами, которые доступны посетителям сайта.</p></div>
            </div>
          </div>

          <div class="scripts-admin-summary">
            <div class="works-summary-card total"><span class="scripts-summary-icon total"></span><div><strong><?= count($items) ?></strong><small>Всего</small></div></div>
            <div class="works-summary-card published"><span class="scripts-summary-icon published"></span><div><strong><?= $published ?></strong><small>Опубликовано</small></div></div>
            <div class="works-summary-card"><span class="scripts-summary-icon featured"></span><div><strong><?= $featured ?></strong><small>Рекомендуемых</small></div></div>
            <div class="works-summary-actions"><a class="btn secondary" href="/scripts.php" target="_blank" rel="noopener">Открыть страницу</a><a class="btn works-add-btn" href="/admin/plugin.php?id=scripts&new=1"><span></span>Добавить скрипт</a></div>
          </div>

          <section class="panel scripts-admin-panel">
            <div class="panel-title-row"><div><h2>Каталог</h2><span>Файлы доступны через защищённую выдачу</span></div><span class="works-panel-count"><?= count($items) ?> шт.</span></div>
            <?php if (!$items): ?><div class="empty">Скриптов пока нет.</div><?php endif; ?>
            <div class="scripts-admin-list">
              <?php foreach ($items as $index => $item): ?>
                <article class="script-admin-card <?= !empty($item['published']) ? 'is-published' : 'is-hidden' ?>">
                  <div class="script-admin-index"><?= str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT) ?></div>
                  <div class="script-admin-main">
                    <div class="script-admin-headline"><div><strong><?= e((string)$item['title']) ?></strong><span><?= e((string)$item['category']) ?></span></div><span class="work-status-pill <?= !empty($item['published']) ? 'published' : 'hidden' ?>"><i></i><?= !empty($item['published']) ? 'На сайте' : 'Скрыто' ?></span></div>
                    <p><?= e((string)$item['description']) ?></p>
                    <div class="script-admin-meta"><span><?= e((string)$item['compatibility']) ?></span><span>v<?= e((string)$item['version']) ?></span><span><?= e(ds_script_size_label((int)$item['size'])) ?></span><span class="script-admin-price"><?= e(ds_script_price_label($item['price'] ?? 0)) ?></span><span><?= e((string)$item['original_name']) ?></span></div>
                  </div>
                  <div class="script-admin-actions">
                    <a class="work-action-btn edit" href="/admin/plugin.php?id=scripts&edit=<?= e((string)$item['id']) ?>"><span></span>Редактировать</a>
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= e((string)$item['id']) ?>"><button class="work-action-btn visibility <?= !empty($item['published']) ? 'hide' : 'show' ?>" type="submit"><span></span><?= !empty($item['published']) ? 'Скрыть' : 'Показать' ?></button></form>
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e((string)$item['id']) ?>"><button class="audio-admin-delete" type="submit" data-confirm="Удалить этот скрипт из каталога?" title="Удалить"><span></span></button></form>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endif;
    },
];
