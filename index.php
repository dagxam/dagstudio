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
    'logo_light' => '/assets/img/logo-horizontal.svg',
    'logo_admin' => '/assets/img/logo-horizontal-dark.svg',
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

function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function site_hex(string $value, string $fallback): string {
    return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : $fallback;
}
function site_asset(string $value, string $fallback): string {
    if ($value === '' || str_contains($value, '..') || !str_starts_with($value, '/')) return $fallback;
    if (!str_starts_with($value, '/assets/') && !str_starts_with($value, '/uploads/branding/')) return $fallback;
    return $value;
}
function site_menu_url(string $value): string {
    $value = trim($value);
    if (str_starts_with($value, '/') || str_starts_with($value, '#')) return $value;
    if (preg_match('#^https?://#i', $value)) return $value;
    return '#';
}

function site_portfolio_menu(array $menu): array {
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

$settings['main_menu'] = site_portfolio_menu(is_array($settings['main_menu'] ?? null) ? $settings['main_menu'] : []);
$themeAccent = site_hex((string)$settings['theme_accent'], '#c96f41');
$themeBg = site_hex((string)$settings['theme_bg'], '#050505');
$themePanel = site_hex((string)$settings['theme_panel'], '#1c1c1c');
$themeText = site_hex((string)$settings['theme_text'], '#f7f7f5');
$logoDark = site_asset((string)$settings['logo_dark'], '/assets/img/logo-horizontal-dark.svg');
$logoMark = site_asset((string)$settings['logo_mark'], '/assets/img/logo-mark-square.svg');
$phoneHref = preg_replace('/[^+0-9]/', '', (string)$settings['phone']) ?: '';
$audioPluginEnabled = true;
$pagesPluginEnabled = true;
$worksPluginEnabled = true;
$requestsPluginEnabled = true;
$pluginStateFile = __DIR__ . '/storage/plugins.json';
if (is_file($pluginStateFile)) {
    $rawPlugins = @file_get_contents($pluginStateFile);
    $pluginStates = $rawPlugins !== false ? json_decode($rawPlugins, true) : null;
    if (is_array($pluginStates) && array_key_exists('audio', $pluginStates)) {
        $audioPluginEnabled = (bool)$pluginStates['audio'];
    }
    if (is_array($pluginStates) && array_key_exists('pages', $pluginStates)) {
        $pagesPluginEnabled = (bool)$pluginStates['pages'];
    }
    if (is_array($pluginStates) && array_key_exists('works', $pluginStates)) {
        $worksPluginEnabled = (bool)$pluginStates['works'];
    }
    if (is_array($pluginStates) && array_key_exists('requests', $pluginStates)) {
        $requestsPluginEnabled = (bool)$pluginStates['requests'];
    }
}
$publicFormToken = $requestsPluginEnabled ? ds_form_token(__DIR__, 'contact') : '';
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="<?= h($themeBg) ?>">
  <meta name="description" content="DAG STUDIO — создание сайтов, дизайн, скрипты и IT-сервис в Дагестане.">
  <title><?= h($settings['site_name']) ?> — создание сайтов & IT-сервис</title>
  <link rel="icon" href="<?= h($logoMark) ?>">
  <link rel="manifest" href="/manifest.php">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600;700&family=Roboto+Condensed:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= h(ds_asset_url(__DIR__, '/assets/css/style.css')) ?>">
  <style>:root{--accent:<?= h($themeAccent) ?>;--bg:<?= h($themeBg) ?>;--bg-soft:<?= h($themeBg) ?>;--panel:<?= h($themePanel) ?>;--text:<?= h($themeText) ?>}</style>
</head>
<body>
  <header class="site-header" id="top">
    <div class="container header-inner">
      <a class="brand" href="#top" aria-label="DAG STUDIO — главная">
        <img src="<?= h($logoDark) ?>" alt="DAG STUDIO">
      </a>
      <button class="menu-toggle" type="button" aria-label="Открыть меню" aria-expanded="false">
        <span></span><span></span><span></span>
      </button>
      <nav class="main-nav" aria-label="Главная навигация">
        <?php foreach (($settings['main_menu'] ?? []) as $menuItem):
          if (!is_array($menuItem) || empty($menuItem['visible'])) continue;
          $menuUrl = site_menu_url((string)($menuItem['url'] ?? '#'));
          $menuPath = parse_url($menuUrl, PHP_URL_PATH);
          if (!$audioPluginEnabled && $menuPath === '/audio.php') continue;
          if (!$pagesPluginEnabled && $menuPath === '/page.php') continue;
          if (!$worksPluginEnabled && $menuPath === '/works.php') continue;
          $menuLabel = trim((string)($menuItem['label'] ?? ''));
          if ($menuLabel === '') continue;
          $newTab = !empty($menuItem['new_tab']);
        ?>
          <a href="<?= h($menuUrl) ?>"<?= $newTab ? ' target="_blank" rel="noopener"' : '' ?>><?= h($menuLabel) ?></a>
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
    <section class="hero section-black">
      <div class="container hero-grid">
        <div class="hero-copy reveal">
          <p class="eyebrow">Студия цифровых решений</p>
          <h1>Создание сайтов<br><span>&amp; IT-сервис</span></h1>
          <p class="hero-text">Разрабатываем сайты с уникальным характером, пишем код и оживляем технику. Сочетаем монументальность традиций и скорость современных технологий.</p>
          <div class="hero-actions">
            <?php if ($worksPluginEnabled): ?><a class="btn btn-primary" href="/works.php">Смотреть работы</a><?php endif; ?>
            <?php if ($requestsPluginEnabled): ?><button class="btn btn-outline" type="button" data-order-open>Заказать услуги</button><?php endif; ?>
          </div>
        </div>
        <div class="hero-art hero-art--ornament reveal">
          <div class="hero-glow" aria-hidden="true"></div>
          <div class="hero-ornament-wrap">
            <img
              class="hero-ornament"
              data-hero-ornament
              src="<?= h(ds_asset_url(__DIR__, '/assets/img/hero-ornament-main.webp')) ?>"
              alt="Дагестанский орнамент DAG STUDIO"
              width="900"
              height="956"
              loading="eager"
              decoding="async"
              fetchpriority="high"
            >
          </div>
        </div>
      </div>
    </section>

    <section class="about-section" id="about">
      <div class="container about-grid">
        <div class="about-copy reveal">
          <h2>Код как орнамент</h2>
          <p>В Дагестане каждый узор на ковре или насечка на серебре имеет значение и строгую структуру. В нашей работе мы придерживаемся тех же принципов...</p>
          <ul class="principles">
            <li><strong>Чистый код:</strong> как идеальная чеканка.</li>
            <li><strong>Надёжность:</strong> ремонт и софт, которые работают годами.</li>
            <li><strong>Уникальность:</strong> дизайн, который запоминается.</li>
          </ul>
        </div>
        <div class="about-mark reveal">
          <img src="<?= h($logoMark) ?>" alt="Фирменный знак DAG STUDIO">
        </div>
      </div>
    </section>

    <section class="services-section section-black" id="services">
      <div class="container">
        <h2 class="section-title reveal">Наши услуги</h2>
        <div class="services-grid">
          <article class="service-card reveal"><span>01</span><h3>Веб разработка</h3><p>Создание сайтов с нуля под ключ. Веб-дизайн, верстка и посадка на CMS. От лендингов до магазинов.</p></article>
          <article class="service-card reveal"><span>02</span><h3>Графика</h3><p>Графический дизайн любой сложности. Разработка логотипов, эмблем, дизайн карточек товаров и айдентика.</p></article>
          <article class="service-card reveal"><span>03</span><h3>Скрипты</h3><p>Написание скриптов на заказ. Автоматизация задач, парсеры и уникальные программные решения.</p></article>
          <article class="service-card reveal"><span>04</span><h3>Моды &amp; плагины<br>для игр</h3><p>Геймдев услуги. Разработка уникальных модов и плагинов для Minecraft и других игр. Реализуем ваши идеи.</p></article>
          <article class="service-card reveal"><span>05</span><h3>Ремонт ПК</h3><p>Профессиональный ремонт компьютеров и ноутбуков. Диагностика неисправностей и аппаратный ремонт.</p></article>
          <article class="service-card reveal"><span>06</span><h3>Обслуживание</h3><p>Сервисное обслуживание техники. Чистка от пыли, замена термопасты, настройка ПО и апгрейд системы.</p></article>
          <article class="service-card reveal"><span>07</span><h3>Гос сайты</h3><p>Разработка официальных сайтов для госструктур, администраций и школ. Полное соответствие требованиям.</p></article>
          <article class="service-card reveal"><span>08</span><h3>Обновление сайтов</h3><p>Модернизация существующих сайтов. Исправление ошибок, лечение вирусов, редизайн и новый функционал.</p></article>
        </div>
      </div>
    </section>

    <section class="contact-section" id="contacts">
      <div class="container contact-grid">
        <div class="contact-panel reveal">
          <h2>Начнем проект?</h2>
          <p class="contact-lead">Оставьте заявку на сайт, скрипт или ремонт техники.</p>
          <div class="contact-meta">
            <div><small>Телефон</small><a href="tel:<?= h($phoneHref) ?>"><?= h((string)$settings['phone']) ?></a></div>
            <div><small>Емайл</small><a href="mailto:<?= h((string)$settings['email']) ?>"><?= h((string)$settings['email']) ?></a></div>
          </div>
          <?php if ($requestsPluginEnabled): ?>
          <form class="request-form" action="/send.php" method="post">
            <input type="hidden" name="source" value="contact">
            <input type="hidden" name="form_token" value="<?= h($publicFormToken) ?>">
            <div class="form-row">
              <label><span class="sr-only">ФИО</span><input type="text" name="name" maxlength="120" autocomplete="name" placeholder="ФИО" required></label>
              <label><span class="sr-only">Ваш телефон</span><input type="tel" name="phone" maxlength="40" autocomplete="tel" placeholder="ВАШ ТЕЛЕФОН" required></label>
            </div>
            <label><span class="sr-only">Ваша почта</span><input type="email" name="email" maxlength="120" autocomplete="email" placeholder="ВАША ПОЧТА" required></label>
            <label><span class="sr-only">Опишите задачу</span><textarea name="message" maxlength="2000" placeholder="ОПИШИТЕ ЗАДАЧУ" required></textarea></label>
            <label class="honeypot" aria-hidden="true">Сайт<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
            <button class="btn btn-outline submit-btn" type="submit">Оставить заявку</button>
            <p class="form-status" role="status" aria-live="polite"></p>
          </form>
          <?php else: ?>
            <div class="form-status">Приём обращений временно отключён.</div>
          <?php endif; ?>
        </div>
        <div class="map-wrap reveal">
          <iframe title="DAG STUDIO на карте" src="https://yandex.ru/map-widget/v1/?ll=47.504682%2C42.984857&z=12&l=map" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
        </div>
      </div>
    </section>

    <section class="email-cta section-black">
      <div class="container email-inner reveal">
        <span>Есть идея?</span>
        <a href="mailto:<?= h((string)$settings['email']) ?>"><?= h(strtoupper((string)$settings['email'])) ?></a>
      </div>
    </section>
  </main>

  <?php if ($requestsPluginEnabled): ?>
  <div class="order-modal" id="orderModal" aria-hidden="true">
    <div class="order-modal-backdrop" data-order-close></div>
    <section class="order-modal-panel" role="dialog" aria-modal="true" aria-labelledby="orderModalTitle">
      <button class="order-modal-close" type="button" aria-label="Закрыть" data-order-close>×</button>
      <div class="order-modal-brand"><img src="<?= h($logoDark) ?>" alt="DAG STUDIO"></div>
      <p class="eyebrow">Новый проект</p>
      <h2 id="orderModalTitle">Заказать услуги</h2>
      <p class="order-modal-lead">Оставьте контакты и кратко опишите задачу. Обращение сохранится в защищённой панели DAG STUDIO.</p>
      <form class="request-form order-form" action="/send.php" method="post">
        <input type="hidden" name="source" value="modal">
        <input type="hidden" name="form_token" value="<?= h($publicFormToken) ?>">
        <div class="form-row">
          <label><span class="sr-only">ФИО</span><input type="text" name="name" maxlength="120" autocomplete="name" placeholder="ФИО" required></label>
          <label><span class="sr-only">Ваш телефон</span><input type="tel" name="phone" maxlength="40" autocomplete="tel" placeholder="ВАШ ТЕЛЕФОН" required></label>
        </div>
        <label><span class="sr-only">Ваша почта</span><input type="email" name="email" maxlength="120" autocomplete="email" placeholder="ВАША ПОЧТА" required></label>
        <label><span class="sr-only">Опишите задачу</span><textarea name="message" maxlength="2000" placeholder="ОПИШИТЕ ЗАДАЧУ" required></textarea></label>
        <label class="honeypot" aria-hidden="true">Сайт<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
        <button class="btn btn-primary order-submit" type="submit">Отправить заявку</button>
        <p class="form-status order-form-status" role="status" aria-live="polite"></p>
      </form>
      <div class="order-modal-note">Мы рассмотрим обращение в админ-панели и свяжемся по указанным контактам.</div>
    </section>
  </div>
  <?php endif; ?>
  <div class="site-toast" id="siteToast" role="status" aria-live="polite"></div>

  <footer class="site-footer">
    <div class="container footer-grid">
      <div class="footer-brand">
        <img src="<?= h($logoDark) ?>" alt="DAG STUDIO">
        <p>Digital-ателье.<br>Шьем сайты по лекалам высоких технологий.</p>
      </div>
      <div class="footer-col"><h4>Навигация</h4><a href="#top">Главная</a><a href="#services">Услуги</a><a href="/works.php">Наши работы</a><a href="#contacts">Контакты</a></div>
      <div class="footer-col"><h4>Контакты</h4><p><?= nl2br(h((string)$settings['location'])) ?></p>
        <?php if ($settings['telegram'] !== ''): ?><a href="<?= h((string)$settings['telegram']) ?>" target="_blank" rel="noopener">Telegram</a><?php endif; ?>
        <?php if ($settings['whatsapp'] !== ''): ?><a href="<?= h((string)$settings['whatsapp']) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
        <?php if ($settings['behance'] !== ''): ?><a href="<?= h((string)$settings['behance']) ?>" target="_blank" rel="noopener">Behance</a><?php endif; ?>
      </div>
    </div>
    <div class="container footer-bottom"><span>© 2026 DAGSTUDIO. All rights reserved.</span><a href="#">Политика конфиденциальности</a></div>
  </footer>

  <script src="<?= h(ds_asset_url(__DIR__, '/assets/js/main.js')) ?>"></script>
</body>
</html>