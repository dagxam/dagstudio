<?php
declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /');
    exit;
}

function clean(string $value, int $max): string {
    $value = trim(strip_tags($value));
    $value = preg_replace('/[\r\n]+/', ' ', $value) ?? '';
    return mb_substr($value, 0, $max);
}

if (!empty($_POST['website'] ?? '')) {
    header('Location: /?sent=1#contacts');
    exit;
}

$name = clean((string)($_POST['name'] ?? ''), 80);
$phone = clean((string)($_POST['phone'] ?? ''), 40);
$messageRaw = trim(strip_tags((string)($_POST['message'] ?? '')));
$message = mb_substr($messageRaw, 0, 1500);

if ($name === '' || $phone === '' || $message === '') {
    header('Location: /?sent=0#contacts');
    exit;
}

$to = 'admin@dagstudio.ru';
$subject = 'Новая заявка с сайта DAG STUDIO';
$body = "Имя: {$name}\nТелефон: {$phone}\n\nЗадача:\n{$message}\n";
$headers = [
    'From: DAG STUDIO <no-reply@dagstudio.ru>',
    'Reply-To: admin@dagstudio.ru',
    'Content-Type: text/plain; charset=UTF-8'
];

$ok = @mail($to, '=?UTF-8?B?'.base64_encode($subject).'?=', $body, implode("\r\n", $headers));
header('Location: /?sent=' . ($ok ? '1' : '0') . '#contacts');
exit;