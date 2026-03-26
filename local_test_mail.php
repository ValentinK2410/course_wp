<?php
/**
 * Локальная проверка отправки почты через WordPress (wp_mail).
 *
 * Разместите файл в КОРНЕ WordPress (рядом с wp-load.php) или поправьте путь $wp_load_candidates ниже.
 * После проверки удалите файл с сервера или ограничьте доступ.
 *
 * Браузер: https://ваш-сайт.ru/local_test_mail.php?key=ВАШ_СЕКРЕТ&to=email@example.com
 * CLI:     LOCAL_TEST_MAIL_SECRET=xxx php local_test_mail.php email@example.com
 */

declare(strict_types=1);

// Секрет: задайте переменную окружения или замените строку (не коммитьте реальный ключ в публичный репозиторий).
$secret = getenv('LOCAL_TEST_MAIL_SECRET');
if ($secret === false || $secret === '') {
    $secret = 'CHANGE_ME_BEFORE_USE';
}

$wp_load_candidates = array(
    __DIR__ . '/wp-load.php',
    dirname(__DIR__) . '/wp-load.php',
);

$wp_load = null;
foreach ($wp_load_candidates as $path) {
    if (is_readable($path)) {
        $wp_load = $path;
        break;
    }
}

$is_cli = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg');

if (!$is_cli) {
    header('Content-Type: text/plain; charset=UTF-8');
    if (!isset($_GET['key']) || !hash_equals((string) $secret, (string) $_GET['key'])) {
        http_response_code(403);
        echo "403: неверный или отсутствует параметр key.\n";
        exit;
    }
}

if ($wp_load === null) {
    http_response_code(500);
    echo "Не найден wp-load.php. Положите local_test_mail.php в корень WordPress или добавьте путь в \$wp_load_candidates.\n";
    exit;
}

require_once $wp_load;

if (!function_exists('wp_mail')) {
    http_response_code(500);
    echo "wp_mail недоступен после загрузки WordPress.\n";
    exit;
}

$to = '';
if ($is_cli) {
    $to = isset($argv[1]) ? trim((string) $argv[1]) : '';
} else {
    $to = isset($_GET['to']) ? sanitize_email((string) $_GET['to']) : '';
}

if ($to === '' || !is_email($to)) {
    http_response_code(400);
    echo "Укажите корректный email.\n";
    echo "Пример: ?key=...&to=test@example.com\n";
    echo "CLI: php local_test_mail.php test@example.com\n";
    exit;
}

$blog = function_exists('wp_specialchars_decode') && function_exists('get_option')
    ? wp_specialchars_decode((string) get_option('blogname'), ENT_QUOTES)
    : 'WordPress';

$subject = sprintf('[%s] Тест почты local_test_mail', $blog);
$message = sprintf(
    "Это тестовое письмо от %s\nВремя: %s\nPHP: %s\n",
    $blog,
    gmdate('Y-m-d H:i:s') . ' UTC',
    PHP_VERSION
);

$headers = array('Content-Type: text/plain; charset=UTF-8');

$sent = wp_mail($to, $subject, $message, $headers);

if ($sent) {
    echo "OK: wp_mail вернул true. Проверьте ящик «{$to}» и спам.\n";
    exit(0);
}

echo "ОШИБКА: wp_mail вернул false.\n";
if (isset($GLOBALS['phpmailer']) && is_object($GLOBALS['phpmailer']) && !empty($GLOBALS['phpmailer']->ErrorInfo)) {
    echo 'PHPMailer: ' . $GLOBALS['phpmailer']->ErrorInfo . "\n";
}
if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_get_last')) {
    $e = error_get_last();
    if ($e) {
        echo 'Last PHP error: ' . print_r($e, true) . "\n";
    }
}
exit(1);
