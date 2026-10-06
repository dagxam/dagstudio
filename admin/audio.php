<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
admin_require_auth();
admin_redirect('/admin/plugin.php?id=audio');
