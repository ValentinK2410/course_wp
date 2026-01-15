<?php
/**
 * Moodle → WordPress/Laravel SSO Buttons
 * 
 * Простой скрипт для отображения кнопок перехода в WordPress и Laravel
 * 
 * Для подключения добавьте в header.mustache темы:
 * {{#isloggedin}}
 * <script src="{{config.wwwroot}}/moodle-sso-buttons.php" async></script>
 * {{/isloggedin}}
 */

// Загружаем конфигурацию Moodle
require_once(__DIR__ . '/config.php');

// Логируем запуск скрипта
function sso_log($message) {
    global $CFG;
    if (isset($CFG->dataroot) && !empty($CFG->dataroot)) {
        $log_file = $CFG->dataroot . '/error.log';
        $timestamp = date('Y-m-d H:i:s');
        $log_message = "[{$timestamp}] Moodle SSO Buttons: {$message}\n";
        @file_put_contents($log_file, $log_message, FILE_APPEND);
    }
}

sso_log('========== СКРИПТ ЗАПУЩЕН ==========');

// Проверяем авторизацию
global $USER;
$is_logged_in = false;

// Простая проверка: если USER установлен и имеет ID > 1
if (isset($USER) && isset($USER->id) && $USER->id > 1) {
    // Проверяем, что это не гость (гость обычно имеет ID 0 или 1)
    if (function_exists('isguestuser')) {
        $is_logged_in = !isguestuser($USER->id);
    } else {
        // Если функция недоступна, считаем авторизованным если ID > 1
        $is_logged_in = true;
    }
}

if (!$is_logged_in) {
    header('Content-Type: application/javascript; charset=utf-8');
    echo '// Пользователь не авторизован';
    exit;
}

// Настройки
$wordpress_url = 'https://mbs.russianseminary.org';
$laravel_url = 'https://dekanat.russianseminary.org';
$sso_api_key = '';

// Получаем данные пользователя
$user_email = isset($USER->email) ? $USER->email : '';
$user_id = isset($USER->id) ? $USER->id : 0;

if (empty($user_email)) {
    header('Content-Type: application/javascript; charset=utf-8');
    echo 'console.error("Moodle SSO: Email пользователя не найден");';
    exit;
}

// Получаем токены от WordPress
$ajax_url = rtrim($wordpress_url, '/') . '/wp-admin/admin-ajax.php?action=get_sso_tokens_from_moodle';
$params = array(
    'email' => $user_email,
    'moodle_user_id' => $user_id,
);

if (!empty($sso_api_key)) {
    $params['api_key'] = $sso_api_key;
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $ajax_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Content-Type: application/x-www-form-urlencoded'
));

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$wordpress_token = '';
$laravel_token = '';

if ($http_code === 200 && !empty($response)) {
    $data = json_decode($response, true);
    if (isset($data['success']) && $data['success']) {
        $wordpress_token = isset($data['data']['wordpress_token']) ? $data['data']['wordpress_token'] : '';
        $laravel_token = isset($data['data']['laravel_token']) ? $data['data']['laravel_token'] : '';
    }
}

// Формируем URL для перехода
$wordpress_sso_url = rtrim($wordpress_url, '/') . '/wp-admin/admin-ajax.php?action=sso_login_from_moodle&token=' . urlencode($wordpress_token);
$laravel_sso_url = rtrim($laravel_url, '/') . '/sso/login?token=' . urlencode($laravel_token);

// Устанавливаем заголовок для JavaScript
header('Content-Type: application/javascript; charset=utf-8');
?>
(function() {
    console.log('Moodle SSO: Скрипт запущен');
    
    // Проверяем, не добавлены ли уже кнопки
    if (document.querySelector('.moodle-sso-buttons-container')) {
        console.log('Moodle SSO: Кнопки уже существуют');
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
            color: white;
        }
        .moodle-sso-buttons-container .sso-button-wordpress {
            background: #2271b1;
        }
        .moodle-sso-buttons-container .sso-button-wordpress:hover {
            background: #135e96;
        }
        .moodle-sso-buttons-container .sso-button-laravel {
            background: #f9322c;
        }
        .moodle-sso-buttons-container .sso-button-laravel:hover {
            background: #e02823;
        }
    `;
    document.head.appendChild(style);

    function addSSOButtons() {
        console.log('Moodle SSO: Функция addSSOButtons вызвана');
        
        // Ищем верхнюю панель (темно-бордовая полоса с иконками)
        var topBar = document.querySelector('.top-bar, .header-top, .top-header, .navbar-top');
        
        // Ищем меню пользователя
        var userMenu = document.querySelector('.usermenu, .user-menu, .dropdown-toggle');
        
        // Ищем навигационную панель
        var navBar = document.querySelector('.navbar-nav, nav.navbar, .navbar');
        
        var container = null;
        
        // Приоритет 1: верхняя панель
        if (topBar) {
            container = topBar;
            console.log('Moodle SSO: Найден topBar');
        }
        // Приоритет 2: рядом с меню пользователя
        else if (userMenu && userMenu.parentElement) {
            container = userMenu.parentElement;
            console.log('Moodle SSO: Найден userMenu parent');
        }
        // Приоритет 3: навигационная панель
        else if (navBar) {
            container = navBar;
            console.log('Moodle SSO: Найден navBar');
        }
        // Приоритет 4: header
        else {
            container = document.querySelector('header') || document.body;
            console.log('Moodle SSO: Используем header или body');
        }

        // Создаем контейнер для кнопок
        var buttonsContainer = document.createElement('div');
        buttonsContainer.className = 'moodle-sso-buttons-container';
        console.log('Moodle SSO: Контейнер создан');

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

        // Если нет кнопок, не вставляем контейнер
        if (buttonsContainer.children.length === 0) {
            console.log('Moodle SSO: Нет кнопок для вставки');
            return;
        }

        console.log('Moodle SSO: Кнопок для вставки: ' + buttonsContainer.children.length);

        // Вставляем кнопки в верхнюю панель справа от иконок
        if (topBar) {
            // Ищем контейнер с иконками (уведомления, сообщения, профиль)
            var iconsContainer = topBar.querySelector('.d-flex, .ml-auto, .navbar-nav');
            if (iconsContainer) {
                iconsContainer.appendChild(buttonsContainer);
                console.log('Moodle SSO: Кнопки вставлены в iconsContainer');
            } else {
                topBar.appendChild(buttonsContainer);
                console.log('Moodle SSO: Кнопки вставлены в topBar');
            }
        }
        // Вставляем перед меню пользователя
        else if (userMenu && userMenu.parentElement) {
            userMenu.parentElement.insertBefore(buttonsContainer, userMenu);
            console.log('Moodle SSO: Кнопки вставлены перед userMenu');
        }
        // Вставляем в начало контейнера
        else if (container.firstChild) {
            container.insertBefore(buttonsContainer, container.firstChild);
            console.log('Moodle SSO: Кнопки вставлены в начало контейнера');
        }
        // Вставляем в конец контейнера
        else {
            container.appendChild(buttonsContainer);
            console.log('Moodle SSO: Кнопки вставлены в конец контейнера');
        }
        
        console.log('Moodle SSO: Кнопки успешно добавлены!');
    }

    // Ждем загрузки DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', addSSOButtons);
    } else {
        addSSOButtons();
    }

    // Также пробуем добавить после небольшой задержки
    setTimeout(addSSOButtons, 500);
})();
