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
    'theme_scheme' => 'copper',
    'theme_accent' => '#c96f41',
    'theme_bg' => '#050505',
    'theme_panel' => '#1c1c1c',
    'theme_text' => '#f7f7f5',
    'logo_dark' => '/assets/img/logo-horizontal-dark.svg',
    'logo_light' => '/assets/img/logo-horizontal.svg',
    'logo_admin' => '/assets/img/logo-horizontal-dark.svg',
    'logo_mark' => '/assets/img/logo-mark-square.svg',
];
$settings = $defaults;
$settingsFile = __DIR__ . '/storage/settings.json';
if (is_file($settingsFile)) {
    $raw = @file_get_contents($settingsFile);
    $decoded = $raw !== false ? json_decode($raw, true) : null;
    if (is_array($decoded)) $settings = array_merge($settings, $decoded);
}

function h_audio(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function audio_hex(string $value, string $fallback): string {
    return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : $fallback;
}
function audio_asset(string $value, string $fallback): string {
    if ($value === '' || str_contains($value, '..') || !str_starts_with($value, '/')) return $fallback;
    if (!str_starts_with($value, '/assets/') && !str_starts_with($value, '/uploads/branding/')) return $fallback;
    return $value;
}
$themeAccent = audio_hex((string)$settings['theme_accent'], '#c96f41');
$themeBg = audio_hex((string)$settings['theme_bg'], '#050505');
$themePanel = audio_hex((string)$settings['theme_panel'], '#1c1c1c');
$themeText = audio_hex((string)$settings['theme_text'], '#f7f7f5');
$logoDark = audio_asset((string)$settings['logo_dark'], '/assets/img/logo-horizontal-dark.svg');
$logoMark = audio_asset((string)$settings['logo_mark'], '/assets/img/logo-mark-square.svg');

$audioPluginEnabled = true;
$pluginStateFile = __DIR__ . '/storage/plugins.json';
if (is_file($pluginStateFile)) {
    $rawPlugins = @file_get_contents($pluginStateFile);
    $pluginStates = $rawPlugins !== false ? json_decode($rawPlugins, true) : null;
    if (is_array($pluginStates) && array_key_exists('audio', $pluginStates)) {
        $audioPluginEnabled = (bool)$pluginStates['audio'];
    }
}
if (!$audioPluginEnabled) {
    http_response_code(404);
    exit('Раздел аудио отключён.');
}

$items = [];
$audioFile = __DIR__ . '/storage/audio.json';
if (is_file($audioFile)) {
    $raw = @file_get_contents($audioFile);
    $decoded = $raw !== false ? json_decode($raw, true) : null;
    if (is_array($decoded) && is_array($decoded['items'] ?? null)) {
        $items = array_values(array_filter($decoded['items'], static function ($item): bool {
            if (!is_array($item) || empty($item['published']) || empty($item['file'])) return false;
            $file = basename((string)$item['file']);
            return $file !== '' && is_file(__DIR__ . '/uploads/audio/' . $file);
        }));
    }
}
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="<?= h_audio($themeBg) ?>">
  <meta name="description" content="Наши аудио — DAG STUDIO. Аудиоработы, музыка и звуковые проекты студии.">
  <title>Наши аудио — <?= h_audio((string)$settings['site_name']) ?></title>
  <link rel="icon" href="<?= h_audio($logoMark) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600;700&family=Roboto+Condensed:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css">
  <style>:root{--accent:<?= h_audio($themeAccent) ?>;--bg:<?= h_audio($themeBg) ?>;--bg-soft:<?= h_audio($themeBg) ?>;--panel:<?= h_audio($themePanel) ?>;--text:<?= h_audio($themeText) ?>}</style>
</head>
<body>
<header class="site-header" id="top">
  <div class="container header-inner">
    <a class="brand" href="/" aria-label="DAG STUDIO — главная"><img src="<?= h_audio($logoDark) ?>" alt="DAG STUDIO"></a>
    <button class="menu-toggle" type="button" aria-label="Открыть меню" aria-expanded="false"><span></span><span></span><span></span></button>
    <nav class="main-nav" aria-label="Главная навигация">
      <a href="/">Главная</a>
      <a href="/#about">Блог</a>
      <a class="active" href="/audio.php">Наши аудио</a>
      <a href="/#services">Скрипты</a>
      <a href="/#about">О студии</a>
    </nav>
    <a class="login-btn" href="/admin/">Вход</a>
  </div>
</header>

<main>
  <section class="audio-hero">
    <div class="container audio-hero-inner">
      <div class="audio-hero-copy">
        <p class="eyebrow">Звук DAG STUDIO</p>
        <h1>Наши <span>аудио</span></h1>
        <p>Музыка, звуковые работы и аудиопроекты студии. Слушайте прямо на сайте — без лишних переходов.</p>
      </div>
      <div class="audio-hero-mark" aria-hidden="true">
        <div class="audio-wave-bars"><?php for ($i = 0; $i < 17; $i++): ?><i></i><?php endfor; ?></div>
      </div>
    </div>
  </section>

  <section class="audio-catalog">
    <div class="container">
      <div class="audio-section-head">
        <div>
          <span>Коллекция</span>
          <h2>Аудиозаписи</h2>
        </div>
        <strong><?= count($items) ?> <?= count($items) === 1 ? 'трек' : 'треков' ?></strong>
      </div>

      <?php if (!$items): ?>
        <div class="public-audio-empty">
          <div class="public-audio-empty-mark">♫</div>
          <h3>Аудио скоро появятся</h3>
          <p>Мы готовим материалы. Загруженные и опубликованные записи будут отображаться здесь автоматически.</p>
        </div>
      <?php else: ?>
        <div class="public-audio-grid">
          <?php foreach ($items as $index => $item):
            $stored = basename((string)$item['file']);
            $url = '/uploads/audio/' . rawurlencode($stored);
            $size = (int)($item['size'] ?? 0);
          ?>
            <article class="public-audio-card">
              <div class="public-audio-number"><?= str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT) ?></div>
              <div class="public-audio-content">
                <div class="public-audio-title">
                  <div>
                    <h3><?= h_audio((string)($item['title'] ?? 'Без названия')) ?></h3>
                    <?php if (!empty($item['artist'])): ?><p><?= h_audio((string)$item['artist']) ?></p><?php endif; ?>
                  </div>
                  <span class="audio-disc" aria-hidden="true">♫</span>
                </div>
                <div class="public-audio-tags">
                  <?php if (!empty($item['contains_music'])): ?><span class="audio-tag music">♫ Содержит музыку</span><?php endif; ?>
                  <?php if (!empty($item['allow_download'])): ?><span class="audio-tag">Скачивание доступно</span><?php endif; ?>
                </div>
                <?php if (!empty($item['description'])): ?><p class="public-audio-description"><?= nl2br(h_audio((string)$item['description'])) ?></p><?php endif; ?>
                <div class="ds-audio-player public-player"><audio controls preload="metadata" src="<?= h_audio($url) ?>"></audio></div>
                <div class="public-audio-actions">
                  <div class="public-audio-meta">
                  <?php if ($size > 0): ?><span><?= h_audio(number_format($size / 1048576, 1, ',', ' ')) ?> МБ</span><?php endif; ?>
                  <?php if (!empty($item['uploaded_at'])): ?><span><?= h_audio(date('d.m.Y', strtotime((string)$item['uploaded_at']))) ?></span><?php endif; ?>
                  </div>
                  <?php if (!empty($item['allow_download'])): ?><a class="audio-download-btn" href="/audio-download.php?id=<?= rawurlencode((string)$item['id']) ?>">↓ Скачать</a><?php endif; ?>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <section class="email-cta section-black">
    <div class="container email-inner">
      <span>Есть идея?</span>
      <a href="mailto:<?= h_audio((string)$settings['email']) ?>"><?= h_audio(strtoupper((string)$settings['email'])) ?></a>
    </div>
  </section>
</main>

<footer class="site-footer">
  <div class="container footer-grid">
    <div class="footer-brand"><img src="<?= h_audio($logoDark) ?>" alt="DAG STUDIO"><p>Digital-ателье.<br>Шьем сайты по лекалам высоких технологий.</p></div>
    <div class="footer-col"><h4>Навигация</h4><a href="/">Главная</a><a href="/#services">Услуги</a><a href="/audio.php">Наши аудио</a><a href="/#contacts">Контакты</a></div>
    <div class="footer-col"><h4>Контакты</h4><p><?= nl2br(h_audio((string)$settings['location'])) ?></p>
      <?php if ($settings['telegram'] !== ''): ?><a href="<?= h_audio((string)$settings['telegram']) ?>" target="_blank" rel="noopener">Telegram</a><?php endif; ?>
      <?php if ($settings['whatsapp'] !== ''): ?><a href="<?= h_audio((string)$settings['whatsapp']) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
      <?php if ($settings['behance'] !== ''): ?><a href="<?= h_audio((string)$settings['behance']) ?>" target="_blank" rel="noopener">Behance</a><?php endif; ?>
    </div>
  </div>
  <div class="container footer-bottom"><span>© 2026 DAGSTUDIO. All rights reserved.</span><a href="#">Политика конфиденциальности</a></div>
</footer>

<script src="/assets/js/main.js"></script>
<script src="/assets/js/audio-player.js"></script>
</body>
</html>