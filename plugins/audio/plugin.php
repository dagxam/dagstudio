<?php
declare(strict_types=1);

if (!defined('DS_ROOT')) {
    http_response_code(404);
    exit;
}

if (!function_exists('ds_audio_dir')) {
    function ds_audio_dir(): string {
        $dir = DS_ROOT . '/uploads/audio';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        return $dir;
    }

    function ds_audio_clean(string $value, int $max): string {
        return mb_substr(trim(strip_tags($value)), 0, $max);
    }

    function ds_audio_items(): array {
        $data = storage_read_json('audio.json', ['items' => []]);
        return is_array($data['items'] ?? null) ? $data['items'] : [];
    }

    function ds_audio_save(array $items): bool {
        return storage_write_json('audio.json', ['items' => array_values($items)]);
    }

    function ds_audio_ini_bytes(string $value): int {
        $value = trim($value);
        if ($value === '') return 0;
        $last = strtolower(substr($value, -1));
        $number = (float)$value;
        if ($last === 'g') $number *= 1024;
        if ($last === 'm' || $last === 'g') $number *= 1024;
        if ($last === 'k' || $last === 'm' || $last === 'g') $number *= 1024;
        return (int)$number;
    }

    function ds_audio_max_bytes(): int {
        $upload = ds_audio_ini_bytes((string)ini_get('upload_max_filesize'));
        $post = ds_audio_ini_bytes((string)ini_get('post_max_size'));
        $server = $upload > 0 && $post > 0 ? min($upload, $post) : max($upload, $post);
        return $server > 0 ? min($server, 100 * 1024 * 1024) : 100 * 1024 * 1024;
    }

    function ds_audio_find(array $items, string $id): ?array {
        foreach ($items as $item) {
            if (is_array($item) && (string)($item['id'] ?? '') === $id) return $item;
        }
        return null;
    }
}

return [
    'id' => 'audio',
    'name' => 'Аудио',
    'menu' => 'Аудио',
    'icon' => '♫',
    'version' => '1.2.0',
    'author' => 'DAG STUDIO',
    'default_enabled' => true,
    'custom_page_head' => true,
    'description' => 'Загрузка и публикация аудио, редактирование метаданных, отметка музыки и управление кнопкой скачивания.',
    'handle' => static function (): void {
        ds_audio_dir();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

        if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            flash('error', 'Файл превышает допустимый размер сервера.');
            admin_redirect('/admin/plugin.php?id=audio');
        }

        csrf_verify();
        $action = (string)($_POST['action'] ?? '');
        $items = ds_audio_items();

        if ($action === 'upload') {
            $file = $_FILES['audio'] ?? null;
            if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                flash('error', 'Не удалось получить аудиофайл. Проверьте размер и повторите загрузку.');
                admin_redirect('/admin/plugin.php?id=audio');
            }

            $size = (int)($file['size'] ?? 0);
            if ($size <= 0 || $size > ds_audio_max_bytes()) {
                flash('error', 'Размер аудио превышает разрешённый лимит.');
                admin_redirect('/admin/plugin.php?id=audio');
            }

            $originalName = (string)($file['name'] ?? 'audio');
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $allowedExt = ['mp3', 'm4a', 'wav', 'ogg', 'flac', 'aac'];
            if (!in_array($ext, $allowedExt, true)) {
                flash('error', 'Поддерживаются MP3, M4A, WAV, OGG, FLAC и AAC.');
                admin_redirect('/admin/plugin.php?id=audio');
            }

            $mime = 'application/octet-stream';
            if (class_exists('finfo')) {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $detected = $finfo->file((string)$file['tmp_name']);
                if (is_string($detected) && $detected !== '') $mime = $detected;
            }
            $mimeAllowed = str_starts_with($mime, 'audio/')
                || in_array($mime, ['application/ogg', 'video/mp4', 'application/octet-stream'], true);
            if (!$mimeAllowed) {
                flash('error', 'Файл не распознан как аудио.');
                admin_redirect('/admin/plugin.php?id=audio');
            }

            $title = ds_audio_clean((string)($_POST['title'] ?? ''), 160);
            if ($title === '') $title = ds_audio_clean(pathinfo($originalName, PATHINFO_FILENAME), 160);
            $id = date('YmdHis') . '-' . bin2hex(random_bytes(4));
            $stored = $id . '.' . $ext;
            $destination = ds_audio_dir() . '/' . $stored;

            if (!move_uploaded_file((string)$file['tmp_name'], $destination)) {
                flash('error', 'Не удалось сохранить файл на сервере. Проверьте права каталога uploads/audio.');
                admin_redirect('/admin/plugin.php?id=audio');
            }
            @chmod($destination, 0644);

            array_unshift($items, [
                'id' => $id,
                'title' => $title,
                'artist' => ds_audio_clean((string)($_POST['artist'] ?? ''), 120),
                'description' => ds_audio_clean((string)($_POST['description'] ?? ''), 700),
                'file' => $stored,
                'original_name' => $originalName,
                'mime' => $mime,
                'size' => $size,
                'published' => !empty($_POST['published']),
                'contains_music' => !empty($_POST['contains_music']),
                'allow_download' => !empty($_POST['allow_download']),
                'uploaded_at' => gmdate('c'),
            ]);

            if (!ds_audio_save($items)) {
                @unlink($destination);
                flash('error', 'Файл загружен, но данные аудио сохранить не удалось.');
                admin_redirect('/admin/plugin.php?id=audio');
            }

            audit_log('audio.uploaded', ['id' => $id, 'title' => $title]);
            flash('success', 'Аудио загружено.');
            admin_redirect('/admin/plugin.php?id=audio');
        }

        $id = preg_replace('/[^a-z0-9-]/i', '', (string)($_POST['id'] ?? ''));
        if ($id === '') {
            flash('error', 'Аудиозапись не выбрана.');
            admin_redirect('/admin/plugin.php?id=audio');
        }

        if ($action === 'update') {
            $found = false;
            foreach ($items as &$item) {
                if (!is_array($item) || (string)($item['id'] ?? '') !== $id) continue;
                $found = true;
                $title = ds_audio_clean((string)($_POST['title'] ?? ''), 160);
                if ($title === '') $title = (string)($item['title'] ?? 'Без названия');
                $item['title'] = $title;
                $item['artist'] = ds_audio_clean((string)($_POST['artist'] ?? ''), 120);
                $item['description'] = ds_audio_clean((string)($_POST['description'] ?? ''), 700);
                $item['published'] = !empty($_POST['published']);
                $item['contains_music'] = !empty($_POST['contains_music']);
                $item['allow_download'] = !empty($_POST['allow_download']);
                $item['updated_at'] = gmdate('c');
                break;
            }
            unset($item);

            if (!$found) {
                flash('error', 'Аудио не найдено.');
            } elseif (ds_audio_save($items)) {
                audit_log('audio.updated', ['id' => $id]);
                flash('success', 'Аудио обновлено.');
            } else {
                flash('error', 'Не удалось сохранить изменения.');
            }
            admin_redirect('/admin/plugin.php?id=audio');
        }

        if ($action === 'toggle' || $action === 'delete') {
            $found = false;
            foreach ($items as $key => &$item) {
                if (!is_array($item) || (string)($item['id'] ?? '') !== $id) continue;
                $found = true;
                if ($action === 'toggle') {
                    $item['published'] = empty($item['published']);
                    audit_log(!empty($item['published']) ? 'audio.published' : 'audio.hidden', ['id' => $id]);
                } else {
                    $fileName = basename((string)($item['file'] ?? ''));
                    if ($fileName !== '') @unlink(ds_audio_dir() . '/' . $fileName);
                    unset($items[$key]);
                    audit_log('audio.deleted', ['id' => $id]);
                }
                break;
            }
            unset($item);

            if (!$found) {
                flash('error', 'Аудио не найдено.');
            } elseif (ds_audio_save($items)) {
                flash('success', $action === 'delete' ? 'Аудио удалено.' : 'Статус публикации изменён.');
            } else {
                flash('error', 'Не удалось сохранить изменения.');
            }
            admin_redirect('/admin/plugin.php?id=audio');
        }
    },
    'render' => static function (): void {
        $items = ds_audio_items();
        $maxBytes = ds_audio_max_bytes();
        $editId = preg_replace('/[^a-z0-9-]/i', '', (string)($_GET['edit'] ?? ''));
        $editItem = $editId !== '' ? ds_audio_find($items, $editId) : null;
        $publishedCount = count(array_filter($items, static fn($item) => !empty($item['published'])));
        $musicCount = count(array_filter($items, static fn($item) => !empty($item['contains_music'])));
        $downloadCount = count(array_filter($items, static fn($item) => !empty($item['allow_download'])));
        ?>
        <div class="page-head audio-admin-head">
          <div class="audio-admin-title-wrap">
            <div class="audio-admin-title-icon" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></div>
            <div>
              <p class="requests-kicker">Медиатека DAG STUDIO</p>
              <h1>Аудио</h1>
              <p>Загрузка, публикация и управление аудиозаписями в одном компактном разделе.</p>
            </div>
          </div>
        </div>

        <div class="audio-admin-summary">
          <div class="audio-summary-card total"><span class="audio-summary-icon"></span><div><strong><?= count($items) ?></strong><small>Всего</small></div></div>
          <div class="audio-summary-card published"><span class="audio-summary-icon"></span><div><strong><?= $publishedCount ?></strong><small>На сайте</small></div></div>
          <div class="audio-summary-card music"><span class="audio-summary-icon"></span><div><strong><?= $musicCount ?></strong><small>С музыкой</small></div></div>
          <div class="audio-summary-card download"><span class="audio-summary-icon"></span><div><strong><?= $downloadCount ?></strong><small>Скачивание</small></div></div>
          <div class="audio-summary-actions">
            <a class="btn secondary audio-open-public" href="/audio.php" target="_blank" rel="noopener"><span aria-hidden="true"></span>Открыть страницу</a>
          </div>
        </div>

        <?php if (is_array($editItem)): ?>
          <section class="panel audio-edit-panel audio-edit-panel-modern" id="edit-audio">
            <div class="audio-section-title">
              <span>01</span>
              <div><strong>Редактирование</strong><small><?= e((string)($editItem['original_name'] ?? '')) ?></small></div>
              <a class="audio-close-edit" href="/admin/plugin.php?id=audio" aria-label="Закрыть редактирование"></a>
            </div>
            <form method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="id" value="<?= e((string)$editItem['id']) ?>">
              <div class="form-grid audio-form-grid audio-form-grid-modern">
                <div class="field"><label>Название</label><input name="title" maxlength="160" value="<?= e((string)($editItem['title'] ?? '')) ?>" required></div>
                <div class="field"><label>Автор / исполнитель</label><input name="artist" maxlength="120" value="<?= e((string)($editItem['artist'] ?? '')) ?>"></div>
                <div class="field full"><label>Описание</label><textarea name="description" maxlength="700"><?= e((string)($editItem['description'] ?? '')) ?></textarea></div>
              </div>
              <div class="audio-options audio-options-modern">
                <label class="audio-option-card"><input type="checkbox" name="published" value="1" <?= !empty($editItem['published']) ? 'checked' : '' ?>><span class="audio-option-toggle"></span><span><strong>Опубликовано</strong><small>Показывать на сайте</small></span></label>
                <label class="audio-option-card"><input type="checkbox" name="contains_music" value="1" <?= !empty($editItem['contains_music']) ? 'checked' : '' ?>><span class="audio-option-toggle"></span><span><strong>Содержит музыку</strong><small>Показывать предупреждение</small></span></label>
                <label class="audio-option-card"><input type="checkbox" name="allow_download" value="1" <?= !empty($editItem['allow_download']) ? 'checked' : '' ?>><span class="audio-option-toggle"></span><span><strong>Скачивание</strong><small>Разрешить загрузку файла</small></span></label>
              </div>
              <div class="form-actions audio-edit-actions"><button class="btn" type="submit">Сохранить изменения</button><a class="btn secondary" href="/admin/plugin.php?id=audio">Отмена</a></div>
            </form>
          </section>
        <?php endif; ?>

        <section class="audio-admin-compact-grid">
          <div class="panel audio-upload-panel audio-upload-panel-modern">
            <div class="audio-section-title">
              <span><?= is_array($editItem) ? '02' : '01' ?></span>
              <div><strong>Добавить аудио</strong><small>До <?= e(number_format($maxBytes / 1048576, 0, ',', ' ')) ?> МБ</small></div>
            </div>
            <form method="post" enctype="multipart/form-data">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="upload">
              <label class="upload-zone audio-upload-zone-modern">
                <input type="file" name="audio" accept=".mp3,.m4a,.wav,.ogg,.flac,.aac,audio/*" required>
                <span class="audio-upload-icon" aria-hidden="true"></span>
                <span><strong>Выберите аудиофайл</strong><small>MP3 · M4A · WAV · OGG · FLAC · AAC</small></span>
                <b>Выбрать</b>
              </label>
              <div class="form-grid audio-form-grid audio-form-grid-modern">
                <div class="field"><label>Название</label><input name="title" maxlength="160" placeholder="Название записи"></div>
                <div class="field"><label>Автор / исполнитель</label><input name="artist" maxlength="120" placeholder="DAG STUDIO"></div>
                <div class="field full"><label>Описание</label><textarea name="description" maxlength="700" placeholder="Короткое описание"></textarea></div>
              </div>
              <div class="audio-options audio-options-modern">
                <label class="audio-option-card"><input type="checkbox" name="published" value="1" checked><span class="audio-option-toggle"></span><span><strong>Опубликовать</strong><small>Сразу показать на сайте</small></span></label>
                <label class="audio-option-card"><input type="checkbox" name="contains_music" value="1"><span class="audio-option-toggle"></span><span><strong>Есть музыка</strong><small>Включить предупреждение</small></span></label>
                <label class="audio-option-card"><input type="checkbox" name="allow_download" value="1"><span class="audio-option-toggle"></span><span><strong>Скачивание</strong><small>Разрешить загрузку</small></span></label>
              </div>
              <div class="form-actions"><button class="btn audio-upload-submit" type="submit"><span aria-hidden="true"></span>Загрузить аудио</button></div>
            </form>
          </div>

          <aside class="panel audio-mini-help">
            <div class="audio-section-title"><span>i</span><div><strong>Форматы</strong><small>Поддерживаемые файлы</small></div></div>
            <div class="audio-format-list"><span>MP3</span><span>M4A</span><span>WAV</span><span>OGG</span><span>FLAC</span><span>AAC</span></div>
            <p>Плеер, предупреждение о музыке и кнопка скачивания формируются автоматически.</p>
          </aside>
        </section>

        <section class="panel audio-list-panel audio-list-panel-modern">
          <div class="panel-title-row audio-list-heading">
            <div><h2>Аудиозаписи</h2><span>Компактный список всех загруженных файлов</span></div>
            <span class="audio-list-count"><?= count($items) ?> шт.</span>
          </div>
          <?php if (!$items): ?><div class="empty audio-empty">Пока ничего не загружено. Добавьте первый аудиофайл выше.</div><?php endif; ?>

          <div class="audio-admin-list audio-admin-list-modern">
            <?php foreach ($items as $index => $item):
              $stored = basename((string)($item['file'] ?? ''));
              $url = '/uploads/audio/' . rawurlencode($stored);
              $published = !empty($item['published']);
            ?>
              <article class="audio-admin-card <?= $published ? 'is-published' : 'is-hidden' ?>">
                <div class="audio-admin-card-index"><?= str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT) ?></div>
                <div class="audio-admin-card-main">
                  <div class="audio-admin-card-head">
                    <div class="audio-admin-card-title">
                      <strong><?= e((string)($item['title'] ?? 'Без названия')) ?></strong>
                      <?php if (!empty($item['artist'])): ?><span><?= e((string)$item['artist']) ?></span><?php endif; ?>
                    </div>
                    <div class="admin-audio-badges">
                      <span class="audio-status-pill <?= $published ? 'published' : 'hidden' ?>"><i></i><?= $published ? 'На сайте' : 'Скрыто' ?></span>
                      <?php if (!empty($item['contains_music'])): ?><span class="audio-mini-pill music">Музыка</span><?php endif; ?>
                      <?php if (!empty($item['allow_download'])): ?><span class="audio-mini-pill download">Скачать</span><?php endif; ?>
                    </div>
                  </div>

                  <div class="ds-audio-player ds-audio-player--compact"><audio controls preload="metadata" src="<?= e($url) ?>"></audio></div>

                  <div class="audio-admin-card-meta">
                    <span><?= e(number_format(((int)($item['size'] ?? 0)) / 1048576, 1, ',', ' ')) ?> МБ</span>
                    <?php if (!empty($item['uploaded_at'])): ?><span><?= e(date('d.m.Y', strtotime((string)$item['uploaded_at']))) ?></span><?php endif; ?>
                    <span class="audio-file-name"><?= e((string)($item['original_name'] ?? '')) ?></span>
                  </div>
                </div>

                <div class="audio-admin-card-actions">
                  <a class="audio-admin-action edit" href="/admin/plugin.php?id=audio&amp;edit=<?= e((string)$item['id']) ?>#edit-audio"><span></span>Редактировать</a>
                  <form method="post">
                    <?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= e((string)$item['id']) ?>">
                    <button class="audio-admin-action visibility <?= $published ? 'hide' : 'show' ?>" type="submit"><span></span><?= $published ? 'Скрыть' : 'Показать' ?></button>
                  </form>
                  <form method="post">
                    <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e((string)$item['id']) ?>">
                    <button class="audio-admin-delete" type="submit" data-confirm="Удалить это аудио без возможности восстановления?" title="Удалить" aria-label="Удалить"><span></span></button>
                  </form>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        </section>
        <?php
    },
];
