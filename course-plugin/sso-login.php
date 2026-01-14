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

// Функция для логирования (записывает в moodledata/error.log)
function sso_log($message) {
    global $CFG;
    // Определяем путь к файлу логов
    if (isset($CFG->dataroot) && !empty($CFG->dataroot)) {
        $log_file = $CFG->dataroot . '/error.log';
    } else {
        // Fallback: используем системный error_log, если dataroot не определен
        $log_file = null;
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[{$timestamp}] Moodle SSO Login: {$message}\n";
    
    // Записываем в файл логов Moodle, если путь определен
    if ($log_file) {
        @file_put_contents($log_file, $log_message, FILE_APPEND);
    }
    
    // Также пишем в PHP error_log для системных логов (всегда работает)
    error_log('Moodle SSO Login: ' . $message);
}

// Логируем начало выполнения скрипта
sso_log('Скрипт запущен');

// Получаем токен из URL
$token = optional_param('token', '', PARAM_RAW);
sso_log('Получен токен из URL. Длина: ' . strlen($token) . ', первые 20 символов: ' . substr($token, 0, 20) . '...');

if (empty($token)) {
    // Если токен не предоставлен, перенаправляем на страницу входа
    sso_log('Ошибка: токен не предоставлен');
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
    sso_log('Ошибка: WordPress URL не настроен');
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

sso_log('Параметры запроса: ' . print_r($params, true));
$post_data = http_build_query($params);
sso_log('POST данные (длина): ' . strlen($post_data));
sso_log('POST данные: ' . $post_data);
sso_log('URL запроса: ' . $api_url);

// Выполняем запрос к WordPress API
// WordPress AJAX работает и через GET, и через POST, поэтому отправляем параметры в URL
// Используем http_build_query с правильными опциями, чтобы избежать экранирования
$query_string = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
$full_url = $api_url . '?' . $query_string;
sso_log('Полный URL с параметрами: ' . $full_url);
sso_log('Query string: ' . $query_string);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $full_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // Не следовать редиректам
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'User-Agent: Moodle-SSO/1.0'
));

// Включаем отладку cURL
$verbose = fopen('php://temp', 'w+');
curl_setopt($ch, CURLOPT_VERBOSE, true);
curl_setopt($ch, CURLOPT_STDERR, $verbose);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
$curl_info = curl_getinfo($ch);

// Читаем отладочную информацию
rewind($verbose);
$verbose_log = stream_get_contents($verbose);
fclose($verbose);

curl_close($ch);

sso_log('HTTP код ответа: ' . $http_code);
sso_log('cURL ошибка: ' . ($curl_error ? $curl_error : 'нет'));
sso_log('cURL info (method): ' . (isset($curl_info['request_header']) ? $curl_info['request_header'] : 'не доступно'));
if (!empty($verbose_log)) {
    sso_log('cURL verbose (первые 500 символов): ' . substr($verbose_log, 0, 500));
}

// Логируем попытку входа
sso_log('Отправка запроса к WordPress API: ' . $api_url);
sso_log('HTTP код ответа: ' . $http_code);
if (!empty($curl_error)) {
    sso_log('Ошибка cURL: ' . $curl_error);
}

if ($http_code !== 200) {
    // Ошибка при запросе к WordPress
    sso_log('Ошибка при запросе к WordPress. HTTP код: ' . $http_code . ', Ответ: ' . substr($response, 0, 500));
    redirect(new moodle_url('/login/index.php'), 'Ошибка проверки токена SSO. Обратитесь к администратору.', null, \core\output\notification::NOTIFY_ERROR);
}

$data = json_decode($response, true);

// Логируем полный ответ для отладки
sso_log('Ответ от WordPress API: ' . print_r($data, true));
sso_log('Raw response (первые 500 символов): ' . substr($response, 0, 500));

if (!isset($data['success']) || !$data['success']) {
    // Токен недействителен
    $error_message = isset($data['data']['message']) ? $data['data']['message'] : 'Неизвестная ошибка';
    sso_log('Токен недействителен. Ошибка: ' . $error_message);
    sso_log('Полный ответ: ' . print_r($data, true));
    redirect(new moodle_url('/login/index.php'), 'Токен SSO недействителен или истек: ' . htmlspecialchars($error_message), null, \core\output\notification::NOTIFY_ERROR);
}

if (!isset($data['data'])) {
    sso_log('Данные пользователя не найдены в ответе');
    redirect(new moodle_url('/login/index.php'), 'Ошибка: данные пользователя не найдены', null, \core\output\notification::NOTIFY_ERROR);
}

$user_data = $data['data'];
$email = isset($user_data['email']) ? $user_data['email'] : '';

if (empty($email)) {
    sso_log('Email не найден в данных пользователя');
    redirect(new moodle_url('/login/index.php'), 'Ошибка: email не найден', null, \core\output\notification::NOTIFY_ERROR);
}

sso_log('Email пользователя из токена: ' . $email);

// Ищем пользователя в Moodle по email
global $DB;
$user = $DB->get_record('user', array('email' => $email, 'deleted' => 0));

if (!$user) {
    // Пользователь не найден в Moodle
    sso_log('Пользователь с email ' . $email . ' не найден в Moodle');
    redirect(new moodle_url('/login/index.php'), 'Пользователь с email ' . htmlspecialchars($email) . ' не найден в Moodle. Обратитесь к администратору.', null, \core\output\notification::NOTIFY_ERROR);
}

sso_log('Пользователь найден в Moodle. ID: ' . $user->id . ', Email: ' . $user->email);

// Автоматически входим пользователя
complete_user_login($user);

// Логируем успешный вход
sso_log('Пользователь ' . $email . ' (ID: ' . $user->id . ') успешно вошел в Moodle через SSO');

// Перенаправляем на главную страницу Moodle
redirect(new moodle_url('/'));
