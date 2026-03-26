<?php
/**
 * Одноразовый скрипт: отключает Яндекс-SMTP, настраивает отправку через сервер и тестирует.
 *
 * Запуск на сервере:
 *   cd /var/www/www-root/data/www/mbs.russianseminary.org
 *   php fix_mail_now.php
 *
 * После успешного теста удалите этот файл.
 */

declare(strict_types=1);

$is_cli = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg');
if (!$is_cli) {
    header('HTTP/1.1 403 Forbidden');
    echo "Только из CLI.\n";
    exit;
}

$_SERVER['HTTP_HOST']      = $_SERVER['HTTP_HOST'] ?? 'mbs.russianseminary.org';
$_SERVER['REQUEST_URI']    = '/fix_mail_now.php';
$_SERVER['SERVER_NAME']    = $_SERVER['HTTP_HOST'];
$_SERVER['HTTPS']          = 'on';
$_SERVER['SERVER_PORT']    = '443';
$_SERVER['REQUEST_METHOD'] = 'GET';

$wp_load = __DIR__ . '/wp-load.php';
if (!is_readable($wp_load)) {
    echo "wp-load.php не найден в " . __DIR__ . "\n";
    exit(1);
}
require_once $wp_load;

$test_to = 'valentink2410@gmail.com';

echo "=== Шаг 1: Отключаем внешний SMTP (Яндекс) ===\n";
update_option('course_smtp_enabled', false);
echo "course_smtp_enabled → false\n";

$host = get_option('course_smtp_host', '');
$user = get_option('course_smtp_username', '');
echo "Яндекс-SMTP данные остаются в БД (host={$host}, user={$user}), но НЕ используются.\n";

echo "\n=== Шаг 2: Отключаем WP Mail SMTP плагин (если он перехватывает) ===\n";
$wp_mail_smtp_opts = get_option('wp_mail_smtp', array());
if (is_array($wp_mail_smtp_opts) && isset($wp_mail_smtp_opts['mail']['mailer'])) {
    echo "WP Mail SMTP mailer: " . $wp_mail_smtp_opts['mail']['mailer'] . "\n";
    if ($wp_mail_smtp_opts['mail']['mailer'] !== 'mail') {
        $wp_mail_smtp_opts['mail']['mailer'] = 'mail';
        update_option('wp_mail_smtp', $wp_mail_smtp_opts);
        echo "Переключён WP Mail SMTP mailer → mail (PHP mail)\n";
    } else {
        echo "Уже стоит 'mail' — ОК.\n";
    }
} else {
    echo "WP Mail SMTP не найден или не настроен — пропускаем.\n";
}

echo "\n=== Шаг 3: Проверяем disable_email_sending ===\n";
$dis = get_option('disable_email_sending', false);
if ($dis) {
    update_option('disable_email_sending', false);
    echo "disable_email_sending было true → сброшено в false.\n";
} else {
    echo "disable_email_sending = false — ОК.\n";
}

echo "\n=== Шаг 4: Проверяем MTA на сервере ===\n";
$sendmail_path = ini_get('sendmail_path');
echo "sendmail_path: " . ($sendmail_path ?: '(пусто)') . "\n";
foreach (array('/usr/sbin/sendmail', '/usr/lib/sendmail', '/usr/sbin/postfix') as $bin) {
    echo "{$bin}: " . (is_executable($bin) ? 'ЕСТЬ' : 'нет') . "\n";
}
$postfix_status = @shell_exec('systemctl is-active postfix 2>/dev/null');
echo "Postfix: " . ($postfix_status ? trim($postfix_status) : 'не определён') . "\n";

echo "\n=== Шаг 4b: Проверяем from_email ===\n";
$from = get_option('course_smtp_from_email', '');
$admin = get_option('admin_email', '');
echo "course_smtp_from_email: " . ($from ?: '(пусто)') . "\n";
echo "admin_email: {$admin}\n";

echo "\n=== Шаг 4c: Проверяем другие почтовые плагины ===\n";
if (function_exists('get_plugins')) {
    $all_plugins = get_plugins();
} else {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    $all_plugins = get_plugins();
}
$active = get_option('active_plugins', array());
foreach ($all_plugins as $file => $data) {
    $name_lower = strtolower($data['Name'] . ' ' . $file);
    if (strpos($name_lower, 'mail') !== false || strpos($name_lower, 'smtp') !== false) {
        $is_active = in_array($file, $active, true) ? 'АКТИВЕН' : 'неактивен';
        echo "  [{$is_active}] {$data['Name']} ({$file})\n";
    }
}

echo "\n=== Шаг 4d: Хуки phpmailer_init ===\n";
global $wp_filter;
if (isset($wp_filter['phpmailer_init'])) {
    $hook = $wp_filter['phpmailer_init'];
    if ($hook instanceof WP_Hook) {
        foreach ($hook->callbacks as $priority => $callbacks) {
            foreach ($callbacks as $id => $cb) {
                $fn = $cb['function'];
                if (is_array($fn)) {
                    $cls = is_object($fn[0]) ? get_class($fn[0]) : (string) $fn[0];
                    echo "  [{$priority}] {$cls}::{$fn[1]}\n";
                } elseif (is_string($fn)) {
                    echo "  [{$priority}] {$fn}\n";
                } else {
                    echo "  [{$priority}] (closure)\n";
                }
            }
        }
    }
} else {
    echo "  Нет хуков phpmailer_init.\n";
}

echo "\n=== Шаг 5: Проверяем is_smtp_fully_configured ===\n";
if (class_exists('Course_Email_Sender')) {
    $configured = Course_Email_Sender::is_smtp_fully_configured();
    echo "is_smtp_fully_configured(): " . ($configured ? 'ДА (ПРОБЛЕМА — SMTP всё ещё включён!)' : 'НЕТ — SMTP отключён, ОК') . "\n";
    if ($configured) {
        echo "Проверьте: возможно, в wp-config.php есть define('COURSE_SMTP_ENABLED', true) или COURSE_SMTP_HOST.\n";
    }
} else {
    echo "Класс Course_Email_Sender не загружен.\n";
}

echo "\n=== Шаг 6: Тест wp_mail (через почту сервера) ===\n";
echo "Отправляем на: {$test_to}\n";

$wp_mail_failed_msg = '';
$on_fail = function ($wp_error) use (&$wp_mail_failed_msg) {
    if (is_wp_error($wp_error)) {
        $wp_mail_failed_msg = $wp_error->get_error_message();
    }
};
add_action('wp_mail_failed', $on_fail, 10, 1);

$blog = wp_specialchars_decode((string) get_option('blogname'), ENT_QUOTES);
$subject = sprintf('[%s] Тест почты fix_mail_now — %s', $blog, gmdate('H:i:s'));
$message = "Это тестовое письмо.\nВремя: " . gmdate('Y-m-d H:i:s') . " UTC\nPHP: " . PHP_VERSION . "\nМетод: wp_mail (без SMTP)\n";
$headers = array('Content-Type: text/plain; charset=UTF-8');

$result = wp_mail($test_to, $subject, $message, $headers);
remove_action('wp_mail_failed', $on_fail, 10);

if ($result) {
    echo "✓ wp_mail вернул TRUE! Проверьте ящик {$test_to} (и спам).\n";
} else {
    echo "✗ wp_mail вернул FALSE.\n";
    if ($wp_mail_failed_msg) {
        echo "wp_mail_failed: {$wp_mail_failed_msg}\n";
    }
    if (isset($GLOBALS['phpmailer']) && is_object($GLOBALS['phpmailer']) && !empty($GLOBALS['phpmailer']->ErrorInfo)) {
        echo "PHPMailer ErrorInfo: " . $GLOBALS['phpmailer']->ErrorInfo . "\n";
        echo "PHPMailer Mailer: " . ($GLOBALS['phpmailer']->Mailer ?? '?') . "\n";
        echo "PHPMailer Host: " . ($GLOBALS['phpmailer']->Host ?? '?') . "\n";
    }

    echo "\n=== Шаг 6b: Тест через mail() напрямую ===\n";
    $subj2 = '[fix_mail_now] mail() test ' . gmdate('H:i:s');
    $body2 = "Тест PHP mail()\n" . gmdate('c') . "\n";
    $hdr2  = "Content-Type: text/plain; charset=UTF-8\r\nFrom: {$blog} <{$admin}>\r\n";
    $r2 = @mail($test_to, $subj2, $body2, $hdr2);
    echo "mail() вернул: " . ($r2 ? 'TRUE — проверьте ящик' : 'FALSE') . "\n";
}

echo "\n=== Шаг 7: Тест через Course_Email_Sender::send_email ===\n";
if (class_exists('Course_Email_Sender')) {
    $sender = Course_Email_Sender::get_instance();
    $res = $sender->send_email($test_to, "[{$blog}] Тест send_email " . gmdate('H:i:s'), "Тест send_email каскад.\n" . gmdate('c') . "\n");
    echo "Результат: " . ($res['success'] ? '✓ УСПЕХ' : '✗ ОШИБКА') . "\n";
    echo "Метод: " . $res['method'] . "\n";
    echo "Сообщение: " . $res['message'] . "\n";
} else {
    echo "Course_Email_Sender не загружен.\n";
}

echo "\n=== Шаг 8: Если ничего не сработало — рекомендации ===\n";
echo "1. Установите Postfix (если не установлен):\n";
echo "   apt-get update && apt-get install -y postfix\n";
echo "   systemctl enable postfix && systemctl start postfix\n";
echo "2. Проверьте sendmail_path в php.ini:\n";
echo "   php -i | grep sendmail_path\n";
echo "   Должно быть: sendmail_path = /usr/sbin/sendmail -t -i\n";
echo "3. Если sendmail_path пустой, добавьте в php.ini:\n";
echo "   sendmail_path = /usr/sbin/sendmail -t -i\n";
echo "   и перезапустите PHP: systemctl restart php*-fpm\n";
echo "4. Проверьте логи: tail -50 /var/log/mail.log\n";

echo "\n=== Готово ===\n";
echo "Если хотя бы один тест показал TRUE/УСПЕХ — письма работают.\n";
echo "Удалите fix_mail_now.php после проверки.\n";
