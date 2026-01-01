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

// Проверка настроек email перед началом синхронизации
$admin_email = get_option('admin_email');
if (empty($admin_email)) {
    die('ОШИБКА: Не настроен email администратора. Перейдите в Настройки → Общие и укажите email администратора.');
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

/**
 * Функция для отправки письма пользователю с проверкой успешности
 * 
 * ВАЖНО для доставляемости в Gmail:
 * 1. Убедитесь, что настроены SPF записи в DNS вашего домена
 * 2. Настройте DKIM подпись для вашего домена
 * 3. Настройте DMARC политику
 * 4. Используйте SMTP плагин (например, WP Mail SMTP) вместо стандартной функции mail()
 * 5. Проверьте, что email администратора WordPress совпадает с доменом сайта
 * 
 * Для диагностики проверьте логи WordPress (wp-content/debug.log)
 */
function send_sync_notification_email($user_email, $user_login, $temp_password, $moodle_url, $moodle_password_changed = false) {
    // Проверяем настройки email перед отправкой
    if (!function_exists('wp_mail')) {
        return array('success' => false, 'message' => 'Функция wp_mail недоступна');
    }
    
    $subject = 'Ваш аккаунт синхронизирован';
    
    $message = "Здравствуйте!\n\n";
    $message .= "Ваш аккаунт был синхронизирован между Moodle, WordPress и системой управления обучением.\n\n";
    
    if ($moodle_password_changed) {
        // Если пароль Moodle был изменен (старая логика - не используется сейчас)
        $message .= "Данные для входа:\n";
        $message .= "Логин: " . $user_login . "\n";
        $message .= "Временный пароль: " . $temp_password . "\n\n";
        $message .= "ВАЖНО: После первого входа рекомендуется сменить пароль.\n\n";
    } else {
        // Пароль Moodle НЕ изменен - пользователь использует существующий пароль
        $message .= "ВАЖНО: Ваш пароль в Moodle НЕ был изменен. Вы можете продолжать использовать свой существующий пароль Moodle для входа.\n\n";
        
        $message .= "═══════════════════════════════════════════════════════════\n";
        $message .= "КАК ВОЙТИ В WORDPRESS\n";
        $message .= "═══════════════════════════════════════════════════════════\n\n";
        
        $message .= "СПОСОБ 1: Вход через SSO из Moodle (РЕКОМЕНДУЕТСЯ)\n";
        $message .= "───────────────────────────────────────────────────────────\n";
        $message .= "Это самый простой способ! Используйте свой существующий пароль Moodle.\n\n";
        $message .= "Пошаговая инструкция:\n";
        $message .= "1. Войдите в Moodle используя свой обычный логин и пароль:\n";
        $message .= "   " . rtrim($moodle_url, '/') . "/login/index.php\n\n";
        $message .= "2. После входа в Moodle перейдите по этой ссылке для автоматического входа в WordPress:\n";
        $sso_url = rtrim($moodle_url, '/') . '/moodle-sso-to-wordpress.php';
        $message .= "   " . $sso_url . "\n\n";
        $message .= "3. Вы автоматически войдете в WordPress без необходимости вводить пароль!\n\n";
        
        $message .= "СПОСОБ 2: Прямой вход в WordPress\n";
        $message .= "───────────────────────────────────────────────────────────\n";
        $message .= "Если вы хотите войти напрямую в WordPress без Moodle:\n\n";
        $message .= "Ссылка для входа: " . home_url('/wp-login.php') . "\n";
        $message .= "Логин: " . $user_login . "\n";
        $message .= "Пароль WordPress: " . $temp_password . "\n\n";
        $message .= "Примечание: После первого входа через SSO вы получите дополнительное письмо с инструкциями по настройке пароля WordPress.\n\n";
        
        $message .= "═══════════════════════════════════════════════════════════\n";
        $message .= "ПОЛЕЗНЫЕ ССЫЛКИ\n";
        $message .= "═══════════════════════════════════════════════════════════\n\n";
        $message .= "• WordPress: " . home_url('/wp-login.php') . "\n";
        $message .= "• Moodle: " . rtrim($moodle_url, '/') . "/login/index.php\n";
        $message .= "• SSO из Moodle в WordPress: " . $sso_url . "\n";
        
        $laravel_url = get_option('laravel_api_url', '');
        if ($laravel_url) {
            $message .= "• Система управления: " . rtrim($laravel_url, '/') . "\n";
        }
        
        $message .= "\n";
        $message .= "═══════════════════════════════════════════════════════════\n";
        $message .= "ВАЖНАЯ ИНФОРМАЦИЯ\n";
        $message .= "═══════════════════════════════════════════════════════════\n\n";
        $message .= "• Ваш пароль в Moodle остался прежним - используйте его для входа в Moodle\n";
        $message .= "• Для входа в WordPress рекомендуется использовать SSO (способ 1)\n";
        $message .= "• SSO позволяет использовать один пароль (Moodle) для доступа ко всем системам\n";
        $message .= "• Если возникнут проблемы со входом, обратитесь к администратору\n\n";
    }
    
    $message .= "\nС уважением,\nАдминистрация";
    
    // Получаем настройки для отправки
    $admin_email = get_option('admin_email');
    $site_name = get_bloginfo('name');
    $site_url = home_url();
    
    // Извлекаем домен из email для диагностики
    $email_domain = substr(strrchr($user_email, "@"), 1);
    $is_gmail = (strpos(strtolower($email_domain), 'gmail.com') !== false);
    
    // Логируем попытку отправки для диагностики
    error_log("Course Sync Email: Попытка отправки письма на {$user_email} (домен: {$email_domain}, Gmail: " . ($is_gmail ? 'да' : 'нет') . ")");
    
    // Отправляем письмо с улучшенными заголовками для лучшей доставляемости (особенно для Gmail)
    $headers = array();
    
    // Content-Type с правильной кодировкой
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    
    // From заголовок - важно для Gmail
    $from_name = !empty($site_name) ? $site_name : 'WordPress';
    $from_email = !empty($admin_email) ? $admin_email : 'noreply@' . parse_url($site_url, PHP_URL_HOST);
    $headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
    
    // Reply-To заголовок - важно для Gmail
    $headers[] = 'Reply-To: ' . $from_name . ' <' . $from_email . '>';
    
    // X-Mailer заголовок для идентификации
    $headers[] = 'X-Mailer: WordPress/' . get_bloginfo('version');
    
    // Дополнительные заголовки для улучшения доставляемости
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'X-Priority: 3'; // Нормальный приоритет
    
    // Для Gmail добавляем дополнительные заголовки
    if ($is_gmail) {
        // List-Unsubscribe заголовок (Gmail это ценит)
        $headers[] = 'List-Unsubscribe: <' . $site_url . '>, <mailto:' . $from_email . '?subject=unsubscribe>';
        error_log("Course Sync Email: Добавлены специальные заголовки для Gmail");
    }
    
    // Логируем заголовки для диагностики
    error_log("Course Sync Email: Заголовки: " . print_r($headers, true));
    
    // Отправляем письмо
    $mail_result = wp_mail($user_email, $subject, $message, $headers);
    
    if ($mail_result) {
        error_log("Course Sync Email: Письмо успешно отправлено на {$user_email}");
        return array('success' => true, 'message' => 'Письмо успешно отправлено');
    } else {
        // Проверяем последнюю ошибку
        global $phpmailer;
        $error_message = 'Неизвестная ошибка отправки email';
        $error_details = '';
        
        if (isset($phpmailer) && is_object($phpmailer)) {
            if (isset($phpmailer->ErrorInfo)) {
                $error_message = $phpmailer->ErrorInfo;
            }
            if (method_exists($phpmailer, 'getSMTPInstance')) {
                $smtp = $phpmailer->getSMTPInstance();
                if ($smtp && method_exists($smtp, 'getError')) {
                    $smtp_error = $smtp->getError();
                    if ($smtp_error) {
                        $error_details = ' SMTP ошибка: ' . $smtp_error['error'];
                    }
                }
            }
        }
        
        $full_error = 'Ошибка отправки email: ' . $error_message . $error_details;
        error_log("Course Sync Email: ОШИБКА отправки на {$user_email}: {$full_error}");
        
        // Для Gmail добавляем дополнительную информацию
        if ($is_gmail) {
            $full_error .= ' (Gmail может блокировать письма без правильных SPF/DKIM записей. Проверьте настройки DNS вашего домена.)';
        }
        
        return array('success' => false, 'message' => $full_error);
    }
}

// Получаем всех пользователей из Moodle
echo "<h1>Синхронизация пользователей из Moodle</h1>\n";
echo "<p>Начало синхронизации: " . date('Y-m-d H:i:s') . "</p>\n";

// Предупреждение о безопасности
echo "<div style='background: #d1ecf1; border: 1px solid #0c5460; padding: 15px; margin: 20px 0; border-radius: 5px;'>\n";
echo "<h3 style='margin-top: 0; color: #0c5460;'>🔒 ГЛАВНОЕ ПРАВИЛО БЕЗОПАСНОСТИ:</h3>\n";
echo "<ul style='color: #0c5460;'>\n";
echo "<li><strong>ПАРОЛИ В MOODLE НЕ БУДУТ ИЗМЕНЕНЫ!</strong></li>\n";
echo "<li>Все пользователи уже существуют в Moodle и имеют свои рабочие пароли</li>\n";
echo "<li>Мы НЕ меняем пароли в Moodle, чтобы пользователи не потеряли доступ</li>\n";
echo "<li>Для существующих пользователей в WordPress - только обновляется связь с Moodle</li>\n";
echo "<li>Для новых пользователей - создается аккаунт в WordPress, пароль Moodle остается прежним</li>\n";
echo "<li>Пользователи могут войти в WordPress через SSO используя свой существующий пароль Moodle</li>\n";
echo "</ul>\n";
echo "</div>\n";

echo "<hr>\n";

try {
    // Получаем пользователей из Moodle
    // Используем публичный метод get_users() для получения всех пользователей
    // Пробуем разные варианты критериев, так как Moodle API может требовать разные форматы
    $moodle_users = $moodle_api->get_users(array(
        array(
            'key' => 'deleted',
            'value' => '0' // Только неудаленные пользователи
        )
    ));
    
    // Если не получилось, пробуем без критериев
    if (isset($moodle_users['exception']) || !isset($moodle_users['users'])) {
        $moodle_users = $moodle_api->get_users(array());
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
    $email_sent_count = 0;
    $email_failed_count = 0;
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
            // Пользователь уже существует - обновляем только moodle_user_id
            // ВАЖНО: НЕ меняем пароль существующего пользователя, чтобы не потерять доступ!
            update_user_meta($wp_user->ID, 'moodle_user_id', $moodle_id);
            $updated_count++;
            echo "<p>✓ Пользователь <strong>{$email}</strong> уже существует в WordPress (ID: {$wp_user->ID}). Обновлен moodle_user_id. Пароль в Moodle НЕ изменен.</p>\n";
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
        
        // ГЛАВНОЕ ПРАВИЛО: НЕ МЕНЯТЬ ПАРОЛИ В MOODLE ДЛЯ СУЩЕСТВУЮЩИХ ПОЛЬЗОВАТЕЛЕЙ!
        // Пользователь уже существует в Moodle и имеет свой рабочий пароль
        // Мы НЕ должны его менять, чтобы пользователь не потерял доступ!
        // Пользователь может войти в WordPress через SSO используя свой существующий пароль Moodle
        
        // Помечаем, что пароль в Moodle НЕ был изменен (это правильно!)
        update_user_meta($wp_user_id, 'moodle_password_synced', false);
        update_user_meta($wp_user_id, 'moodle_password_not_changed', true);
        update_user_meta($wp_user_id, 'wp_password', $temp_password); // Сохраняем пароль WP для справки
        
        echo "<p>✓ Пользователь <strong>{$email}</strong> создан в WordPress (ID: {$wp_user_id})</p>\n";
        echo "<p>ℹ Пароль в Moodle НЕ изменен - пользователь сохраняет свой существующий доступ в Moodle</p>\n";
        
        // Отправляем уведомление пользователю (без пароля Moodle, т.к. он не менялся)
        $email_result = send_sync_notification_email($email, $username, $temp_password, $moodle_url, false);
        
        if ($email_result['success']) {
            $email_sent_count++;
            echo "<p>✓ Email уведомление отправлено пользователю <strong>{$email}</strong></p>\n";
        } else {
            $email_failed_count++;
            $errors[] = "Email не отправлен пользователю {$email}: {$email_result['message']}";
            echo "<p style='color: orange;'>⚠ Email не отправлен пользователю <strong>{$email}</strong>: {$email_result['message']}</p>\n";
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
        
        $created_count++;
        
        // Небольшая задержка, чтобы не перегружать сервер
        usleep(100000); // 0.1 секунды
    }
    
    echo "<hr>\n";
    echo "<h2>Результаты синхронизации</h2>\n";
    echo "<p>Всего пользователей в Moodle: <strong>{$total_users}</strong></p>\n";
    echo "<p>Создано новых пользователей в WordPress: <strong>{$created_count}</strong></p>\n";
    echo "<p>Обновлено существующих пользователей: <strong>{$updated_count}</strong></p>\n";
    echo "<p>Пропущено пользователей: <strong>{$skipped_count}</strong></p>\n";
    echo "<hr>\n";
    echo "<div style='background: #d4edda; border: 1px solid #155724; padding: 10px; margin: 10px 0; border-radius: 5px;'>\n";
    echo "<p style='color: #155724; margin: 0;'><strong>✓ Безопасность:</strong> Пароли в Moodle НЕ были изменены. Все пользователи сохраняют свой существующий доступ в Moodle.</p>\n";
    echo "</div>\n";
    echo "<hr>\n";
    echo "<h3>Статистика отправки email:</h3>\n";
    echo "<p>Email успешно отправлено: <strong style='color: green;'>{$email_sent_count}</strong></p>\n";
    if ($email_failed_count > 0) {
        echo "<p>Email НЕ отправлено: <strong style='color: orange;'>{$email_failed_count}</strong></p>\n";
        echo "<p style='color: orange;'>Пользователи, которым не отправился email, все равно могут использовать свой существующий пароль Moodle для входа через SSO.</p>\n";
    }
    
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

