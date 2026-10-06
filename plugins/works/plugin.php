<?php
declare(strict_types=1);

require_once DS_ROOT . '/includes/works-data.php';

if (!function_exists('ds_work_clean')) {
    function ds_work_clean(string $value, int $max): string {
        return mb_substr(trim(strip_tags($value)), 0, $max);
    }

    function ds_work_safe_url(string $value, bool $optional = false): string {
        $value = trim($value);
        if ($optional && $value === '') return '';
        if (!filter_var($value, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $value)) return '';
        return mb_substr($value, 0, 500);
    }

    function ds_work_find(array $items, string $id): ?array {
        foreach ($items as $item) {
            if (is_array($item) && (string)($item['id'] ?? '') === $id) return $item;
        }
        return null;
    }
}

return [
    'id' => 'works',
    'name' => 'Наши работы',
    'menu' => 'Наши работы',
    'icon' => '▦',
    'version' => '1.0.0',
    'author' => 'DAG STUDIO',
    'default_enabled' => true,
    'description' => 'Портфолио DAG STUDIO: добавление, редактирование, публикация и порядок проектов на странице «Наши работы».',
    'handle' => static function (): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
        csrf_verify();

        $items = ds_works_read(DS_ROOT);
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'save') {
            $id = preg_replace('/[^a-z0-9-]/i', '', (string)($_POST['id'] ?? ''));
            $isNew = $id === '';
            if ($isNew) $id = date('YmdHis') . '-' . bin2hex(random_bytes(4));

            $title = ds_work_clean((string)($_POST['title'] ?? ''), 160);
            $url = ds_work_safe_url((string)($_POST['url'] ?? ''));
            $category = ds_work_clean((string)($_POST['category'] ?? ''), 100);
            $description = ds_work_clean((string)($_POST['description'] ?? ''), 700);
            $previewUrl = ds_work_safe_url((string)($_POST['preview_url'] ?? ''), true);
            $published = !empty($_POST['published']);

            if ($title === '' || $url === '') {
                flash('error', 'Укажите название и корректный адрес сайта с http:// или https://.');
                admin_redirect('/admin/plugin.php?id=works' . ($isNew ? '&new=1' : '&edit=' . rawurlencode($id)));
            }

            $found = false;
            foreach ($items as &$item) {
                if (!is_array($item) || (string)($item['id'] ?? '') !== $id) continue;
                $found = true;
                $created = (string)($item['created_at'] ?? gmdate('c'));
                $item = [
                    'id' => $id,
                    'title' => $title,
                    'url' => $url,
                    'category' => $category,
                    'description' => $description,
                    'preview_url' => $previewUrl,
                    'published' => $published,
                    'created_at' => $created,
                    'updated_at' => gmdate('c'),
                ];
                break;
            }
            unset($item);

            if (!$found) {
                $items[] = [
                    'id' => $id,
                    'title' => $title,
                    'url' => $url,
                    'category' => $category,
                    'description' => $description,
                    'preview_url' => $previewUrl,
                    'published' => $published,
                    'created_at' => gmdate('c'),
                    'updated_at' => gmdate('c'),
                ];
            }

            if (ds_works_save(DS_ROOT, $items)) {
                audit_log($found ? 'work.updated' : 'work.created', ['id' => $id, 'url' => $url]);
                flash('success', $found ? 'Работа обновлена.' : 'Работа добавлена.');
            } else {
                flash('error', 'Не удалось сохранить работу.');
            }
            admin_redirect('/admin/plugin.php?id=works&edit=' . rawurlencode($id));
        }

        if (in_array($action, ['toggle', 'delete', 'up', 'down'], true)) {
            $id = preg_replace('/[^a-z0-9-]/i', '', (string)($_POST['id'] ?? ''));
            $index = null;
            foreach ($items as $key => $item) {
                if (is_array($item) && (string)($item['id'] ?? '') === $id) { $index = $key; break; }
            }

            if ($index === null) {
                flash('error', 'Работа не найдена.');
                admin_redirect('/admin/plugin.php?id=works');
            }

            if ($action === 'toggle') {
                $items[$index]['published'] = empty($items[$index]['published']);
                $items[$index]['updated_at'] = gmdate('c');
                audit_log(!empty($items[$index]['published']) ? 'work.published' : 'work.hidden', ['id' => $id]);
            } elseif ($action === 'delete') {
                array_splice($items, $index, 1);
                audit_log('work.deleted', ['id' => $id]);
            } elseif ($action === 'up' && $index > 0) {
                [$items[$index - 1], $items[$index]] = [$items[$index], $items[$index - 1]];
                audit_log('work.reordered', ['id' => $id, 'direction' => 'up']);
            } elseif ($action === 'down' && $index < count($items) - 1) {
                [$items[$index + 1], $items[$index]] = [$items[$index], $items[$index + 1]];
                audit_log('work.reordered', ['id' => $id, 'direction' => 'down']);
            }

            if (ds_works_save(DS_ROOT, $items)) {
                flash('success', $action === 'delete' ? 'Работа удалена.' : 'Изменения сохранены.');
            } else {
                flash('error', 'Не удалось сохранить изменения.');
            }
            admin_redirect('/admin/plugin.php?id=works');
        }
    },
    'render' => static function (): void {
        $items = ds_works_read(DS_ROOT);
        $editId = preg_replace('/[^a-z0-9-]/i', '', (string)($_GET['edit'] ?? ''));
        $isNew = isset($_GET['new']);
        $edit = $editId !== '' ? ds_work_find($items, $editId) : null;

        if ($isNew || is_array($edit)):
            $work = is_array($edit) ? $edit : [
                'id' => '',
                'title' => '',
                'url' => '',
                'category' => '',
                'description' => '',
                'preview_url' => '',
                'published' => true,
            ];
            $preview = (string)($work['url'] ?? '') !== '' ? ds_works_preview((string)$work['url'], (string)($work['preview_url'] ?? '')) : '';
        ?>
          <section class="panel work-editor-panel">
            <div class="panel-title-row">
              <div>
                <h2><?= is_array($edit) ? 'Редактировать работу' : 'Добавить работу' ?></h2>
                <span><?= is_array($edit) ? e(ds_works_domain((string)$work['url'])) : 'Новый проект в портфолио' ?></span>
              </div>
              <a class="toggle-btn" href="/admin/plugin.php?id=works">К списку</a>
            </div>

            <?php if ($preview !== ''): ?>
              <div class="admin-work-preview">
                <img src="<?= e($preview) ?>" alt="Предпросмотр" loading="lazy">
                <span><?= e(ds_works_domain((string)$work['url'])) ?></span>
              </div>
            <?php endif; ?>

            <form method="post" class="work-editor-form">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="save">
              <input type="hidden" name="id" value="<?= e((string)$work['id']) ?>">

              <div class="form-grid">
                <div class="field"><label>Название проекта</label><input name="title" maxlength="160" value="<?= e((string)$work['title']) ?>" placeholder="Название сайта" required></div>
                <div class="field"><label>Категория</label><input name="category" maxlength="100" value="<?= e((string)$work['category']) ?>" placeholder="Например: Интернет-магазин"></div>
                <div class="field full"><label>Адрес сайта</label><input type="url" name="url" maxlength="500" value="<?= e((string)$work['url']) ?>" placeholder="https://example.ru/" required></div>
                <div class="field full"><label>Собственное изображение предпросмотра — необязательно</label><input type="url" name="preview_url" maxlength="500" value="<?= e((string)($work['preview_url'] ?? '')) ?>" placeholder="https://.../screenshot.jpg"><small>Если оставить пустым, снимок сайта формируется автоматически.</small></div>
                <div class="field full"><label>Краткое описание</label><textarea name="description" maxlength="700" placeholder="Что было сделано в проекте"><?= e((string)$work['description']) ?></textarea></div>
              </div>

              <label class="check-card work-publish-check"><input type="checkbox" name="published" value="1" <?= !empty($work['published']) ? 'checked' : '' ?>><span><strong>Показывать в «Наших работах»</strong><small>Скрытый проект остаётся в админке, но не выводится посетителям.</small></span></label>
              <div class="form-actions"><button class="btn" type="submit">Сохранить работу</button><a class="btn secondary" href="/admin/plugin.php?id=works">Отмена</a></div>
            </form>
          </section>
        <?php else: ?>
          <div class="works-admin-toolbar">
            <div>
              <strong><?= count($items) ?></strong><span>всего</span>
              <strong><?= count(array_filter($items, static fn($item) => !empty($item['published']))) ?></strong><span>на сайте</span>
            </div>
            <div class="works-admin-toolbar-actions">
              <a class="btn secondary" href="/works.php" target="_blank" rel="noopener">Открыть страницу ↗</a>
              <a class="btn" href="/admin/plugin.php?id=works&new=1">+ Добавить работу</a>
            </div>
          </div>

          <section class="panel works-admin-panel">
            <div class="panel-title-row"><h2>Наши работы</h2><span>Добавляйте проекты и меняйте их порядок</span></div>
            <div class="works-admin-list">
              <?php foreach ($items as $index => $item):
                $url = (string)($item['url'] ?? '');
                $preview = ds_works_preview($url, (string)($item['preview_url'] ?? ''));
              ?>
                <article class="works-admin-row">
                  <div class="works-admin-shot"><img src="<?= e($preview) ?>" alt="" loading="lazy"></div>
                  <div class="works-admin-main">
                    <div class="works-admin-title">
                      <strong><?= e((string)($item['title'] ?? 'Без названия')) ?></strong>
                      <span class="badge <?= !empty($item['published']) ? 'on' : 'off' ?>"><?= !empty($item['published']) ? 'Опубликовано' : 'Скрыто' ?></span>
                    </div>
                    <div class="works-admin-meta"><span><?= e(ds_works_domain($url)) ?></span><?php if (!empty($item['category'])): ?><span><?= e((string)$item['category']) ?></span><?php endif; ?></div>
                    <p><?= e((string)($item['description'] ?? '')) ?></p>
                  </div>
                  <div class="works-admin-actions">
                    <a class="toggle-btn" href="/admin/plugin.php?id=works&edit=<?= e((string)$item['id']) ?>">Редактировать</a>
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= e((string)$item['id']) ?>"><button class="toggle-btn" type="submit"><?= !empty($item['published']) ? 'Скрыть' : 'Показать' ?></button></form>
                    <div class="works-order-actions">
                      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="up"><input type="hidden" name="id" value="<?= e((string)$item['id']) ?>"><button class="menu-order-btn" type="submit" <?= $index === 0 ? 'disabled' : '' ?> title="Выше">↑</button></form>
                      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="down"><input type="hidden" name="id" value="<?= e((string)$item['id']) ?>"><button class="menu-order-btn" type="submit" <?= $index === count($items)-1 ? 'disabled' : '' ?> title="Ниже">↓</button></form>
                    </div>
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e((string)$item['id']) ?>"><button class="toggle-btn danger" type="submit" data-confirm="Удалить эту работу из портфолио?">Удалить</button></form>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endif;
    },
];
