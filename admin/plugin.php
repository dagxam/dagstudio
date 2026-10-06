<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';
admin_require_auth();

$id = preg_replace('/[^a-z0-9_-]/i', '', (string)($_GET['id'] ?? ''));
$plugins = plugin_definitions();

if (!isset($plugins[$id])) {
    http_response_code(404);
    exit('Плагин не найден.');
}

$plugin = $plugins[$id];
if (!plugin_enabled($plugin)) {
    flash('error', 'Сначала включите этот плагин.');
    admin_redirect('/admin/plugins.php');
}

if (isset($plugin['handle']) && is_callable($plugin['handle'])) {
    $plugin['handle']();
}

admin_header((string)($plugin['name'] ?? $id), 'plugin-' . $id);
?>
<div class="page-head">
  <div>
    <h1><?= e((string)($plugin['name'] ?? $id)) ?></h1>
    <p><?= e((string)($plugin['description'] ?? '')) ?></p>
  </div>
  <a class="btn secondary" href="/admin/plugins.php">Все плагины</a>
</div>
<div class="plugin-page plugin-page-wide">
<?php
if (isset($plugin['render']) && is_callable($plugin['render'])) {
    $plugin['render']();
} else {
    echo '<div class="panel empty">У плагина нет административной страницы.</div>';
}
?>
</div>
<?php admin_footer(); ?>