<?php
/**
 * Скрипт для тестирования синхронизации курсов с Laravel
 * 
 * Использование:
 * 1. Загрузите этот файл в корень WordPress
 * 2. Откройте в браузере: https://site.dekan.pro/test-course-sync.php
 * 3. Проверьте вывод
 * 
 * ВАЖНО: Удалите этот файл после использования!
 */

// Загружаем WordPress
require_once('wp-load.php');

// Проверяем права доступа (только для администраторов)
if (!current_user_can('manage_options')) {
    die('Доступ запрещен. Требуются права администратора.');
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Тест синхронизации курсов с Laravel</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .info { color: blue; }
        pre { background: #f5f5f5; padding: 10px; border: 1px solid #ddd; overflow-x: auto; }
        h2 { border-bottom: 2px solid #333; padding-bottom: 5px; }
    </style>
</head>
<body>
    <h1>Тест синхронизации курсов с Laravel</h1>
    
    <?php
    // Проверка 1: Настройки Laravel API
    echo '<h2>1. Проверка настроек Laravel API</h2>';
    $laravel_api_url = get_option('laravel_api_url', '');
    $laravel_api_token = get_option('laravel_api_token', '');
    
    if (empty($laravel_api_url)) {
        echo '<p class="error">❌ Laravel API URL не настроен!</p>';
    } else {
        echo '<p class="success">✅ Laravel API URL: ' . esc_html($laravel_api_url) . '</p>';
    }
    
    if (empty($laravel_api_token)) {
        echo '<p class="error">❌ Laravel API Token не настроен!</p>';
    } else {
        echo '<p class="success">✅ Laravel API Token установлен (длина: ' . strlen($laravel_api_token) . ' символов)</p>';
    }
    
    // Проверка 2: Наличие курсов в WordPress
    echo '<h2>2. Проверка курсов в WordPress</h2>';
    $courses = get_posts(array(
        'post_type' => 'course',
        'posts_per_page' => 10,
        'post_status' => 'any'
    ));
    
    echo '<p class="info">Найдено курсов: ' . count($courses) . '</p>';
    
    if (empty($courses)) {
        echo '<p class="warning">⚠️ Курсы не найдены в WordPress. Сначала выполните синхронизацию из Moodle.</p>';
    } else {
        echo '<ul>';
        foreach ($courses as $course) {
            $moodle_id = get_post_meta($course->ID, 'moodle_course_id', true);
            echo '<li>' . esc_html($course->post_title) . ' (WP ID: ' . $course->ID . ', Moodle ID: ' . ($moodle_id ?: 'нет') . ')</li>';
        }
        echo '</ul>';
    }
    
    // Проверка 3: Тестовый запрос к Laravel API
    if (!empty($laravel_api_url) && !empty($laravel_api_token) && !empty($courses)) {
        echo '<h2>3. Тестовый запрос к Laravel API</h2>';
        
        $test_course = $courses[0];
        $moodle_course_id = get_post_meta($test_course->ID, 'moodle_course_id', true);
        $moodle_category_id = get_post_meta($test_course->ID, 'moodle_category_id', true);
        $start_date = get_post_meta($test_course->ID, '_course_start_date', true);
        $end_date = get_post_meta($test_course->ID, '_course_end_date', true);
        $duration = get_post_meta($test_course->ID, '_course_duration', true);
        $price = get_post_meta($test_course->ID, '_course_price', true);
        $capacity = get_post_meta($test_course->ID, '_course_capacity', true);
        $enrolled = get_post_meta($test_course->ID, '_course_enrolled', true);
        
        $categories = wp_get_post_terms($test_course->ID, 'course_specialization', array('fields' => 'names'));
        $category_name = !empty($categories) ? $categories[0] : '';
        
        $test_data = array(
            'wordpress_course_id' => $test_course->ID,
            'moodle_course_id' => $moodle_course_id ?: null,
            'name' => $test_course->post_title,
            'description' => $test_course->post_content,
            'short_description' => $test_course->post_excerpt,
            'category_id' => $moodle_category_id ?: null,
            'category_name' => $category_name,
            'start_date' => $start_date ?: null,
            'end_date' => $end_date ?: null,
            'duration' => $duration ?: null,
            'price' => $price ?: null,
            'capacity' => $capacity ?: null,
            'enrolled' => $enrolled ?: 0,
            'status' => $test_course->post_status,
            'action' => 'created',
        );
        
        echo '<p class="info">Отправка тестового запроса для курса: ' . esc_html($test_course->post_title) . '</p>';
        echo '<pre>Данные запроса: ' . print_r($test_data, true) . '</pre>';
        
        $api_url = rtrim($laravel_api_url, '/') . '/api/courses/sync-from-wordpress';
        
        $response = wp_remote_post($api_url, array(
            'body' => json_encode($test_data),
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-API-Token' => $laravel_api_token,
            ),
            'timeout' => 30,
        ));
        
        if (is_wp_error($response)) {
            echo '<p class="error">❌ Ошибка запроса: ' . esc_html($response->get_error_message()) . '</p>';
            echo '<p class="error">Код ошибки: ' . esc_html($response->get_error_code()) . '</p>';
        } else {
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            $response_data = json_decode($response_body, true);
            
            echo '<p class="info">Код ответа: ' . $response_code . '</p>';
            echo '<pre>Ответ сервера: ' . esc_html($response_body) . '</pre>';
            
            if ($response_code === 201 || $response_code === 200) {
                if (isset($response_data['success']) && $response_data['success']) {
                    echo '<p class="success">✅ Тестовый запрос успешен! Курс должен быть создан/обновлен в Laravel.</p>';
                } else {
                    echo '<p class="error">❌ Запрос выполнен, но вернул ошибку.</p>';
                }
            } else {
                echo '<p class="error">❌ Ошибка HTTP: ' . $response_code . '</p>';
                
                if ($response_code === 401) {
                    echo '<p class="error">Проблема: Неверный токен API. Проверьте, что токены в WordPress и Laravel совпадают.</p>';
                } elseif ($response_code === 422) {
                    echo '<p class="error">Проблема: Ошибка валидации данных. Проверьте логи Laravel для деталей.</p>';
                } elseif ($response_code === 500) {
                    echo '<p class="error">Проблема: Внутренняя ошибка сервера. Проверьте логи Laravel.</p>';
                }
            }
        }
    }
    
    // Проверка 4: Логи WordPress
    echo '<h2>4. Последние записи в логах WordPress</h2>';
    $log_file = WP_CONTENT_DIR . '/debug.log';
    if (file_exists($log_file)) {
        $lines = file($log_file);
        $recent_lines = array_slice($lines, -20);
        echo '<pre>' . esc_html(implode('', $recent_lines)) . '</pre>';
    } else {
        echo '<p class="warning">⚠️ Файл debug.log не найден. Включите WP_DEBUG в wp-config.php для логирования.</p>';
    }
    
    // Проверка 5: Инструкции
    echo '<h2>5. Что делать дальше?</h2>';
    echo '<ol>';
    echo '<li>Если Laravel API не настроен - заполните настройки в WordPress админ-панели</li>';
    echo '<li>Если токены не совпадают - проверьте WORDPRESS_API_TOKEN в Laravel .env</li>';
    echo '<li>Если миграция не выполнена - выполните: <code>php artisan migrate</code> в Laravel</li>';
    echo '<li>Если есть ошибки - проверьте логи Laravel: <code>storage/logs/laravel.log</code></li>';
    echo '<li>После исправления проблем - выполните синхронизацию курсов из Moodle в WordPress</li>';
    echo '</ol>';
    ?>
    
    <p><strong>ВАЖНО:</strong> Удалите этот файл после использования!</p>
</body>
</html>

