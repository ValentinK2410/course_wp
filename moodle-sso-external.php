<?php
/**
 * Moodle External SSO Login Handler
 * 
 * Этот файл должен быть размещен в КОРНЕ Moodle:
 * /path/to/moodle/sso-login.php
 * 
 * Это внешний скрипт SSO, который НЕ требует установки плагина аутентификации.
 * Он работает независимо от настроек аутентификации Moodle.
 * 
 * Использование:
 * https://class.dekan.pro/sso-login.php?token=YOUR_TOKEN
 */

// Загружаем конфигурацию Moodle
require_once(__DIR__ . '/config.php');

// Проверяем, что токен передан
if (!isset($_GET['token']) || empty($_GET['token'])) {
    // Если токен не передан, перенаправляем на страницу входа
    redirect(new moodle_url('/login/index.php'), 'SSO токен не предоставлен', null, \core\output\notification::NOTIFY_ERROR);
}

// Получаем токен из URL
$token = required_param('token', PARAM_RAW);

// Настройки WordPress SSO
$wordpress_url = 'https://site.dekan.pro';
$sso_api_key = 'bsaQHUiGl4vU59OFcLGBKUohtstpX7JQo4o3S6jlt9qC5tzythZ4b7a1qlAkhPDk';

// Проверяем, что настройки заполнены
if (empty($wordpress_url) || empty($sso_api_key)) {
    redirect(new moodle_url('/login/index.php'), 'SSO не настроен', null, \core\output\notification::NOTIFY_ERROR);
}

// Проверяем токен через WordPress API
$api_url = rtrim($wordpress_url, '/') . '/wp-admin/admin-ajax.php';
$params = array(
    'action' => 'verify_sso_token',
    'token' => $token,
    'service' => 'moodle',
    'api_key' => $sso_api_key,
);

// Выполняем запрос к WordPress API с обработкой ошибок
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url . '?' . http_build_query($params));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

// Обработка ошибок cURL
if ($response === false || !empty($curl_error)) {
    error_log('Moodle SSO: Ошибка cURL - ' . $curl_error);
    redirect(new moodle_url('/login/index.php'), 'Ошибка подключения к WordPress', null, \core\output\notification::NOTIFY_ERROR);
}

// Проверяем HTTP код ответа
if ($http_code !== 200) {
    error_log('Moodle SSO: HTTP код ' . $http_code);
    redirect(new moodle_url('/login/index.php'), 'Ошибка проверки токена', null, \core\output\notification::NOTIFY_ERROR);
}

// Декодируем JSON ответ
$data = json_decode($response, true);

// Проверяем формат ответа
if (!is_array($data)) {
    error_log('Moodle SSO: Неверный формат ответа от WordPress');
    redirect(new moodle_url('/login/index.php'), 'Ошибка проверки токена', null, \core\output\notification::NOTIFY_ERROR);
}

// Проверяем успешность операции
if (!isset($data['success']) || !$data['success'] || !isset($data['data'])) {
    $error_msg = isset($data['message']) ? $data['message'] : 'Токен недействителен';
    error_log('Moodle SSO: ' . $error_msg);
    redirect(new moodle_url('/login/index.php'), $error_msg, null, \core\output\notification::NOTIFY_ERROR);
}

$user_data = $data['data'];

// Проверяем наличие email
if (empty($user_data['email'])) {
    error_log('Moodle SSO: Email не найден в данных пользователя');
    redirect(new moodle_url('/login/index.php'), 'Ошибка данных пользователя', null, \core\output\notification::NOTIFY_ERROR);
}

$email = $user_data['email'];

// Ищем пользователя в Moodle по email
global $DB;
try {
    $user = $DB->get_record('user', array('email' => $email, 'deleted' => 0));
} catch (Exception $e) {
    error_log('Moodle SSO: Ошибка базы данных - ' . $e->getMessage());
    redirect(new moodle_url('/login/index.php'), 'Ошибка поиска пользователя', null, \core\output\notification::NOTIFY_ERROR);
}

if (!$user) {
    // Пользователь не найден в Moodle
    error_log('Moodle SSO: Пользователь с email ' . $email . ' не найден в Moodle');
    redirect(new moodle_url('/login/index.php'), 'Пользователь не найден в системе. Обратитесь к администратору.', null, \core\output\notification::NOTIFY_ERROR);
}

// Автоматически входим пользователя
try {
    complete_user_login($user);
    
    // Логируем успешный вход
    error_log('Moodle SSO: Пользователь ' . $email . ' успешно вошел через SSO');
    
    // Перенаправляем на главную страницу
    redirect(new moodle_url('/'));
} catch (Exception $e) {
    error_log('Moodle SSO: Ошибка входа пользователя - ' . $e->getMessage());
    redirect(new moodle_url('/login/index.php'), 'Ошибка входа в систему', null, \core\output\notification::NOTIFY_ERROR);
}

