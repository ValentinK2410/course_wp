<?php
/**
 * Moodle → WordPress SSO (обратный SSO)
 * 
 * Этот файл должен быть размещен в директории public Moodle:
 * /var/www/www-root/data/www/class.dekan.pro/public/moodle-sso-to-wordpress.php
 * 
 * config.php Moodle находится на уровень выше:
 * /var/www/www-root/data/www/class.dekan.pro/config.php
 * 
 * Использование:
 * https://class.dekan.pro/moodle-sso-to-wordpress.php
 * 
 * Пользователь должен быть авторизован в Moodle.
 * Файл автоматически загружает config.php из родительской директории.
 */

// Загружаем конфигурацию Moodle
// config.php находится на уровень выше: /var/www/www-root/data/www/class.dekan.pro/config.php
require_once(__DIR__ . '/../config.php');

// Проверяем, что пользователь авторизован в Moodle
require_login();

// Получаем текущего пользователя Moodle
global $USER;

if (!$USER || !$USER->id) {
    redirect(new moodle_url('/login/index.php'), 'Пользователь Moodle не авторизован', null, \core\output\notification::NOTIFY_ERROR);
}

// Проверяем, что у пользователя есть email
if (empty($USER->email)) {
    redirect(new moodle_url('/'), 'У вашего аккаунта Moodle не указан email. Обратитесь к администратору.', null, \core\output\notification::NOTIFY_ERROR);
}

// Настройки WordPress SSO
// ВАЖНО: Замените эти значения на ваши настройки из WordPress
$wordpress_url = 'https://site.dekan.pro'; // URL вашего WordPress сайта
$moodle_sso_api_key = 'zf9Nt1ckaYFIwK6qPYTb7f8peaNu10W9p2BGbHXpjAJuVFFs9qH8AEyzAvBDbW2R'; // Ключ из WordPress: Настройки → Moodle Sync → Moodle SSO API Key

// Проверяем, что настройки заполнены
if (empty($wordpress_url) || empty($moodle_sso_api_key) || $moodle_sso_api_key === 'ВАШ_MOODLE_SSO_API_KEY') {
    redirect(new moodle_url('/'), 'SSO не настроен. Обратитесь к администратору.', null, \core\output\notification::NOTIFY_ERROR);
}

// Генерируем токен для WordPress
// Формат: base64(user_id:email:timestamp:hash)
$timestamp = time();
$data = $USER->id . '|' . $USER->email . '|' . $timestamp;
$token_hash = hash_hmac('sha256', $data, $moodle_sso_api_key);
$sso_token = base64_encode($USER->id . ':' . $USER->email . ':' . $timestamp . ':' . $token_hash);

// URL для перенаправления в WordPress
$redirect_url = rtrim($wordpress_url, '/') . '/wp-admin/admin-ajax.php?' . http_build_query([
    'action' => 'sso_login_from_moodle',
    'token' => $sso_token,
    'moodle_api_key' => $moodle_sso_api_key,
]);

// Логируем попытку входа
error_log('Moodle SSO: Пользователь ' . $USER->email . ' (ID: ' . $USER->id . ') пытается войти в WordPress');
error_log('Moodle SSO: Используемый API ключ (первые 20 символов): ' . substr($moodle_sso_api_key, 0, 20) . '...');
error_log('Moodle SSO: Длина API ключа: ' . strlen($moodle_sso_api_key));
error_log('Moodle SSO: URL редиректа: ' . $redirect_url);

// Перенаправляем в WordPress (используем обычный HTTP редирект для внешнего URL)
header('Location: ' . $redirect_url);
exit;
