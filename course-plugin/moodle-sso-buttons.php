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

// Логируем для отладки
if (isset($CFG->dataroot) && !empty($CFG->dataroot)) {
    $log_file = $CFG->dataroot . '/error.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[{$timestamp}] Moodle SSO: USER->id=" . (isset($USER->id) ? $USER->id : 'не установлен') . ", is_logged_in=" . ($is_logged_in ? 'true' : 'false') . "\n";
    @file_put_contents($log_file, $log_message, FILE_APPEND);
}

if (!$is_logged_in) {
    header('Content-Type: application/javascript; charset=utf-8');
    echo 'console.log("Moodle SSO: Пользователь не авторизован. USER->id=' . (isset($USER->id) ? $USER->id : 'не установлен') . '");';
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
        
        sso_log('Токены получены от WordPress. WordPress токен (длина): ' . strlen($wordpress_token) . ', Laravel токен (длина): ' . strlen($laravel_token));
    } else {
        sso_log('Ошибка получения токенов от WordPress. Ответ: ' . substr($response, 0, 500));
    }
} else {
    sso_log('HTTP ошибка при получении токенов. Код: ' . $http_code . ', Ответ: ' . substr($response, 0, 500));
}

// Формируем URL для перехода
$wordpress_sso_url = '';
$laravel_sso_url = '';

if (!empty($wordpress_token)) {
    $wordpress_sso_url = rtrim($wordpress_url, '/') . '/wp-admin/admin-ajax.php?action=sso_login_from_moodle&token=' . urlencode($wordpress_token);
}

if (!empty($laravel_token)) {
    $laravel_sso_url = rtrim($laravel_url, '/') . '/sso/login?token=' . urlencode($laravel_token);
    sso_log('Laravel SSO URL сформирован: ' . substr($laravel_sso_url, 0, 100) . '...');
} else {
    sso_log('ВНИМАНИЕ: Laravel токен пустой, кнопка не будет отображена');
}

// Устанавливаем заголовок для JavaScript
header('Content-Type: application/javascript; charset=utf-8');

// Добавляем явный маркер для проверки загрузки
echo "console.log('MOODLE SSO: Файл moodle-sso-buttons.php ЗАГРУЖЕН в ' + new Date().toLocaleString());\n";
?>
(function() {
    console.log('Moodle SSO: Скрипт запущен');
    
    // Глобальный флаг для предотвращения множественных вызовов
    if (window.moodleSSOButtonsProcessing) {
        console.log('Moodle SSO: Скрипт уже обрабатывается, выходим');
        return;
    }
    
    // Проверяем, не добавлены ли уже кнопки
    var existingButtons = document.querySelectorAll('.moodle-sso-buttons-container');
    if (existingButtons.length > 0) {
        console.log('Moodle SSO: Кнопки уже существуют (' + existingButtons.length + ' контейнеров), выходим');
        // Если кнопки уже есть, просто выходим - не удаляем и не добавляем заново
        return;
    }
    
    // Если кнопки уже были добавлены (флаг установлен), но их нет в DOM - сбрасываем флаг
    if (window.moodleSSOButtonsAdded && existingButtons.length === 0) {
        console.log('Moodle SSO: Флаг установлен, но кнопок нет в DOM - сбрасываем флаг');
        window.moodleSSOButtonsAdded = false;
    }
    
    // Устанавливаем флаг обработки
    window.moodleSSOButtonsProcessing = true;

    // Добавляем стили
    var style = document.createElement('style');
    style.textContent = `
        .moodle-sso-buttons-container {
            display: inline-flex;
            gap: 15px;
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
            margin-right: 10px;
        }
        .moodle-sso-buttons-container .sso-button-laravel:hover {
            background: #e02823;
        }
    `;
    document.head.appendChild(style);

    function addSSOButtons() {
        // Проверяем флаг перед выполнением
        if (window.moodleSSOButtonsAdded) {
            console.log('Moodle SSO: Кнопки уже добавлены (флаг установлен), пропускаем');
            return;
        }
        
        // Проверяем, не добавлены ли уже кнопки в DOM
        var existingInDOM = document.querySelector('.moodle-sso-buttons-container');
        if (existingInDOM) {
            console.log('Moodle SSO: Кнопки уже существуют в DOM, устанавливаем флаг и выходим');
            window.moodleSSOButtonsAdded = true;
            window.moodleSSOButtonsProcessing = false;
            return;
        }
        
        // Проверяем флаг обработки
        if (window.moodleSSOButtonsProcessing && !window.moodleSSOButtonsAdded) {
            // Это нормально - мы в процессе добавления
            console.log('Moodle SSO: Функция addSSOButtons вызвана (в процессе обработки)');
        } else if (!window.moodleSSOButtonsProcessing) {
            console.log('Moodle SSO: Функция addSSOButtons вызвана, но обработка не начата - устанавливаем флаг');
            window.moodleSSOButtonsProcessing = true;
        }
        
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
        laravelBtn.onclick = function() {
            console.log('Moodle SSO: Переход в Laravel. URL: ' + this.href);
        };
        buttonsContainer.appendChild(laravelBtn);
        console.log('Moodle SSO: Кнопка Laravel создана. URL длина: ' + laravelBtn.href.length);
        <?php else: ?>
        console.log('Moodle SSO: Laravel токен пустой, кнопка не создана');
        <?php endif; ?>

        // Если нет кнопок, не вставляем контейнер и сбрасываем флаги
        if (buttonsContainer.children.length === 0) {
            console.log('Moodle SSO: Нет кнопок для вставки, сбрасываем флаги');
            window.moodleSSOButtonsProcessing = false;
            return;
        }
        
        // Устанавливаем флаг сразу после создания контейнера с кнопками
        // Это предотвратит повторные вызовы функции
        window.moodleSSOButtonsAdded = true;

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
        // Флаг moodleSSOButtonsAdded уже установлен выше, сбрасываем только флаг обработки
        window.moodleSSOButtonsProcessing = false;
    }

    // Ждем загрузки DOM и вызываем только один раз
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            // Проверяем флаги и наличие кнопок перед вызовом
            if (!window.moodleSSOButtonsAdded && !window.moodleSSOButtonsProcessing && !document.querySelector('.moodle-sso-buttons-container')) {
                addSSOButtons();
            } else {
                console.log('Moodle SSO: DOMContentLoaded - пропускаем (флаги или кнопки уже есть)');
                window.moodleSSOButtonsProcessing = false;
            }
        });
    } else {
        // DOM уже загружен, проверяем перед вызовом
        if (!window.moodleSSOButtonsAdded && !window.moodleSSOButtonsProcessing && !document.querySelector('.moodle-sso-buttons-container')) {
            addSSOButtons();
        } else {
            console.log('Moodle SSO: DOM загружен - пропускаем (флаги или кнопки уже есть)');
            window.moodleSSOButtonsProcessing = false;
        }
    }

    // Дополнительная попытка через задержку (только если кнопки еще не добавлены)
    setTimeout(function() {
        // Проверяем все условия перед вызовом
        if (!window.moodleSSOButtonsAdded && !window.moodleSSOButtonsProcessing && !document.querySelector('.moodle-sso-buttons-container')) {
            console.log('Moodle SSO: setTimeout - пытаемся добавить кнопки');
            addSSOButtons();
        } else {
            console.log('Moodle SSO: setTimeout - пропускаем (флаги или кнопки уже есть)');
            window.moodleSSOButtonsProcessing = false;
        }
    }, 500);
})();
