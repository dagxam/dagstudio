<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
admin_require_auth();

$status = (string)($_GET['status'] ?? 'all');
if (!in_array($status, ['all', 'pending', 'approved', 'rejected'], true)) $status = 'all';
admin_redirect('/admin/plugin.php?id=requests&status=' . rawurlencode($status));
