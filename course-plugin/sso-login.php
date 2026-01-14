<?php
/**
 * WordPress → Moodle SSO Login Handler
 * 
 * Этот файл должен быть размещен в корневой директории Moodle:
 * /var/www/www-root/data/www/class.russianseminary.org/sso-login.php
 * 
 * Использование:
 * https://class.russianseminary.org/sso-login.php?token=TOKEN
 * 
 * Файл автоматически загружает config.php Moodle и входит пользователя.
 */

// Загружаем конфигурацию Moodle
// config.php находится в корневой директории Moodle
require_once(__DIR__ . '/config.php');

// Логируем начало выполнения скрипта
error_log('Moodle SSO Login: Скрипт запущен. Время: ' . date('Y-m-d H:i:s'));
error_log('Moodle SSO Login: PHP error_log настроен на: ' . ini_get('error_log'));

// Получаем токен из URL
$token = optional_param('token', '', PARAM_RAW);
error_log('Moodle SSO Login: Получен токен из URL. Длина: ' . strlen($token) . ', первые 20 символов: ' . substr($token, 0, 20) . '...');

if (empty($token)) {
    // Если токен не предоставлен, перенаправляем на страницу входа
    redirect(new moodle_url('/login/index.php'), 'Токен SSO не предоставлен', null, \core\output\notification::NOTIFY_ERROR);
}

// Настройки WordPress SSO
// ВАЖНО: Замените эти значения на ваши настройки из WordPress
$wordpress_url = 'https://mbs.russianseminary.org'; // URL вашего WordPress сайта
// SSO API Key (НЕ Moodle SSO API Key!) из WordPress: Настройки → Moodle Sync → SSO API Key
// Этот ключ используется для проверки токенов при переходе из WordPress в Moodle
$sso_api_key = ''; // Если пусто, проверка будет работать без ключа (только по токену)

// Проверяем, что настройки заполнены
if (empty($wordpress_url)) {
    redirect(new moodle_url('/login/index.php'), 'SSO не настроен. Обратитесь к администратору.', null, \core\output\notification::NOTIFY_ERROR);
}

// Проверяем токен через WordPress API
$api_url = rtrim($wordpress_url, '/') . '/wp-admin/admin-ajax.php';
$params = array(
    'action' => 'verify_sso_token',
    'token' => $token,
    'service' => 'moodle',
);

// Если используется API ключ, добавляем его
if (!empty($sso_api_key)) {
    $params['api_key'] = $sso_api_key;
}

// Выполняем запрос к WordPress API
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url . '?' . http_build_query($params));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

// Логируем попытку входа
error_log('Moodle SSO Login: Попытка входа с токеном (первые 20 символов): ' . substr($token, 0, 20) . '...');
error_log('Moodle SSO Login: HTTP код ответа: ' . $http_code);
if (!empty($curl_error)) {
    error_log('Moodle SSO Login: Ошибка cURL: ' . $curl_error);
}

if ($http_code !== 200) {
    // Ошибка при запросе к WordPress
    error_log('Moodle SSO Login: Ошибка при запросе к WordPress. HTTP код: ' . $http_code);
    redirect(new moodle_url('/login/index.php'), 'Ошибка проверки токена SSO. Обратитесь к администратору.', null, \core\output\notification::NOTIFY_ERROR);
}

$data = json_decode($response, true);

// Логируем полный ответ для отладки
error_log('Moodle SSO Login: Ответ от WordPress API: ' . print_r($data, true));
error_log('Moodle SSO Login: Raw response: ' . substr($response, 0, 500));

if (!isset($data['success']) || !$data['success']) {
    // Токен недействителен
    $error_message = isset($data['data']['message']) ? $data['data']['message'] : 'Неизвестная ошибка';
    error_log('Moodle SSO Login: Токен недействителен. Ошибка: ' . $error_message);
    error_log('Moodle SSO Login: Полный ответ: ' . print_r($data, true));
    redirect(new moodle_url('/login/index.php'), 'Токен SSO недействителен или истек: ' . htmlspecialchars($error_message), null, \core\output\notification::NOTIFY_ERROR);
}

if (!isset($data['data'])) {
    error_log('Moodle SSO Login: Данные пользователя не найдены в ответе');
    redirect(new moodle_url('/login/index.php'), 'Ошибка: данные пользователя не найдены', null, \core\output\notification::NOTIFY_ERROR);
}

$user_data = $data['data'];
$email = isset($user_data['email']) ? $user_data['email'] : '';

if (empty($email)) {
    error_log('Moodle SSO Login: Email не найден в данных пользователя');
    redirect(new moodle_url('/login/index.php'), 'Ошибка: email не найден', null, \core\output\notification::NOTIFY_ERROR);
}

// Ищем пользователя в Moodle по email
global $DB;
$user = $DB->get_record('user', array('email' => $email, 'deleted' => 0));

if (!$user) {
    // Пользователь не найден в Moodle
    error_log('Moodle SSO Login: Пользователь с email ' . $email . ' не найден в Moodle');
    redirect(new moodle_url('/login/index.php'), 'Пользователь с email ' . htmlspecialchars($email) . ' не найден в Moodle. Обратитесь к администратору.', null, \core\output\notification::NOTIFY_ERROR);
}

// Автоматически входим пользователя
complete_user_login($user);

// Логируем успешный вход
error_log('Moodle SSO Login: Пользователь ' . $email . ' (ID: ' . $user->id . ') успешно вошел в Moodle через SSO');

// Перенаправляем на главную страницу Moodle
redirect(new moodle_url('/'));
