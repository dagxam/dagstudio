<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/security.php';

$base = 'https://dagstudio.ru';
$urls = [];

function ds_sitemap_date(array $paths): string {
    $latest = 0;
    foreach ($paths as $path) {
        if (is_file($path)) {
            $mtime = @filemtime($path);
            if (is_int($mtime)) $latest = max($latest, $mtime);
        }
    }
    return gmdate('Y-m-d', $latest > 0 ? $latest : time());
}

function ds_sitemap_add(array &$urls, string $loc, string $lastmod, string $changefreq, string $priority): void {
    $urls[] = compact('loc', 'lastmod', 'changefreq', 'priority');
}

$states = [];
$pluginFile = __DIR__ . '/storage/plugins.json';
if (is_file($pluginFile)) {
    $raw = @file_get_contents($pluginFile);
    $decoded = $raw !== false ? json_decode($raw, true) : null;
    if (is_array($decoded)) $states = $decoded;
}
$enabled = static fn(string $id): bool => !array_key_exists($id, $states) || !empty($states[$id]);

ds_sitemap_add($urls, $base . '/', ds_sitemap_date([__DIR__ . '/index.php', __DIR__ . '/assets/css/style.css']), 'weekly', '1.0');
ds_sitemap_add($urls, $base . '/about.php', ds_sitemap_date([__DIR__ . '/about.php', __DIR__ . '/assets/css/style.css']), 'monthly', '0.9');

if ($enabled('works')) {
    ds_sitemap_add($urls, $base . '/works.php', ds_sitemap_date([__DIR__ . '/works.php', __DIR__ . '/storage/works.json']), 'weekly', '0.9');
}
if ($enabled('scripts')) {
    ds_sitemap_add($urls, $base . '/scripts.php', ds_sitemap_date([__DIR__ . '/scripts.php', __DIR__ . '/storage/scripts.json']), 'weekly', '0.8');
}
if ($enabled('audio')) {
    ds_sitemap_add($urls, $base . '/audio.php', ds_sitemap_date([__DIR__ . '/audio.php', __DIR__ . '/storage/audio.json']), 'weekly', '0.7');
}

if ($enabled('pages')) {
    $pagesFile = __DIR__ . '/storage/pages.json';
    if (is_file($pagesFile)) {
        $raw = @file_get_contents($pagesFile);
        $decoded = $raw !== false ? json_decode($raw, true) : null;
        $items = is_array($decoded) && is_array($decoded['items'] ?? null) ? $decoded['items'] : [];
        foreach ($items as $item) {
            if (!is_array($item) || empty($item['published'])) continue;
            $slug = trim((string)($item['slug'] ?? ''));
            if ($slug === '') continue;
            $updated = strtotime((string)($item['updated_at'] ?? '')) ?: (@filemtime($pagesFile) ?: time());
            ds_sitemap_add(
                $urls,
                $base . '/page.php?slug=' . rawurlencode($slug),
                gmdate('Y-m-d', $updated),
                'monthly',
                '0.6'
            );
        }
    }
}

header('Content-Type: application/xml; charset=UTF-8');
header('Cache-Control: public, max-age=1800');
echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
foreach ($urls as $url) {
    echo "  <url>\n";
    echo '    <loc>' . htmlspecialchars($url['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</loc>\n";
    echo '    <lastmod>' . $url['lastmod'] . "</lastmod>\n";
    echo '    <changefreq>' . $url['changefreq'] . "</changefreq>\n";
    echo '    <priority>' . $url['priority'] . "</priority>\n";
    echo "  </url>\n";
}
echo "</urlset>\n";
