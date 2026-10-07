<?php
declare(strict_types=1);

if (!defined('DS_ROOT')) {
    http_response_code(404);
    exit;
}

require_once DS_ROOT . '/includes/requests-data.php';

return [
    'id' => 'requests',
    'name' => 'Обращения',
    'menu' => 'Обращения',
    'icon' => '✉',
    'version' => '1.0.0',
    'author' => 'DAG STUDIO',
    'default_enabled' => true,
    'custom_page_head' => true,
    'description' => 'Все обращения из форм «Заказать услуги» и формы возле карты на главной странице. Одобрение, отклонение и защита от спама.',
    'counter' => static function (): string {
        $items = ds_requests_read(DS_ROOT);
        $pending = 0;
        foreach ($items as $item) {
            if (is_array($item) && (string)($item['status'] ?? 'pending') === 'pending') $pending++;
        }
        return $pending > 0 ? ($pending > 99 ? '99+' : (string)$pending) : '';
    },
    'handle' => static function (): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
        csrf_verify();

        $action = (string)($_POST['action'] ?? '');
        $id = preg_replace('/[^a-z0-9-]/i', '', (string)($_POST['id'] ?? ''));
        $items = ds_requests_read(DS_ROOT);
        $found = false;

        foreach ($items as $key => &$item) {
            if (!is_array($item) || (string)($item['id'] ?? '') !== $id) continue;
            $found = true;

            if (in_array($action, ['approve', 'reject', 'pending'], true)) {
                $item['status'] = match ($action) {
                    'approve' => 'approved',
                    'reject' => 'rejected',
                    default => 'pending',
                };
                $item['updated_at'] = gmdate('c');
                audit_log('request.status_changed', ['id' => $id, 'status' => $item['status']]);
            } elseif ($action === 'delete') {
                unset($items[$key]);
                audit_log('request.deleted', ['id' => $id]);
            }
            break;
        }
        unset($item);

        if (!$found) {
            flash('error', 'Обращение не найдено.');
        } elseif (ds_requests_save(DS_ROOT, $items)) {
            flash('success', $action === 'delete' ? 'Обращение удалено.' : 'Статус обращения обновлён.');
        } else {
            flash('error', 'Не удалось сохранить изменения.');
        }

        $filter = (string)($_POST['return_status'] ?? 'all');
        if (!in_array($filter, ['all', 'pending', 'approved', 'rejected'], true)) $filter = 'all';
        admin_redirect('/admin/plugin.php?id=requests&status=' . rawurlencode($filter));
    },
    'render' => static function (): void {
        $items = ds_requests_read(DS_ROOT);
        $status = (string)($_GET['status'] ?? 'all');
        if (!in_array($status, ['all', 'pending', 'approved', 'rejected'], true)) $status = 'all';

        $counts = ['all' => count($items), 'pending' => 0, 'approved' => 0, 'rejected' => 0];
        foreach ($items as $item) {
            $itemStatus = (string)($item['status'] ?? 'pending');
            if (isset($counts[$itemStatus])) $counts[$itemStatus]++;
        }

        $filtered = array_values(array_filter($items, static function ($item) use ($status): bool {
            if (!is_array($item)) return false;
            if ($status === 'all') return true;
            return (string)($item['status'] ?? 'pending') === $status;
        }));
        ?>
        <div class="page-head requests-page-head">
          <div class="requests-title-wrap">
            <div class="requests-title-icon" aria-hidden="true"><span></span><span></span><span></span></div>
            <div>
              <p class="requests-kicker">Центр входящих</p>
              <h1>Обращения</h1>
              <p>Сюда автоматически поступают сообщения из обеих форм главной страницы: «Заказать услуги» и формы возле карты.</p>
            </div>
          </div>
          <div class="requests-head-badge">
            <small>Ожидают решения</small>
            <strong><?= $counts['pending'] ?></strong>
          </div>
        </div>

        <div class="request-source-strip">
          <div><span class="request-source-icon">01</span><strong>Заказать услуги</strong><small>Всплывающая форма на главной</small></div>
          <div><span class="request-source-icon">02</span><strong>Форма возле карты</strong><small>Нижняя форма «Оставить заявку»</small></div>
          <div class="request-source-security"><span>✓</span><strong>Антиспам активен</strong><small>Токен, honeypot, лимиты и фильтры</small></div>
        </div>

        <div class="request-stats">
          <a class="request-stat all <?= $status === 'all' ? 'active' : '' ?>" href="/admin/plugin.php?id=requests&status=all"><span class="request-stat-icon">◫</span><div><strong><?= $counts['all'] ?></strong><span>Все обращения</span></div></a>
          <a class="request-stat pending <?= $status === 'pending' ? 'active' : '' ?>" href="/admin/plugin.php?id=requests&status=pending"><span class="request-stat-icon">●</span><div><strong><?= $counts['pending'] ?></strong><span>Новые</span></div></a>
          <a class="request-stat approved <?= $status === 'approved' ? 'active' : '' ?>" href="/admin/plugin.php?id=requests&status=approved"><span class="request-stat-icon">✓</span><div><strong><?= $counts['approved'] ?></strong><span>Одобрено</span></div></a>
          <a class="request-stat rejected <?= $status === 'rejected' ? 'active' : '' ?>" href="/admin/plugin.php?id=requests&status=rejected"><span class="request-stat-icon">×</span><div><strong><?= $counts['rejected'] ?></strong><span>Отклонено</span></div></a>
        </div>

        <section class="panel requests-panel">
          <div class="panel-title-row">
            <div>
              <h2><?= e(match ($status) { 'pending' => 'Новые обращения', 'approved' => 'Одобренные', 'rejected' => 'Отклонённые', default => 'Все обращения' }) ?></h2>
              <span><?= count($filtered) ?> шт.</span>
            </div>
          </div>

          <?php if (!$filtered): ?><div class="empty requests-empty">В этом разделе пока нет обращений.</div><?php endif; ?>

          <div class="requests-list">
            <?php foreach ($filtered as $item):
              $itemStatus = (string)($item['status'] ?? 'pending');
              $created = strtotime((string)($item['created_at'] ?? '')) ?: 0;
              $sourceLabel = (string)($item['source'] ?? '') === 'modal' ? 'Заказать услуги' : 'Форма возле карты';
              $phoneHref = preg_replace('/[^+0-9]/', '', (string)($item['phone'] ?? '')) ?: '';
            ?>
              <article class="request-card request-status-<?= e($itemStatus) ?>">
                <div class="request-card-top">
                  <div class="request-person">
                    <span class="request-avatar"><?= e(mb_strtoupper(mb_substr((string)($item['name'] ?? '?'), 0, 1))) ?></span>
                    <div>
                      <strong><?= e((string)($item['name'] ?? 'Без имени')) ?></strong>
                      <span><?= e($sourceLabel) ?><?= $created ? ' · ' . e(date('d.m.Y H:i', $created)) : '' ?></span>
                    </div>
                  </div>
                  <span class="request-status-badge <?= e($itemStatus) ?>"><?= e(ds_request_status_label($itemStatus)) ?></span>
                </div>

                <div class="request-contact-grid">
                  <div><small>Телефон</small><a href="tel:<?= e($phoneHref) ?>"><?= e((string)($item['phone'] ?? '')) ?></a></div>
                  <div><small>Почта</small><a href="mailto:<?= e((string)($item['email'] ?? '')) ?>"><?= e((string)($item['email'] ?? '')) ?></a></div>
                </div>

                <div class="request-message">
                  <small>Сообщение</small>
                  <p><?= nl2br(e((string)($item['message'] ?? ''))) ?></p>
                </div>

                <div class="request-security-meta">
                  <span><b>№</b> <?= e((string)($item['id'] ?? '')) ?></span>
                  <?php if (!empty($item['ip_hash'])): ?><span><b>Защита</b> <?= e(substr((string)$item['ip_hash'], 0, 12)) ?>…</span><?php endif; ?>
                </div>

                <div class="request-actions">
                  <?php if ($itemStatus !== 'approved'): ?>
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?= e((string)$item['id']) ?>"><input type="hidden" name="return_status" value="<?= e($status) ?>"><button class="request-action approve" type="submit">✓ Одобрить</button></form>
                  <?php endif; ?>
                  <?php if ($itemStatus !== 'rejected'): ?>
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="reject"><input type="hidden" name="id" value="<?= e((string)$item['id']) ?>"><input type="hidden" name="return_status" value="<?= e($status) ?>"><button class="request-action reject" type="submit">× Отклонить</button></form>
                  <?php endif; ?>
                  <?php if ($itemStatus !== 'pending'): ?>
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="pending"><input type="hidden" name="id" value="<?= e((string)$item['id']) ?>"><input type="hidden" name="return_status" value="<?= e($status) ?>"><button class="request-action neutral" type="submit">↺ Вернуть в новые</button></form>
                  <?php endif; ?>
                  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e((string)$item['id']) ?>"><input type="hidden" name="return_status" value="<?= e($status) ?>"><button class="request-action delete" type="submit" data-confirm="Удалить обращение без возможности восстановления?">Удалить</button></form>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        </section>
        <?php
    },
];
