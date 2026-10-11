<?php
declare(strict_types=1);

if (!function_exists('ds_scripts_defaults')) {
    function ds_scripts_defaults(): array {
        return [
            [
                'id' => 'photoalbums-dle',
                'title' => 'Фотоальбомы для DLE',
                'category' => 'DataLife Engine',
                'compatibility' => 'DLE',
                'version' => '1.0',
                'description' => 'Модуль фотоальбомов для DataLife Engine: список альбомов, просмотр, AJAX-счётчики и лайки, шаблоны и настройки.',
                'file' => '/assets/downloads/scripts/photoalbums-dle.rar',
                'original_name' => 'photoalbums.rar',
                'size' => 22317,
                'published' => true,
                'featured' => true,
                'created_at' => '2026-10-11T00:00:00Z',
                'updated_at' => '2026-10-11T00:00:00Z',
            ],
            [
                'id' => 'personal-insta-theme',
                'title' => 'Тема в стиле Instagram',
                'category' => 'WordPress',
                'compatibility' => 'WordPress',
                'version' => '99.5',
                'description' => 'Адаптивная WordPress-тема Personal Insta в стиле Instagram: сетка публикаций, мобильная адаптация и автоматическая светлая/тёмная схема.',
                'file' => '/assets/downloads/scripts/personal-insta-theme.zip',
                'original_name' => 'themein.zip',
                'size' => 2470,
                'published' => true,
                'featured' => true,
                'created_at' => '2026-10-11T00:00:00Z',
                'updated_at' => '2026-10-11T00:00:00Z',
            ],
            [
                'id' => 'accessibility-version',
                'title' => 'Версия для слабовидящих',
                'category' => 'Доступность',
                'compatibility' => 'Web / DLE',
                'version' => '1.0',
                'description' => 'Универсальный фронтенд-пакет доступности: размер шрифта, ч/б режим, контраст, скрытие изображений и озвучивание. Не зависит от CMS, но может требовать адаптации под конкретный сайт.',
                'file' => '/assets/downloads/scripts/accessibility-version.zip',
                'original_name' => 'версия-слабовидящих.zip',
                'size' => 1880,
                'published' => true,
                'featured' => true,
                'created_at' => '2026-10-11T00:00:00Z',
                'updated_at' => '2026-10-11T00:00:00Z',
            ],
        ];
    }

    function ds_scripts_read(string $root): array {
        $path = rtrim($root, '/\\') . '/storage/scripts.json';
        if (!is_file($path)) return ds_scripts_defaults();
        $raw = @file_get_contents($path);
        $decoded = $raw !== false ? json_decode($raw, true) : null;
        if (!is_array($decoded) || !is_array($decoded['items'] ?? null)) return ds_scripts_defaults();
        return array_values(array_filter($decoded['items'], 'is_array'));
    }

    function ds_scripts_save(string $root, array $items): bool {
        $storage = rtrim($root, '/\\') . '/storage';
        if (!is_dir($storage) && !@mkdir($storage, 0750, true) && !is_dir($storage)) return false;
        $json = json_encode(['items' => array_values($items)], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) return false;
        $tmp = $storage . '/scripts.json.tmp-' . bin2hex(random_bytes(4));
        if (@file_put_contents($tmp, $json . PHP_EOL, LOCK_EX) === false) return false;
        @chmod($tmp, 0640);
        return @rename($tmp, $storage . '/scripts.json');
    }

    function ds_script_find(array $items, string $id): ?array {
        foreach ($items as $item) {
            if (is_array($item) && (string)($item['id'] ?? '') === $id) return $item;
        }
        return null;
    }

    function ds_script_file_path(string $root, string $file): string {
        $root = rtrim($root, '/\\');
        if (str_starts_with($file, '/assets/downloads/scripts/')) {
            $base = realpath($root . '/assets/downloads/scripts');
        } elseif (str_starts_with($file, '/uploads/scripts/')) {
            $base = realpath($root . '/uploads/scripts');
        } else {
            return '';
        }
        if ($base === false) return '';
        $path = realpath($root . $file);
        if ($path === false || !is_file($path)) return '';
        $prefix = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!str_starts_with($path, $prefix)) return '';
        return $path;
    }

    function ds_script_size_label(int $bytes): string {
        if ($bytes <= 0) return '—';
        if ($bytes >= 1048576) return number_format($bytes / 1048576, 1, ',', ' ') . ' МБ';
        return number_format($bytes / 1024, 0, ',', ' ') . ' КБ';
    }
}
