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

// Проверяем, что пользователь авторизован (без редиректа)
global $USER, $CFG;

sso_log('Начало выполнения скрипта. USER установлен: ' . (isset($USER) ? 'да' : 'нет'));
sso_log('isloggedin(): ' . (function_exists('isloggedin') ? (isloggedin() ? 'true' : 'false') : 'функция не существует'));
sso_log('isguestuser(): ' . (function_exists('isguestuser') ? (isguestuser() ? 'true' : 'false') : 'функция не существует'));

if (!isset($USER) || !isloggedin() || isguestuser()) {
    // Если пользователь не авторизован, возвращаем пустой JavaScript
    header('Content-Type: application/javascript; charset=utf-8');
    sso_log('Пользователь не авторизован. USER: ' . (isset($USER) ? 'установлен' : 'не установлен'));
    echo '// Пользователь не авторизован';
    exit;
}

sso_log('Проверка авторизации пройдена. Пользователь: ' . $USER->email . ' (ID: ' . $USER->id . ')');

// Настройки WordPress SSO
$wordpress_url = 'https://mbs.russianseminary.org'; // URL вашего WordPress сайта
$laravel_url = 'https://dekanat.russianseminary.org'; // URL вашего Laravel приложения
$sso_api_key = ''; // SSO API Key из WordPress (опционально)

// Получаем данные текущего пользователя Moodle
global $USER;
$user_email = isset($USER->email) ? $USER->email : '';
$user_id = isset($USER->id) ? $USER->id : 0;
$user_username = isset($USER->username) ? $USER->username : '';

sso_log('Пользователь Moodle - Email: ' . ($user_email ? $user_email : 'ПУСТО') . ', Username: ' . ($user_username ? $user_username : 'ПУСТО') . ', ID: ' . $user_id);

// Проверяем, что email не пустой
if (empty($user_email)) {
    sso_log('ОШИБКА: Email пользователя пустой! Проверяем альтернативные источники...');
    // Пробуем получить email из других источников
    if (isset($USER->email) && !empty($USER->email)) {
        $user_email = $USER->email;
    } elseif (isset($USER->username) && !empty($USER->username)) {
        // Если email пустой, пробуем использовать username
        $user_email = $USER->username;
        sso_log('Используем username как email: ' . $user_email);
    } else {
        sso_log('КРИТИЧЕСКАЯ ОШИБКА: Не удалось получить email или username пользователя!');
        header('Content-Type: application/javascript; charset=utf-8');
        echo 'console.error("Moodle SSO: Не удалось получить email пользователя");';
        exit;
    }
}

// Генерируем токены через WordPress API
// ВАЖНО: WordPress AJAX требует параметр 'action' в URL, а не только в POST данных
$ajax_url = rtrim($wordpress_url, '/') . '/wp-admin/admin-ajax.php?action=get_sso_tokens_from_moodle';

// Проверяем, что email не пустой перед отправкой запроса
if (empty($user_email)) {
    sso_log('КРИТИЧЕСКАЯ ОШИБКА: Email пустой, невозможно сгенерировать токены');
    header('Content-Type: application/javascript; charset=utf-8');
    echo 'console.error("Moodle SSO: Email пользователя не найден");';
    exit;
}

// Создаем запрос для получения токенов
// Параметр 'action' уже в URL, поэтому не добавляем его в POST данные
$params = array(
    'email' => $user_email,
    'moodle_user_id' => $user_id,
);

sso_log('Запрос к WordPress API: ' . $ajax_url);
sso_log('Параметры запроса к WordPress: email=' . $user_email . ', moodle_user_id=' . $user_id);

// Если используется API ключ, добавляем его
if (!empty($sso_api_key)) {
    $params['api_key'] = $sso_api_key;
}

$post_data = http_build_query($params);
sso_log('Отправка POST запроса к WordPress API: ' . $ajax_url);
sso_log('POST данные (raw): ' . $post_data);
sso_log('POST параметры (array): ' . print_r($params, true));

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $ajax_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Content-Type: application/x-www-form-urlencoded',
    'Content-Length: ' . strlen($post_data)
));

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
$curl_info = curl_getinfo($ch);
curl_close($ch);

sso_log('cURL информация: ' . print_r($curl_info, true));

$wordpress_token = '';
$laravel_token = '';

sso_log('HTTP код ответа от WordPress: ' . $http_code);
sso_log('Ответ от WordPress (длина: ' . strlen($response) . ', первые 500 символов): ' . substr($response, 0, 500));
sso_log('Полный ответ от WordPress: ' . $response);
sso_log('URL запроса был: ' . $ajax_url);
sso_log('POST данные были: ' . $post_data);

if ($http_code === 200 && !empty($response)) {
    $data = json_decode($response, true);
    sso_log('Декодированные данные: ' . print_r($data, true));
    
    if (isset($data['success']) && $data['success']) {
        if (isset($data['data']['wordpress_token'])) {
            $wordpress_token = $data['data']['wordpress_token'];
            sso_log('WordPress токен получен (длина: ' . strlen($wordpress_token) . ')');
        } else {
            sso_log('WordPress токен не найден в ответе');
        }
        if (isset($data['data']['laravel_token'])) {
            $laravel_token = $data['data']['laravel_token'];
            sso_log('Laravel токен получен (длина: ' . strlen($laravel_token) . ')');
        } else {
            sso_log('Laravel токен не найден в ответе');
        }
        
        if (!empty($wordpress_token) || !empty($laravel_token)) {
            sso_log('Токены успешно получены от WordPress');
        } else {
            sso_log('ОШИБКА: Токены пустые после успешного ответа!');
        }
    } else {
        $error_msg = isset($data['data']['message']) ? $data['data']['message'] : 'Неизвестная ошибка';
        sso_log('Ошибка получения токенов: ' . $error_msg);
        sso_log('Полный ответ: ' . print_r($data, true));
    }
} else {
    sso_log('Ошибка HTTP запроса к WordPress: ' . $http_code . ($curl_error ? ', ' . $curl_error : ''));
    sso_log('Ответ сервера: ' . substr($response, 0, 500));
}

// Если токены не получены, пытаемся сгенерировать их локально
if (empty($wordpress_token) && empty($laravel_token)) {
    sso_log('Токены не получены от WordPress, пытаемся сгенерировать локально');
    // Генерируем простой токен на основе email и времени
    $token_data = $user_email . '|' . $user_id . '|' . time();
    $token_hash = hash('sha256', $token_data . '|' . (isset($CFG->passwordsaltmain) ? $CFG->passwordsaltmain : 'default_salt'));
    $wordpress_token = base64_encode($token_data . '|' . $token_hash);
    $laravel_token = base64_encode($token_data . '|' . $token_hash);
    sso_log('Токены сгенерированы локально (WordPress: ' . strlen($wordpress_token) . ', Laravel: ' . strlen($laravel_token) . ')');
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
        // Добавляем селекторы для темы Academi
        var selectors = [
            // Селекторы для верхней панели (темно-бордовая полоса)
            '.top-bar',
            '.header-top',
            '.top-header',
            '.navbar-top',
            '.top-navbar',
            '.header-top-bar',
            // Селекторы для меню пользователя
            '.usermenu',
            '.user-menu',
            '.usermenu .dropdown',
            '#usermenu',
            // Селекторы для навигации
            '.navbar-nav',
            '.navbar .navbar-nav',
            '.navbar-nav.d-none.d-md-flex',
            '.navbar-nav.d-flex',
            '.navbar .navbar-nav.d-none.d-md-flex',
            // Селекторы для контейнеров действий
            '.header-actions',
            '.header-actions-container',
            '.navbar .ml-auto',
            '.navbar-nav.ml-auto',
            '.navbar .navbar-nav.ml-auto',
            // Общие селекторы
            '.navbar .d-flex',
            '.navbar .navbar-nav .nav-item',
            '.navbar .nav-item.dropdown',
            'nav.navbar',
            '.navbar-header',
            '.navbar-collapse',
            'header .container-fluid',
            '.navbar-light',
            '.navbar-expand'
        ];
        
        var container = null;
        for (var i = 0; i < selectors.length; i++) {
            var elements = document.querySelectorAll(selectors[i]);
            if (elements.length > 0) {
                for (var j = 0; j < elements.length; j++) {
                    var text = elements[j].textContent || elements[j].innerText;
                    // Ищем контейнер с текстом меню или элементами пользователя
                    if (text.indexOf('Профиль') !== -1 || text.indexOf('Выход') !== -1 || 
                        text.indexOf('Личный кабинет') !== -1 || text.indexOf('Мои курсы') !== -1 ||
                        text.indexOf('Администрирование') !== -1 || text.indexOf('Поиск курса') !== -1 ||
                        text.indexOf('Profile') !== -1 || text.indexOf('Logout') !== -1 ||
                        elements[j].querySelector('.usermenu') || elements[j].querySelector('.dropdown-toggle') ||
                        elements[j].querySelector('.nav-link') || elements[j].querySelector('.navbar-nav')) {
                        container = elements[j];
                        console.log('Moodle SSO: Найден контейнер по селектору:', selectors[i]);
                        break;
                    }
                }
                if (container) break;
            }
        }
        
        // Если не нашли по тексту, пробуем найти по структуре
        if (!container) {
            // Ищем верхнюю темно-бордовую панель
            var topBar = document.querySelector('.top-bar, .header-top, .top-header, .navbar-top');
            if (topBar) {
                container = topBar;
                console.log('Moodle SSO: Найден верхний бар');
            }
        }
        
        if (!container) {
            var header = document.querySelector('header, .navbar, nav.navbar, .navbar-nav');
            if (header) {
                container = header;
            }
        }
        
        if (!container) {
            console.log('Moodle SSO: Не найден контейнер для вставки кнопок. Пробуем добавить в body.');
            // Если не нашли контейнер, добавляем в начало body
            container = document.body;
            if (!container) {
                console.error('Moodle SSO: Body не найден!');
                return;
            }
        }
        
        console.log('Moodle SSO: Найден контейнер:', container);
        
        // Создаем контейнер для кнопок
        var buttonsContainer = document.createElement('div');
        buttonsContainer.className = 'moodle-sso-buttons-container';
        
        var hasButtons = false;
        
        <?php if (!empty($wordpress_token)): ?>
        var wordpressBtn = document.createElement('a');
        wordpressBtn.href = '<?php echo addslashes($wordpress_sso_url); ?>';
        wordpressBtn.className = 'sso-button sso-button-wordpress';
        wordpressBtn.textContent = 'Сайт семинарии';
        wordpressBtn.target = '_blank';
        buttonsContainer.appendChild(wordpressBtn);
        hasButtons = true;
        console.log('Moodle SSO: Добавлена кнопка WordPress');
        <?php else: ?>
        console.warn('Moodle SSO: WordPress токен пустой!');
        <?php endif; ?>
        
        <?php if (!empty($laravel_token)): ?>
        var laravelBtn = document.createElement('a');
        laravelBtn.href = '<?php echo addslashes($laravel_sso_url); ?>';
        laravelBtn.className = 'sso-button sso-button-laravel';
        laravelBtn.textContent = 'Деканат';
        laravelBtn.target = '_blank';
        buttonsContainer.appendChild(laravelBtn);
        hasButtons = true;
        console.log('Moodle SSO: Добавлена кнопка Laravel');
        <?php else: ?>
        console.warn('Moodle SSO: Laravel токен пустой!');
        <?php endif; ?>
        
        // Если кнопок нет, не добавляем контейнер
        if (!hasButtons) {
            console.error('Moodle SSO: ОШИБКА - нет токенов, кнопки не будут добавлены!');
            console.error('Moodle SSO: WordPress токен: <?php echo !empty($wordpress_token) ? "есть" : "пусто"; ?>');
            console.error('Moodle SSO: Laravel токен: <?php echo !empty($laravel_token) ? "есть" : "пусто"; ?>');
            return;
        }
        
        // Вставляем кнопки перед меню пользователя или в конец контейнера
        var inserted = false;
        if (container.querySelector('.usermenu, .dropdown-toggle, .nav-item.dropdown')) {
            var userMenu = container.querySelector('.usermenu, .dropdown-toggle, .nav-item.dropdown');
            if (userMenu) {
                var parent = userMenu.parentElement;
                if (parent && parent.parentElement) {
                    parent.parentElement.insertBefore(buttonsContainer, parent);
                    inserted = true;
                    console.log('Moodle SSO: Кнопки вставлены перед меню пользователя');
                }
            }
        }
        
        if (!inserted) {
            // Пробуем вставить в начало контейнера
            if (container.firstChild) {
                container.insertBefore(buttonsContainer, container.firstChild);
                console.log('Moodle SSO: Кнопки вставлены в начало контейнера');
            } else {
                container.appendChild(buttonsContainer);
                console.log('Moodle SSO: Кнопки вставлены в конец контейнера');
            }
        }
        
        console.log('Moodle SSO: Кнопки успешно добавлены!');
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
