<?php
/**
 * Локальная проверка отправки почты.
 *
 * 1) По умолчанию: WordPress wp_mail (учитываются SMTP-плагины).
 * 2) Режим sendmail: PHP mail() → обычно /usr/sbin/sendmail на сервере, без WP.
 *
 * Разместите в корне WordPress (рядом с wp-load.php) или поправьте путь $wp_load_candidates.
 * После проверки удалите файл с сервера.
 *
 * Браузер (wp_mail):  ?key=СЕКРЕТ
 * Браузер (sendmail): ?key=СЕКРЕТ&sendmail=1
 * CLI (wp_mail):       php local_test_mail.php [email]
 * CLI (sendmail):      LOCAL_TEST_USE_SENDMAIL=1 php local_test_mail.php [email]
 *                      или: php local_test_mail.php sendmail [email]
 */

declare(strict_types=1);

$default_to = 'valentink2410@gmail.com';

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

$use_sendmail = false;
if ($is_cli) {
    $env = getenv('LOCAL_TEST_USE_SENDMAIL');
    if ($env === '1' || $env === 'true') {
        $use_sendmail = true;
    }
}

$cli_args = isset($argv) && is_array($argv) ? array_slice($argv, 1) : array();
if ($is_cli && !empty($cli_args[0]) && ($cli_args[0] === 'sendmail' || $cli_args[0] === '--sendmail')) {
    $use_sendmail = true;
    array_shift($cli_args);
}

if (!$is_cli && isset($_GET['sendmail']) && $_GET['sendmail'] !== '' && $_GET['sendmail'] !== '0') {
    $use_sendmail = true;
}

$to = '';
if ($is_cli) {
    $to = isset($cli_args[0]) ? trim((string) $cli_args[0]) : '';
} elseif ($use_sendmail) {
    $to = isset($_GET['to']) ? trim((string) $_GET['to']) : '';
} else {
    $to = ''; // после wp-load
}

if ($to === '' && $default_to !== '') {
    $to = $default_to;
}

if ($use_sendmail) {
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo "Укажите корректный email (параметр to или \$default_to).\n";
        echo "Пример: ?key=...&sendmail=1&to=test@example.com\n";
        echo "CLI: LOCAL_TEST_USE_SENDMAIL=1 php local_test_mail.php\n";
        echo "     php local_test_mail.php sendmail user@example.com\n";
        exit;
    }

    $subject = '[sendmail test] local_test_mail.php ' . gmdate('Y-m-d H:i:s') . ' UTC';
    $message = "Тест PHP mail() (sendmail на сервере).\nВремя: " . gmdate('Y-m-d H:i:s') . " UTC\nPHP: " . PHP_VERSION . "\n";
    $headers = "Content-Type: text/plain; charset=UTF-8\r\n";

    $sent = @mail($to, $subject, $message, $headers);
    if ($sent) {
        echo "OK: mail() вернул true (очередь sendmail/postfix). Проверьте «{$to}» и спам.\n";
        exit(0);
    }
    echo "ОШИБКА: mail() вернул false. Смотрите логи почты (mail.log, journalctl) и права sendmail.\n";
    if (function_exists('error_get_last')) {
        $e = error_get_last();
        if ($e) {
            echo 'Last PHP error: ' . print_r($e, true) . "\n";
        }
    }
    exit(1);
}

if ($wp_load === null) {
    http_response_code(500);
    echo "Не найден wp-load.php. Положите local_test_mail.php в корень WordPress или добавьте путь в \$wp_load_candidates.\n";
    exit;
}

if ($is_cli) {
    if (empty($_SERVER['HTTP_HOST'])) {
        $_SERVER['HTTP_HOST'] = 'localhost';
    }
    $_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';
    $_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'];
    $_SERVER['HTTPS']       = $_SERVER['HTTPS'] ?? 'off';
    $_SERVER['SERVER_PORT'] = $_SERVER['SERVER_PORT'] ?? '80';
    $_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'GET';
}

require_once $wp_load;

if (!function_exists('wp_mail')) {
    http_response_code(500);
    echo "wp_mail недоступен после загрузки WordPress.\n";
    exit;
}

if (!$is_cli) {
    $to = isset($_GET['to']) ? sanitize_email((string) $_GET['to']) : '';
    if ($to === '' && $default_to !== '') {
        $to = $default_to;
    }
}

if ($to === '' || !is_email($to)) {
    http_response_code(400);
    echo "Укажите корректный email в \$default_to или параметр to.\n";
    echo "Пример: ?key=...&to=test@example.com\n";
    echo "CLI: php local_test_mail.php test@example.com\n";
    exit;
}

$blog = function_exists('wp_specialchars_decode') && function_exists('get_option')
    ? wp_specialchars_decode((string) get_option('blogname'), ENT_QUOTES)
    : 'WordPress';

$subject = sprintf('[%s] Тест почты local_test_mail (wp_mail)', $blog);
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
    if (stripos((string) $GLOBALS['phpmailer']->ErrorInfo, 'авторизации') !== false
        || stripos((string) $GLOBALS['phpmailer']->ErrorInfo, 'SMTP') !== false) {
        echo "\nПодсказка: в админке WP откройте настройки SMTP-плагина и проверьте логин, пароль, порт (465/587) и шифрование.\n";
        echo "Или проверьте отправку через sendmail: ?key=...&sendmail=1\n";
    }
}
if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_get_last')) {
    $e = error_get_last();
    if ($e && isset($e['message']) && is_string($e['message'])
        && (stripos($e['message'], 'elementor') !== false || stripos($e['message'], 'theme-builder') !== false)) {
        echo "\n(Сообщение Elementor в CLI часто можно игнорировать при проверке SMTP.)\n";
    } elseif ($e) {
        echo 'Last PHP error: ' . print_r($e, true) . "\n";
    }
}
exit(1);
