<?php
/**
 * Moodle → WordPress/Laravel SSO Buttons
 * 
 * Этот файл должен быть размещен в корневой директории Moodle:
 * /var/www/www-root/data/www/class.russianseminary.org/moodle-sso-buttons.php
 * 
 * Отображает кнопки для перехода в WordPress и Laravel для авторизованных пользователей Moodle.
 * Кнопки можно встроить в шапку Moodle или отобразить на отдельной странице.
 */

// Загружаем конфигурацию Moodle
require_once(__DIR__ . '/config.php');

// Функция для логирования
function sso_log($message) {
    global $CFG;
    if (isset($CFG->dataroot) && !empty($CFG->dataroot)) {
        $log_file = $CFG->dataroot . '/error.log';
        $timestamp = date('Y-m-d H:i:s');
        $log_message = "[{$timestamp}] Moodle SSO Buttons: {$message}\n";
        @file_put_contents($log_file, $log_message, FILE_APPEND);
    }
    error_log('Moodle SSO Buttons: ' . $message);
}

// Проверяем, что пользователь авторизован
require_login();

// Настройки WordPress SSO
$wordpress_url = 'https://mbs.russianseminary.org'; // URL вашего WordPress сайта
$laravel_url = 'https://dekanat.russianseminary.org'; // URL вашего Laravel приложения
$sso_api_key = ''; // SSO API Key из WordPress (опционально)

// Получаем данные текущего пользователя Moodle
global $USER;
$user_email = $USER->email;
$user_id = $USER->id;

sso_log('Пользователь ' . $user_email . ' (ID: ' . $user_id . ') запросил кнопки SSO');

// Генерируем токены через WordPress API
$ajax_url = rtrim($wordpress_url, '/') . '/wp-admin/admin-ajax.php';

// Создаем запрос для получения токенов
$params = array(
    'action' => 'get_sso_tokens_from_moodle',
    'email' => $user_email,
    'moodle_user_id' => $user_id,
);

// Если используется API ключ, добавляем его
if (!empty($sso_api_key)) {
    $params['api_key'] = $sso_api_key;
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $ajax_url . '?' . http_build_query($params));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

$wordpress_token = '';
$laravel_token = '';

if ($http_code === 200 && !empty($response)) {
    $data = json_decode($response, true);
    if (isset($data['success']) && $data['success']) {
        if (isset($data['data']['wordpress_token'])) {
            $wordpress_token = $data['data']['wordpress_token'];
        }
        if (isset($data['data']['laravel_token'])) {
            $laravel_token = $data['data']['laravel_token'];
        }
        sso_log('Токены успешно получены от WordPress');
    } else {
        sso_log('Ошибка получения токенов: ' . (isset($data['data']['message']) ? $data['data']['message'] : 'Неизвестная ошибка'));
    }
} else {
    sso_log('Ошибка HTTP запроса к WordPress: ' . $http_code . ($curl_error ? ', ' . $curl_error : ''));
}

// Если токены не получены, пытаемся сгенерировать их локально
if (empty($wordpress_token) || empty($laravel_token)) {
    // Генерируем простой токен на основе email и времени
    $token_data = $user_email . '|' . $user_id . '|' . time();
    $token_hash = hash('sha256', $token_data . '|' . $CFG->passwordsaltmain);
    $wordpress_token = base64_encode($token_data . '|' . $token_hash);
    $laravel_token = base64_encode($token_data . '|' . $token_hash);
    sso_log('Токены сгенерированы локально');
}

// Формируем URL для перехода
$wordpress_sso_url = rtrim($wordpress_url, '/') . '/wp-admin/admin-ajax.php?action=sso_login_from_moodle&token=' . urlencode($wordpress_token);
$laravel_sso_url = rtrim($laravel_url, '/') . '/sso/login?token=' . urlencode($laravel_token);

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Быстрый переход</title>
    <style>
        .sso-buttons-container {
            display: flex;
            gap: 10px;
            align-items: center;
            padding: 10px;
        }
        .sso-button {
            display: inline-block;
            padding: 10px 20px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            white-space: nowrap;
        }
        .sso-button-wordpress {
            background: #2271b1;
            color: white;
        }
        .sso-button-wordpress:hover {
            background: #135e96;
            color: white;
        }
        .sso-button-laravel {
            background: #f9322c;
            color: white;
        }
        .sso-button-laravel:hover {
            background: #e02823;
            color: white;
        }
    </style>
</head>
<body>
    <div class="sso-buttons-container">
        <?php if (!empty($wordpress_token)): ?>
        <a href="<?php echo htmlspecialchars($wordpress_sso_url); ?>" class="sso-button sso-button-wordpress" target="_blank">
            Сайт семинарии
        </a>
        <?php endif; ?>
        <?php if (!empty($laravel_token)): ?>
        <a href="<?php echo htmlspecialchars($laravel_sso_url); ?>" class="sso-button sso-button-laravel" target="_blank">
            Деканат
        </a>
        <?php endif; ?>
    </div>
</body>
</html>
