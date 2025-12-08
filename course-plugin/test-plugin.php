<?php
/**
 * Тестовый файл для проверки работы плагина
 * Добавьте этот код в functions.php вашей темы временно для отладки
 */

// Проверка регистрации типа поста
add_action('admin_notices', function() {
    if (current_user_can('manage_options')) {
        if (post_type_exists('course')) {
            echo '<div class="notice notice-success"><p>✅ Тип поста "course" зарегистрирован!</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>❌ Тип поста "course" НЕ зарегистрирован!</p></div>';
        }
        
        // Проверка таксономий
        $taxonomies = array('course_specialization', 'course_level', 'course_topic', 'course_teacher');
        foreach ($taxonomies as $taxonomy) {
            if (taxonomy_exists($taxonomy)) {
                echo '<div class="notice notice-success"><p>✅ Таксономия "' . $taxonomy . '" зарегистрирована!</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>❌ Таксономия "' . $taxonomy . '" НЕ зарегистрирована!</p></div>';
            }
        }
        
        // Проверка активных плагинов
        if (is_plugin_active('course-plugin/course-plugin.php')) {
            echo '<div class="notice notice-success"><p>✅ Плагин "Курсы Про" активирован!</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>❌ Плагин "Курсы Про" НЕ активирован!</p></div>';
        }
    }
});

