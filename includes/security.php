<?php
declare(strict_types=1);

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    http_response_code(404);
    exit;
}

if (!function_exists('ds_is_https')) {
    function ds_is_https(): bool {
        if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') return true;
        return strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }

    function ds_public_security_headers(): void {
        if (headers_sent()) return;
        header_remove('X-Powered-By');
        if (ds_is_https()) header('Strict-Transport-Security: max-age=31536000');
        header("Content-Security-Policy: default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'none'; form-action 'self'; script-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' data: https://fonts.gstatic.com; img-src 'self' data: https:; media-src 'self' blob:; connect-src 'self'; frame-src https://yandex.ru https://*.yandex.ru; manifest-src 'self'; worker-src 'self';");
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: accelerometer=(), autoplay=(self), camera=(), display-capture=(), encrypted-media=(), fullscreen=(self), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), picture-in-picture=(), publickey-credentials-get=(self), usb=()');
        header('Cross-Origin-Opener-Policy: same-origin');
        header('Cross-Origin-Resource-Policy: same-origin');
        header('X-Permitted-Cross-Domain-Policies: none');
    }

    function ds_admin_security_headers(): void {
        if (headers_sent()) return;
        header_remove('X-Powered-By');
        if (ds_is_https()) header('Strict-Transport-Security: max-age=31536000');
        header("Content-Security-Policy: default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'none'; form-action 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; media-src 'self'; connect-src 'self'; frame-src 'none';");
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: same-origin');
        header('Permissions-Policy: accelerometer=(), autoplay=(self), camera=(), display-capture=(), encrypted-media=(), fullscreen=(self), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), picture-in-picture=(), publickey-credentials-get=(self), usb=()');
        header('Cross-Origin-Opener-Policy: same-origin');
        header('Cross-Origin-Resource-Policy: same-origin');
        header('X-Permitted-Cross-Domain-Policies: none');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }

    function ds_client_ip(): string {
        $ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        return mb_substr($ip !== '' ? $ip : 'unknown', 0, 64);
    }

    function ds_ip_hash(): string {
        return hash('sha256', ds_client_ip());
    }

    function ds_same_origin_request(): bool {
        $current = strtolower((string)($_SERVER['HTTP_HOST'] ?? 'dagstudio.ru'));
        $current = preg_replace('/:\d+$/', '', $current) ?? $current;
        if ($current === '') return false;

        foreach (['HTTP_ORIGIN', 'HTTP_REFERER'] as $header) {
            $value = trim((string)($_SERVER[$header] ?? ''));
            if ($value === '') continue;
            $host = strtolower((string)(parse_url($value, PHP_URL_HOST) ?? ''));
            if ($host !== '' && !hash_equals($current, $host)) return false;
        }
        return true;
    }

    function ds_rate_limit(
        string $root,
        string $scope,
        int $limit,
        int $windowSeconds,
        string $identity = '',
        bool $includeIp = true
    ): bool {
        if ($limit < 1 || $windowSeconds < 1) return false;

        $storage = rtrim($root, '/\\') . '/storage';
        if (!is_dir($storage) && !@mkdir($storage, 0755, true) && !is_dir($storage)) return true;

        $path = $storage . '/security-rate.json';
        $handle = @fopen($path, 'c+');
        if ($handle === false) return true;
        if (!@flock($handle, LOCK_EX)) {
            fclose($handle);
            return true;
        }

        rewind($handle);
        $raw = stream_get_contents($handle);
        $data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
        if (!is_array($data)) $data = [];

        $now = time();
        $keyMaterial = $scope . '|' . ($includeIp ? ds_ip_hash() : '*') . '|' . $identity;
        $key = hash('sha256', $keyMaterial);
        $events = is_array($data[$key] ?? null) ? $data[$key] : [];
        $events = array_values(array_filter($events, static fn($ts): bool => is_int($ts) && $ts > $now - $windowSeconds));

        $allowed = count($events) < $limit;
        if ($allowed) $events[] = $now;
        $data[$key] = $events;

        foreach ($data as $bucket => $timestamps) {
            if (!is_array($timestamps)) {
                unset($data[$bucket]);
                continue;
            }
            $fresh = array_values(array_filter($timestamps, static fn($ts): bool => is_int($ts) && $ts > $now - 86400));
            if ($fresh) $data[$bucket] = $fresh;
            else unset($data[$bucket]);
        }

        rewind($handle);
        ftruncate($handle, 0);
        fwrite($handle, json_encode($data, JSON_UNESCAPED_SLASHES) ?: '{}');
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);
        @chmod($path, 0640);

        return $allowed;
    }

    function ds_security_secret(string $root): string {
        $storage = rtrim($root, '/\\') . '/storage';
        if (!is_dir($storage) && !@mkdir($storage, 0750, true) && !is_dir($storage)) return '';

        $path = $storage . '/security-secret.txt';
        $handle = @fopen($path, 'c+');
        if ($handle === false) return '';
        if (!@flock($handle, LOCK_EX)) {
            fclose($handle);
            return '';
        }

        rewind($handle);
        $secret = trim((string)stream_get_contents($handle));
        if (!preg_match('/^[a-f0-9]{64}$/', $secret)) {
            $secret = bin2hex(random_bytes(32));
            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, $secret);
            fflush($handle);
            @chmod($path, 0640);
        }

        flock($handle, LOCK_UN);
        fclose($handle);
        return $secret;
    }

    function ds_form_token(string $root, string $scope = 'form'): string {
        $secret = ds_security_secret($root);
        if ($secret === '') return '';
        $ts = time();
        $nonce = bin2hex(random_bytes(8));
        $payload = $scope . '|' . $ts . '|' . $nonce;
        $sig = hash_hmac('sha256', $payload, $secret);
        return $ts . '.' . $nonce . '.' . $sig;
    }

    function ds_verify_form_token(
        string $root,
        string $token,
        string $scope = 'form',
        int $minAge = 1,
        int $maxAge = 7200
    ): bool {
        $parts = explode('.', trim($token));
        if (count($parts) !== 3) return false;
        [$tsRaw, $nonce, $sig] = $parts;
        if (!ctype_digit($tsRaw) || !preg_match('/^[a-f0-9]{16}$/', $nonce) || !preg_match('/^[a-f0-9]{64}$/', $sig)) return false;

        $ts = (int)$tsRaw;
        $age = time() - $ts;
        if ($age < $minAge || $age > $maxAge) return false;

        $secret = ds_security_secret($root);
        if ($secret === '') return false;
        $payload = $scope . '|' . $ts . '|' . $nonce;
        $expected = hash_hmac('sha256', $payload, $secret);
        return hash_equals($expected, $sig);
    }

    function ds_security_log(string $root, string $event, array $context = []): void {
        $storage = rtrim($root, '/\\') . '/storage';
        if (!is_dir($storage) && !@mkdir($storage, 0755, true) && !is_dir($storage)) return;

        $safeContext = [];
        foreach ($context as $key => $value) {
            if (!is_scalar($value) && $value !== null) continue;
            $safeContext[mb_substr((string)$key, 0, 60)] = mb_substr((string)$value, 0, 240);
        }

        $row = [
            'time' => gmdate('c'),
            'event' => mb_substr($event, 0, 100),
            'ip_hash' => ds_ip_hash(),
            'context' => $safeContext,
        ];
        $line = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($line !== false) @file_put_contents($storage . '/security.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
