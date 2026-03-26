<?php
/**
 * Исчерпывающая диагностика почты: PHP, ini, WordPress, плагины SMTP, тесты mail() и wp_mail.
 *
 * Веб:   https://сайт.ru/local_mail_diagnostics.php?key=СЕКРЕТ
 *        &send=1 — дополнительно отправить тестовые письма (mail + wp_mail)
 *        &to=email@example.com
 * CLI:   php local_mail_diagnostics.php
 *        php local_mail_diagnostics.php --send
 *        php local_mail_diagnostics.php --send другой@mail.ru
 *
 * Удалите файл с продакшена после отладки.
 */

declare(strict_types=1);

$default_test_to = 'valentink2410@gmail.com';

$secret = getenv('LOCAL_MAIL_DIAG_SECRET');
if ($secret === false || $secret === '') {
    $secret = getenv('LOCAL_TEST_MAIL_SECRET');
}
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
    if (!isset($_GET['key']) || !hash_equals((string) $secret, (string) $_GET['key'])) {
        header('HTTP/1.1 403 Forbidden');
        header('Content-Type: text/plain; charset=UTF-8');
        echo "403\n";
        exit;
    }
}

$do_send = false;
$test_to = $default_test_to;

if ($is_cli) {
    $args = isset($argv) ? array_slice($argv, 1) : array();
    foreach ($args as $i => $a) {
        if ($a === '--send' || $a === '-s') {
            $do_send = true;
            unset($args[$i]);
        }
    }
    $args = array_values($args);
    if (!empty($args[0]) && filter_var($args[0], FILTER_VALIDATE_EMAIL)) {
        $test_to = $args[0];
    }
} else {
    $do_send = isset($_GET['send']) && $_GET['send'] !== '' && $_GET['send'] !== '0';
    if (isset($_GET['to']) && filter_var((string) $_GET['to'], FILTER_VALIDATE_EMAIL)) {
        $test_to = (string) $_GET['to'];
    }
}

$lines = array();

function diag_line(string $s): void {
    global $lines;
    $lines[] = $s;
}

function diag_section(string $title): void {
    diag_line('');
    diag_line('=== ' . $title . ' ===');
}

function mask_sensitive($data, int $depth = 0) {
    if ($depth > 12) {
        return '…';
    }
    if (is_array($data)) {
        $out = array();
        foreach ($data as $k => $v) {
            $ks = is_string($k) ? $k : (string) $k;
            if (preg_match('/pass|secret|pwd|password|api_?key|token|auth|credential|smtp_pass/i', $ks)) {
                $out[$k] = is_string($v) && $v !== '' ? '***MASKED***' : $v;
            } else {
                $out[$k] = mask_sensitive($v, $depth + 1);
            }
        }
        return $out;
    }
    return $data;
}

function diag_format_value($v): string {
    if (is_bool($v)) {
        return $v ? 'true' : 'false';
    }
    if (is_null($v)) {
        return '(null)';
    }
    if (is_scalar($v)) {
        return (string) $v;
    }
    return print_r(mask_sensitive($v), true);
}

// --- A. Среда PHP ---
diag_section('Среда PHP');
diag_line('PHP: ' . PHP_VERSION);
diag_line('SAPI: ' . PHP_SAPI);
diag_line('OS: ' . PHP_OS_FAMILY . ' / ' . php_uname('r'));
diag_line('Скрипт: ' . __FILE__);
diag_line('CWD: ' . getcwd());
if (function_exists('posix_geteuid')) {
    diag_line('posix UID: ' . posix_geteuid() . (function_exists('posix_getpwuid') ? ' (' . (posix_getpwuid(posix_geteuid())['name'] ?? '?') . ')' : ''));
}
diag_line('memory_limit: ' . ini_get('memory_limit'));
diag_line('max_execution_time: ' . ini_get('max_execution_time'));
diag_line('php.ini: ' . (function_exists('php_ini_loaded_file') ? (php_ini_loaded_file() ?: '(none)') : 'n/a'));

$df = @ini_get('disable_functions');
diag_line('disable_functions (фрагмент): ' . ($df !== false && $df !== '' ? substr($df, 0, 200) . (strlen($df) > 200 ? '…' : '') : '(пусто)'));
if ($df !== false && stripos($df, 'mail') !== false) {
    diag_line('ВНИМАНИЕ: функция mail() может быть отключена в disable_functions.');
}

// --- B. Настройки php.ini, связанные с почтой ---
diag_section('php.ini: почта');
diag_line('sendmail_path: ' . (string) ini_get('sendmail_path'));
diag_line('SMTP (только Win): ' . (string) ini_get('SMTP'));
diag_line('smtp_port: ' . (string) ini_get('smtp_port'));
diag_line('mail.add_x_header: ' . ini_get('mail.add_x_header'));
diag_line('function_exists(mail): ' . (function_exists('mail') ? 'yes' : 'NO'));

$sendmail_bins = array('/usr/sbin/sendmail', '/usr/lib/sendmail', '/bin/sendmail');
foreach ($sendmail_bins as $b) {
    if (is_file($b)) {
        diag_line('найден бинарник: ' . $b . (is_executable($b) ? ' (executable)' : ' (not executable)'));
    }
}

// --- C. WordPress ---
$wp_loaded = false;
if ($wp_load !== null) {
    if ($is_cli) {
        $_SERVER['HTTP_HOST']   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/local_mail_diagnostics.php';
        $_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'];
        $_SERVER['HTTPS']       = $_SERVER['HTTPS'] ?? 'off';
        $_SERVER['SERVER_PORT'] = $_SERVER['SERVER_PORT'] ?? '80';
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }
    require_once $wp_load;
    $wp_loaded = defined('ABSPATH');
}

diag_section('WordPress');
if (!$wp_loaded) {
    diag_line('wp-load.php не найден — разделы WP/SMTP-плагинов пропущены. Путь искали: ' . implode(', ', $wp_load_candidates));
} else {
    global $wp_version;
    diag_line('Версия WP: ' . (isset($wp_version) ? $wp_version : 'unknown'));
    diag_line('ABSPATH: ' . ABSPATH);
    diag_line('siteurl: ' . get_option('siteurl'));
    diag_line('home: ' . get_option('home'));
    diag_line('admin_email: ' . get_option('admin_email'));
    diag_line('blogname: ' . get_option('blogname'));
    diag_line('WP_DEBUG: ' . (defined('WP_DEBUG') && WP_DEBUG ? 'true' : 'false'));
    diag_line('WP_DEBUG_LOG: ' . (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG ? 'true' : 'false'));

    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    $plugins = function_exists('get_plugins') ? get_plugins() : array();
    $mail_slugs = array();
    foreach ($plugins as $file => $p) {
        $name = isset($p['Name']) ? $p['Name'] : '';
        $slug = dirname($file);
        if (preg_match('/smtp|mail|postman|fluent|easy.*smtp|wp-mail/i', $name . ' ' . $file)) {
            $mail_slugs[] = $file . ' — ' . $name . (is_plugin_active($file) ? ' [ACTIVE]' : '');
        }
    }
    if ($mail_slugs) {
        diag_line('Плагины (похожие на почту):');
        foreach ($mail_slugs as $m) {
            diag_line('  - ' . $m);
        }
    } else {
        diag_line('Плагины с "mail/smtp" в имени не найдены в списке (или get_plugins недоступен).');
    }

    // Распространённые опции SMTP в БД (маскируем секреты)
    $option_keys = array(
        'wp_mail_smtp',
        'wp_mail_smtp_debug',
        'postman_options',
        'fluentmail-settings',
        'mail_bank',
        'easy_wp_smtp',
        'swpsmtp_options',
        'smtp_mailer_options',
    );
    diag_section('Опции БД (фрагменты, секреты замаскированы)');
    foreach ($option_keys as $ok) {
        $v = get_option($ok, null);
        if ($v !== null && $v !== false && $v !== '') {
            diag_line($ok . ':');
            $dump = is_array($v) ? mask_sensitive($v) : $v;
            diag_line(is_scalar($dump) ? (string) $dump : print_r($dump, true));
        }
    }

    // Плагин course-plugin: отдельные опции и константы (не путать с wp_mail_smtp)
    if (class_exists('Course_Email_Sender')) {
        diag_section('Плагин course-plugin: Course_Email_Sender');
        diag_line('course_smtp_enabled (внешний SMTP): ' . (get_option('course_smtp_enabled', true) ? 'да' : 'НЕТ — используется почта сервера'));
        $smtp_ok = Course_Email_Sender::is_smtp_fully_configured();
        diag_line('is_smtp_fully_configured(): ' . ($smtp_ok ? 'ДА — на хуке phpmailer_init включается SMTP (приоритет 99999)' : 'нет — SMTP из плагина не подставляется'));
        $course_opt_keys = array(
            'course_smtp_host',
            'course_smtp_port',
            'course_smtp_username',
            'course_smtp_password',
            'course_smtp_encryption',
            'course_smtp_from_email',
            'course_smtp_from_name',
        );
        foreach ($course_opt_keys as $ck) {
            $v = get_option($ck, '');
            if ($ck === 'course_smtp_password') {
                diag_line($ck . ': ' . ($v !== '' && $v !== null ? '*** задано, длина ' . strlen((string) $v) . ' ***' : '(пусто)'));
            } else {
                diag_line($ck . ': ' . ($v !== '' && $v !== null ? (string) $v : '(пусто)'));
            }
        }
        $consts = array(
            'COURSE_SMTP_HOST',
            'COURSE_SMTP_PORT',
            'COURSE_SMTP_USERNAME',
            'COURSE_SMTP_PASSWORD',
            'COURSE_SMTP_ENCRYPTION',
            'COURSE_SMTP_FROM_EMAIL',
            'COURSE_SMTP_FROM_NAME',
        );
        foreach ($consts as $c) {
            if (defined($c)) {
                if ($c === 'COURSE_SMTP_PASSWORD') {
                    diag_line($c . ': определена в wp-config (значение скрыто)');
                } else {
                    diag_line($c . ': ' . (string) constant($c));
                }
            }
        }
    } else {
        diag_section('Плагин course-plugin: Course_Email_Sender');
        diag_line('Класс не загружен (плагин не активен?).');
    }

    // Хуки phpmailer
    diag_section('Хуки phpmailer_init / wp_mail (приоритеты)');
    $diag_hook = static function ($tag) {
        global $wp_filter;
        $out = array();
        if (!isset($wp_filter[$tag])) {
            return $out;
        }
        $hook = $wp_filter[$tag];
        $callbacks = is_object($hook) && isset($hook->callbacks) ? $hook->callbacks : (is_array($hook) ? $hook : array());
        foreach ($callbacks as $prio => $cbs) {
            if (!is_array($cbs)) {
                continue;
            }
            foreach ($cbs as $cb) {
                $fn = isset($cb['function']) ? $cb['function'] : null;
                if ($fn === null) {
                    continue;
                }
                $name = is_array($fn)
                    ? (is_object($fn[0]) ? get_class($fn[0]) : (string) $fn[0]) . '::' . (string) $fn[1]
                    : (is_string($fn) ? $fn : 'closure');
                $out[] = array((int) $prio, $name);
            }
        }
        return $out;
    };
    $list_pi = $diag_hook('phpmailer_init');
    if ($list_pi) {
        foreach ($list_pi as $row) {
            diag_line('  phpmailer_init prio ' . $row[0] . ': ' . $row[1]);
        }
    } else {
        diag_line('  (нет зарегистрированных phpmailer_init в момент проверки)');
    }
    $list_wm = $diag_hook('wp_mail');
    if ($list_wm) {
        foreach ($list_wm as $row) {
            diag_line('  wp_mail prio ' . $row[0] . ': ' . $row[1]);
        }
    } else {
        diag_line('  (нет зарегистрированных wp_mail)');
    }
}

// --- D. DNS (домен получателя) ---
diag_section('DNS (получатель теста)');
$domain = '';
if (strpos($test_to, '@') !== false) {
    $domain = substr(strrchr($test_to, '@'), 1);
}
if ($domain !== '') {
    diag_line('Домен: ' . $domain);
    if (function_exists('checkdnsrr')) {
        diag_line('checkdnsrr MX: ' . (checkdnsrr($domain, 'MX') ? 'yes' : 'no'));
    }
    if (function_exists('dns_get_record')) {
        $mx = @dns_get_record($domain, DNS_MX);
        if (is_array($mx) && $mx !== array()) {
            foreach ($mx as $r) {
                if (isset($r['target'])) {
                    diag_line('  MX → ' . $r['target'] . (isset($r['pri']) ? ' (pri ' . $r['pri'] . ')' : ''));
                }
            }
        } else {
            diag_line('  MX записи не получены или пусто.');
        }
    }
}

// --- E. Тестовая отправка ---
$mail_fn_result = null;
$wp_mail_result = null;
$phpmailer_error = '';

if ($do_send) {
    diag_section('Тестовая отправка (запрошена)');
    diag_line('Получатель: ' . $test_to);

    // mail()
    if (function_exists('mail')) {
        $subj = '[diag mail()] ' . gmdate('Y-m-d H:i:s') . ' UTC';
        $body = "Тест PHP mail()\n" . gmdate('c') . "\n";
        $hdr  = "Content-Type: text/plain; charset=UTF-8\r\n";
        $mail_fn_result = @mail($test_to, $subj, $body, $hdr);
        diag_line('mail() вернул: ' . ($mail_fn_result ? 'true' : 'false'));
    } else {
        diag_line('mail() недоступна.');
    }

    if ($wp_loaded && function_exists('wp_mail')) {
        $blog = wp_specialchars_decode((string) get_option('blogname'), ENT_QUOTES);
        $subj = sprintf('[%s] Тест wp_mail (диагностика)', $blog);
        $body = "Тест wp_mail\n" . gmdate('c') . "\nPHP " . PHP_VERSION . "\n";
        $headers = array('Content-Type: text/plain; charset=UTF-8');

        $wp_mail_result = wp_mail($test_to, $subj, $body, $headers);
        diag_line('wp_mail() вернул: ' . ($wp_mail_result ? 'true' : 'false'));

        if (isset($GLOBALS['phpmailer']) && is_object($GLOBALS['phpmailer'])) {
            $pm = $GLOBALS['phpmailer'];
            if (isset($pm->ErrorInfo) && $pm->ErrorInfo !== '') {
                $phpmailer_error = (string) $pm->ErrorInfo;
                diag_line('PHPMailer ErrorInfo: ' . $phpmailer_error);
            }
            if (property_exists($pm, 'Mailer')) {
                diag_line('PHPMailer Mailer: ' . $pm->Mailer);
            }
            if (property_exists($pm, 'Host') && $pm->Host) {
                diag_line('PHPMailer Host: ' . $pm->Host);
            }
        }
    } elseif ($wp_loaded) {
        diag_line('wp_mail недоступна.');
    }

    if (function_exists('error_get_last')) {
        $e = error_get_last();
        if ($e && isset($e['message'])) {
            diag_line('error_get_last после отправки: ' . $e['message'] . ' @ ' . ($e['file'] ?? '') . ':' . ($e['line'] ?? ''));
        }
    }
} else {
    diag_section('Тестовая отправка');
    diag_line('Не выполнялась. Чтобы отправить тесты:');
    diag_line('  CLI: php ' . basename(__FILE__) . ' --send [' . $test_to . ']');
    diag_line('  Web: добавьте &send=1&to=email (to необязателен)');
}

// --- Вывод ---
$text = implode("\n", $lines);

if ($is_cli) {
    echo $text . "\n";
    exit(0);
}

header('Content-Type: text/html; charset=UTF-8');
echo '<!DOCTYPE html><html lang="ru"><head><meta charset="utf-8"><title>Почта: диагностика</title>';
echo '<style>body{font-family:system-ui,Segoe UI,sans-serif;max-width:960px;margin:24px auto;padding:0 16px;background:#1a1a1a;color:#e8e8e8;}';
echo 'pre{white-space:pre-wrap;background:#111;padding:16px;border-radius:8px;border:1px solid #333;font-size:13px;line-height:1.45;}';
echo 'h1{font-size:1.25rem;color:#7cb8ff;} .note{color:#888;font-size:14px;margin-bottom:16px;}</style></head><body>';
echo '<h1>Диагностика почты</h1>';
echo '<p class="note">Удалите файл после проверки. Секреты в опциях замаскированы.</p>';
echo '<pre>' . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre>';
echo '</body></html>';
