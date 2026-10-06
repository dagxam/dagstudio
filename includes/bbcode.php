<?php
declare(strict_types=1);

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    http_response_code(404);
    exit;
}

if (!function_exists('ds_bbcode_render')) {
    function ds_bbcode_safe_url(string $url): ?string {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($url === '') return null;
        if (str_starts_with($url, '/') || str_starts_with($url, '#')) return $url;
        if (preg_match('#^https?://#i', $url)) return $url;
        if (preg_match('#^mailto:[^\s@]+@[^\s@]+$#i', $url)) return $url;
        if (preg_match('#^tel:\+?[0-9()\-\s]+$#i', $url)) return $url;
        return null;
    }

    function ds_bbcode_render(string $source): string {
        $source = str_replace(["\r\n", "\r"], "\n", $source);
        $tokens = [];

        $put = static function (string $html) use (&$tokens): string {
            $key = '%%DSBB' . count($tokens) . '%%';
            $tokens[$key] = $html;
            return $key;
        };

        $source = preg_replace_callback('#\[code\](.*?)\[/code\]#is', static function ($m) use ($put) {
            return $put('<pre class="bb-code"><code>' . htmlspecialchars((string)$m[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code></pre>');
        }, $source) ?? $source;

        $source = preg_replace_callback('#\[url=([^\]]+)\](.*?)\[/url\]#is', static function ($m) use ($put) {
            $url = ds_bbcode_safe_url((string)$m[1]);
            $label = htmlspecialchars(trim((string)$m[2]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            if ($url === null) return $label;
            $external = preg_match('#^https?://#i', $url) === 1;
            return $put('<a class="bb-link" href="' . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"' . ($external ? ' target="_blank" rel="noopener"' : '') . '>' . $label . '</a>');
        }, $source) ?? $source;

        $source = preg_replace_callback('#\[img\](.*?)\[/img\]#is', static function ($m) use ($put) {
            $url = ds_bbcode_safe_url((string)$m[1]);
            if ($url === null || str_starts_with($url, 'mailto:') || str_starts_with($url, 'tel:') || str_starts_with($url, '#')) return '';
            $safe = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            return $put('<figure class="bb-image"><img src="' . $safe . '" alt="" loading="lazy"></figure>');
        }, $source) ?? $source;

        $source = preg_replace_callback('#\[color=(#[0-9a-fA-F]{6})\](.*?)\[/color\]#is', static function ($m) use ($put) {
            $color = strtolower((string)$m[1]);
            $text = htmlspecialchars((string)$m[2], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            return $put('<span style="color:' . $color . '">' . $text . '</span>');
        }, $source) ?? $source;

        $html = htmlspecialchars($source, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $pairs = [
            '#\[b\](.*?)\[/b\]#is' => '<strong>$1</strong>',
            '#\[i\](.*?)\[/i\]#is' => '<em>$1</em>',
            '#\[u\](.*?)\[/u\]#is' => '<u>$1</u>',
            '#\[s\](.*?)\[/s\]#is' => '<s>$1</s>',
            '#\[mark\](.*?)\[/mark\]#is' => '<mark>$1</mark>',
            '#\[h2\](.*?)\[/h2\]#is' => '<h2>$1</h2>',
            '#\[h3\](.*?)\[/h3\]#is' => '<h3>$1</h3>',
            '#\[quote\](.*?)\[/quote\]#is' => '<blockquote>$1</blockquote>',
            '#\[center\](.*?)\[/center\]#is' => '<div class="bb-center">$1</div>',
            '#\[left\](.*?)\[/left\]#is' => '<div class="bb-left">$1</div>',
            '#\[right\](.*?)\[/right\]#is' => '<div class="bb-right">$1</div>',
        ];
        foreach ($pairs as $pattern => $replacement) {
            for ($i = 0; $i < 3; $i++) {
                $next = preg_replace($pattern, $replacement, $html) ?? $html;
                if ($next === $html) break;
                $html = $next;
            }
        }

        $html = preg_replace_callback('#\[list\](.*?)\[/list\]#is', static function ($m) {
            $chunk = trim((string)$m[1]);
            $parts = preg_split('#\[\*\]#', $chunk) ?: [];
            $items = '';
            foreach ($parts as $part) {
                $part = trim($part);
                if ($part !== '') $items .= '<li>' . $part . '</li>';
            }
            return $items !== '' ? '<ul class="bb-list">' . $items . '</ul>' : '';
        }, $html) ?? $html;

        $html = nl2br($html, false);

        foreach ($tokens as $key => $value) {
            $html = str_replace($key, $value, $html);
        }

        $html = preg_replace('#(?:<br>\s*)+(</?(?:h2|h3|blockquote|ul|pre|div|figure)[^>]*>)#i', '$1', $html) ?? $html;
        $html = preg_replace('#(</?(?:h2|h3|blockquote|ul|pre|div|figure)[^>]*>)(?:\s*<br>)+#i', '$1', $html) ?? $html;

        return $html;
    }
}
