<?php
declare(strict_types=1);

return [
    'id' => 'system-info',
    'name' => 'Состояние системы',
    'menu' => 'Состояние системы',
    'version' => '1.0.0',
    'author' => 'DAG STUDIO',
    'default_enabled' => true,
    'description' => 'Служебный модуль для проверки PHP, каталогов, записи настроек и основных параметров окружения.',
    'render' => static function (): void {
        $storageWritable = is_dir(DS_STORAGE) && is_writable(DS_STORAGE);
        $pluginsWritable = is_dir(DS_PLUGINS) && is_readable(DS_PLUGINS);
        ?>
        <section class="panel">
          <h2>Системная информация</h2>
          <table class="info-table">
            <tr><td>PHP</td><td><?= e(PHP_VERSION) ?></td></tr>
            <tr><td>Сервер</td><td><?= e((string)($_SERVER['SERVER_SOFTWARE'] ?? 'Не определён')) ?></td></tr>
            <tr><td>Каталог storage</td><td><?= $storageWritable ? 'Доступен для записи' : 'Нет прав записи' ?></td></tr>
            <tr><td>Каталог plugins</td><td><?= $pluginsWritable ? 'Доступен для чтения' : 'Недоступен' ?></td></tr>
            <tr><td>HTTPS</td><td><?= (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'Включён' : 'Не определён' ?></td></tr>
            <tr><td>Время сервера</td><td><?= e(date('d.m.Y H:i:s')) ?></td></tr>
          </table>
        </section>
        <section class="panel" style="margin-top:20px">
          <h2>Архитектура плагинов</h2>
          <p style="color:#999;line-height:1.6">Каждый модуль находится в отдельной папке <code>plugins/ID-плагина/</code> и содержит файл <code>plugin.php</code> с метаданными и административным интерфейсом. Это позволяет добавлять новые функции без переделки ядра админки.</p>
        </section>
        <?php
    },
];