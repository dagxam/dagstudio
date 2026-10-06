<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/security.php';
ds_public_security_headers();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /', true, 303);
    exit;
}

if (!ds_same_origin_request()) {
    ds_security_log(__DIR__, 'form.cross_origin_blocked');
    http_response_code(403);
    exit('Запрос отклонён.');
}

if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 32768) {
    ds_security_log(__DIR__, 'form.body_too_large');
    http_response_code(413);
    exit('Слишком большой запрос.');
}

function clean_line(string $value, int $max): string {
    $value = trim(strip_tags($value));
    $value = preg_replace('/[\r\n]+/', ' ', $value) ?? '';
    return mb_substr($value, 0, $max);
}

function clean_text(string $value, int $max): string {
    $value = trim(strip_tags($value));
    $value = preg_replace("/\r\n?|\n/", "\n", $value) ?? '';
    return mb_substr($value, 0, $max);
}

function esc(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$source = (string)($_POST['source'] ?? 'contact');
$source = in_array($source, ['contact', 'modal'], true) ? $source : 'contact';
$anchor = $source === 'contact' ? '#contacts' : '#top';

if (!empty($_POST['website'] ?? '')) {
    ds_security_log(__DIR__, 'form.honeypot');
    header('Location: /?sent=1&source=' . rawurlencode($source) . $anchor, true, 303);
    exit;
}

$name = clean_line((string)($_POST['name'] ?? ''), 120);
$phone = clean_line((string)($_POST['phone'] ?? ''), 40);
$email = strtolower(clean_line((string)($_POST['email'] ?? ''), 120));
$message = clean_text((string)($_POST['message'] ?? ''), 2000);

$phoneDigits = preg_replace('/\D+/', '', $phone) ?? '';

if ($name === '' || mb_strlen($name) < 2 || strlen($phoneDigits) < 6 || $message === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    ds_security_log(__DIR__, 'form.invalid_input');
    header('Location: /?sent=0&source=' . rawurlencode($source) . $anchor, true, 303);
    exit;
}

$shortWindow = ds_rate_limit(__DIR__, 'contact_form_short', 5, 900, 'contact', true);
$hourWindow = ds_rate_limit(__DIR__, 'contact_form_hour', 20, 3600, 'contact', true);
$globalWindow = ds_rate_limit(__DIR__, 'contact_form_global', 60, 3600, 'contact', false);
if (!$shortWindow || !$hourWindow || !$globalWindow) {
    ds_security_log(__DIR__, 'form.rate_limited');
    header('Location: /?sent=0&source=' . rawurlencode($source) . $anchor, true, 303);
    exit;
}

$settings = [
    'site_name' => 'DAG STUDIO',
    'theme_accent' => '#c96f41',
];
$settingsFile = __DIR__ . '/storage/settings.json';
if (is_file($settingsFile)) {
    $raw = @file_get_contents($settingsFile);
    $decoded = $raw !== false ? json_decode($raw, true) : null;
    if (is_array($decoded)) $settings = array_merge($settings, $decoded);
}

$accent = (string)($settings['theme_accent'] ?? '#c96f41');
if (!preg_match('/^#[0-9a-fA-F]{6}$/', $accent)) $accent = '#c96f41';

$to = 'admin@dagstudio.ru';
$sourceLabel = $source === 'modal' ? 'Кнопка «Заказать услуги»' : 'Форма «Оставить заявку»';
$subject = $source === 'modal' ? 'Новая заявка на услуги — DAG STUDIO' : 'Новая заявка с сайта — DAG STUDIO';
$submittedAt = date('d.m.Y H:i');

$nameHtml = esc($name);
$phoneHtml = esc($phone);
$emailHtml = esc($email);
$messageHtml = nl2br(esc($message));
$sourceHtml = esc($sourceLabel);
$timeHtml = esc($submittedAt);

$html = '<!doctype html>
<html lang="ru">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width"></head>
<body style="margin:0;padding:0;background:#050505;color:#f4f4f1;font-family:Arial,sans-serif">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#050505;padding:32px 14px">
    <tr><td align="center">
      <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:680px;background:#111111;border:1px solid #292929">
        <tr><td style="height:4px;background:' . esc($accent) . ';font-size:0;line-height:0">&nbsp;</td></tr>
        <tr><td style="padding:34px 38px 20px">
          <div style="font-size:12px;letter-spacing:3px;text-transform:uppercase;color:' . esc($accent) . ';font-weight:700">DAG STUDIO</div>
          <h1 style="margin:12px 0 8px;font-size:30px;line-height:1.1;color:#ffffff;text-transform:uppercase">Новая заявка</h1>
          <p style="margin:0;color:#8c8c8c;font-size:14px;line-height:1.6">На сайте оставили новую заявку. Все контактные данные собраны ниже.</p>
        </td></tr>
        <tr><td style="padding:0 38px 6px">
          <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
            <tr>
              <td style="width:50%;padding:13px 14px;background:#0a0a0a;border:1px solid #242424;vertical-align:top">
                <div style="font-size:10px;letter-spacing:1.4px;text-transform:uppercase;color:' . esc($accent) . '">ФИО</div>
                <div style="margin-top:7px;font-size:16px;color:#ffffff;font-weight:700">' . $nameHtml . '</div>
              </td>
              <td style="width:12px"></td>
              <td style="width:50%;padding:13px 14px;background:#0a0a0a;border:1px solid #242424;vertical-align:top">
                <div style="font-size:10px;letter-spacing:1.4px;text-transform:uppercase;color:' . esc($accent) . '">Телефон</div>
                <div style="margin-top:7px;font-size:16px;color:#ffffff;font-weight:700">' . $phoneHtml . '</div>
              </td>
            </tr>
          </table>
        </td></tr>
        <tr><td style="padding:6px 38px">
          <div style="padding:13px 14px;background:#0a0a0a;border:1px solid #242424">
            <div style="font-size:10px;letter-spacing:1.4px;text-transform:uppercase;color:' . esc($accent) . '">Почта клиента</div>
            <div style="margin-top:7px;font-size:16px"><a href="mailto:' . $emailHtml . '" style="color:#ffffff;text-decoration:none;font-weight:700">' . $emailHtml . '</a></div>
          </div>
        </td></tr>
        <tr><td style="padding:6px 38px 12px">
          <div style="padding:16px 14px;background:#0a0a0a;border:1px solid #242424">
            <div style="font-size:10px;letter-spacing:1.4px;text-transform:uppercase;color:' . esc($accent) . '">Задача</div>
            <div style="margin-top:10px;font-size:15px;line-height:1.65;color:#d0d0d0">' . $messageHtml . '</div>
          </div>
        </td></tr>
        <tr><td style="padding:14px 38px 34px">
          <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
            <tr>
              <td style="font-size:12px;color:#686868;line-height:1.5">Источник: ' . $sourceHtml . '<br>Дата: ' . $timeHtml . '</td>
              <td align="right" style="font-size:12px"><a href="mailto:' . $emailHtml . '" style="display:inline-block;padding:11px 16px;border:1px solid ' . esc($accent) . ';color:' . esc($accent) . ';text-decoration:none;text-transform:uppercase;font-weight:700">Ответить клиенту</a></td>
            </tr>
          </table>
        </td></tr>
      </table>
      <div style="max-width:680px;padding:16px 6px 0;color:#444444;font-size:11px;text-align:left">Автоматическое письмо с сайта dagstudio.ru. Получатель: admin@dagstudio.ru.</div>
    </td></tr>
  </table>
</body>
</html>';

$headers = [
    'MIME-Version: 1.0',
    'Content-Type: text/html; charset=UTF-8',
    'From: DAG STUDIO <no-reply@dagstudio.ru>',
    'Reply-To: ' . $email,
    'X-Mailer: DAG STUDIO Website',
];

$encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
$ok = @mail($to, $encodedSubject, $html, implode("\r\n", $headers));

ds_security_log(__DIR__, $ok ? 'form.mail_sent' : 'form.mail_failed', ['source' => $source]);
header('Location: /?sent=' . ($ok ? '1' : '0') . '&source=' . rawurlencode($source) . $anchor, true, 303);
exit;
