<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/scripts-data.php';
ds_public_security_headers();

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
        ['label' => 'Скрипты', 'url' => '/scripts.php', 'visible' => true, 'new_tab' => false],
        ['label' => 'О студии', 'url' => '/about.php', 'visible' => true, 'new_tab' => false],
    ],
];

$settings = $defaults;
$settingsFile = __DIR__ . '/storage/settings.json';
if (is_file($settingsFile)) {
    $raw = @file_get_contents($settingsFile);
    $decoded = $raw !== false ? json_decode($raw, true) : null;
    if (is_array($decoded)) $settings = array_merge($settings, $decoded);
}

function h_scripts(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function scripts_hex(string $value, string $fallback): string {
    return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : $fallback;
}
function scripts_asset(string $value, string $fallback): string {
    if ($value === '' || str_contains($value, '..') || !str_starts_with($value, '/')) return $fallback;
    if (!str_starts_with($value, '/assets/') && !str_starts_with($value, '/uploads/branding/')) return $fallback;
    return $value;
}
function scripts_menu_url(string $value): string {
    $value = trim($value);
    if (str_starts_with($value, '/') || str_starts_with($value, '#')) return $value;
    if (preg_match('#^https?://#i', $value)) return $value;
    return '#';
}
function scripts_normalize_menu(array $menu): array {
    foreach ($menu as &$item) {
        if (!is_array($item)) continue;
        $label = mb_strtolower(trim((string)($item['label'] ?? '')));
        if ($label === 'блог') {
            $item['label'] = 'Наши работы';
            $item['url'] = '/works.php';
        }
        if ($label === 'скрипты') {
            $item['label'] = 'Скрипты';
            $item['url'] = '/scripts.php';
            $item['visible'] = true;
            $item['new_tab'] = false;
        }
        if ($label === 'о студии') {
            $item['label'] = 'О студии';
            $item['url'] = '/about.php';
            $item['visible'] = true;
            $item['new_tab'] = false;
        }
    }
    unset($item);
    return $menu;
}

$settings['main_menu'] = scripts_normalize_menu(is_array($settings['main_menu'] ?? null) ? $settings['main_menu'] : $defaults['main_menu']);
$themeAccent = scripts_hex((string)$settings['theme_accent'], '#c96f41');
$themeBg = scripts_hex((string)$settings['theme_bg'], '#050505');
$themePanel = scripts_hex((string)$settings['theme_panel'], '#1c1c1c');
$themeText = scripts_hex((string)$settings['theme_text'], '#f7f7f5');
$logoDark = scripts_asset((string)$settings['logo_dark'], '/assets/img/logo-horizontal-dark.svg');
$logoMark = scripts_asset((string)$settings['logo_mark'], '/assets/img/logo-mark-square.svg');

$states = [];
$stateFile = __DIR__ . '/storage/plugins.json';
if (is_file($stateFile)) {
    $raw = @file_get_contents($stateFile);
    $decoded = $raw !== false ? json_decode($raw, true) : null;
    if (is_array($decoded)) $states = $decoded;
}
$scriptsEnabled = !array_key_exists('scripts', $states) || !empty($states['scripts']);
$audioEnabled = !array_key_exists('audio', $states) || !empty($states['audio']);
$worksEnabled = !array_key_exists('works', $states) || !empty($states['works']);
$pagesEnabled = !array_key_exists('pages', $states) || !empty($states['pages']);
if (!$scriptsEnabled) {
    http_response_code(404);
    exit('Раздел «Скрипты» отключён.');
}

$items = array_values(array_filter(ds_scripts_read(__DIR__), static fn($item) => is_array($item) && !empty($item['published'])));
usort($items, static function(array $a, array $b): int {
    $fa = !empty($a['featured']) ? 1 : 0;
    $fb = !empty($b['featured']) ? 1 : 0;
    if ($fa !== $fb) return $fb <=> $fa;
    return strcmp((string)($b['updated_at'] ?? ''), (string)($a['updated_at'] ?? ''));
});
$count = count($items);
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="<?= h_scripts($themeBg) ?>">
  <meta name="description" content="Скрипты DAG STUDIO — готовые модули, темы и инструменты для сайтов, CMS и веб-проектов.">
  <title>Скрипты и готовые решения — DAG STUDIO</title>
  <link rel="icon" href="<?= h_scripts($logoMark) ?>">
  <?php ds_seo_render([
    'title' => 'Скрипты и готовые решения — DAG STUDIO',
    'description' => 'Скрипты DAG STUDIO — готовые модули, темы и инструменты для сайтов, CMS и веб-проектов.',
    'path' => '/scripts.php',
    'image' => $logoMark,
    'schema_type' => 'CollectionPage',
    'breadcrumb' => 'Скрипты',
], $settings); ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600;700&family=Roboto+Condensed:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= h_scripts(ds_asset_url(__DIR__, '/assets/css/style.css')) ?>">
  <style>:root{--accent:<?= h_scripts($themeAccent) ?>;--bg:<?= h_scripts($themeBg) ?>;--bg-soft:<?= h_scripts($themeBg) ?>;--panel:<?= h_scripts($themePanel) ?>;--text:<?= h_scripts($themeText) ?>}</style>
</head>
<body class="scripts-page">
<header class="site-header" id="top">
  <div class="container header-inner">
    <a class="brand" href="/" aria-label="DAG STUDIO — главная"><img src="<?= h_scripts($logoDark) ?>" alt="DAG STUDIO"></a>
    <button class="menu-toggle" type="button" aria-label="Открыть меню" aria-expanded="false"><span></span><span></span><span></span></button>
    <nav class="main-nav" aria-label="Главная навигация">
      <?php foreach (($settings['main_menu'] ?? []) as $menuItem):
        if (!is_array($menuItem) || empty($menuItem['visible'])) continue;
        $menuUrl = scripts_menu_url((string)($menuItem['url'] ?? '#'));
        $path = parse_url($menuUrl, PHP_URL_PATH);
        if (!$audioEnabled && $path === '/audio.php') continue;
        if (!$worksEnabled && $path === '/works.php') continue;
        if (!$pagesEnabled && $path === '/page.php') continue;
        $label = trim((string)($menuItem['label'] ?? ''));
        if ($label === '') continue;
        $active = $path === '/scripts.php';
        $newTab = !empty($menuItem['new_tab']);
      ?>
        <a<?= $active ? ' class="active"' : '' ?> href="<?= h_scripts($menuUrl) ?>"<?= $newTab ? ' target="_blank" rel="noopener"' : '' ?>><?= h_scripts($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="admin-access" data-admin-access>
      <a class="login-btn admin-access-trigger" href="/admin/" data-admin-trigger aria-haspopup="true" aria-expanded="false">
        <span class="admin-access-dot" aria-hidden="true"></span><span data-admin-label>Вход</span><span class="admin-access-chevron" aria-hidden="true">⌄</span>
      </a>
      <div class="admin-access-menu" data-admin-menu hidden>
        <a href="/admin/"><span class="admin-access-menu-icon">▦</span><span><strong>Админка</strong><small>Панель управления</small></span></a>
        <a class="admin-access-logout" href="/admin/logout.php?return=site"><span class="admin-access-menu-icon">↪</span><span><strong>Выйти</strong><small>Завершить сеанс</small></span></a>
      </div>
    </div>
  </div>
</header>

<main>
  <section class="scripts-hero">
    <div class="container scripts-hero-grid">
      <div>
        <p class="eyebrow">DAG STUDIO · CODE LIBRARY</p>
        <h1>Скрипты<br><span>& решения</span></h1>
      </div>
      <div class="scripts-hero-copy">
        <strong><?= $count ?> <?= $count === 1 ? 'решение' : ($count >= 2 && $count <= 4 ? 'решения' : 'решений') ?></strong>
        <p>Готовые модули, темы и инструменты из нашей разработки. Скачивайте архив и адаптируйте решение под свой проект.</p>
      </div>
    </div>
  </section>

  <section class="scripts-catalog">
    <div class="container">
      <div class="scripts-intro">
        <div><span>Готовые решения</span><h2>Библиотека DAG STUDIO</h2></div>
        <p>Перед установкой стороннего модуля или темы рекомендуем создать резервную копию сайта и проверить совместимость с вашей версией CMS.</p>
      </div>

      <?php if (!$items): ?>
        <div class="scripts-empty"><span></span><h3>Каталог пока пуст</h3><p>Новые разработки появятся здесь после публикации.</p></div>
      <?php endif; ?>

      <div class="scripts-grid">
        <?php foreach ($items as $index => $item):
          $compat = mb_strtolower((string)($item['compatibility'] ?? ''));
          $kind = str_contains($compat, 'wordpress') ? 'wordpress' : (str_contains($compat, 'dle') && !str_contains($compat, 'web') ? 'dle' : 'accessibility');
          $ext = strtoupper(pathinfo((string)($item['original_name'] ?? ''), PATHINFO_EXTENSION));
          $price = ds_script_price_value($item['price'] ?? 0);
          $paymentUrl = ds_script_payment_url((string)($item['payment_url'] ?? ''));
          $isPaid = $price > 0;
        ?>
          <article class="script-card script-card--<?= h_scripts($kind) ?><?= !empty($item['featured']) ? ' is-featured' : '' ?>">
            <div class="script-card-top">
              <span class="script-number"><?= str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
              <?php if (!empty($item['featured'])): ?><span class="script-featured">Рекомендуем</span><?php endif; ?>
              <span class="script-format"><?= h_scripts($ext !== '' ? $ext : 'ARCHIVE') ?></span>
            </div>
            <div class="script-visual" aria-hidden="true">
              <?php if ($kind === 'wordpress'): ?>
                <div class="script-phone"><i></i><b></b><span></span><em></em></div>
              <?php elseif ($kind === 'dle'): ?>
                <div class="script-album"><i></i><i></i><i></i><i></i><b></b></div>
              <?php else: ?>
                <div class="script-access"><b>A</b><i></i><span></span></div>
              <?php endif; ?>
            </div>
            <div class="script-card-body">
              <div class="script-tags">
                <?php if (!empty($item['category'])): ?><span><?= h_scripts((string)$item['category']) ?></span><?php endif; ?>
                <?php if (!empty($item['compatibility'])): ?><span><?= h_scripts((string)$item['compatibility']) ?></span><?php endif; ?>
              </div>
              <h2><?= h_scripts((string)$item['title']) ?></h2>
              <p><?= h_scripts((string)$item['description']) ?></p>
              <div class="script-meta">
                <span><small>Версия</small><strong><?= h_scripts((string)($item['version'] ?: '—')) ?></strong></span>
                <span><small>Размер</small><strong><?= h_scripts(ds_script_size_label((int)$item['size'])) ?></strong></span>
              </div>
              <div class="script-commerce <?= $isPaid ? 'is-paid' : 'is-free' ?>">
                <div class="script-price"><small><?= $isPaid ? 'Стоимость' : 'Доступ' ?></small><strong><?= h_scripts(ds_script_price_label($price)) ?></strong></div>
                <?php if ($isPaid && $paymentUrl !== ''): ?>
                  <a class="script-buy" href="<?= h_scripts($paymentUrl) ?>" target="_blank" rel="noopener noreferrer"><span></span>Купить</a>
                <?php else: ?>
                  <a class="script-download" href="/script-download.php?id=<?= rawurlencode((string)$item['id']) ?>"><span></span>Скачать архив</a>
                <?php endif; ?>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="scripts-cta">
    <div class="container scripts-cta-inner">
      <div><span>Нужна доработка?</span><h2>Адаптируем под ваш сайт</h2><p>Поможем установить, изменить или разработать отдельный модуль под вашу CMS.</p></div>
      <a class="btn btn-primary" href="/#contacts">Обсудить задачу</a>
    </div>
  </section>
</main>

<footer class="site-footer">
  <div class="container footer-grid">
    <div class="footer-brand"><img src="<?= h_scripts($logoDark) ?>" alt="DAG STUDIO"><p>Digital-ателье.<br>Шьем сайты по лекалам высоких технологий.</p></div>
    <div class="footer-col"><h4>Навигация</h4><a href="/">Главная</a><a href="/works.php">Наши работы</a><a href="/audio.php">Наши аудио</a><a href="/scripts.php">Скрипты</a></div>
    <div class="footer-col"><h4>Контакты</h4><p><?= nl2br(h_scripts((string)$settings['location'])) ?></p>
      <?php if ($settings['telegram'] !== ''): ?><a href="<?= h_scripts((string)$settings['telegram']) ?>" target="_blank" rel="noopener">Telegram</a><?php endif; ?>
      <?php if ($settings['whatsapp'] !== ''): ?><a href="<?= h_scripts((string)$settings['whatsapp']) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
      <?php if ($settings['behance'] !== ''): ?><a href="<?= h_scripts((string)$settings['behance']) ?>" target="_blank" rel="noopener">Behance</a><?php endif; ?>
    </div>
  </div>
  <div class="container footer-bottom"><span>© 2026 DAGSTUDIO. All rights reserved.</span></div>
</footer>

<script src="<?= h_scripts(ds_asset_url(__DIR__, '/assets/js/main.js')) ?>"></script>
</body>
</html>
