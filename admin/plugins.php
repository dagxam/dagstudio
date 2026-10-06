<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';
admin_require_auth();

$plugins = plugin_definitions();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $id = preg_replace('/[^a-z0-9_-]/i', '', (string)($_POST['id'] ?? ''));
    $enable = (string)($_POST['mode'] ?? '') === 'enable';

    if (!isset($plugins[$id])) {
        flash('error', 'Плагин не найден.');
    } elseif (set_plugin_enabled($id, $enable)) {
        flash('success', $enable ? 'Плагин включён.' : 'Плагин отключён.');
    } else {
        flash('error', 'Не удалось изменить состояние плагина. Проверьте права записи каталога storage.');
    }
    admin_redirect('/admin/plugins.php');
}

$states = plugin_states();
admin_header('Функции и плагины', 'plugins');
?>
<div class="page-head">
  <div>
    <h1>Функции и плагины</h1>
    <p>Новая функциональность подключается отдельными модулями в каталоге <code>plugins</code>. Админка автоматически увидит совместимый модуль и позволит его включить или отключить.</p>
  </div>
</div>

<div class="notice info">Для безопасности загрузка исполняемых PHP-плагинов через браузер отключена. Новые модули добавляем через репозиторий, после чего здесь ими можно управлять.</div>

<div class="grid plugins-grid">
  <?php if (!$plugins): ?><div class="panel empty">Установленных плагинов пока нет.</div><?php endif; ?>
  <?php foreach ($plugins as $plugin): $enabled = plugin_enabled($plugin, $states); ?>
    <article class="plugin-card">
      <div class="plugin-top">
        <div class="plugin-name"><?= e((string)($plugin['name'] ?? $plugin['id'])) ?></div>
        <span class="plugin-version">v<?= e((string)($plugin['version'] ?? '1.0.0')) ?></span>
      </div>
      <p><?= e((string)($plugin['description'] ?? '')) ?></p>
      <div class="plugin-meta">ID: <?= e($plugin['id']) ?><?= !empty($plugin['author']) ? ' · ' . e((string)$plugin['author']) : '' ?></div>
      <div class="plugin-actions">
        <span class="badge <?= $enabled ? 'on' : 'off' ?>"><?= $enabled ? 'Активен' : 'Отключён' ?></span>
        <div style="display:flex;gap:8px">
          <?php if ($enabled && !empty($plugin['render'])): ?><a class="toggle-btn" href="/admin/plugin.php?id=<?= e($plugin['id']) ?>">Открыть</a><?php endif; ?>
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= e($plugin['id']) ?>">
            <input type="hidden" name="mode" value="<?= $enabled ? 'disable' : 'enable' ?>">
            <button class="toggle-btn" type="submit"><?= $enabled ? 'Отключить' : 'Включить' ?></button>
          </form>
        </div>
      </div>
    </article>
  <?php endforeach; ?>
</div>
<?php admin_footer(); ?>