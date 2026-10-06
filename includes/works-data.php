<?php
declare(strict_types=1);

if (!function_exists('ds_works_defaults')) {
    function ds_works_defaults(): array {
        return [
            [
                'id' => 'akhikhan',
                'title' => 'АХИХЪАН',
                'url' => 'https://akhikhan.ru/',
                'category' => 'Сетевое издание',
                'description' => 'Новостной сайт районного издания: публикации, документы, фотоматериалы и электронные выпуски.',
                'preview_url' => '',
                'published' => true,
                'created_at' => '2026-10-06T00:00:00Z',
                'updated_at' => '2026-10-06T00:00:00Z',
            ],
            [
                'id' => 'urovia',
                'title' => 'UROVIA',
                'url' => 'https://urovia.ru/login.html',
                'category' => 'Образовательная платформа',
                'description' => 'Цифровая платформа для учителей и учеников: задания, выполнение работ, результаты и учебный журнал.',
                'preview_url' => '',
                'published' => true,
                'created_at' => '2026-10-06T00:00:00Z',
                'updated_at' => '2026-10-06T00:00:00Z',
            ],
            [
                'id' => 'dagstudio-game',
                'title' => 'DAG STUDIO GAME',
                'url' => 'https://game.dagstudio.ru/',
                'category' => 'Интерактивный проект',
                'description' => 'Отдельный игровой веб-проект в инфраструктуре DAG STUDIO с собственным интерфейсом и логикой.',
                'preview_url' => '',
                'published' => true,
                'created_at' => '2026-10-06T00:00:00Z',
                'updated_at' => '2026-10-06T00:00:00Z',
            ],
            [
                'id' => 'mo-urkarakh',
                'title' => 'Дахадаевский район',
                'url' => 'http://mo-urkarakh.ru/',
                'category' => 'Муниципальный сайт',
                'description' => 'Официальный информационный ресурс муниципального образования: новости, документы и сервисные разделы.',
                'preview_url' => '',
                'published' => true,
                'created_at' => '2026-10-06T00:00:00Z',
                'updated_at' => '2026-10-06T00:00:00Z',
            ],
            [
                'id' => 'dshi-magomedovicha',
                'title' => 'Школа искусств',
                'url' => 'https://dshi-im-g-magomedovicha.ru/',
                'category' => 'Образование',
                'description' => 'Сайт школы искусств Унцукульского района с новостями учреждения и информационными разделами.',
                'preview_url' => '',
                'published' => true,
                'created_at' => '2026-10-06T00:00:00Z',
                'updated_at' => '2026-10-06T00:00:00Z',
            ],
            [
                'id' => 'zuberkha',
                'title' => 'Зуберха.ру',
                'url' => 'http://zuberkha.ru/',
                'category' => 'Сетевое издание',
                'description' => 'Интернет-издание с муниципальными и республиканскими новостями, документами и выпусками газеты.',
                'preview_url' => '',
                'published' => true,
                'created_at' => '2026-10-06T00:00:00Z',
                'updated_at' => '2026-10-06T00:00:00Z',
            ],
        ];
    }

    function ds_works_read(string $root): array {
        $path = rtrim($root, '/\\') . '/storage/works.json';
        if (!is_file($path)) return ds_works_defaults();
        $raw = @file_get_contents($path);
        if ($raw === false) return ds_works_defaults();
        $decoded = json_decode($raw, true);
        $items = is_array($decoded) && is_array($decoded['items'] ?? null) ? $decoded['items'] : null;
        return $items !== null ? array_values(array_filter($items, 'is_array')) : ds_works_defaults();
    }

    function ds_works_save(string $root, array $items): bool {
        $storage = rtrim($root, '/\\') . '/storage';
        if (!is_dir($storage) && !@mkdir($storage, 0755, true) && !is_dir($storage)) return false;
        $path = $storage . '/works.json';
        $json = json_encode(['items' => array_values($items)], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) return false;
        $tmp = $path . '.tmp-' . bin2hex(random_bytes(4));
        if (@file_put_contents($tmp, $json . PHP_EOL, LOCK_EX) === false) return false;
        @chmod($tmp, 0640);
        return @rename($tmp, $path);
    }

    function ds_works_domain(string $url): string {
        $host = (string)(parse_url($url, PHP_URL_HOST) ?? '');
        return preg_replace('/^www\./i', '', $host) ?: $url;
    }

    function ds_works_mark(string $title): string {
        $title = trim($title);
        if ($title === '') return 'DS';
        $parts = preg_split('/\s+/u', $title) ?: [];
        if (count($parts) >= 2) {
            return mb_strtoupper(mb_substr((string)$parts[0], 0, 1) . mb_substr((string)$parts[1], 0, 1));
        }
        return mb_strtoupper(mb_substr($title, 0, 2));
    }

    function ds_works_preview(string $url, string $custom = ''): string {
        $custom = trim($custom);
        if ($custom !== '' && filter_var($custom, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $custom)) return $custom;
        return 'https://s.wordpress.com/mshots/v1/' . rawurlencode($url) . '?w=1200';
    }
}
