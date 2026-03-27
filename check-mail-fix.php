<?php
/**
 * Диагностика и тест отправки почты.
 * Запуск: php check-mail-fix.php
 */
$_SERVER['HTTP_HOST']    = 'mbs.russianseminary.org';
$_SERVER['REQUEST_URI']  = '/';
$_SERVER['SERVER_NAME']  = 'mbs.russianseminary.org';
$_SERVER['HTTPS']        = 'on';
$_SERVER['REQUEST_METHOD'] = 'GET';

require_once __DIR__ . '/wp-load.php';

echo "=== ДИАГНОСТИКА ПОЧТЫ ===\n\n";

// 1. SMTP настройки
$smtp_enabled  = get_option('course_smtp_enabled');
$smtp_host     = get_option('course_smtp_host');
$smtp_port     = get_option('course_smtp_port');
$smtp_user     = get_option('course_smtp_username');
$smtp_pass     = get_option('course_smtp_password');
$smtp_enc      = get_option('course_smtp_encryption');
$smtp_from     = get_option('course_smtp_from_email');

echo "course_smtp_enabled:    " . var_export($smtp_enabled, true) . "\n";
echo "course_smtp_host:       " . $smtp_host . "\n";
echo "course_smtp_port:       " . $smtp_port . "\n";
echo "course_smtp_username:   " . $smtp_user . "\n";
echo "course_smtp_password:   " . (strlen($smtp_pass) > 0 ? 'SET (' . strlen($smtp_pass) . ' chars)' : 'EMPTY') . "\n";
echo "course_smtp_encryption: " . $smtp_enc . "\n";
echo "course_smtp_from_email: " . $smtp_from . "\n\n";

// 2. Если SMTP не включён — включаем
if (empty($smtp_enabled) || $smtp_enabled === '0' || $smtp_enabled === false) {
    echo ">>> SMTP выключен! Включаю...\n";
    update_option('course_smtp_enabled', '1');
    echo ">>> course_smtp_enabled = 1\n\n";
} else {
    echo "SMTP включён — OK\n\n";
}

// 3. Проверяем пароль
if (empty($smtp_pass)) {
    echo "!!! ВНИМАНИЕ: Пароль SMTP пустой! Зайдите в wp-admin -> Настройки -> Email (SMTP) и введите пароль приложения Яндекс.\n\n";
}

// 4. Проверяем класс Course_WP_Login_Registration
echo "Course_WP_Login_Registration: " . (class_exists('Course_WP_Login_Registration') ? 'ЗАГРУЖЕН' : 'НЕ НАЙДЕН') . "\n";
echo "Course_Email_Sender:          " . (class_exists('Course_Email_Sender') ? 'ЗАГРУЖЕН' : 'НЕ НАЙДЕН') . "\n";
echo "Course_Moodle_User_Sync:      " . (class_exists('Course_Moodle_User_Sync') ? 'ЗАГРУЖЕН' : 'НЕ НАЙДЕН') . "\n\n";

// 5. Тест отправки
echo "=== ТЕСТ ОТПРАВКИ ПИСЬМА ===\n";
$to = 'valentink2410@gmail.com';
$subject = 'Test SMTP ' . date('Y-m-d H:i:s');
$body = "Тестовое письмо для проверки SMTP.\nВремя: " . date('Y-m-d H:i:s');

$result = wp_mail($to, $subject, $body);
echo "wp_mail('$to'): " . ($result ? 'SUCCESS' : 'FAILED') . "\n";

global $phpmailer;
if (isset($phpmailer) && $phpmailer->ErrorInfo) {
    echo "PHPMailer error: " . $phpmailer->ErrorInfo . "\n";
}

echo "\nГотово.\n";
