<?php
/**
 * Тестовый файл для проверки работы регистрации
 * Добавьте этот файл в корень плагина и откройте через браузер: 
 * https://ваш-сайт.ru/wp-content/plugins/course-plugin/test-registration.php
 */

// Загружаем WordPress
require_once('../../../wp-load.php');

// Проверяем, что мы в админке или можем выполнить тест
if (!current_user_can('manage_options')) {
    die('Доступ запрещен. Войдите как администратор.');
}

echo '<h1>Тест системы регистрации</h1>';

// Проверка 1: Классы загружены
echo '<h2>1. Проверка классов</h2>';
$classes = array(
    'Course_Registration',
    'Course_Moodle_User_Sync',
    'Course_Moodle_API',
    'Course_Logger'
);

foreach ($classes as $class) {
    if (class_exists($class)) {
        echo "✓ Класс <strong>{$class}</strong> загружен<br>";
    } else {
        echo "✗ Класс <strong>{$class}</strong> НЕ загружен<br>";
    }
}

// Проверка 2: AJAX обработчики зарегистрированы
echo '<h2>2. Проверка AJAX обработчиков</h2>';
global $wp_filter;
$ajax_actions = array(
    'wp_ajax_course_register',
    'wp_ajax_nopriv_course_register',
    'wp_ajax_course_check_moodle_email',
    'wp_ajax_nopriv_course_check_moodle_email'
);

foreach ($ajax_actions as $action) {
    if (isset($wp_filter[$action])) {
        echo "✓ Хук <strong>{$action}</strong> зарегистрирован<br>";
    } else {
        echo "✗ Хук <strong>{$action}</strong> НЕ зарегистрирован<br>";
    }
}

// Проверка 3: Настройки Moodle
echo '<h2>3. Проверка настроек Moodle</h2>';
$moodle_url = get_option('moodle_sync_url', '');
$moodle_token = get_option('moodle_sync_token', '');
$sync_enabled = get_option('moodle_sync_users_enabled', true);

echo "URL Moodle: " . ($moodle_url ? "<strong>{$moodle_url}</strong>" : "<span style='color:red'>НЕ УСТАНОВЛЕН</span>") . "<br>";
echo "Token Moodle: " . ($moodle_token ? "<strong>Установлен</strong> (длина: " . strlen($moodle_token) . ")" : "<span style='color:red'>НЕ УСТАНОВЛЕН</span>") . "<br>";
echo "Синхронизация пользователей: " . ($sync_enabled ? "<strong>Включена</strong>" : "<span style='color:red'>Отключена</span>") . "<br>";

// Проверка 4: Регистрация разрешена
echo '<h2>4. Проверка настроек WordPress</h2>';
$users_can_register = get_option('users_can_register');
echo "Регистрация пользователей: " . ($users_can_register ? "<strong>Разрешена</strong>" : "<span style='color:red'>Запрещена</span>") . "<br>";

// Проверка 5: Логирование
echo '<h2>5. Проверка логирования</h2>';
$log_file = WP_CONTENT_DIR . '/course-registration-debug.log';
$log_dir = dirname($log_file);

if (is_writable($log_dir)) {
    echo "✓ Директория <strong>{$log_dir}</strong> доступна для записи<br>";
    
    // Пробуем записать тестовое сообщение
    $test_message = '[' . date('Y-m-d H:i:s') . '] ТЕСТОВОЕ СООБЩЕНИЕ\n';
    if (@file_put_contents($log_file, $test_message, FILE_APPEND)) {
        echo "✓ Файл <strong>{$log_file}</strong> доступен для записи<br>";
    } else {
        echo "✗ Не удалось записать в файл <strong>{$log_file}</strong><br>";
    }
} else {
    echo "✗ Директория <strong>{$log_dir}</strong> НЕ доступна для записи<br>";
}

// Проверка 6: Инициализация классов
echo '<h2>6. Проверка инициализации классов</h2>';
try {
    if (class_exists('Course_Registration')) {
        $reg_instance = Course_Registration::get_instance();
        echo "✓ Экземпляр <strong>Course_Registration</strong> создан<br>";
    }
    
    if (class_exists('Course_Moodle_User_Sync')) {
        $sync_instance = Course_Moodle_User_Sync::get_instance();
        echo "✓ Экземпляр <strong>Course_Moodle_User_Sync</strong> создан<br>";
    }
} catch (Exception $e) {
    echo "✗ Ошибка при создании экземпляров: " . $e->getMessage() . "<br>";
}

// Проверка 7: Тест AJAX URL
echo '<h2>7. AJAX URL</h2>';
$ajax_url = admin_url('admin-ajax.php');
echo "AJAX URL: <strong>{$ajax_url}</strong><br>";

echo '<h2>Готово!</h2>';
echo '<p>Если все проверки пройдены, попробуйте зарегистрировать пользователя через форму.</p>';
echo '<p>Логи будут в файле: <strong>' . $log_file . '</strong></p>';

