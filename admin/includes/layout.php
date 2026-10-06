<?php
declare(strict_types=1);

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    http_response_code(404);
    exit;
}

function admin_header(string $title, string $active = ''): void {
    $settings = site_settings();
    $flashes = take_flashes();
    $plugins = plugin_definitions();
    $states = plugin_states();
    $accent = setting_color($settings, 'theme_accent', '#c96f41');
    $bg = setting_color($settings, 'theme_bg', '#050505');
    $panel = setting_color($settings, 'theme_panel', '#1c1c1c');
    $text = setting_color($settings, 'theme_text', '#f7f7f5');
    $adminLogo = setting_asset($settings, 'logo_admin', '/assets/img/logo-horizontal-dark.svg');
    $markLogo = setting_asset($settings, 'logo_mark', '/assets/img/logo-mark-square.svg');
    ?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="theme-color" content="<?= e($bg) ?>">
  <meta name="robots" content="noindex,nofollow">
  <title><?= e($title) ?> — DAG STUDIO</title>
  <link rel="icon" href="<?= e($markLogo) ?>">
  <link rel="stylesheet" href="/admin/assets/admin.css">
  <style>:root{--admin-accent:<?= e($accent) ?>;--admin-bg:<?= e($bg) ?>;--admin-panel:<?= e($panel) ?>;--admin-text:<?= e($text) ?>}</style>
</head>
<body>
<div class="admin-shell">
  <aside class="admin-sidebar" id="adminSidebar">
    <a class="admin-brand" href="/admin/">
      <img src="<?= e($adminLogo) ?>" alt="DAG STUDIO">
    </a>
    <nav class="admin-nav">
      <a class="<?= $active === 'dashboard' ? 'active' : '' ?>" href="/admin/"><span>◫</span>Обзор</a>
      <a class="<?= $active === 'settings' ? 'active' : '' ?>" href="/admin/settings.php"><span>⚙</span>Настройки</a>
      <a class="<?= $active === 'plugins' ? 'active' : '' ?>" href="/admin/plugins.php"><span>◆</span>Функции и плагины</a>
      <?php foreach ($plugins as $plugin): ?>
        <?php if (plugin_enabled($plugin, $states) && !empty($plugin['menu'])): ?>
          <a class="<?= $active === 'plugin-' . $plugin['id'] ? 'active' : '' ?>" href="/admin/plugin.php?id=<?= e($plugin['id']) ?>"><span><?= e((string)($plugin['icon'] ?? '+')) ?></span><?= e((string)$plugin['menu']) ?></a>
        <?php endif; ?>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar-bottom">
      <a href="/" target="_blank" rel="noopener">Открыть сайт ↗</a>
      <a href="/admin/logout.php">Выйти</a>
    </div>
  </aside>

  <div class="admin-main">
    <header class="admin-topbar">
      <button class="sidebar-toggle" type="button" aria-label="Открыть меню">☰</button>
      <div>
        <span class="topbar-kicker">Панель управления</span>
        <strong><?= e($settings['site_name']) ?></strong>
      </div>
      <div class="admin-user">
        <span class="user-dot"></span>
        <span>Администратор</span>
      </div>
    </header>

    <main class="admin-content">
      <?php foreach ($flashes as $flash): ?>
        <div class="notice <?= e((string)($flash['type'] ?? 'info')) ?>"><?= e((string)($flash['message'] ?? '')) ?></div>
      <?php endforeach; ?>
    <?php
}

function admin_footer(): void {
    ?>
    </main>
  </div>
</div>
<script src="/admin/assets/admin.js"></script>
<script src="/assets/js/audio-player.js"></script>
</body>
</html>
    <?php
}
