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
    $requestData = storage_read_json('requests.json', ['items' => []]);
    $requestPending = 0;
    foreach (($requestData['items'] ?? []) as $requestItem) {
        if (is_array($requestItem) && (string)($requestItem['status'] ?? 'pending') === 'pending') $requestPending++;
    }
    $accent = setting_color($settings, 'theme_accent', '#c96f41');
    $bg = setting_color($settings, 'theme_bg', '#050505');
    $panel = setting_color($settings, 'theme_panel', '#1c1c1c');
    $text = setting_color($settings, 'theme_text', '#f7f7f5');
    $adminLogo = setting_asset($settings, 'logo_admin', '/assets/img/logo-horizontal-dark.svg');
    $markLogo = setting_asset($settings, 'logo_mark', '/assets/img/logo-mark-square.svg');
    $sidebarItems = admin_sidebar_available_items($plugins, $states, $requestPending);
    $sidebarOrder = admin_sidebar_order($sidebarItems);
    ?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
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
    <div class="sidebar-nav-head">
      <span>Навигация</span>
      <a class="sidebar-order-edit<?= $active === 'sidebar-menu' ? ' active' : '' ?>" href="/admin/sidebar-menu.php" aria-label="Изменить порядок меню" title="Изменить порядок меню">
        <span class="sidebar-order-glyph" aria-hidden="true"><i></i><i></i><i></i></span>
      </a>
    </div>
    <nav class="admin-nav">
      <?php foreach ($sidebarOrder as $sidebarId):
        $item = $sidebarItems[$sidebarId] ?? null;
        if (!is_array($item)) continue;
      ?>
        <a class="admin-nav-link nav-item-<?= e($sidebarId) ?><?= $active === $sidebarId ? ' active' : '' ?>" href="<?= e((string)$item['href']) ?>">
          <span class="admin-nav-icon"><?= e((string)$item['icon']) ?></span>
          <span class="admin-nav-label"><?= e((string)$item['label']) ?></span>
          <?php if ((string)$item['counter'] !== ''): ?><b class="nav-counter"><?= e((string)$item['counter']) ?></b><?php endif; ?>
        </a>
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
