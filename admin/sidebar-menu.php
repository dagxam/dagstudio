<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

admin_require_auth();

$plugins = plugin_definitions();
$states = plugin_states();
$requestData = storage_read_json('requests.json', ['items' => []]);
$requestPending = 0;
foreach (($requestData['items'] ?? []) as $requestItem) {
    if (is_array($requestItem) && (string)($requestItem['status'] ?? 'pending') === 'pending') $requestPending++;
}

$available = admin_sidebar_available_items($plugins, $states, $requestPending);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = (string)($_POST['action'] ?? 'save');

    if ($action === 'reset') {
        $defaultOrder = array_keys($available);
        if (storage_write_json('admin-menu.json', ['order' => $defaultOrder])) {
            audit_log('admin.sidebar_order_reset');
            flash('success', 'Порядок меню сброшен.');
        } else {
            flash('error', 'Не удалось сбросить порядок меню.');
        }
    } else {
        $order = is_array($_POST['menu_order'] ?? null) ? $_POST['menu_order'] : [];
        if (save_admin_sidebar_order($order, $available)) {
            flash('success', 'Порядок левого меню сохранён.');
        } else {
            flash('error', 'Не удалось сохранить порядок меню.');
        }
    }

    admin_redirect('/admin/sidebar-menu.php');
}

$order = admin_sidebar_order($available);

admin_header('Порядок меню', 'sidebar-menu');
?>
<div class="page-head">
  <div>
    <h1>Левое меню</h1>
    <p>Меняйте расположение пунктов панели управления. Можно перетаскивать строки мышью или использовать стрелки.</p>
  </div>
</div>

<section class="panel sidebar-order-panel">
  <div class="panel-title-row">
    <div>
      <h2>Порядок пунктов</h2>
      <span><?= count($order) ?> пунктов</span>
    </div>
  </div>

  <form method="post" data-sidebar-order-form>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">

    <div class="sidebar-order-list" data-sidebar-order-list>
      <?php foreach ($order as $index => $id):
        $item = $available[$id] ?? null;
        if (!is_array($item)) continue;
      ?>
        <article class="sidebar-order-row" draggable="true" data-sidebar-order-row>
          <input type="hidden" name="menu_order[]" value="<?= e($id) ?>">
          <button class="sidebar-drag-handle" type="button" aria-label="Перетащить" title="Перетащить">⋮⋮</button>
          <span class="sidebar-order-icon"><?= e((string)$item['icon']) ?></span>
          <div class="sidebar-order-copy">
            <strong><?= e((string)$item['label']) ?></strong>
            <small><?= e((string)$item['href']) ?></small>
          </div>
          <?php if ((string)$item['counter'] !== ''): ?><span class="sidebar-order-counter"><?= e((string)$item['counter']) ?></span><?php endif; ?>
          <div class="sidebar-order-actions">
            <button class="menu-order-btn" type="button" data-sidebar-up title="Выше" <?= $index === 0 ? 'disabled' : '' ?>>↑</button>
            <button class="menu-order-btn" type="button" data-sidebar-down title="Ниже" <?= $index === count($order)-1 ? 'disabled' : '' ?>>↓</button>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <div class="form-actions sidebar-order-save">
      <button class="btn" type="submit">Сохранить порядок</button>
    </div>
  </form>

  <form method="post" class="sidebar-order-reset">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="reset">
    <button class="toggle-btn" type="submit" data-confirm="Вернуть стандартный порядок левого меню?">Сбросить порядок</button>
  </form>
</section>
<?php admin_footer(); ?>
