<?php
declare(strict_types=1);

if (!function_exists('ds_pages_items')) {
    function ds_pages_items(): array {
        $data = storage_read_json('pages.json', ['items' => []]);
        return is_array($data['items'] ?? null) ? array_values($data['items']) : [];
    }

    function ds_pages_save(array $items): bool {
        return storage_write_json('pages.json', ['items' => array_values($items)]);
    }

    function ds_pages_slug(string $value): string {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9а-яё_-]+/ui', '-', $value) ?? '';
        $value = trim($value, '-_');
        if ($value === '') return 'page-' . date('Ymd-His');
        return mb_substr($value, 0, 100);
    }

    function ds_pages_find(array $items, string $id): ?array {
        foreach ($items as $item) {
            if (is_array($item) && (string)($item['id'] ?? '') === $id) return $item;
        }
        return null;
    }

    function ds_pages_unique_slug(array $items, string $slug, string $ignoreId = ''): string {
        $base = $slug;
        $n = 2;
        while (true) {
            $busy = false;
            foreach ($items as $item) {
                if (!is_array($item) || (string)($item['id'] ?? '') === $ignoreId) continue;
                if ((string)($item['slug'] ?? '') === $slug) { $busy = true; break; }
            }
            if (!$busy) return $slug;
            $slug = $base . '-' . $n++;
        }
    }
}

return [
    'id' => 'pages',
    'name' => 'Статичные страницы',
    'menu' => 'Страницы',
    'icon' => '▤',
    'version' => '1.0.0',
    'author' => 'DAG STUDIO',
    'default_enabled' => true,
    'description' => 'Создание и редактирование статичных страниц сайта с безопасным BBCode-редактором и публикацией.',
    'handle' => static function (): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
        csrf_verify();

        $action = (string)($_POST['action'] ?? '');
        $items = ds_pages_items();

        if ($action === 'save') {
            $id = preg_replace('/[^a-z0-9-]/i', '', (string)($_POST['id'] ?? ''));
            $isNew = $id === '';
            if ($isNew) $id = date('YmdHis') . '-' . bin2hex(random_bytes(4));

            $title = mb_substr(trim(strip_tags((string)($_POST['title'] ?? ''))), 0, 180);
            $slugInput = (string)($_POST['slug'] ?? '');
            $slug = ds_pages_slug($slugInput !== '' ? $slugInput : $title);
            $slug = ds_pages_unique_slug($items, $slug, $id);
            $content = mb_substr((string)($_POST['content'] ?? ''), 0, 100000);
            $seo = mb_substr(trim(strip_tags((string)($_POST['seo_description'] ?? ''))), 0, 300);
            $published = !empty($_POST['published']);

            if ($title === '') {
                flash('error', 'Укажите название страницы.');
                admin_redirect('/admin/plugin.php?id=pages' . ($isNew ? '&new=1' : '&edit=' . rawurlencode($id)));
            }

            $found = false;
            foreach ($items as &$item) {
                if (!is_array($item) || (string)($item['id'] ?? '') !== $id) continue;
                $found = true;
                $created = (string)($item['created_at'] ?? gmdate('c'));
                $item = [
                    'id' => $id,
                    'title' => $title,
                    'slug' => $slug,
                    'content' => $content,
                    'seo_description' => $seo,
                    'published' => $published,
                    'created_at' => $created,
                    'updated_at' => gmdate('c'),
                ];
                break;
            }
            unset($item);

            if (!$found) {
                array_unshift($items, [
                    'id' => $id,
                    'title' => $title,
                    'slug' => $slug,
                    'content' => $content,
                    'seo_description' => $seo,
                    'published' => $published,
                    'created_at' => gmdate('c'),
                    'updated_at' => gmdate('c'),
                ]);
            }

            if (ds_pages_save($items)) {
                audit_log($found ? 'page.updated' : 'page.created', ['id' => $id, 'slug' => $slug]);
                flash('success', $found ? 'Страница обновлена.' : 'Страница создана.');
            } else {
                flash('error', 'Не удалось сохранить страницу.');
            }
            admin_redirect('/admin/plugin.php?id=pages&edit=' . rawurlencode($id));
        }

        if ($action === 'toggle' || $action === 'delete') {
            $id = preg_replace('/[^a-z0-9-]/i', '', (string)($_POST['id'] ?? ''));
            $found = false;

            foreach ($items as $key => &$item) {
                if (!is_array($item) || (string)($item['id'] ?? '') !== $id) continue;
                $found = true;
                if ($action === 'toggle') {
                    $item['published'] = empty($item['published']);
                    $item['updated_at'] = gmdate('c');
                    audit_log(!empty($item['published']) ? 'page.published' : 'page.hidden', ['id' => $id]);
                } else {
                    unset($items[$key]);
                    audit_log('page.deleted', ['id' => $id]);
                }
                break;
            }
            unset($item);

            if (!$found) flash('error', 'Страница не найдена.');
            elseif (ds_pages_save($items)) flash('success', $action === 'delete' ? 'Страница удалена.' : 'Статус публикации изменён.');
            else flash('error', 'Не удалось сохранить изменения.');

            admin_redirect('/admin/plugin.php?id=pages');
        }
    },
    'render' => static function (): void {
        $items = ds_pages_items();
        $editId = preg_replace('/[^a-z0-9-]/i', '', (string)($_GET['edit'] ?? ''));
        $isNew = isset($_GET['new']);
        $edit = $editId !== '' ? ds_pages_find($items, $editId) : null;

        if ($isNew || is_array($edit)):
            $page = is_array($edit) ? $edit : [
                'id' => '',
                'title' => '',
                'slug' => '',
                'content' => '',
                'seo_description' => '',
                'published' => true,
            ];
        ?>
          <section class="panel page-editor-panel">
            <div class="panel-title-row">
              <div>
                <h2><?= is_array($edit) ? 'Редактировать страницу' : 'Новая страница' ?></h2>
                <span><?= is_array($edit) ? '/page.php?slug=' . e((string)$page['slug']) : 'Создайте новую статичную страницу' ?></span>
              </div>
              <div class="page-editor-top-actions">
                <?php if (is_array($edit) && !empty($edit['published'])): ?><a class="toggle-btn" href="/page.php?slug=<?= rawurlencode((string)$edit['slug']) ?>" target="_blank" rel="noopener">Открыть ↗</a><?php endif; ?>
                <a class="toggle-btn" href="/admin/plugin.php?id=pages">К списку</a>
              </div>
            </div>

            <form method="post" class="page-editor-form">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="save">
              <input type="hidden" name="id" value="<?= e((string)$page['id']) ?>">

              <div class="form-grid">
                <div class="field"><label>Название страницы</label><input name="title" maxlength="180" value="<?= e((string)$page['title']) ?>" placeholder="О компании" required></div>
                <div class="field"><label>Адрес страницы (slug)</label><input name="slug" maxlength="100" value="<?= e((string)$page['slug']) ?>" placeholder="o-kompanii"></div>
                <div class="field full"><label>SEO-описание</label><input name="seo_description" maxlength="300" value="<?= e((string)$page['seo_description']) ?>" placeholder="Краткое описание для поисковых систем"></div>
              </div>

              <div class="bb-editor">
                <div class="bb-toolbar" data-bb-toolbar>
                  <button type="button" data-bb-tag="b"><strong>B</strong></button>
                  <button type="button" data-bb-tag="i"><em>I</em></button>
                  <button type="button" data-bb-tag="u"><u>U</u></button>
                  <button type="button" data-bb-tag="s"><s>S</s></button>
                  <span></span>
                  <button type="button" data-bb-tag="h2">H2</button>
                  <button type="button" data-bb-tag="h3">H3</button>
                  <button type="button" data-bb-tag="quote">❝</button>
                  <button type="button" data-bb-list>• Список</button>
                  <button type="button" data-bb-link>🔗 Ссылка</button>
                  <button type="button" data-bb-image>▧ Изображение</button>
                  <button type="button" data-bb-color>● Цвет</button>
                  <button type="button" data-bb-tag="center">Центр</button>
                  <button type="button" data-bb-tag="code">&lt;/&gt;</button>
                </div>
                <textarea id="page_content" name="content" maxlength="100000" data-bb-textarea placeholder="Текст страницы. Используйте панель BBCode для форматирования."><?= e((string)$page['content']) ?></textarea>
                <div class="bb-help">
                  <span>BBCode безопасно преобразуется в оформление страницы.</span>
                  <details><summary>Поддерживаемые коды</summary><code>[b] [i] [u] [s] [h2] [h3] [quote] [url=https://...] [img]https://...[/img] [color=#c96f41] [center] [list][*]...[/list] [code]</code></details>
                </div>
              </div>

              <label class="check-card page-publish-check"><input type="checkbox" name="published" value="1" <?= !empty($page['published']) ? 'checked' : '' ?>><span><strong>Опубликовать страницу</strong><small>Неопубликованная страница доступна только в админке.</small></span></label>
              <div class="form-actions"><button class="btn" type="submit">Сохранить страницу</button><a class="btn secondary" href="/admin/plugin.php?id=pages">Отмена</a></div>
            </form>
          </section>
        <?php else: ?>
          <div class="pages-toolbar">
            <div>
              <strong><?= count($items) ?></strong><span>страниц</span>
              <strong><?= count(array_filter($items, static fn($item) => !empty($item['published']))) ?></strong><span>опубликовано</span>
            </div>
            <a class="btn" href="/admin/plugin.php?id=pages&new=1">+ Создать страницу</a>
          </div>

          <section class="panel pages-list-panel">
            <div class="panel-title-row"><h2>Статичные страницы</h2><span>Управление публикацией и содержимым</span></div>
            <?php if (!$items): ?><div class="empty pages-empty">Страниц пока нет. Создайте первую страницу.</div><?php endif; ?>
            <div class="pages-list">
              <?php foreach ($items as $item): ?>
                <article class="page-list-row">
                  <div class="page-list-icon">▤</div>
                  <div class="page-list-main">
                    <div class="page-list-title">
                      <strong><?= e((string)($item['title'] ?? 'Без названия')) ?></strong>
                      <span class="badge <?= !empty($item['published']) ? 'on' : 'off' ?>"><?= !empty($item['published']) ? 'Опубликовано' : 'Черновик' ?></span>
                    </div>
                    <div class="page-list-meta">
                      <span>/page.php?slug=<?= e((string)($item['slug'] ?? '')) ?></span>
                      <?php if (!empty($item['updated_at'])): ?><span><?= e(date('d.m.Y H:i', strtotime((string)$item['updated_at']))) ?></span><?php endif; ?>
                    </div>
                  </div>
                  <div class="page-list-actions">
                    <?php if (!empty($item['published'])): ?><a class="toggle-btn" href="/page.php?slug=<?= rawurlencode((string)$item['slug']) ?>" target="_blank" rel="noopener">Открыть</a><?php endif; ?>
                    <a class="toggle-btn" href="/admin/plugin.php?id=pages&edit=<?= e((string)$item['id']) ?>">Редактировать</a>
                    <form method="post">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="toggle">
                      <input type="hidden" name="id" value="<?= e((string)$item['id']) ?>">
                      <button class="toggle-btn" type="submit"><?= !empty($item['published']) ? 'Скрыть' : 'Опубликовать' ?></button>
                    </form>
                    <form method="post">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= e((string)$item['id']) ?>">
                      <button class="toggle-btn danger" type="submit" data-confirm="Удалить страницу без возможности восстановления?">Удалить</button>
                    </form>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endif;
    },
];
