<?php
declare(strict_types=1);

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    http_response_code(404);
    exit;
}

if (!function_exists('ds_requests_read')) {
    function ds_requests_path(string $root): string {
        return rtrim($root, '/\\') . '/storage/requests.json';
    }

    function ds_requests_read(string $root): array {
        $path = ds_requests_path($root);
        if (!is_file($path)) return [];

        $raw = @file_get_contents($path);
        if ($raw === false) return [];
        $decoded = json_decode($raw, true);
        $items = is_array($decoded) && is_array($decoded['items'] ?? null) ? $decoded['items'] : [];
        return array_values(array_filter($items, 'is_array'));
    }

    function ds_requests_save(string $root, array $items): bool {
        $storage = rtrim($root, '/\\') . '/storage';
        if (!is_dir($storage) && !@mkdir($storage, 0750, true) && !is_dir($storage)) return false;

        $path = ds_requests_path($root);
        $json = json_encode(
            ['items' => array_values($items)],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        if ($json === false) return false;

        $tmp = $path . '.tmp-' . bin2hex(random_bytes(4));
        if (@file_put_contents($tmp, $json . PHP_EOL, LOCK_EX) === false) return false;
        @chmod($tmp, 0640);
        return @rename($tmp, $path);
    }

    function ds_requests_append(string $root, array $item): bool {
        $storage = rtrim($root, '/\\') . '/storage';
        if (!is_dir($storage) && !@mkdir($storage, 0750, true) && !is_dir($storage)) return false;

        $path = ds_requests_path($root);
        $handle = @fopen($path, 'c+');
        if ($handle === false) return false;
        if (!@flock($handle, LOCK_EX)) {
            fclose($handle);
            return false;
        }

        rewind($handle);
        $raw = stream_get_contents($handle);
        $decoded = is_string($raw) && trim($raw) !== '' ? json_decode($raw, true) : [];
        $items = is_array($decoded) && is_array($decoded['items'] ?? null) ? $decoded['items'] : [];

        array_unshift($items, $item);
        if (count($items) > 5000) {
            $items = array_slice($items, 0, 5000);
        }

        $json = json_encode(
            ['items' => array_values($items)],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        $ok = false;
        if ($json !== false) {
            rewind($handle);
            ftruncate($handle, 0);
            $ok = fwrite($handle, $json . PHP_EOL) !== false;
            fflush($handle);
            @chmod($path, 0640);
        }

        flock($handle, LOCK_UN);
        fclose($handle);
        return $ok;
    }

    function ds_requests_find(array $items, string $id): ?array {
        foreach ($items as $item) {
            if (is_array($item) && (string)($item['id'] ?? '') === $id) return $item;
        }
        return null;
    }

    function ds_requests_fingerprint(string $email, string $phone, string $message): string {
        $normalizedMessage = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $message) ?? $message));
        $normalizedPhone = preg_replace('/\D+/', '', $phone) ?? '';
        return hash('sha256', mb_strtolower(trim($email)) . '|' . $normalizedPhone . '|' . $normalizedMessage);
    }

    function ds_requests_is_recent_duplicate(array $items, string $fingerprint, int $windowSeconds = 600): bool {
        $cutoff = time() - $windowSeconds;
        foreach ($items as $item) {
            if (!is_array($item) || (string)($item['fingerprint'] ?? '') !== $fingerprint) continue;
            $created = strtotime((string)($item['created_at'] ?? '')) ?: 0;
            if ($created >= $cutoff) return true;
        }
        return false;
    }

    function ds_request_status_label(string $status): string {
        return match ($status) {
            'approved' => 'Одобрено',
            'rejected' => 'Отклонено',
            default => 'Новое',
        };
    }
}
