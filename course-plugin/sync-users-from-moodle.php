<?php
/**
 * Скрипт для синхронизации пользователей из Moodle в WordPress и Laravel
 * 
 * Использование:
 * 1. Поместите этот файл в корневую директорию WordPress
 * 2. Откройте в браузере: https://site.dekan.pro/sync-users-from-moodle.php
 * 3. Или запустите через командную строку: php sync-users-from-moodle.php
 * 
 * ВАЖНО: После использования удалите этот файл с сервера!
 */

// Загружаем WordPress
require_once('wp-load.php');

// Проверяем права доступа (только администраторы)
if (!current_user_can('manage_options')) {
    die('Доступ запрещен. Только администраторы могут запускать этот скрипт.');
}

// Подключаем необходимые классы
require_once(COURSE_PLUGIN_DIR . 'includes/class-course-moodle-api.php');
require_once(COURSE_PLUGIN_DIR . 'includes/class-course-moodle-user-sync.php');

// Настройки
$moodle_url = get_option('moodle_sync_url', '');
$moodle_token = get_option('moodle_sync_token', '');
$laravel_api_url = get_option('laravel_api_url', '');
$laravel_api_token = get_option('laravel_api_token', '');

// Проверка настроек
if (empty($moodle_url) || empty($moodle_token)) {
    die('ОШИБКА: Настройки Moodle API не заполнены. Перейдите в админ-панель WordPress и заполните настройки синхронизации.');
}

// Инициализация
$moodle_api = new Course_Moodle_API($moodle_url, $moodle_token);
$sync_class = Course_Moodle_User_Sync::get_instance();

// Функция для генерации временного пароля
function generate_temp_password() {
    $length = 12;
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

// Функция для создания пользователя в Laravel
function create_user_in_laravel($user_data, $moodle_user_id) {
    global $laravel_api_url, $laravel_api_token;
    
    if (empty($laravel_api_url) || empty($laravel_api_token)) {
        return array('success' => false, 'message' => 'Laravel API не настроен');
    }
    
    $url = rtrim($laravel_api_url, '/') . '/api/users/sync-from-wordpress';
    
    $data = array(
        'name' => $user_data['name'],
        'email' => $user_data['email'],
        'password' => $user_data['password'],
        'moodle_user_id' => $moodle_user_id,
        'phone' => isset($user_data['phone']) ? $user_data['phone'] : ''
    );
    
    $response = wp_remote_post($url, array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'X-API-Token' => $laravel_api_token
        ),
        'body' => json_encode($data),
        'timeout' => 30
    ));
    
    if (is_wp_error($response)) {
        return array('success' => false, 'message' => $response->get_error_message());
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    
    if ($response_code === 201) {
        return array('success' => true, 'message' => 'Пользователь создан в Laravel');
    }
    
    return array('success' => false, 'message' => 'Ошибка Laravel API: ' . $response_body);
}

// Функция для отправки письма пользователю
function send_sync_notification_email($user_email, $user_login, $temp_password, $moodle_url) {
    $subject = 'Ваш аккаунт синхронизирован';
    
    $message = "Здравствуйте!\n\n";
    $message .= "Ваш аккаунт был синхронизирован между Moodle, WordPress и системой управления обучением.\n\n";
    $message .= "Данные для входа:\n";
    $message .= "Логин: " . $user_login . "\n";
    $message .= "Временный пароль: " . $temp_password . "\n\n";
    $message .= "ВАЖНО: После первого входа рекомендуется сменить пароль.\n\n";
    $message .= "Ссылки для входа:\n";
    $message .= "- WordPress: " . home_url('/wp-login.php') . "\n";
    $message .= "- Moodle: " . rtrim($moodle_url, '/') . "/login/index.php\n";
    
    $laravel_url = get_option('laravel_api_url', '');
    if ($laravel_url) {
        $message .= "- Система управления: " . rtrim($laravel_url, '/') . "\n";
    }
    
    $message .= "\nС уважением,\nАдминистрация";
    
    wp_mail($user_email, $subject, $message);
}

// Получаем всех пользователей из Moodle
echo "<h1>Синхронизация пользователей из Moodle</h1>\n";
echo "<p>Начало синхронизации: " . date('Y-m-d H:i:s') . "</p>\n";
echo "<hr>\n";

try {
    // Получаем пользователей из Moodle
    // Используем core_user_get_users для получения всех пользователей
    // Пробуем разные варианты критериев, так как Moodle API может требовать разные форматы
    $moodle_users = $moodle_api->call('core_user_get_users', array(
        'criteria' => array(
            array(
                'key' => 'deleted',
                'value' => '0' // Только неудаленные пользователи
            )
        )
    ));
    
    // Если не получилось, пробуем без критериев
    if (isset($moodle_users['exception']) || !isset($moodle_users['users'])) {
        $moodle_users = $moodle_api->call('core_user_get_users', array(
            'criteria' => array()
        ));
    }
    
    if (isset($moodle_users['exception'])) {
        die('ОШИБКА Moodle API: ' . $moodle_users['message']);
    }
    
    if (!isset($moodle_users['users']) || !is_array($moodle_users['users'])) {
        die('ОШИБКА: Неожиданный формат ответа от Moodle API. Ответ: ' . print_r($moodle_users, true));
    }
    
    $users = $moodle_users['users'];
    $total_users = count($users);
    
    echo "<p>Найдено пользователей в Moodle: <strong>{$total_users}</strong></p>\n";
    echo "<hr>\n";
    
    $created_count = 0;
    $updated_count = 0;
    $skipped_count = 0;
    $errors = array();
    
    foreach ($users as $moodle_user) {
        $moodle_id = $moodle_user['id'];
        $email = isset($moodle_user['email']) ? $moodle_user['email'] : '';
        $username = isset($moodle_user['username']) ? $moodle_user['username'] : '';
        $firstname = isset($moodle_user['firstname']) ? $moodle_user['firstname'] : '';
        $lastname = isset($moodle_user['lastname']) ? $moodle_user['lastname'] : '';
        $fullname = trim($firstname . ' ' . $lastname);
        
        if (empty($fullname)) {
            $fullname = $username;
        }
        
        // Пропускаем пользователей без email
        if (empty($email)) {
            $skipped_count++;
            $errors[] = "Пользователь ID {$moodle_id} ({$username}) пропущен: нет email";
            continue;
        }
        
        // Проверяем, существует ли пользователь в WordPress
        $wp_user = get_user_by('email', $email);
        
        if ($wp_user) {
            // Пользователь уже существует - обновляем moodle_user_id
            update_user_meta($wp_user->ID, 'moodle_user_id', $moodle_id);
            $updated_count++;
            echo "<p>✓ Пользователь <strong>{$email}</strong> уже существует в WordPress (ID: {$wp_user->ID}). Обновлен moodle_user_id.</p>\n";
            continue;
        }
        
        // Проверяем, существует ли пользователь с таким логином
        $wp_user_by_login = get_user_by('login', $username);
        if ($wp_user_by_login) {
            $skipped_count++;
            $errors[] = "Пользователь с логином {$username} уже существует в WordPress";
            continue;
        }
        
        // Генерируем временный пароль
        $temp_password = generate_temp_password();
        
        // Создаем пользователя в WordPress
        $user_data = array(
            'user_login' => $username,
            'user_email' => $email,
            'user_pass' => $temp_password,
            'display_name' => $fullname,
            'first_name' => $firstname,
            'last_name' => $lastname,
            'role' => 'subscriber' // Можно изменить на нужную роль
        );
        
        $wp_user_id = wp_insert_user($user_data);
        
        if (is_wp_error($wp_user_id)) {
            $skipped_count++;
            $errors[] = "Ошибка создания пользователя {$email}: " . $wp_user_id->get_error_message();
            continue;
        }
        
        // Сохраняем moodle_user_id
        update_user_meta($wp_user_id, 'moodle_user_id', $moodle_id);
        
        // Помечаем, что пароль нужно синхронизировать обратно в Moodle при первом входе
        // Пользователь получит временный пароль, который нужно будет синхронизировать
        update_user_meta($wp_user_id, 'moodle_password_needs_sync', true);
        update_user_meta($wp_user_id, 'moodle_password_synced', false);
        
        // Обновляем пароль в Moodle на временный пароль
        // Это позволит пользователю войти в Moodle с тем же временным паролем
        try {
            $moodle_api = new Course_Moodle_API($moodle_url, $moodle_token);
            $update_result = $moodle_api->update_user($moodle_id, array(
                'password' => $temp_password
            ));
            
            if ($update_result !== false) {
                // Помечаем, что пароль синхронизирован
                update_user_meta($wp_user_id, 'moodle_password_synced', true);
                delete_user_meta($wp_user_id, 'moodle_password_needs_sync');
                echo "<p>✓ Пароль пользователя <strong>{$email}</strong> обновлен в Moodle</p>\n";
            } else {
                echo "<p>⚠ Не удалось обновить пароль в Moodle для пользователя <strong>{$email}</strong></p>\n";
            }
        } catch (Exception $e) {
            echo "<p>⚠ Ошибка при обновлении пароля в Moodle: " . $e->getMessage() . "</p>\n";
        }
        
        // Создаем пользователя в Laravel
        $laravel_result = create_user_in_laravel(array(
            'name' => $fullname,
            'email' => $email,
            'password' => $temp_password
        ), $moodle_id);
        
        if ($laravel_result['success']) {
            echo "<p>✓ Пользователь <strong>{$email}</strong> создан в WordPress (ID: {$wp_user_id}) и Laravel</p>\n";
        } else {
            echo "<p>⚠ Пользователь <strong>{$email}</strong> создан в WordPress (ID: {$wp_user_id}), но ошибка в Laravel: {$laravel_result['message']}</p>\n";
        }
        
        // Отправляем письмо пользователю
        send_sync_notification_email($email, $username, $temp_password, $moodle_url);
        
        $created_count++;
        
        // Небольшая задержка, чтобы не перегружать сервер
        usleep(100000); // 0.1 секунды
    }
    
    echo "<hr>\n";
    echo "<h2>Результаты синхронизации</h2>\n";
    echo "<p>Всего пользователей в Moodle: <strong>{$total_users}</strong></p>\n";
    echo "<p>Создано новых пользователей: <strong>{$created_count}</strong></p>\n";
    echo "<p>Обновлено существующих пользователей: <strong>{$updated_count}</strong></p>\n";
    echo "<p>Пропущено пользователей: <strong>{$skipped_count}</strong></p>\n";
    
    if (!empty($errors)) {
        echo "<h3>Ошибки и предупреждения:</h3>\n";
        echo "<ul>\n";
        foreach ($errors as $error) {
            echo "<li>{$error}</li>\n";
        }
        echo "</ul>\n";
    }
    
    echo "<hr>\n";
    echo "<p>Синхронизация завершена: " . date('Y-m-d H:i:s') . "</p>\n";
    echo "<p><strong style='color: red;'>ВАЖНО: Удалите этот файл с сервера после использования!</strong></p>\n";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>КРИТИЧЕСКАЯ ОШИБКА: " . $e->getMessage() . "</p>\n";
    echo "<pre>" . $e->getTraceAsString() . "</pre>\n";
}

