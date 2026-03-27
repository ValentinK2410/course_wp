<?php
/**
 * Moodle → WordPress/Laravel SSO Buttons (Inline версия)
 * 
 * Этот файл возвращает JavaScript код, который можно встроить напрямую в header.mustache
 * 
 * Для подключения добавьте в header.mustache темы ПРЯМО В КОД (не через script src):
 * {{#isloggedin}}
 * <script>
 * <?php include('/var/www/www-root/data/www/class.russianseminary.org/moodle-sso-buttons-inline.php'); ?>
 * </script>
 * {{/isloggedin}}
 * 
 * ИЛИ используйте AJAX версию (см. moodle-sso-buttons-ajax.js)
 */

// Загружаем конфигурацию Moodle
require_once(__DIR__ . '/config.php');

// Проверяем авторизацию
global $USER;
$is_logged_in = false;

if (isset($USER) && isset($USER->id) && $USER->id > 1) {
    if (function_exists('isguestuser')) {
        $is_logged_in = !isguestuser($USER->id);
    } else {
        $is_logged_in = true;
    }
}

if (!$is_logged_in) {
    echo '// Пользователь не авторизован';
    exit;
}

// Настройки
$wordpress_url = 'https://mbs.russianseminary.org';
$laravel_url = 'https://dean.russianseminary.org';
$sso_api_key = '';

// Получаем данные пользователя
$user_email = isset($USER->email) ? $USER->email : '';
$user_id = isset($USER->id) ? $USER->id : 0;

if (empty($user_email)) {
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
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));

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
?>
(function() {
    console.log('Moodle SSO: Скрипт запущен');
    
    if (document.querySelector('.moodle-sso-buttons-container')) {
        return;
    }

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
        
        // Ищем верхнюю панель (темно-бордовая полоса)
        var topBar = document.querySelector('.top-bar, .header-top, .top-header, .navbar-top');
        
        // Ищем контейнер с иконками (уведомления, сообщения, профиль)
        var iconsContainer = null;
        if (topBar) {
            iconsContainer = topBar.querySelector('.d-flex, .ml-auto, .navbar-nav, [class*="icon"], [class*="notification"]');
        }
        
        var container = iconsContainer || topBar || document.querySelector('.usermenu, .navbar-nav, nav.navbar, header') || document.body;

        var buttonsContainer = document.createElement('div');
        buttonsContainer.className = 'moodle-sso-buttons-container';

        <?php if (!empty($wordpress_token)): ?>
        var wordpressBtn = document.createElement('a');
        wordpressBtn.href = '<?php echo addslashes($wordpress_sso_url); ?>';
        wordpressBtn.className = 'sso-button sso-button-wordpress';
        wordpressBtn.textContent = 'Сайт семинарии';
        wordpressBtn.target = '_blank';
        buttonsContainer.appendChild(wordpressBtn);
        console.log('Moodle SSO: Кнопка WordPress добавлена');
        <?php endif; ?>

        <?php if (!empty($laravel_token)): ?>
        var laravelBtn = document.createElement('a');
        laravelBtn.href = '<?php echo addslashes($laravel_sso_url); ?>';
        laravelBtn.className = 'sso-button sso-button-laravel';
        laravelBtn.textContent = 'Деканат';
        laravelBtn.target = '_blank';
        buttonsContainer.appendChild(laravelBtn);
        console.log('Moodle SSO: Кнопка Laravel добавлена');
        <?php endif; ?>

        if (buttonsContainer.children.length === 0) {
            console.log('Moodle SSO: Нет токенов, кнопки не будут отображены');
            return;
        }

        // Вставляем кнопки
        if (iconsContainer) {
            iconsContainer.appendChild(buttonsContainer);
            console.log('Moodle SSO: Кнопки вставлены в iconsContainer');
        } else if (topBar) {
            topBar.appendChild(buttonsContainer);
            console.log('Moodle SSO: Кнопки вставлены в topBar');
        } else if (container.firstChild) {
            container.insertBefore(buttonsContainer, container.firstChild);
            console.log('Moodle SSO: Кнопки вставлены в начало контейнера');
        } else {
            container.appendChild(buttonsContainer);
            console.log('Moodle SSO: Кнопки вставлены в конец контейнера');
        }
        
        console.log('Moodle SSO: Кнопки успешно добавлены!');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', addSSOButtons);
    } else {
        addSSOButtons();
    }

    setTimeout(addSSOButtons, 500);
    setTimeout(addSSOButtons, 1000);
})();
