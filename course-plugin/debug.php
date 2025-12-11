<?php
/**
 * Файл отладки плагина "Курсы Про"
 * Добавьте этот код в functions.php вашей темы для диагностики
 */

add_action('admin_notices', function() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    echo '<div class="notice notice-info"><h3>Отладка плагина "Курсы Про"</h3>';
    
    // Проверка активации плагина
    $plugin_file = 'course-plugin/course-plugin.php';
    if (is_plugin_active($plugin_file)) {
        echo '<p>✅ Плагин активирован</p>';
    } else {
        echo '<p>❌ Плагин НЕ активирован</p>';
        echo '<p>Путь: ' . $plugin_file . '</p>';
    }
    
    // Проверка существования классов
    echo '<h4>Проверка классов:</h4>';
    $classes = array('Course_Plugin', 'Course_Post_Type', 'Course_Taxonomies', 'Course_Admin', 'Course_Meta_Boxes');
    foreach ($classes as $class) {
        if (class_exists($class)) {
            echo '<p>✅ Класс ' . $class . ' существует</p>';
        } else {
            echo '<p>❌ Класс ' . $class . ' НЕ существует</p>';
        }
    }
    
    // Проверка типа поста
    echo '<h4>Проверка типа поста:</h4>';
    if (post_type_exists('course')) {
        echo '<p>✅ Тип поста "course" зарегистрирован</p>';
        $post_type_obj = get_post_type_object('course');
        if ($post_type_obj) {
            echo '<p>Название меню: ' . $post_type_obj->labels->menu_name . '</p>';
            echo '<p>show_in_menu: ' . ($post_type_obj->show_in_menu ? 'true' : 'false') . '</p>';
        }
    } else {
        echo '<p>❌ Тип поста "course" НЕ зарегистрирован</p>';
    }
    
    // Проверка таксономий
    echo '<h4>Проверка таксономий:</h4>';
    $taxonomies = array('course_specialization', 'course_level', 'course_topic', 'course_teacher');
    foreach ($taxonomies as $taxonomy) {
        if (taxonomy_exists($taxonomy)) {
            echo '<p>✅ Таксономия "' . $taxonomy . '" зарегистрирована</p>';
        } else {
            echo '<p>❌ Таксономия "' . $taxonomy . '" НЕ зарегистрирована</p>';
        }
    }
    
    // Проверка файлов
    echo '<h4>Проверка файлов плагина:</h4>';
    $plugin_dir = WP_PLUGIN_DIR . '/course-plugin/';
    $files = array(
        'course-plugin.php',
        'includes/class-course-post-type.php',
        'includes/class-course-taxonomies.php',
        'includes/class-course-admin.php',
        'includes/class-course-meta-boxes.php',
    );
    
    foreach ($files as $file) {
        $file_path = $plugin_dir . $file;
        if (file_exists($file_path)) {
            echo '<p>✅ ' . $file . '</p>';
        } else {
            echo '<p>❌ ' . $file . ' (не найден по пути: ' . $file_path . ')</p>';
        }
    }
    
    // Проверка хуков
    echo '<h4>Проверка хуков:</h4>';
    global $wp_filter;
    if (isset($wp_filter['init'])) {
        $init_hooks = $wp_filter['init'];
        $found = false;
        foreach ($init_hooks->callbacks as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                if (is_array($callback['function']) && 
                    is_object($callback['function'][0]) && 
                    get_class($callback['function'][0]) === 'Course_Post_Type') {
                    $found = true;
                    echo '<p>✅ Хук init найден для Course_Post_Type (приоритет: ' . $priority . ')</p>';
                    break 2;
                }
            }
        }
        if (!$found) {
            echo '<p>❌ Хук init для Course_Post_Type не найден</p>';
        }
    }
    
    echo '</div>';
});


