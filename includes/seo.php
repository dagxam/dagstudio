<?php
declare(strict_types=1);

if (!function_exists('ds_seo_base_url')) {
    function ds_seo_base_url(): string {
        return 'https://dagstudio.ru';
    }

    function ds_seo_escape(string $value): string {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    function ds_seo_absolute(string $value): string {
        $value = trim($value);
        if ($value === '') return ds_seo_base_url() . '/';
        if (preg_match('#^https://#i', $value)) return $value;
        if (!str_starts_with($value, '/')) $value = '/' . $value;
        return ds_seo_base_url() . $value;
    }

    function ds_seo_social_links(array $settings): array {
        $links = [];
        foreach (['telegram', 'whatsapp', 'behance'] as $key) {
            $value = trim((string)($settings[$key] ?? ''));
            if ($value !== '' && filter_var($value, FILTER_VALIDATE_URL) && preg_match('#^https://#i', $value)) {
                $links[] = $value;
            }
        }
        return array_values(array_unique($links));
    }

    function ds_seo_json(array $value): string {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        return $json === false ? '{}' : $json;
    }

    function ds_seo_render(array $config, array $settings): void {
        $title = trim((string)($config['title'] ?? 'DAG STUDIO'));
        $description = trim((string)($config['description'] ?? 'DAG STUDIO — цифровая студия: сайты, CMS, скрипты, дизайн и IT-сервис.'));
        $path = (string)($config['path'] ?? '/');
        $canonical = ds_seo_absolute($path);
        $image = ds_seo_absolute((string)($config['image'] ?? ($settings['hero_ornament'] ?? '/assets/img/hero-ornament-main-v2.webp')));
        $siteName = trim((string)($settings['site_name'] ?? 'DAG STUDIO')) ?: 'DAG STUDIO';
        $pageType = (string)($config['schema_type'] ?? 'WebPage');
        $breadcrumbName = trim((string)($config['breadcrumb'] ?? ''));
        $includeOrganization = !empty($config['organization']);
        $includeWebsite = !empty($config['website']);
        $robots = trim((string)($config['robots'] ?? 'index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1'));

        echo '<link rel="canonical" href="' . ds_seo_escape($canonical) . '">' . PHP_EOL;
        echo '<meta name="robots" content="' . ds_seo_escape($robots) . '">' . PHP_EOL;
        echo '<meta property="og:locale" content="ru_RU">' . PHP_EOL;
        echo '<meta property="og:type" content="website">' . PHP_EOL;
        echo '<meta property="og:site_name" content="' . ds_seo_escape($siteName) . '">' . PHP_EOL;
        echo '<meta property="og:title" content="' . ds_seo_escape($title) . '">' . PHP_EOL;
        echo '<meta property="og:description" content="' . ds_seo_escape($description) . '">' . PHP_EOL;
        echo '<meta property="og:url" content="' . ds_seo_escape($canonical) . '">' . PHP_EOL;
        echo '<meta property="og:image" content="' . ds_seo_escape($image) . '">' . PHP_EOL;
        echo '<meta property="og:image:alt" content="DAG STUDIO — цифровая студия">' . PHP_EOL;
        echo '<meta name="twitter:card" content="summary_large_image">' . PHP_EOL;
        echo '<meta name="twitter:title" content="' . ds_seo_escape($title) . '">' . PHP_EOL;
        echo '<meta name="twitter:description" content="' . ds_seo_escape($description) . '">' . PHP_EOL;
        echo '<meta name="twitter:image" content="' . ds_seo_escape($image) . '">' . PHP_EOL;

        $graph = [];

        if ($includeOrganization) {
            $org = [
                '@type' => 'Organization',
                '@id' => ds_seo_base_url() . '/#organization',
                'name' => $siteName,
                'alternateName' => 'DAGSTUDIO',
                'url' => ds_seo_base_url() . '/',
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => ds_seo_absolute((string)($settings['logo_mark'] ?? '/assets/img/logo-mark-square.svg')),
                ],
                'image' => $image,
                'description' => 'DAG STUDIO — студия цифровых решений из Дагестана: сайты, CMS, скрипты, плагины, дизайн и IT-сервис.',
            ];
            $email = trim((string)($settings['email'] ?? ''));
            $phone = trim((string)($settings['phone'] ?? ''));
            if ($email !== '') $org['email'] = $email;
            if ($phone !== '') $org['telephone'] = $phone;
            $sameAs = ds_seo_social_links($settings);
            if ($sameAs) $org['sameAs'] = $sameAs;
            $org['address'] = [
                '@type' => 'PostalAddress',
                'addressLocality' => 'Махачкала',
                'addressRegion' => 'Республика Дагестан',
                'addressCountry' => 'RU',
            ];
            $graph[] = $org;
        }

        if ($includeWebsite) {
            $graph[] = [
                '@type' => 'WebSite',
                '@id' => ds_seo_base_url() . '/#website',
                'url' => ds_seo_base_url() . '/',
                'name' => $siteName,
                'alternateName' => 'DAGSTUDIO',
                'inLanguage' => 'ru-RU',
                'publisher' => ['@id' => ds_seo_base_url() . '/#organization'],
            ];
        }

        $page = [
            '@type' => $pageType,
            '@id' => $canonical . '#webpage',
            'url' => $canonical,
            'name' => $title,
            'description' => $description,
            'inLanguage' => 'ru-RU',
            'primaryImageOfPage' => [
                '@type' => 'ImageObject',
                'url' => $image,
            ],
        ];
        if ($path !== '/') $page['isPartOf'] = ['@id' => ds_seo_base_url() . '/#website'];
        $graph[] = $page;

        if ($breadcrumbName !== '' && $path !== '/') {
            $graph[] = [
                '@type' => 'BreadcrumbList',
                '@id' => $canonical . '#breadcrumb',
                'itemListElement' => [
                    [
                        '@type' => 'ListItem',
                        'position' => 1,
                        'name' => 'Главная',
                        'item' => ds_seo_base_url() . '/',
                    ],
                    [
                        '@type' => 'ListItem',
                        'position' => 2,
                        'name' => $breadcrumbName,
                        'item' => $canonical,
                    ],
                ],
            ];
        }

        echo '<script type="application/ld+json">' . ds_seo_json([
            '@context' => 'https://schema.org',
            '@graph' => $graph,
        ]) . '</script>' . PHP_EOL;
    }
}
