<?php
/**
 * Moodle SSO Login Handler
 * 
 * Этот файл должен быть размещен в Moodle по пути:
 * /path/to/moodle/auth/sso/login.php
 * 
 * Или можно создать плагин аутентификации для Moodle
 */

// Защита от прямого доступа (если используется как отдельный файл)
// require_once(__DIR__ . '/../../config.php');

// Если файл размещен в auth/sso/, используйте:
require_once(__DIR__ . '/../../config.php');

// Получаем токен из URL
$token = optional_param('token', '', PARAM_RAW);

if (empty($token)) {
    // Если токен не предоставлен, перенаправляем на страницу входа
    redirect(new moodle_url('/login/index.php'), get_string('sso_token_missing', 'auth_sso'), null, \core\output\notification::NOTIFY_ERROR);
}

// Настройки WordPress SSO
$wordpress_url = get_config('auth_sso', 'wordpress_url');
$sso_api_key = get_config('auth_sso', 'sso_api_key');

if (empty($wordpress_url) || empty($sso_api_key)) {
    // Если настройки не заполнены, перенаправляем на страницу входа
    redirect(new moodle_url('/login/index.php'), get_string('sso_not_configured', 'auth_sso'), null, \core\output\notification::NOTIFY_ERROR);
}

// Проверяем токен через WordPress API
$api_url = rtrim($wordpress_url, '/') . '/wp-admin/admin-ajax.php';
$params = array(
    'action' => 'verify_sso_token',
    'token' => $token,
    'service' => 'moodle',
    'api_key' => $sso_api_key,
);

// Выполняем запрос к WordPress API
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url . '?' . http_build_query($params));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200) {
    // Ошибка при запросе к WordPress
    redirect(new moodle_url('/login/index.php'), get_string('sso_verification_failed', 'auth_sso'), null, \core\output\notification::NOTIFY_ERROR);
}

$data = json_decode($response, true);

if (!isset($data['success']) || !$data['success'] || !isset($data['data'])) {
    // Токен недействителен
    redirect(new moodle_url('/login/index.php'), get_string('sso_invalid_token', 'auth_sso'), null, \core\output\notification::NOTIFY_ERROR);
}

$user_data = $data['data'];
$email = $user_data['email'];

// Ищем пользователя в Moodle по email
global $DB;
$user = $DB->get_record('user', array('email' => $email, 'deleted' => 0));

if (!$user) {
    // Пользователь не найден в Moodle
    redirect(new moodle_url('/login/index.php'), get_string('sso_user_not_found', 'auth_sso'), null, \core\output\notification::NOTIFY_ERROR);
}

// Автоматически входим пользователя
complete_user_login($user);

// Перенаправляем на главную страницу
redirect(new moodle_url('/'));

