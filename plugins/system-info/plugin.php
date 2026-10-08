<?php
declare(strict_types=1);

if (!defined('DS_ROOT')) {
    http_response_code(404);
    exit;
}

return [
    'id' => 'system-info',
    'name' => 'Состояние системы',
    'menu' => 'Состояние системы',
    'icon' => '◉',
    'version' => '1.1.0',
    'author' => 'DAG STUDIO',
    'default_enabled' => true,
    'custom_page_head' => true,
    'description' => 'Состояние сервера, служебная информация и безопасный сброс кэша сайта.',
    'handle' => static function (): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
        csrf_verify();

        $action = (string)($_POST['action'] ?? '');
        if ($action !== 'reset_cache') return;

        $result = ds_reset_site_cache(DS_ROOT);
        if (!empty($result['ok'])) {
            audit_log('system.cache_reset', [
                'version' => (string)($result['version'] ?? ''),
                'opcache' => !empty($result['opcache']) ? 'cleared' : 'unavailable',
                'apcu' => !empty($result['apcu']) ? 'cleared' : 'unavailable',
            ]);
            flash('success', 'Кэш сайта сброшен. CSS и JavaScript получили новую версию — страницы «Аудио» и «Наши работы» загрузят свежий дизайн.');
        } else {
            flash('error', 'Не удалось обновить версию кэша. Проверьте права записи каталога storage.');
        }

        admin_redirect('/admin/plugin.php?id=system-info');
    },
    'render' => static function (): void {
        $storageWritable = is_dir(DS_STORAGE) && is_writable(DS_STORAGE);
        $pluginsReadable = is_dir(DS_PLUGINS) && is_readable(DS_PLUGINS);
        $cacheVersion = ds_site_cache_version(DS_ROOT);
        $cacheFile = DS_STORAGE . '/cache-version.json';
        $cacheUpdated = is_file($cacheFile) ? @filemtime($cacheFile) : false;
        $https = ds_is_https();
        $opcacheAvailable = function_exists('opcache_reset');
        $apcuAvailable = function_exists('apcu_clear_cache');
        ?>
        <div class="page-head system-page-head">
          <div class="system-title-wrap">
            <div class="system-title-icon" aria-hidden="true"><i></i><i></i><i></i></div>
            <div>
              <p class="requests-kicker">DAG STUDIO · сервер</p>
              <h1>Состояние системы</h1>
              <p>Проверка окружения, состояния файлов и управление кэшем сайта после обновлений.</p>
            </div>
          </div>
        </div>

        <div class="system-status-grid">
          <div class="system-status-card <?= $storageWritable ? 'ok' : 'bad' ?>"><span></span><div><small>Storage</small><strong><?= $storageWritable ? 'Запись доступна' : 'Нет доступа' ?></strong></div></div>
          <div class="system-status-card <?= $pluginsReadable ? 'ok' : 'bad' ?>"><span></span><div><small>Plugins</small><strong><?= $pluginsReadable ? 'Чтение доступно' : 'Недоступно' ?></strong></div></div>
          <div class="system-status-card <?= $https ? 'ok' : 'warn' ?>"><span></span><div><small>HTTPS</small><strong><?= $https ? 'Активен' : 'Не определён' ?></strong></div></div>
          <div class="system-status-card ok"><span></span><div><small>PHP</small><strong><?= e(PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION) ?></strong></div></div>
        </div>

        <section class="panel system-cache-panel">
          <div class="system-cache-main">
            <div class="system-cache-icon" aria-hidden="true"><i></i></div>
            <div class="system-cache-copy">
              <p class="requests-kicker">Обновление сайта</p>
              <h2>Сброс кэша</h2>
              <p>Используйте после изменений дизайна, если браузер продолжает показывать старую версию страниц. Кнопка обновит версию CSS и JavaScript и, если доступно на сервере, очистит PHP OPcache и APCu.</p>
              <div class="system-cache-meta">
                <span><small>Версия кэша</small><strong><?= e($cacheVersion) ?></strong></span>
                <span><small>Последний ручной сброс</small><strong><?= $cacheUpdated ? e(date('d.m.Y H:i', $cacheUpdated)) : 'Ещё не выполнялся' ?></strong></span>
              </div>
            </div>
          </div>
          <div class="system-cache-actions">
            <form method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="reset_cache">
              <button class="btn system-cache-reset" type="submit" data-confirm="Сбросить кэш сайта и принудительно обновить CSS и JavaScript?">
                <span class="system-cache-reset-icon" aria-hidden="true"></span>
                Сбросить кэш сайта
              </button>
            </form>
            <a class="btn secondary system-open-site" href="/?cache=<?= e($cacheVersion) ?>" target="_blank" rel="noopener"><span aria-hidden="true"></span>Открыть сайт</a>
          </div>
        </section>

        <div class="system-info-layout">
          <section class="panel">
            <div class="panel-title-row">
              <div><h2>Системная информация</h2><span>Текущее окружение сервера</span></div>
            </div>
            <table class="info-table system-info-table">
              <tr><td>PHP</td><td><?= e(PHP_VERSION) ?></td></tr>
              <tr><td>Сервер</td><td><?= e((string)($_SERVER['SERVER_SOFTWARE'] ?? 'Не определён')) ?></td></tr>
              <tr><td>Каталог storage</td><td><?= $storageWritable ? 'Доступен для записи' : 'Нет прав записи' ?></td></tr>
              <tr><td>Каталог plugins</td><td><?= $pluginsReadable ? 'Доступен для чтения' : 'Недоступен' ?></td></tr>
              <tr><td>HTTPS</td><td><?= $https ? 'Включён' : 'Не определён' ?></td></tr>
              <tr><td>PHP OPcache</td><td><?= $opcacheAvailable ? 'Доступен для сброса' : 'Недоступен / не включён' ?></td></tr>
              <tr><td>APCu</td><td><?= $apcuAvailable ? 'Доступен для сброса' : 'Недоступен / не включён' ?></td></tr>
              <tr><td>Время сервера</td><td><?= e(date('d.m.Y H:i:s')) ?></td></tr>
            </table>
          </section>

          <aside class="panel system-cache-note">
            <div class="system-note-icon" aria-hidden="true">i</div>
            <h2>Как работает обновление</h2>
            <p>HTML-страницы теперь запрашиваются с проверкой свежести. CSS и JavaScript получают номер версии в адресе, поэтому после сброса браузер загружает новые файлы вместо старых из кэша.</p>
            <div class="system-cache-tags"><span>CSS</span><span>JavaScript</span><span>OPcache</span><span>APCu</span></div>
          </aside>
        </div>
        <?php
    },
];
