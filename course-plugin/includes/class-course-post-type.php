<?php
/**
 * Класс для регистрации Custom Post Type "Курсы"
 */

if (!defined('ABSPATH')) {
    exit;
}

class Course_Post_Type {
    
    /**
     * Единственный экземпляр класса
     */
    private static $instance = null;
    
    /**
     * Получить экземпляр класса
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Конструктор
     */
    private function __construct() {
        add_action('init', array($this, 'register_post_type'));
    }
    
    /**
     * Регистрация Custom Post Type "Курсы"
     */
    public function register_post_type() {
        $labels = array(
            'name'                  => _x('Курсы', 'Post Type General Name', 'course-plugin'),
            'singular_name'         => _x('Курс', 'Post Type Singular Name', 'course-plugin'),
            'menu_name'             => __('Курсы', 'course-plugin'),
            'name_admin_bar'        => __('Курс', 'course-plugin'),
            'archives'              => __('Архив курсов', 'course-plugin'),
            'attributes'            => __('Атрибуты курса', 'course-plugin'),
            'parent_item_colon'     => __('Родительский курс:', 'course-plugin'),
            'all_items'             => __('Все курсы', 'course-plugin'),
            'add_new_item'          => __('Добавить новый курс', 'course-plugin'),
            'add_new'               => __('Добавить новый', 'course-plugin'),
            'new_item'              => __('Новый курс', 'course-plugin'),
            'edit_item'             => __('Редактировать курс', 'course-plugin'),
            'update_item'           => __('Обновить курс', 'course-plugin'),
            'view_item'             => __('Просмотреть курс', 'course-plugin'),
            'view_items'            => __('Просмотреть курсы', 'course-plugin'),
            'search_items'          => __('Поиск курсов', 'course-plugin'),
            'not_found'             => __('Не найдено', 'course-plugin'),
            'not_found_in_trash'    => __('Не найдено в корзине', 'course-plugin'),
            'featured_image'        => __('Изображение курса', 'course-plugin'),
            'set_featured_image'    => __('Установить изображение курса', 'course-plugin'),
            'remove_featured_image' => __('Удалить изображение курса', 'course-plugin'),
            'use_featured_image'    => __('Использовать как изображение курса', 'course-plugin'),
            'insert_into_item'      => __('Вставить в курс', 'course-plugin'),
            'uploaded_to_this_item' => __('Загружено для этого курса', 'course-plugin'),
            'items_list'            => __('Список курсов', 'course-plugin'),
            'items_list_navigation' => __('Навигация по списку курсов', 'course-plugin'),
            'filter_items_list'     => __('Фильтровать список курсов', 'course-plugin'),
        );
        
        $args = array(
            'label'                 => __('Курс', 'course-plugin'),
            'description'           => __('Управление курсами', 'course-plugin'),
            'labels'                => $labels,
            'supports'              => array('title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'),
            'taxonomies'            => array('course_specialization', 'course_level', 'course_topic', 'course_teacher'),
            'hierarchical'          => false,
            'public'                => true,
            'show_ui'               => true,
            'show_in_menu'          => true,
            'menu_position'         => 5,
            'menu_icon'             => 'dashicons-book-alt',
            'show_in_admin_bar'     => true,
            'show_in_nav_menus'     => true,
            'can_export'            => true,
            'has_archive'           => true,
            'exclude_from_search'   => false,
            'publicly_queryable'    => true,
            'capability_type'       => 'post',
            'show_in_rest'          => true,
            'rest_base'             => 'courses',
            'rewrite'               => array('slug' => 'courses'),
        );
        
        register_post_type('course', $args);
    }
}

