<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';
admin_require_auth();

$audioDir = DS_ROOT . '/uploads/audio';
if (!is_dir($audioDir)) {
    @mkdir($audioDir, 0755, true);
}

function audio_clean(string $value, int $max): string {
    $value = trim(strip_tags($value));
    return mb_substr($value, 0, $max);
}

function audio_items(): array {
    $data = storage_read_json('audio.json', ['items' => []]);
    $items = $data['items'] ?? [];
    return is_array($items) ? $items : [];
}

function save_audio_items(array $items): bool {
    return storage_write_json('audio.json', ['items' => array_values($items)]);
}

function ini_size_bytes(string $value): int {
    $value = trim($value);
    if ($value === '') return 0;
    $last = strtolower(substr($value, -1));
    $number = (float)$value;
    if ($last === 'g') $number *= 1024;
    if ($last === 'm' || $last === 'g') $number *= 1024;
    if ($last === 'k' || $last === 'm' || $last === 'g') $number *= 1024;
    return (int)$number;
}

$uploadLimit = ini_size_bytes((string)ini_get('upload_max_filesize'));
$postLimit = ini_size_bytes((string)ini_get('post_max_size'));
$serverLimit = $uploadLimit > 0 && $postLimit > 0 ? min($uploadLimit, $postLimit) : max($uploadLimit, $postLimit);
$maxBytes = $serverLimit > 0 ? min($serverLimit, 100 * 1024 * 1024) : 100 * 1024 * 1024;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        flash('error', 'Файл превышает допустимый размер сервера.');
        admin_redirect('/admin/audio.php');
    }

    csrf_verify();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'upload') {
        $file = $_FILES['audio'] ?? null;
        if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            flash('error', 'Не удалось получить аудиофайл. Проверьте размер и повторите загрузку.');
            admin_redirect('/admin/audio.php');
        }

        $size = (int)($file['size'] ?? 0);
        if ($size <= 0 || $size > $maxBytes) {
            flash('error', 'Размер аудио превышает разрешённый лимит.');
            admin_redirect('/admin/audio.php');
        }

        $originalName = (string)($file['name'] ?? 'audio');
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExt = ['mp3', 'm4a', 'wav', 'ogg', 'flac', 'aac'];
        if (!in_array($ext, $allowedExt, true)) {
            flash('error', 'Поддерживаются MP3, M4A, WAV, OGG, FLAC и AAC.');
            admin_redirect('/admin/audio.php');
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
            admin_redirect('/admin/audio.php');
        }

        $title = audio_clean((string)($_POST['title'] ?? ''), 160);
        if ($title === '') {
            $title = audio_clean(pathinfo($originalName, PATHINFO_FILENAME), 160);
        }
        $artist = audio_clean((string)($_POST['artist'] ?? ''), 120);
        $description = audio_clean((string)($_POST['description'] ?? ''), 700);
        $published = !empty($_POST['published']);

        $id = date('YmdHis') . '-' . bin2hex(random_bytes(4));
        $stored = $id . '.' . $ext;
        $destination = $audioDir . '/' . $stored;

        if (!move_uploaded_file((string)$file['tmp_name'], $destination)) {
            flash('error', 'Не удалось сохранить файл на сервере. Проверьте права каталога uploads/audio.');
            admin_redirect('/admin/audio.php');
        }
        @chmod($destination, 0644);

        $items = audio_items();
        array_unshift($items, [
            'id' => $id,
            'title' => $title,
            'artist' => $artist,
            'description' => $description,
            'file' => $stored,
            'original_name' => $originalName,
            'mime' => $mime,
            'size' => $size,
            'published' => $published,
            'uploaded_at' => gmdate('c'),
        ]);

        if (!save_audio_items($items)) {
            @unlink($destination);
            flash('error', 'Файл загружен, но данные аудио сохранить не удалось.');
            admin_redirect('/admin/audio.php');
        }

        audit_log('audio.uploaded', ['id' => $id, 'title' => $title]);
        flash('success', 'Аудио загружено.');
        admin_redirect('/admin/audio.php');
    }

    if ($action === 'toggle' || $action === 'delete') {
        $id = preg_replace('/[^a-z0-9-]/i', '', (string)($_POST['id'] ?? ''));
        $items = audio_items();
        $found = false;

        foreach ($items as $key => &$item) {
            if (($item['id'] ?? '') !== $id) continue;
            $found = true;

            if ($action === 'toggle') {
                $item['published'] = empty($item['published']);
                audit_log(!empty($item['published']) ? 'audio.published' : 'audio.hidden', ['id' => $id]);
            } else {
                $fileName = basename((string)($item['file'] ?? ''));
                if ($fileName !== '') @unlink($audioDir . '/' . $fileName);
                unset($items[$key]);
                audit_log('audio.deleted', ['id' => $id]);
            }
            break;
        }
        unset($item);

        if (!$found) {
            flash('error', 'Аудио не найдено.');
        } elseif (save_audio_items($items)) {
            flash('success', $action === 'delete' ? 'Аудио удалено.' : 'Статус публикации изменён.');
        } else {
            flash('error', 'Не удалось сохранить изменения.');
        }
        admin_redirect('/admin/audio.php');
    }
}

$items = audio_items();
admin_header('Аудио', 'audio');
?>
<div class="page-head">
  <div>
    <h1>Аудио</h1>
    <p>Загружайте аудиофайлы для публичной страницы «Наши аудио», управляйте публикацией и удаляйте старые записи.</p>
  </div>
  <a class="btn secondary" href="/audio.php" target="_blank" rel="noopener">Открыть страницу ↗</a>
</div>

<div class="audio-admin-grid">
  <section class="panel audio-upload-panel">
    <h2>Добавить аудио</h2>
    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="upload">

      <label class="upload-zone">
        <input type="file" name="audio" accept=".mp3,.m4a,.wav,.ogg,.flac,.aac,audio/*" required>
        <span class="upload-icon">♫</span>
        <strong>Выберите аудиофайл</strong>
        <small>MP3, M4A, WAV, OGG, FLAC, AAC · максимум <?= e(number_format($maxBytes / 1048576, 0, ',', ' ')) ?> МБ</small>
      </label>

      <div class="form-grid audio-form-grid">
        <div class="field"><label for="title">Название</label><input id="title" name="title" maxlength="160" placeholder="Например: Горный рассвет"></div>
        <div class="field"><label for="artist">Автор / исполнитель</label><input id="artist" name="artist" maxlength="120" placeholder="DAG STUDIO"></div>
        <div class="field full"><label for="description">Описание</label><textarea id="description" name="description" maxlength="700" placeholder="Короткое описание аудио"></textarea></div>
      </div>

      <label class="check-row"><input type="checkbox" name="published" value="1" checked><span>Сразу опубликовать на сайте</span></label>
      <div class="form-actions"><button class="btn" type="submit">Загрузить аудио</button></div>
    </form>
  </section>

  <aside class="panel audio-help">
    <h2>Публикация</h2>
    <p>После загрузки запись появится на странице <strong>«Наши аудио»</strong>, если включена публикация.</p>
    <div class="audio-help-stat"><span>Всего</span><strong><?= count($items) ?></strong></div>
    <div class="audio-help-stat"><span>Опубликовано</span><strong><?= count(array_filter($items, static fn($item) => !empty($item['published']))) ?></strong></div>
  </aside>
</div>

<section class="panel audio-list-panel">
  <div class="panel-title-row"><h2>Загруженные аудио</h2><span><?= count($items) ?> шт.</span></div>

  <?php if (!$items): ?>
    <div class="empty audio-empty">Пока ничего не загружено. Добавьте первый аудиофайл выше.</div>
  <?php endif; ?>

  <div class="audio-admin-list">
    <?php foreach ($items as $item):
      $stored = basename((string)($item['file'] ?? ''));
      $url = '/uploads/audio/' . rawurlencode($stored);
      $published = !empty($item['published']);
    ?>
      <article class="audio-admin-row">
        <div class="audio-row-icon">♫</div>
        <div class="audio-row-main">
          <div class="audio-row-head">
            <div>
              <strong><?= e((string)($item['title'] ?? 'Без названия')) ?></strong>
              <?php if (!empty($item['artist'])): ?><span><?= e((string)$item['artist']) ?></span><?php endif; ?>
            </div>
            <span class="badge <?= $published ? 'on' : 'off' ?>"><?= $published ? 'Опубликовано' : 'Скрыто' ?></span>
          </div>
          <audio controls preload="metadata" src="<?= e($url) ?>"></audio>
          <div class="audio-row-meta">
            <span><?= e(number_format(((int)($item['size'] ?? 0)) / 1048576, 1, ',', ' ')) ?> МБ</span>
            <span><?= e((string)($item['original_name'] ?? '')) ?></span>
            <span><?= !empty($item['uploaded_at']) ? e(date('d.m.Y H:i', strtotime((string)$item['uploaded_at']))) : '' ?></span>
          </div>
        </div>
        <div class="audio-row-actions">
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="id" value="<?= e((string)$item['id']) ?>">
            <button class="toggle-btn" type="submit"><?= $published ? 'Скрыть' : 'Опубликовать' ?></button>
          </form>
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= e((string)$item['id']) ?>">
            <button class="toggle-btn danger" type="submit" data-confirm="Удалить это аудио без возможности восстановления?">Удалить</button>
          </form>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</section>
<?php admin_footer(); ?>