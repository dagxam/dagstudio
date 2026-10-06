<?php
declare(strict_types=1);

$defaults = [
    'site_name' => 'DAG STUDIO',
    'email' => 'admin@dagstudio.ru',
    'phone' => '+7 (928) 809-50-18',
    'location' => "Россия, Дагестан\nг. Махачкала",
    'telegram' => '',
    'whatsapp' => '',
    'behance' => '',
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

function h_works(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function works_hex(string $value, string $fallback): string {
    return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : $fallback;
}
function works_asset(string $value, string $fallback): string {
    if ($value === '' || str_contains($value, '..') || !str_starts_with($value, '/')) return $fallback;
    if (!str_starts_with($value, '/assets/') && !str_starts_with($value, '/uploads/branding/')) return $fallback;
    return $value;
}
function works_menu_url(string $value): string {
    $value = trim($value);
    if (str_starts_with($value, '/') || str_starts_with($value, '#')) return $value;
    if (preg_match('#^https?://#i', $value)) return $value;
    return '#';
}
function works_portfolio_menu(array $menu): array {
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

$settings['main_menu'] = works_portfolio_menu(is_array($settings['main_menu'] ?? null) ? $settings['main_menu'] : $defaults['main_menu']);
$themeAccent = works_hex((string)$settings['theme_accent'], '#c96f41');
$themeBg = works_hex((string)$settings['theme_bg'], '#050505');
$themePanel = works_hex((string)$settings['theme_panel'], '#1c1c1c');
$themeText = works_hex((string)$settings['theme_text'], '#f7f7f5');
$logoDark = works_asset((string)$settings['logo_dark'], '/assets/img/logo-horizontal-dark.svg');
$logoMark = works_asset((string)$settings['logo_mark'], '/assets/img/logo-mark-square.svg');

$audioEnabled = true;
$pagesEnabled = true;
$stateFile = __DIR__ . '/storage/plugins.json';
if (is_file($stateFile)) {
    $raw = @file_get_contents($stateFile);
    $states = $raw !== false ? json_decode($raw, true) : null;
    if (is_array($states)) {
        if (array_key_exists('audio', $states)) $audioEnabled = (bool)$states['audio'];
        if (array_key_exists('pages', $states)) $pagesEnabled = (bool)$states['pages'];
    }
}

$works = [
    [
        'number' => '01',
        'title' => 'АХИХЪАН',
        'domain' => 'akhikhan.ru',
        'url' => 'https://akhikhan.ru/',
        'category' => 'Сетевое издание',
        'description' => 'Новостной сайт районного издания: публикации, документы, фотоматериалы и электронные выпуски.',
        'mark' => 'АХ',
    ],
    [
        'number' => '02',
        'title' => 'UROVIA',
        'domain' => 'urovia.ru',
        'url' => 'https://urovia.ru/login.html',
        'category' => 'Образовательная платформа',
        'description' => 'Цифровая платформа для учителей и учеников: задания, выполнение работ, результаты и учебный журнал.',
        'mark' => 'UR',
    ],
    [
        'number' => '03',
        'title' => 'DAG STUDIO GAME',
        'domain' => 'game.dagstudio.ru',
        'url' => 'https://game.dagstudio.ru/',
        'category' => 'Интерактивный проект',
        'description' => 'Отдельный игровой веб-проект в инфраструктуре DAG STUDIO с собственным интерфейсом и логикой.',
        'mark' => 'DG',
    ],
    [
        'number' => '04',
        'title' => 'Дахадаевский район',
        'domain' => 'mo-urkarakh.ru',
        'url' => 'http://mo-urkarakh.ru/',
        'category' => 'Муниципальный сайт',
        'description' => 'Официальный информационный ресурс муниципального образования: новости, документы и сервисные разделы.',
        'mark' => 'МО',
    ],
    [
        'number' => '05',
        'title' => 'Школа искусств',
        'domain' => 'dshi-im-g-magomedovicha.ru',
        'url' => 'https://dshi-im-g-magomedovicha.ru/',
        'category' => 'Образование',
        'description' => 'Сайт школы искусств Унцукульского района с новостями учреждения и информационными разделами.',
        'mark' => 'ДШ',
    ],
    [
        'number' => '06',
        'title' => 'Зуберха.ру',
        'domain' => 'zuberkha.ru',
        'url' => 'http://zuberkha.ru/',
        'category' => 'Сетевое издание',
        'description' => 'Интернет-издание с муниципальными и республиканскими новостями, документами и выпусками газеты.',
        'mark' => 'ЗБ',
    ],
];
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="<?= h_works($themeBg) ?>">
  <meta name="description" content="Наши работы — проекты и сайты DAG STUDIO.">
  <title>Наши работы — <?= h_works((string)$settings['site_name']) ?></title>
  <link rel="icon" href="<?= h_works($logoMark) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600;700&family=Roboto+Condensed:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css">
  <style>:root{--accent:<?= h_works($themeAccent) ?>;--bg:<?= h_works($themeBg) ?>;--bg-soft:<?= h_works($themeBg) ?>;--panel:<?= h_works($themePanel) ?>;--text:<?= h_works($themeText) ?>}</style>
</head>
<body>
<header class="site-header" id="top">
  <div class="container header-inner">
    <a class="brand" href="/" aria-label="DAG STUDIO — главная"><img src="<?= h_works($logoDark) ?>" alt="DAG STUDIO"></a>
    <button class="menu-toggle" type="button" aria-label="Открыть меню" aria-expanded="false"><span></span><span></span><span></span></button>
    <nav class="main-nav" aria-label="Главная навигация">
      <?php foreach (($settings['main_menu'] ?? []) as $menuItem):
        if (!is_array($menuItem) || empty($menuItem['visible'])) continue;
        $menuUrl = works_menu_url((string)($menuItem['url'] ?? '#'));
        $path = parse_url($menuUrl, PHP_URL_PATH);
        if (!$audioEnabled && $path === '/audio.php') continue;
        if (!$pagesEnabled && $path === '/page.php') continue;
        $label = trim((string)($menuItem['label'] ?? ''));
        if ($label === '') continue;
        $active = $path === '/works.php';
        $newTab = !empty($menuItem['new_tab']);
      ?>
        <a<?= $active ? ' class="active"' : '' ?> href="<?= h_works($menuUrl) ?>"<?= $newTab ? ' target="_blank" rel="noopener"' : '' ?>><?= h_works($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <a class="login-btn" href="/admin/">Вход</a>
  </div>
</header>

<main>
  <section class="works-hero">
    <div class="container works-hero-grid">
      <div>
        <p class="eyebrow">Портфолио DAG STUDIO</p>
        <h1>Наши<br><span>работы</span></h1>
      </div>
      <div class="works-hero-copy">
        <strong>06 проектов</strong>
        <p>Сайты и цифровые продукты для образования, муниципальных организаций, сетевых изданий и собственных проектов.</p>
      </div>
    </div>
  </section>

  <section class="works-section">
    <div class="container">
      <div class="works-intro">
        <span>Выбранные проекты</span>
        <p>Каждая работа — отдельная задача, структура и визуальный характер. Нажмите на карточку, чтобы открыть проект.</p>
      </div>

      <div class="works-grid">
        <?php foreach ($works as $work): ?>
          <a class="work-card reveal" href="<?= h_works($work['url']) ?>" target="_blank" rel="noopener">
            <div class="work-card-top">
              <span class="work-number"><?= h_works($work['number']) ?></span>
              <span class="work-category"><?= h_works($work['category']) ?></span>
              <span class="work-arrow">↗</span>
            </div>
            <div class="work-browser">
              <div class="work-browser-bar"><i></i><i></i><i></i><span><?= h_works($work['domain']) ?></span></div>
              <div class="work-browser-screen">
                <div class="work-mark"><?= h_works($work['mark']) ?></div>
                <div class="work-lines"><b></b><b></b><b></b><em></em></div>
              </div>
            </div>
            <div class="work-card-copy">
              <h2><?= h_works($work['title']) ?></h2>
              <p><?= h_works($work['description']) ?></p>
              <div class="work-domain"><?= h_works($work['domain']) ?><span>Открыть сайт</span></div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="works-cta">
    <div class="container works-cta-inner">
      <div><span>Следующий проект</span><h2>Может быть вашим</h2></div>
      <a class="btn btn-primary" href="/#contacts">Обсудить проект</a>
    </div>
  </section>
</main>

<footer class="site-footer">
  <div class="container footer-grid">
    <div class="footer-brand"><img src="<?= h_works($logoDark) ?>" alt="DAG STUDIO"><p>Digital-ателье.<br>Шьем сайты по лекалам высоких технологий.</p></div>
    <div class="footer-col"><h4>Навигация</h4><a href="/">Главная</a><a href="/works.php">Наши работы</a><a href="/#services">Услуги</a><a href="/#contacts">Контакты</a></div>
    <div class="footer-col"><h4>Контакты</h4><p><?= nl2br(h_works((string)$settings['location'])) ?></p>
      <?php if ($settings['telegram'] !== ''): ?><a href="<?= h_works((string)$settings['telegram']) ?>" target="_blank" rel="noopener">Telegram</a><?php endif; ?>
      <?php if ($settings['whatsapp'] !== ''): ?><a href="<?= h_works((string)$settings['whatsapp']) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
      <?php if ($settings['behance'] !== ''): ?><a href="<?= h_works((string)$settings['behance']) ?>" target="_blank" rel="noopener">Behance</a><?php endif; ?>
    </div>
  </div>
  <div class="container footer-bottom"><span>© 2026 DAGSTUDIO. All rights reserved.</span></div>
</footer>
<script src="/assets/js/main.js"></script>
</body>
</html>
