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
        // Регистрируем тип поста с приоритетом 10 (до таксономий)
        add_action('init', array($this, 'register_post_type'), 10);
    }
    
    /**
     * Регистрация Custom Post Type "Курсы"
     */
    public function register_post_type() {
        // Проверяем, что функция register_post_type доступна
        if (!function_exists('register_post_type')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Course Plugin: функция register_post_type не доступна');
            }
            return;
        }
        
        $labels = array(
            'name'                  => _x('Курсы', 'Post Type General Name', 'course-plugin'),
            'singular_name'         => _x('Курс', 'Post Type Singular Name', 'course-plugin'),
            'menu_name'             => __('Курсы Про', 'course-plugin'),
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
            'map_meta_cap'          => true,
            'show_in_rest'          => true,
            'rest_base'             => 'courses',
            'rewrite'               => array('slug' => 'courses'),
        );
        
        // Проверяем, не зарегистрирован ли уже тип поста
        if (post_type_exists('course')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Course Plugin: Тип поста "course" уже зарегистрирован');
            }
            return;
        }
        
        // Регистрируем тип поста
        $result = register_post_type('course', $args);
        
        // Логируем результат регистрации
        if (defined('WP_DEBUG') && WP_DEBUG) {
            if (is_wp_error($result)) {
                error_log('Course Plugin: Ошибка регистрации типа поста - ' . $result->get_error_message());
            } elseif ($result === null || $result === false) {
                error_log('Course Plugin: Регистрация типа поста вернула null или false');
            } else {
                error_log('Course Plugin: Тип поста "course" успешно зарегистрирован');
            }
        }
        
        // Дополнительная проверка после регистрации
        if (!post_type_exists('course')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Course Plugin: КРИТИЧЕСКАЯ ОШИБКА - тип поста не зарегистрирован после вызова register_post_type()');
            }
        }
    }
}

