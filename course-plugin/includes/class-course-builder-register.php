<?php
/**
 * Регистрация виджетов Course Builder
 */

if (!defined('ABSPATH')) {
    exit;
}

class Course_Builder_Register {
    
    /**
     * Инициализация регистрации виджетов
     */
    public static function init() {
        add_action('course_builder_register_widgets', array(__CLASS__, 'register_widgets'));
    }
    
    /**
     * Регистрация всех виджетов
     */
    public static function register_widgets() {
        // Подключаем файлы виджетов
        $widget_files = array(
            'widgets/class-widget-text.php',
            'widgets/class-widget-heading.php',
            'widgets/class-widget-image.php',
            'widgets/class-widget-button.php',
            'widgets/class-widget-columns.php',
            'widgets/class-widget-video.php',
            'widgets/class-widget-course-card.php',
            'widgets/class-widget-course-filter.php',
            'widgets/class-widget-course-register.php',
            'widgets/class-widget-teacher-info.php',
        );
        
        foreach ($widget_files as $file) {
            $file_path = COURSE_PLUGIN_DIR . 'includes/' . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
        
        // Регистрируем базовые виджеты
        Course_Builder::register_widget('text', 'Course_Builder_Widget_Text');
        Course_Builder::register_widget('heading', 'Course_Builder_Widget_Heading');
        Course_Builder::register_widget('image', 'Course_Builder_Widget_Image');
        Course_Builder::register_widget('button', 'Course_Builder_Widget_Button');
        Course_Builder::register_widget('columns', 'Course_Builder_Widget_Columns');
        Course_Builder::register_widget('video', 'Course_Builder_Widget_Video');
        
        // Регистрируем специализированные виджеты для курсов
        Course_Builder::register_widget('course_card', 'Course_Builder_Widget_Course_Card');
        Course_Builder::register_widget('course_filter', 'Course_Builder_Widget_Course_Filter');
        Course_Builder::register_widget('course_register', 'Course_Builder_Widget_Course_Register');
        Course_Builder::register_widget('teacher_info', 'Course_Builder_Widget_Teacher_Info');
    }
}

// Инициализируем регистрацию виджетов
Course_Builder_Register::init();
