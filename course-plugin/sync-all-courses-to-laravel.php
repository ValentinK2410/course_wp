<?php
/**
 * Скрипт для синхронизации всех существующих курсов из WordPress в Laravel
 * 
 * ВАЖНО: Этот файл должен находиться в КОРНЕ WordPress (там же, где wp-config.php)
 * 
 * Использование:
 * 1. Скопируйте этот файл в корень WordPress
 * 2. Откройте в браузере: https://site.dekan.pro/sync-all-courses-to-laravel.php
 * 3. Дождитесь завершения синхронизации
 * 4. УДАЛИТЕ файл после использования!
 */

// Загружаем WordPress
if (file_exists('wp-load.php')) {
    require_once('wp-load.php');
} elseif (file_exists('../wp-load.php')) {
    require_once('../wp-load.php');
} elseif (file_exists('../../wp-load.php')) {
    require_once('../../wp-load.php');
} else {
    die('Ошибка: файл wp-load.php не найден. Убедитесь, что файл находится в корне WordPress.');
}

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
    <title>Синхронизация всех курсов с Laravel</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .info { color: blue; }
        pre { background: #f5f5f5; padding: 10px; border: 1px solid #ddd; overflow-x: auto; }
        h2 { border-bottom: 2px solid #333; padding-bottom: 5px; }
        .progress { margin: 10px 0; }
    </style>
</head>
<body>
    <h1>Синхронизация всех курсов с Laravel</h1>
    
    <?php
    // Проверка настроек Laravel API
    $laravel_api_url = get_option('laravel_api_url', '');
    $laravel_api_token = get_option('laravel_api_token', '');
    
    if (empty($laravel_api_url) || empty($laravel_api_token)) {
        echo '<p class="error">❌ Laravel API не настроен! Заполните настройки в WordPress админ-панели.</p>';
        echo '<p><a href="' . admin_url('options-general.php?page=moodle-sync-settings') . '">Перейти к настройкам</a></p>';
        exit;
    }
    
    // Получаем все курсы из WordPress
    $courses = get_posts(array(
        'post_type' => 'course',
        'posts_per_page' => -1,
        'post_status' => 'any'
    ));
    
    echo '<p class="info">Найдено курсов для синхронизации: ' . count($courses) . '</p>';
    
    if (empty($courses)) {
        echo '<p class="warning">⚠️ Курсы не найдены в WordPress.</p>';
        exit;
    }
    
    $synced = 0;
    $errors = 0;
    $skipped = 0;
    
    echo '<h2>Процесс синхронизации:</h2>';
    echo '<div class="progress">';
    
    foreach ($courses as $course) {
        $moodle_course_id = get_post_meta($course->ID, 'moodle_course_id', true);
        $moodle_category_id = get_post_meta($course->ID, 'moodle_category_id', true);
        $start_date = get_post_meta($course->ID, '_course_start_date', true);
        $end_date = get_post_meta($course->ID, '_course_end_date', true);
        $duration = get_post_meta($course->ID, '_course_duration', true);
        $price = get_post_meta($course->ID, '_course_price', true);
        $capacity = get_post_meta($course->ID, '_course_capacity', true);
        $enrolled = get_post_meta($course->ID, '_course_enrolled', true);
        
        $categories = wp_get_post_terms($course->ID, 'course_specialization', array('fields' => 'names'));
        $category_name = !empty($categories) ? $categories[0] : '';
        
        // Подготавливаем данные
        $data = array(
            'wordpress_course_id' => $course->ID,
            'moodle_course_id' => $moodle_course_id ?: null,
            'name' => $course->post_title,
            'description' => $course->post_content,
            'short_description' => $course->post_excerpt,
            'category_id' => $moodle_category_id ?: null,
            'category_name' => $category_name,
            'start_date' => $start_date ?: null,
            'end_date' => $end_date ?: null,
            'duration' => $duration ?: null,
            'price' => $price ?: null,
            'capacity' => $capacity ?: null,
            'enrolled' => $enrolled ?: 0,
            'status' => $course->post_status,
            'action' => 'created',
        );
        
        // Отправляем запрос
        $api_url = rtrim($laravel_api_url, '/') . '/api/courses/sync-from-wordpress';
        
        $response = wp_remote_post($api_url, array(
            'body' => json_encode($data),
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-API-Token' => $laravel_api_token,
            ),
            'timeout' => 30,
        ));
        
        if (is_wp_error($response)) {
            echo '<p class="error">❌ ' . esc_html($course->post_title) . ' - Ошибка: ' . esc_html($response->get_error_message()) . '</p>';
            $errors++;
        } else {
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            $response_data = json_decode($response_body, true);
            
            if (($response_code === 201 || $response_code === 200) && isset($response_data['success']) && $response_data['success']) {
                echo '<p class="success">✅ ' . esc_html($course->post_title) . ' - Синхронизирован (Laravel ID: ' . (isset($response_data['course']['id']) ? $response_data['course']['id'] : 'N/A') . ')</p>';
                $synced++;
            } else {
                echo '<p class="error">❌ ' . esc_html($course->post_title) . ' - Ошибка (код: ' . $response_code . '): ' . esc_html(substr($response_body, 0, 200)) . '</p>';
                $errors++;
            }
        }
        
        // Небольшая задержка, чтобы не перегружать сервер
        usleep(100000); // 0.1 секунды
    }
    
    echo '</div>';
    
    echo '<h2>Результаты синхронизации:</h2>';
    echo '<ul>';
    echo '<li class="success">Успешно синхронизировано: ' . $synced . '</li>';
    echo '<li class="error">Ошибок: ' . $errors . '</li>';
    echo '<li class="info">Пропущено: ' . $skipped . '</li>';
    echo '</ul>';
    
    if ($synced > 0) {
        echo '<p class="success"><strong>✅ Синхронизация завершена! Проверьте курсы в Laravel приложении.</strong></p>';
    }
    ?>
    
    <p><strong>ВАЖНО:</strong> Удалите этот файл после использования!</p>
</body>
</html>

