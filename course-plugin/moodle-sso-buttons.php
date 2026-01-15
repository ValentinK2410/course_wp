<?php
/**
 * Moodle → WordPress/Laravel SSO Buttons
 * 
 * Этот файл должен быть размещен в корневой директории Moodle:
 * /var/www/www-root/data/www/class.russianseminary.org/moodle-sso-buttons.php
 * 
 * Отображает кнопки для перехода в WordPress и Laravel для авторизованных пользователей Moodle.
 * Кнопки автоматически вставляются в шапку Moodle через JavaScript.
 * 
 * Для подключения добавьте в footer.mustache темы:
 * {{#isloggedin}}
 * <script src="{{config.wwwroot}}/moodle-sso-buttons.php" async></script>
 * {{/isloggedin}}
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

// Формируем URL для перехода
$wordpress_sso_url = rtrim($wordpress_url, '/') . '/wp-admin/admin-ajax.php?action=sso_login_from_moodle&token=' . urlencode($wordpress_token);
$laravel_sso_url = rtrim($laravel_url, '/') . '/sso/login?token=' . urlencode($laravel_token);

// Устанавливаем заголовок для JavaScript
header('Content-Type: application/javascript; charset=utf-8');

// Выводим JavaScript и CSS для автоматической вставки кнопок в шапку Moodle
?>
(function() {
    // Проверяем, не добавлены ли уже кнопки
    if (document.querySelector('.moodle-sso-buttons-container')) {
        return;
    }
    
    // Добавляем стили
    var style = document.createElement('style');
    style.textContent = `
        .moodle-sso-buttons-container {
            display: inline-flex;
            gap: 10px;
            align-items: center;
            margin-left: 15px;
        }
        .moodle-sso-buttons-container .sso-button {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            white-space: nowrap;
        }
        .moodle-sso-buttons-container .sso-button-wordpress {
            background: #2271b1;
            color: white;
        }
        .moodle-sso-buttons-container .sso-button-wordpress:hover {
            background: #135e96;
            color: white;
        }
        .moodle-sso-buttons-container .sso-button-laravel {
            background: #f9322c;
            color: white;
        }
        .moodle-sso-buttons-container .sso-button-laravel:hover {
            background: #e02823;
            color: white;
        }
    `;
    document.head.appendChild(style);
    
    function addSSOButtons() {
        // Ищем контейнер с меню пользователя или навигацией в Moodle
        var selectors = [
            '.usermenu',
            '.navbar-nav',
            '.navbar .navbar-nav',
            '.header-actions',
            '.header-actions-container',
            '.user-menu',
            '.usermenu .dropdown',
            '#usermenu',
            '.nav-link.dropdown-toggle',
            '.navbar .ml-auto',
            'header .container-fluid',
            '.navbar .navbar-collapse'
        ];
        
        var container = null;
        for (var i = 0; i < selectors.length; i++) {
            var elements = document.querySelectorAll(selectors[i]);
            if (elements.length > 0) {
                for (var j = 0; j < elements.length; j++) {
                    var text = elements[j].textContent || elements[j].innerText;
                    if (text.indexOf('Профиль') !== -1 || text.indexOf('Выход') !== -1 || 
                        text.indexOf('Profile') !== -1 || text.indexOf('Logout') !== -1 ||
                        elements[j].querySelector('.usermenu') || elements[j].querySelector('.dropdown-toggle')) {
                        container = elements[j];
                        break;
                    }
                }
                if (container) break;
            }
        }
        
        if (!container) {
            var header = document.querySelector('header, .navbar, nav.navbar, .navbar-nav');
            if (header) {
                container = header;
            }
        }
        
        if (!container) {
            console.log('Moodle SSO: Не найден контейнер для вставки кнопок');
            return;
        }
        
        // Создаем контейнер для кнопок
        var buttonsContainer = document.createElement('div');
        buttonsContainer.className = 'moodle-sso-buttons-container';
        
        <?php if (!empty($wordpress_token)): ?>
        var wordpressBtn = document.createElement('a');
        wordpressBtn.href = '<?php echo addslashes($wordpress_sso_url); ?>';
        wordpressBtn.className = 'sso-button sso-button-wordpress';
        wordpressBtn.textContent = 'Сайт семинарии';
        wordpressBtn.target = '_blank';
        buttonsContainer.appendChild(wordpressBtn);
        <?php endif; ?>
        
        <?php if (!empty($laravel_token)): ?>
        var laravelBtn = document.createElement('a');
        laravelBtn.href = '<?php echo addslashes($laravel_sso_url); ?>';
        laravelBtn.className = 'sso-button sso-button-laravel';
        laravelBtn.textContent = 'Деканат';
        laravelBtn.target = '_blank';
        buttonsContainer.appendChild(laravelBtn);
        <?php endif; ?>
        
        // Вставляем кнопки перед меню пользователя или в конец контейнера
        if (container.querySelector('.usermenu, .dropdown-toggle')) {
            var userMenu = container.querySelector('.usermenu, .dropdown-toggle').parentElement;
            if (userMenu && userMenu.parentElement) {
                userMenu.parentElement.insertBefore(buttonsContainer, userMenu);
            } else {
                container.appendChild(buttonsContainer);
            }
        } else {
            container.appendChild(buttonsContainer);
        }
    }
    
    // Ждем загрузки DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', addSSOButtons);
    } else {
        addSSOButtons();
    }
    
    // Также пробуем добавить после небольшой задержки (на случай динамической загрузки)
    setTimeout(addSSOButtons, 500);
})();
