<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';
admin_require_auth();

$plugins = plugin_definitions();
$states = plugin_states();
$activePlugins = 0;
foreach ($plugins as $plugin) if (plugin_enabled($plugin, $states)) $activePlugins++;
$settings = site_settings();
$audioData = storage_read_json('audio.json', ['items' => []]);
$audioItems = is_array($audioData['items'] ?? null) ? $audioData['items'] : [];
$audioPublished = 0;
foreach ($audioItems as $audioItem) {
    if (!empty($audioItem['published'])) $audioPublished++;
}
$requestData = storage_read_json('requests.json', ['items' => []]);
$requestItems = is_array($requestData['items'] ?? null) ? $requestData['items'] : [];
$requestPending = 0;
foreach ($requestItems as $requestItem) {
    if (is_array($requestItem) && (string)($requestItem['status'] ?? 'pending') === 'pending') $requestPending++;
}
$recent = audit_recent(8);

admin_header('Обзор', 'dashboard');
?>
<div class="page-head">
  <div>
    <h1>Обзор</h1>
    <p>Центр управления DAG STUDIO. Здесь будут собираться настройки сайта, функции, плагины и служебные инструменты.</p>
  </div>
</div>

<div class="grid stats-grid">
  <div class="stat-card"><small>Плагины</small><strong><?= count($plugins) ?></strong><span><?= $activePlugins ?> активных</span></div>
  <div class="stat-card"><small>Аудио</small><strong><?= count($audioItems) ?></strong><span><?= $audioPublished ?> опубликовано</span></div>
  <div class="stat-card"><small>Обращения</small><strong><?= count($requestItems) ?></strong><span><?= $requestPending ?> новых</span></div>
  <div class="stat-card"><small>Сайт</small><strong>ONLINE</strong><span><?= e($settings['site_name']) ?></span></div>
</div>

<div class="grid content-grid">
  <section class="panel">
    <h2>Быстрые действия</h2>
    <div class="quick-actions">
      <a href="/admin/requests.php"><strong>Обращения<?= $requestPending > 0 ? ' · ' . $requestPending : '' ?></strong><span>Проверять новые заявки, одобрять и отклонять</span></a>
      <?php if (isset($plugins['audio']) && plugin_enabled($plugins['audio'], $states)): ?><a href="/admin/plugin.php?id=audio"><strong>Аудио</strong><span>Загружать, редактировать и публиковать треки</span></a><?php endif; ?>
      <a href="/admin/plugins.php"><strong>Функции и плагины</strong><span>Включать и отключать установленные модули</span></a>
      <a href="/admin/settings.php"><strong>Настройки сайта</strong><span>Контакты и основные данные проекта</span></a>
      <a href="/" target="_blank" rel="noopener"><strong>Открыть сайт</strong><span>Посмотреть публичную версию в новой вкладке</span></a>
      <a href="/admin/plugin.php?id=system-info"><strong>Состояние системы</strong><span>PHP, каталог хранения и доступность записи</span></a>
    </div>
  </section>

  <section class="panel">
    <h2>Последние действия</h2>
    <div class="activity">
      <?php if (!$recent): ?><div class="empty">История пока пуста.</div><?php endif; ?>
      <?php foreach ($recent as $row): ?>
        <div class="activity-row">
          <time><?= e(isset($row['time']) ? date('d.m.Y H:i', strtotime((string)$row['time'])) : '') ?></time>
          <span><?= e((string)($row['action'] ?? '')) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
</div>
<?php admin_footer(); ?>