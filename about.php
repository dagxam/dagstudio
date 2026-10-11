<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/security.php';
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
    'hero_ornament' => '/assets/img/hero-ornament-main-v2.webp',
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

foreach (['telegram', 'whatsapp', 'behance'] as $socialKey) {
    $socialValue = trim((string)($settings[$socialKey] ?? ''));
    if ($socialValue !== '' && (!filter_var($socialValue, FILTER_VALIDATE_URL) || !preg_match('#^https://#i', $socialValue))) {
        $settings[$socialKey] = '';
    }
}

function h_about(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function about_hex(string $value, string $fallback): string {
    return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : $fallback;
}
function about_asset(string $value, string $fallback): string {
    if ($value === '' || str_contains($value, '..') || !str_starts_with($value, '/')) return $fallback;
    if (!str_starts_with($value, '/assets/') && !str_starts_with($value, '/uploads/branding/')) return $fallback;
    return $value;
}
function about_menu_url(string $value): string {
    $value = trim($value);
    if (str_starts_with($value, '/') || str_starts_with($value, '#')) return $value;
    if (preg_match('#^https?://#i', $value)) return $value;
    return '#';
}
function about_normalize_menu(array $menu): array {
    foreach ($menu as &$item) {
        if (!is_array($item)) continue;
        $label = mb_strtolower(trim((string)($item['label'] ?? '')));
        if ($label === 'блог') {
            $item['label'] = 'Наши работы';
            $item['url'] = '/works.php';
            $item['visible'] = true;
            $item['new_tab'] = false;
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

$settings['main_menu'] = about_normalize_menu(is_array($settings['main_menu'] ?? null) ? $settings['main_menu'] : $defaults['main_menu']);
$themeAccent = about_hex((string)$settings['theme_accent'], '#c96f41');
$themeBg = about_hex((string)$settings['theme_bg'], '#050505');
$themePanel = about_hex((string)$settings['theme_panel'], '#1c1c1c');
$themeText = about_hex((string)$settings['theme_text'], '#f7f7f5');
$logoDark = about_asset((string)$settings['logo_dark'], '/assets/img/logo-horizontal-dark.svg');
$logoMark = about_asset((string)$settings['logo_mark'], '/assets/img/logo-mark-square.svg');
$heroOrnament = about_asset((string)($settings['hero_ornament'] ?? ''), '/assets/img/hero-ornament-main-v2.webp');
$phoneHref = preg_replace('/[^+0-9]/', '', (string)$settings['phone']) ?: '';

$states = [];
$stateFile = __DIR__ . '/storage/plugins.json';
if (is_file($stateFile)) {
    $raw = @file_get_contents($stateFile);
    $decoded = $raw !== false ? json_decode($raw, true) : null;
    if (is_array($decoded)) $states = $decoded;
}
$audioEnabled = !array_key_exists('audio', $states) || !empty($states['audio']);
$worksEnabled = !array_key_exists('works', $states) || !empty($states['works']);
$pagesEnabled = !array_key_exists('pages', $states) || !empty($states['pages']);
$scriptsEnabled = !array_key_exists('scripts', $states) || !empty($states['scripts']);
$requestsEnabled = !array_key_exists('requests', $states) || !empty($states['requests']);
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="<?= h_about($themeBg) ?>">
  <meta name="description" content="О DAG STUDIO — цифровой студии из Дагестана: сайты, CMS, скрипты, плагины, дизайн и IT-сервис.">
  <title>О студии — <?= h_about((string)$settings['site_name']) ?></title>
  <link rel="icon" href="<?= h_about($logoMark) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600;700&family=Roboto+Condensed:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= h_about(ds_asset_url(__DIR__, '/assets/css/style.css')) ?>">
  <style>:root{--accent:<?= h_about($themeAccent) ?>;--bg:<?= h_about($themeBg) ?>;--bg-soft:<?= h_about($themeBg) ?>;--panel:<?= h_about($themePanel) ?>;--text:<?= h_about($themeText) ?>}</style>
</head>
<body class="about-page">
<header class="site-header" id="top">
  <div class="container header-inner">
    <a class="brand" href="/" aria-label="DAG STUDIO — главная"><img src="<?= h_about($logoDark) ?>" alt="DAG STUDIO"></a>
    <button class="menu-toggle" type="button" aria-label="Открыть меню" aria-expanded="false"><span></span><span></span><span></span></button>
    <nav class="main-nav" aria-label="Главная навигация">
      <?php foreach (($settings['main_menu'] ?? []) as $menuItem):
        if (!is_array($menuItem) || empty($menuItem['visible'])) continue;
        $menuUrl = about_menu_url((string)($menuItem['url'] ?? '#'));
        $path = parse_url($menuUrl, PHP_URL_PATH);
        if (!$audioEnabled && $path === '/audio.php') continue;
        if (!$worksEnabled && $path === '/works.php') continue;
        if (!$pagesEnabled && $path === '/page.php') continue;
        if (!$scriptsEnabled && $path === '/scripts.php') continue;
        $label = trim((string)($menuItem['label'] ?? ''));
        if ($label === '') continue;
        $active = $path === '/about.php';
        $newTab = !empty($menuItem['new_tab']);
      ?>
        <a<?= $active ? ' class="active"' : '' ?> href="<?= h_about($menuUrl) ?>"<?= $newTab ? ' target="_blank" rel="noopener"' : '' ?>><?= h_about($label) ?></a>
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
  <section class="about-hero">
    <div class="container about-hero-grid">
      <div class="about-hero-copy reveal">
        <p class="eyebrow">DAG STUDIO · цифровая мастерская</p>
        <h1>Дизайн<br>с характером.<br><span>Код без лишнего.</span></h1>
        <p>DAG STUDIO — студия цифровых решений из Дагестана. Создаём сайты, CMS и веб-сервисы, пишем скрипты и плагины, развиваем фирменный стиль и помогаем с IT-задачами.</p>
        <div class="about-hero-actions">
          <?php if ($worksEnabled): ?><a class="btn btn-primary" href="/works.php">Смотреть работы</a><?php endif; ?>
          <?php if ($requestsEnabled): ?><a class="btn btn-outline" href="/#contacts">Обсудить проект</a><?php endif; ?>
        </div>
      </div>
      <div class="about-hero-art reveal" aria-hidden="true">
        <div class="about-hero-art-frame"></div>
        <img src="<?= h_about(ds_asset_url(__DIR__, $heroOrnament)) ?>" alt="" loading="eager" decoding="async">
        <div class="about-hero-art-label"><span>01</span><strong>Традиция × технология</strong></div>
      </div>
    </div>
  </section>

  <section class="about-story">
    <div class="container about-story-grid">
      <div class="about-story-index"><span>О нас</span><strong>DAG<br>STUDIO</strong></div>
      <div class="about-story-copy">
        <p class="about-lead">Мы смотрим на цифровой продукт как на вещь, которая должна быть одновременно красивой, понятной и надёжной.</p>
        <div class="about-story-columns">
          <p>В дагестанском ремесле орнамент строится из точных повторяющихся элементов: каждый фрагмент имеет своё место, а итоговая композиция держится на ритме и структуре. Этот принцип близок нам и в разработке.</p>
          <p>Поэтому в проектах мы стараемся не перегружать интерфейс случайными эффектами. Сначала выстраиваем логику, архитектуру и удобство, затем добавляем визуальный характер, а после запуска продолжаем улучшать продукт.</p>
        </div>
      </div>
    </div>
  </section>

  <section class="about-principles section-black">
    <div class="container">
      <div class="about-section-head">
        <div><span>Наш подход</span><h2>Четыре принципа</h2></div>
        <p>Не делаем сайт ради картинки. Собираем систему, которой удобно пользоваться владельцу, редактору и посетителю.</p>
      </div>
      <div class="about-principles-grid">
        <article><span>01</span><h3>Смысл до декора</h3><p>Сначала задача, структура и сценарии пользователя. Дизайн усиливает продукт, а не маскирует его слабые места.</p></article>
        <article><span>02</span><h3>Система вместо хаоса</h3><p>Продумываем компоненты, роли, настройки и админку так, чтобы проект можно было развивать дальше без полной переделки.</p></article>
        <article><span>03</span><h3>Адаптивность и доступность</h3><p>Интерфейс должен нормально работать на телефоне, планшете и ПК. Учитываем читаемость, навигацию и сценарии для слабовидящих.</p></article>
        <article><span>04</span><h3>Безопасность и поддержка</h3><p>Проверяем ввод данных, права доступа, загрузки файлов и служебные разделы. После запуска можем продолжать развитие проекта.</p></article>
      </div>
    </div>
  </section>

  <section class="about-directions">
    <div class="container">
      <div class="about-section-head">
        <div><span>Что мы делаем</span><h2>От идеи до работающего продукта</h2></div>
        <p>Берём как отдельные задачи, так и проекты, где дизайн, разработка, контент и техническая часть должны работать вместе.</p>
      </div>
      <div class="about-directions-grid">
        <article class="about-direction-card"><b>WEB</b><div><h3>Сайты и CMS</h3><p>Корпоративные сайты, образовательные платформы, сайты организаций и администраций, каталоги, личные кабинеты и собственные панели управления.</p></div></article>
        <article class="about-direction-card"><b>CODE</b><div><h3>Скрипты и автоматизация</h3><p>PHP/JavaScript-модули, интеграции, служебные инструменты, автоматизация повторяющихся процессов и доработка существующих систем.</p></div></article>
        <article class="about-direction-card"><b>GAME</b><div><h3>Плагины и игровые механики</h3><p>Разработка плагинов и модификаций, в том числе для Minecraft: игровые системы, генераторы миров, механики предметов и серверные функции.</p></div></article>
        <article class="about-direction-card"><b>BRAND</b><div><h3>Дизайн и айдентика</h3><p>Логотипы, визуальные системы, интерфейсы, оформление цифровых продуктов и работа с локальными культурными мотивами без декоративной перегрузки.</p></div></article>
      </div>
    </div>
  </section>

  <section class="about-process section-black">
    <div class="container">
      <div class="about-section-head">
        <div><span>Процесс</span><h2>Как строится работа</h2></div>
        <p>Этапы можно сокращать или объединять — всё зависит от масштаба задачи и состояния существующего проекта.</p>
      </div>
      <div class="about-process-line">
        <article><span>01</span><h3>Разбираем задачу</h3><p>Фиксируем цели, ограничения, аудиторию и то, что уже есть.</p></article>
        <article><span>02</span><h3>Проектируем</h3><p>Выстраиваем структуру, роли, сценарии и визуальное направление.</p></article>
        <article><span>03</span><h3>Разрабатываем</h3><p>Собираем интерфейс и функционал, проверяем адаптивность и устойчивость.</p></article>
        <article><span>04</span><h3>Запускаем и улучшаем</h3><p>Разворачиваем проект, исправляем найденные нюансы и развиваем дальше.</p></article>
      </div>
    </div>
  </section>

  <section class="about-tech">
    <div class="container about-tech-grid">
      <div>
        <span class="eyebrow">Инструменты</span>
        <h2>Подбираем технологии под задачу</h2>
        <p>Не привязываем каждый проект к одному шаблону. Используем подходящий стек и стараемся оставить систему понятной для дальнейшей поддержки.</p>
      </div>
      <div class="about-tech-cloud" aria-label="Технологии и направления">
        <span>PHP</span><span>JavaScript</span><span>HTML / CSS</span><span>MySQL</span><span>CMS</span><span>API</span><span>GitHub</span><span>Java</span><span>Minecraft plugins</span><span>Responsive UI</span>
      </div>
    </div>
  </section>

  <section class="about-identity section-black">
    <div class="container about-identity-grid">
      <div class="about-identity-mark"><img src="<?= h_about($logoMark) ?>" alt="Фирменный знак DAG STUDIO"></div>
      <div>
        <span class="eyebrow">DAG · Дагестан</span>
        <h2>Локальный характер.<br>Современный цифровой язык.</h2>
        <p>Нам близка идея соединять технологичность с визуальной культурой региона. Орнамент, металл, дерево, геометрия и ритм могут стать частью современного интерфейса — если использовать их аккуратно и по смыслу.</p>
      </div>
    </div>
  </section>

  <section class="about-contact">
    <div class="container about-contact-inner">
      <div>
        <span>Есть задача?</span>
        <h2>Расскажите, что нужно сделать</h2>
        <p>Сайт, CMS, скрипт, плагин, редизайн или доработка существующего проекта.</p>
      </div>
      <div class="about-contact-actions">
        <?php if ($requestsEnabled): ?><a class="btn btn-primary" href="/#contacts">Оставить заявку</a><?php endif; ?>
        <a class="btn btn-outline" href="mailto:<?= h_about((string)$settings['email']) ?>">Написать на почту</a>
      </div>
    </div>
    <div class="container about-contact-meta">
      <a href="tel:<?= h_about($phoneHref) ?>"><?= h_about((string)$settings['phone']) ?></a>
      <a href="mailto:<?= h_about((string)$settings['email']) ?>"><?= h_about((string)$settings['email']) ?></a>
      <span><?= nl2br(h_about((string)$settings['location'])) ?></span>
    </div>
  </section>
</main>

<footer class="site-footer">
  <div class="container footer-grid">
    <div class="footer-brand"><img src="<?= h_about($logoDark) ?>" alt="DAG STUDIO"><p>Digital-ателье.<br>Шьём цифровые продукты по лекалам современных технологий.</p></div>
    <div class="footer-col"><h4>Навигация</h4><a href="/">Главная</a><a href="/works.php">Наши работы</a><a href="/audio.php">Наши аудио</a><a href="/scripts.php">Скрипты</a><a href="/about.php">О студии</a></div>
    <div class="footer-col"><h4>Контакты</h4><p><?= nl2br(h_about((string)$settings['location'])) ?></p>
      <?php if ($settings['telegram'] !== ''): ?><a href="<?= h_about((string)$settings['telegram']) ?>" target="_blank" rel="noopener">Telegram</a><?php endif; ?>
      <?php if ($settings['whatsapp'] !== ''): ?><a href="<?= h_about((string)$settings['whatsapp']) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
      <?php if ($settings['behance'] !== ''): ?><a href="<?= h_about((string)$settings['behance']) ?>" target="_blank" rel="noopener">Behance</a><?php endif; ?>
    </div>
  </div>
  <div class="container footer-bottom"><span>© 2026 DAGSTUDIO. All rights reserved.</span></div>
</footer>

<script src="<?= h_about(ds_asset_url(__DIR__, '/assets/js/main.js')) ?>"></script>
</body>
</html>
