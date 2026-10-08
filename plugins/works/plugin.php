<?php
declare(strict_types=1);

if (!defined('DS_ROOT')) {
    http_response_code(404);
    exit;
}

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
    'custom_page_head' => true,
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
        $publishedCount = count(array_filter($items, static fn($item) => !empty($item['published'])));
        $hiddenCount = max(0, count($items) - $publishedCount);

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
          <div class="page-head works-editor-head">
            <div class="works-title-wrap">
              <div class="works-title-icon" aria-hidden="true"><span></span><span></span><span></span></div>
              <div>
                <p class="requests-kicker">Портфолио DAG STUDIO</p>
                <h1><?= is_array($edit) ? 'Редактирование работы' : 'Новая работа' ?></h1>
                <p><?= is_array($edit) ? 'Обновите данные проекта, предпросмотр и его видимость на публичной странице.' : 'Добавьте новый проект в портфолио и настройте его отображение.' ?></p>
              </div>
            </div>
            <a class="works-back-btn" href="/admin/plugin.php?id=works"><span aria-hidden="true"></span>К списку</a>
          </div>

          <section class="work-editor-layout">
            <div class="panel work-editor-panel">
              <div class="work-editor-section-title">
                <span>01</span>
                <div><strong>Данные проекта</strong><small>Название, категория, ссылка и описание</small></div>
              </div>

              <form method="post" class="work-editor-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= e((string)$work['id']) ?>">

                <div class="form-grid works-form-grid">
                  <div class="field"><label>Название проекта</label><input name="title" maxlength="160" value="<?= e((string)$work['title']) ?>" placeholder="Например: UROVIA" required></div>
                  <div class="field"><label>Категория</label><input name="category" maxlength="100" value="<?= e((string)$work['category']) ?>" placeholder="Образование, портал, сервис..."></div>
                  <div class="field full"><label>Адрес сайта</label><input type="url" name="url" maxlength="500" value="<?= e((string)$work['url']) ?>" placeholder="https://example.ru/" required></div>
                  <div class="field full"><label>Собственное изображение предпросмотра <em>необязательно</em></label><input type="url" name="preview_url" maxlength="500" value="<?= e((string)($work['preview_url'] ?? '')) ?>" placeholder="https://.../screenshot.jpg"><small>Оставьте пустым — снимок сайта будет сформирован автоматически.</small></div>
                  <div class="field full"><label>Краткое описание</label><textarea name="description" maxlength="700" placeholder="Кратко опишите проект и выполненную работу"><?= e((string)$work['description']) ?></textarea></div>
                </div>

                <label class="work-publish-switch">
                  <input type="checkbox" name="published" value="1" <?= !empty($work['published']) ? 'checked' : '' ?>>
                  <span class="work-publish-toggle" aria-hidden="true"></span>
                  <span class="work-publish-copy"><strong>Показывать на странице «Наши работы»</strong><small>Если выключить, проект останется в админке, но посетители его не увидят.</small></span>
                </label>

                <div class="form-actions work-editor-actions">
                  <button class="btn works-save-btn" type="submit"><span class="works-save-icon" aria-hidden="true"></span><?= is_array($edit) ? 'Сохранить изменения' : 'Добавить работу' ?></button>
                  <a class="btn secondary" href="/admin/plugin.php?id=works">Отмена</a>
                </div>
              </form>
            </div>

            <aside class="panel work-preview-panel">
              <div class="work-editor-section-title">
                <span>02</span>
                <div><strong>Предпросмотр</strong><small><?= $preview !== '' ? e(ds_works_domain((string)$work['url'])) : 'Появится после указания адреса сайта' ?></small></div>
              </div>

              <?php if ($preview !== ''): ?>
                <a class="admin-work-preview admin-work-preview-large" href="<?= e((string)$work['url']) ?>" target="_blank" rel="noopener">
                  <div class="admin-work-browserbar"><i></i><i></i><i></i><span><?= e(ds_works_domain((string)$work['url'])) ?></span></div>
                  <img src="<?= e($preview) ?>" alt="Предпросмотр сайта <?= e(ds_works_domain((string)$work['url'])) ?>" loading="lazy">
                  <span class="admin-work-preview-action">Открыть сайт <b aria-hidden="true"></b></span>
                </a>
              <?php else: ?>
                <div class="work-preview-empty">
                  <span class="work-preview-empty-icon" aria-hidden="true"></span>
                  <strong>Предпросмотр пока пуст</strong>
                  <small>Введите адрес сайта и сохраните проект.</small>
                </div>
              <?php endif; ?>
            </aside>
          </section>
        <?php else: ?>
          <div class="page-head works-page-head">
            <div class="works-title-wrap">
              <div class="works-title-icon" aria-hidden="true"><span></span><span></span><span></span></div>
              <div>
                <p class="requests-kicker">Портфолио DAG STUDIO</p>
                <h1>Наши работы</h1>
                <p>Управляйте проектами, меняйте порядок, скрывайте работы и редактируйте карточки публичного портфолио.</p>
              </div>
            </div>
          </div>

          <div class="works-admin-summary">
            <div class="works-summary-card total"><span class="works-summary-icon" aria-hidden="true"></span><div><strong><?= count($items) ?></strong><small>Всего проектов</small></div></div>
            <div class="works-summary-card published"><span class="works-summary-icon" aria-hidden="true"></span><div><strong><?= $publishedCount ?></strong><small>Опубликовано</small></div></div>
            <div class="works-summary-card hidden"><span class="works-summary-icon" aria-hidden="true"></span><div><strong><?= $hiddenCount ?></strong><small>Скрыто</small></div></div>
            <div class="works-summary-actions">
              <a class="btn secondary works-open-public" href="/works.php" target="_blank" rel="noopener"><span aria-hidden="true"></span>Открыть страницу</a>
              <a class="btn works-add-btn" href="/admin/plugin.php?id=works&new=1"><span aria-hidden="true"></span>Добавить работу</a>
            </div>
          </div>

          <section class="panel works-admin-panel works-admin-panel-modern">
            <div class="panel-title-row works-panel-heading">
              <div><h2>Проекты</h2><span>Порядок карточек здесь совпадает с порядком на сайте</span></div>
              <span class="works-panel-count"><?= count($items) ?> шт.</span>
            </div>

            <?php if (!$items): ?>
              <div class="works-empty-state">
                <span class="works-empty-icon" aria-hidden="true"></span>
                <strong>Портфолио пока пусто</strong>
                <p>Добавьте первую работу — она появится на публичной странице после публикации.</p>
                <a class="btn" href="/admin/plugin.php?id=works&new=1">Добавить первую работу</a>
              </div>
            <?php endif; ?>

            <div class="works-admin-grid">
              <?php foreach ($items as $index => $item):
                $url = (string)($item['url'] ?? '');
                $preview = ds_works_preview($url, (string)($item['preview_url'] ?? ''));
                $published = !empty($item['published']);
              ?>
                <article class="work-admin-card <?= $published ? 'is-published' : 'is-hidden' ?>">
                  <a class="work-admin-cover" href="<?= e($url) ?>" target="_blank" rel="noopener" title="Открыть сайт">
                    <div class="admin-work-browserbar"><i></i><i></i><i></i><span><?= e(ds_works_domain($url)) ?></span></div>
                    <img src="<?= e($preview) ?>" alt="Предпросмотр <?= e((string)($item['title'] ?? '')) ?>" loading="lazy">
                    <span class="work-admin-cover-link">Открыть сайт <b aria-hidden="true"></b></span>
                  </a>

                  <div class="work-admin-card-body">
                    <div class="work-admin-card-topline">
                      <span class="work-admin-index"><?= str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
                      <span class="work-status-pill <?= $published ? 'published' : 'hidden' ?>"><i></i><?= $published ? 'Опубликовано' : 'Скрыто' ?></span>
                    </div>

                    <div class="work-admin-card-title">
                      <h3><?= e((string)($item['title'] ?? 'Без названия')) ?></h3>
                      <?php if (!empty($item['category'])): ?><span><?= e((string)$item['category']) ?></span><?php endif; ?>
                    </div>
                    <p><?= e((string)($item['description'] ?? '')) ?></p>

                    <div class="work-admin-card-actions">
                      <a class="work-action-btn edit" href="/admin/plugin.php?id=works&edit=<?= e((string)$item['id']) ?>"><span aria-hidden="true"></span>Редактировать</a>

                      <form method="post">
                        <?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= e((string)$item['id']) ?>">
                        <button class="work-action-btn visibility <?= $published ? 'hide' : 'show' ?>" type="submit"><span aria-hidden="true"></span><?= $published ? 'Скрыть' : 'Показать' ?></button>
                      </form>

                      <div class="work-card-order">
                        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="up"><input type="hidden" name="id" value="<?= e((string)$item['id']) ?>"><button class="work-order-btn up" type="submit" <?= $index === 0 ? 'disabled' : '' ?> title="Поднять выше" aria-label="Поднять выше"></button></form>
                        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="down"><input type="hidden" name="id" value="<?= e((string)$item['id']) ?>"><button class="work-order-btn down" type="submit" <?= $index === count($items)-1 ? 'disabled' : '' ?> title="Опустить ниже" aria-label="Опустить ниже"></button></form>
                      </div>

                      <form method="post" class="work-delete-form">
                        <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e((string)$item['id']) ?>">
                        <button class="work-delete-btn" type="submit" data-confirm="Удалить эту работу из портфолио?" aria-label="Удалить работу" title="Удалить"><span aria-hidden="true"></span></button>
                      </form>
                    </div>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endif;
    },
];
