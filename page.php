<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/security.php';
ds_public_security_headers();

require __DIR__ . '/includes/bbcode.php';

$defaults = [
    'site_name' => 'DAG STUDIO',
    'email' => 'admin@dagstudio.ru',
    'phone' => '+7 (928) 809-50-18',
    'location' => "Россия, Дагестан\nг. Махачкала",
    'telegram' => '',
    'whatsapp' => '',
    'behance' => '',
    'theme_scheme' => 'copper',
    'theme_accent' => '#c96f41',
    'theme_bg' => '#050505',
    'theme_panel' => '#1c1c1c',
    'theme_text' => '#f7f7f5',
    'logo_dark' => '/assets/img/logo-horizontal-dark.svg',
    'logo_mark' => '/assets/img/logo-mark-square.svg',
    'main_menu' => [
        ['label' => 'Главная', 'url' => '/#top', 'visible' => true, 'new_tab' => false],
        ['label' => 'Наши работы', 'url' => '/works.php', 'visible' => true, 'new_tab' => false],
        ['label' => 'Наши аудио', 'url' => '/audio.php', 'visible' => true, 'new_tab' => false],
        ['label' => 'Скрипты', 'url' => '/#services', 'visible' => true, 'new_tab' => false],
        ['label' => 'О студии', 'url' => '/#about', 'visible' => true, 'new_tab' => false],
    ],
];

$settings = $defaults;
$settingsFile = __DIR__ . '/storage/settings.json';
if (is_file($settingsFile)) {
    $raw = @file_get_contents($settingsFile);
    $decoded = $raw !== false ? json_decode($raw, true) : null;
    if (is_array($decoded)) $settings = array_merge($settings, $decoded);
}

foreach (['telegram', 'whatsapp', 'behance'] as $socialKey) {
    $socialValue = trim((string)($settings[$socialKey] ?? ''));
    if ($socialValue !== '' && (!filter_var($socialValue, FILTER_VALIDATE_URL) || !preg_match('#^https://#i', $socialValue))) {
        $settings[$socialKey] = '';
    }
}

function h_page(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function page_hex(string $value, string $fallback): string {
    return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : $fallback;
}
function page_asset(string $value, string $fallback): string {
    if ($value === '' || str_contains($value, '..') || !str_starts_with($value, '/')) return $fallback;
    if (!str_starts_with($value, '/assets/') && !str_starts_with($value, '/uploads/branding/')) return $fallback;
    return $value;
}
function page_menu_url(string $value): string {
    $value = trim($value);
    if (str_starts_with($value, '/') || str_starts_with($value, '#')) return $value;
    if (preg_match('#^https?://#i', $value)) return $value;
    return '#';
}

$pluginStates = [];
$pluginsFile = __DIR__ . '/storage/plugins.json';
if (is_file($pluginsFile)) {
    $raw = @file_get_contents($pluginsFile);
    $decoded = $raw !== false ? json_decode($raw, true) : null;
    if (is_array($decoded)) $pluginStates = $decoded;
}
$pagesEnabled = !array_key_exists('pages', $pluginStates) || !empty($pluginStates['pages']);
$audioEnabled = !array_key_exists('audio', $pluginStates) || !empty($pluginStates['audio']);
$worksEnabled = !array_key_exists('works', $pluginStates) || !empty($pluginStates['works']);
if (!$pagesEnabled) {
    http_response_code(404);
    exit('Страница не найдена.');
}

$slug = mb_strtolower(trim((string)($_GET['slug'] ?? '')));
$slug = preg_replace('/[^a-z0-9а-яё_-]+/ui', '-', $slug) ?? '';
$slug = trim($slug, '-_');

$dataFile = __DIR__ . '/storage/pages.json';
$items = [];
if (is_file($dataFile)) {
    $raw = @file_get_contents($dataFile);
    $decoded = $raw !== false ? json_decode($raw, true) : null;
    if (is_array($decoded) && is_array($decoded['items'] ?? null)) $items = $decoded['items'];
}

$page = null;
foreach ($items as $item) {
    if (!is_array($item)) continue;
    if ((string)($item['slug'] ?? '') === $slug && !empty($item['published'])) {
        $page = $item;
        break;
    }
}
if (!is_array($page)) {
    http_response_code(404);
    $page = [
        'title' => 'Страница не найдена',
        'seo_description' => 'Запрошенная страница не найдена.',
        'content' => '[h2]404[/h2]\nСтраница не существует или временно недоступна.',
        'slug' => '',
    ];
}


function page_portfolio_menu(array $menu): array {
    foreach ($menu as &$item) {
        if (!is_array($item)) continue;
        if (mb_strtolower(trim((string)($item['label'] ?? ''))) === 'блог') {
            $item['label'] = 'Наши работы';
            $item['url'] = '/works.php';
            $item['visible'] = true;
            $item['new_tab'] = false;
        }
    }
    unset($item);
    return $menu;
}

$settings['main_menu'] = page_portfolio_menu(is_array($settings['main_menu'] ?? null) ? $settings['main_menu'] : []);
$themeAccent = page_hex((string)$settings['theme_accent'], '#c96f41');
$themeBg = page_hex((string)$settings['theme_bg'], '#050505');
$themePanel = page_hex((string)$settings['theme_panel'], '#1c1c1c');
$themeText = page_hex((string)$settings['theme_text'], '#f7f7f5');
$logoDark = page_asset((string)$settings['logo_dark'], '/assets/img/logo-horizontal-dark.svg');
$logoMark = page_asset((string)$settings['logo_mark'], '/assets/img/logo-mark-square.svg');
$title = trim((string)($page['title'] ?? 'Страница'));
$description = trim((string)($page['seo_description'] ?? ''));
if ($description === '') $description = $title . ' — DAG STUDIO';
$contentHtml = ds_bbcode_render((string)($page['content'] ?? ''));
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="<?= h_page($themeBg) ?>">
  <meta name="description" content="<?= h_page($description) ?>">
  <title><?= h_page($title) ?> — <?= h_page((string)$settings['site_name']) ?></title>
  <link rel="icon" href="<?= h_page($logoMark) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600;700&family=Roboto+Condensed:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css">
  <style>:root{--accent:<?= h_page($themeAccent) ?>;--bg:<?= h_page($themeBg) ?>;--bg-soft:<?= h_page($themeBg) ?>;--panel:<?= h_page($themePanel) ?>;--text:<?= h_page($themeText) ?>}</style>
</head>
<body>
<header class="site-header" id="top">
  <div class="container header-inner">
    <a class="brand" href="/" aria-label="DAG STUDIO — главная"><img src="<?= h_page($logoDark) ?>" alt="DAG STUDIO"></a>
    <button class="menu-toggle" type="button" aria-label="Открыть меню" aria-expanded="false"><span></span><span></span><span></span></button>
    <nav class="main-nav" aria-label="Главная навигация">
      <?php foreach (($settings['main_menu'] ?? []) as $menuItem):
        if (!is_array($menuItem) || empty($menuItem['visible'])) continue;
        $menuUrl = page_menu_url((string)($menuItem['url'] ?? '#'));
        $path = parse_url($menuUrl, PHP_URL_PATH);
        if (!$audioEnabled && $path === '/audio.php') continue;
        if (!$pagesEnabled && $path === '/page.php') continue;
        if (!$worksEnabled && $path === '/works.php') continue;
        $menuLabel = trim((string)($menuItem['label'] ?? ''));
        if ($menuLabel === '') continue;
        $newTab = !empty($menuItem['new_tab']);
        $isActive = $path === '/page.php' && str_contains($menuUrl, 'slug=' . rawurlencode($slug));
      ?>
        <a<?= $isActive ? ' class="active"' : '' ?> href="<?= h_page($menuUrl) ?>"<?= $newTab ? ' target="_blank" rel="noopener"' : '' ?>><?= h_page($menuLabel) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="admin-access" data-admin-access>
      <a class="login-btn admin-access-trigger" href="/admin/" data-admin-trigger aria-haspopup="true" aria-expanded="false">
        <span class="admin-access-dot" aria-hidden="true"></span>
        <span data-admin-label>Вход</span>
        <span class="admin-access-chevron" aria-hidden="true">⌄</span>
      </a>
      <div class="admin-access-menu" data-admin-menu hidden>
        <a href="/admin/"><span class="admin-access-menu-icon">▦</span><span><strong>Админка</strong><small>Панель управления</small></span></a>
        <a class="admin-access-logout" href="/admin/logout.php?return=site"><span class="admin-access-menu-icon">↪</span><span><strong>Выйти</strong><small>Завершить сеанс</small></span></a>
      </div>
    </div>
  </div>
</header>

<main>
  <section class="static-page-hero">
    <div class="container static-page-hero-inner">
      <p class="eyebrow">DAG STUDIO</p>
      <h1><?= h_page($title) ?></h1>
      <?php if ($description !== ''): ?><p><?= h_page($description) ?></p><?php endif; ?>
    </div>
  </section>
  <section class="static-page-section">
    <div class="container"><article class="static-page-content"><?= $contentHtml ?></article></div>
  </section>
</main>

<footer class="site-footer">
  <div class="container footer-grid">
    <div class="footer-brand"><img src="<?= h_page($logoDark) ?>" alt="DAG STUDIO"><p>Digital-ателье.<br>Шьем сайты по лекалам высоких технологий.</p></div>
    <div class="footer-col"><h4>Навигация</h4><a href="/">Главная</a><a href="/#services">Услуги</a><a href="/#contacts">Контакты</a></div>
    <div class="footer-col"><h4>Контакты</h4><p><?= nl2br(h_page((string)$settings['location'])) ?></p>
      <?php if ($settings['telegram'] !== ''): ?><a href="<?= h_page((string)$settings['telegram']) ?>" target="_blank" rel="noopener">Telegram</a><?php endif; ?>
      <?php if ($settings['whatsapp'] !== ''): ?><a href="<?= h_page((string)$settings['whatsapp']) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
      <?php if ($settings['behance'] !== ''): ?><a href="<?= h_page((string)$settings['behance']) ?>" target="_blank" rel="noopener">Behance</a><?php endif; ?>
    </div>
  </div>
  <div class="container footer-bottom"><span>© 2026 DAGSTUDIO. All rights reserved.</span></div>
</footer>
<script src="/assets/js/main.js"></script>
</body>
</html>
