<?php
/**
 * Moodle SSO Login Handler (Simplified Version)
 * 
 * Этот файл должен быть размещен в Moodle по пути:
 * /path/to/moodle/auth/sso/login.php
 * 
 * ВАЖНО: Замените значения WordPress URL и SSO API Key на ваши!
 */

require_once(__DIR__ . '/../../config.php');

// ============================================
// НАСТРОЙКИ - ЗАМЕНИТЕ НА ВАШИ ЗНАЧЕНИЯ!
// ============================================
$wordpress_url = 'https://site.dekan.pro';  // URL вашего WordPress сайта
$sso_api_key = 'ВАШ-SSO-API-KEY-ИЗ-WORDPRESS';  // SSO API Key из настроек WordPress
// ============================================

// Получаем токен из URL
$token = optional_param('token', '', PARAM_RAW);

if (empty($token)) {
    // Если токен не предоставлен, перенаправляем на страницу входа
    redirect(new moodle_url('/login/index.php'), 'SSO токен не предоставлен', null, \core\output\notification::NOTIFY_ERROR);
}

if (empty($wordpress_url) || empty($sso_api_key) || $sso_api_key === 'ВАШ-SSO-API-KEY-ИЗ-WORDPRESS') {
    // Если настройки не заполнены
    redirect(new moodle_url('/login/index.php'), 'SSO не настроен. Обратитесь к администратору.', null, \core\output\notification::NOTIFY_ERROR);
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
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($http_code !== 200 || !empty($curl_error)) {
    // Ошибка при запросе к WordPress
    error_log('Moodle SSO: Ошибка при запросе к WordPress. HTTP код: ' . $http_code . ', Ошибка CURL: ' . $curl_error);
    redirect(new moodle_url('/login/index.php'), 'Ошибка при проверке токена. Попробуйте позже.', null, \core\output\notification::NOTIFY_ERROR);
}

$data = json_decode($response, true);

if (!isset($data['success']) || !$data['success'] || !isset($data['data'])) {
    // Токен недействителен
    error_log('Moodle SSO: Недействительный токен. Ответ: ' . $response);
    redirect(new moodle_url('/login/index.php'), 'Недействительный или истекший токен. Войдите в WordPress заново.', null, \core\output\notification::NOTIFY_ERROR);
}

$user_data = $data['data'];
$email = $user_data['email'];

if (empty($email)) {
    redirect(new moodle_url('/login/index.php'), 'Email не найден в данных пользователя.', null, \core\output\notification::NOTIFY_ERROR);
}

// Ищем пользователя в Moodle по email
global $DB;
$user = $DB->get_record('user', array('email' => $email, 'deleted' => 0));

if (!$user) {
    // Пользователь не найден в Moodle
    error_log('Moodle SSO: Пользователь не найден в Moodle. Email: ' . $email);
    redirect(new moodle_url('/login/index.php'), 'Пользователь не найден в Moodle. Обратитесь к администратору.', null, \core\output\notification::NOTIFY_ERROR);
}

// Автоматически входим пользователя
complete_user_login($user);

// Логируем успешный вход
error_log('Moodle SSO: Пользователь успешно вошел. Email: ' . $email . ', User ID: ' . $user->id);

// Перенаправляем на главную страницу
redirect(new moodle_url('/'));

