<?php
/**
 * Класс для регистрации Custom Post Type "Курсы"
 * 
 * Этот класс отвечает за создание нового типа записей WordPress "course" (Курсы)
 * Custom Post Type позволяет создавать специальные типы контента с собственными настройками
 * 
 * @copyright Copyright (c) 2024 Кузьменко Валентин (Valentink2410)
 * @author Кузьменко Валентин (Valentink2410)
 */

// Проверка безопасности: если файл вызывается напрямую, прекращаем выполнение
if (!defined('ABSPATH')) {
    exit;
}

class Course_Post_Type {
    
    /**
     * Единственный экземпляр класса (Singleton)
     * Хранит объект класса, если он уже был создан
     */
    private static $instance = null;
    
    /**
     * Получить экземпляр класса
     * Паттерн Singleton: гарантирует создание только одного экземпляра класса
     * 
     * @return Course_Post_Type Экземпляр класса
     */
    public static function get_instance() {
        // Если экземпляр еще не создан, создаем его
        if (null === self::$instance) {
            self::$instance = new self();
        }
        // Возвращаем существующий или только что созданный экземпляр
        return self::$instance;
    }
    
    /**
     * Конструктор класса
     * Приватный, чтобы предотвратить создание экземпляра напрямую
     * Регистрирует хук для регистрации типа поста
     */
    private function __construct() {
        // Регистрируем метод register_post_type() на хук 'init' с приоритетом 20
        // Приоритет 20 означает, что тип поста будет зарегистрирован после стандартных типов WordPress
        // Это важно для правильной работы плагина
        add_action('init', array($this, 'register_post_type'), 20);
    }
    
    /**
     * Регистрация Custom Post Type "Курсы"
     * Создает новый тип записей WordPress с названием "course"
     * Вызывается на хуке 'init'
     */
    public function register_post_type() {
        // Проверяем, не зарегистрирован ли уже тип поста "course"
        // Это предотвращает повторную регистрацию и возможные конфликты
        if (post_type_exists('course')) {
            return; // Если уже зарегистрирован, выходим из функции
        }
        
        // Проверяем, что функция register_post_type() доступна
        // Это стандартная функция WordPress для регистрации типов постов
        if (!function_exists('register_post_type')) {
            return; // Если функция недоступна, выходим из функции
        }
        
        // Массив с переводами названий для типа поста
        // Используется для локализации интерфейса WordPress
        $labels = array(
            'name'                  => _x('Курсы', 'Post Type General Name', 'course-plugin'),              // Множественное название (используется в меню)
            'singular_name'         => _x('Курс', 'Post Type Singular Name', 'course-plugin'),              // Единственное название
            'menu_name'             => __('Курсы Про', 'course-plugin'),                                    // Название в меню админ-панели
            'name_admin_bar'        => __('Курс', 'course-plugin'),                                          // Название в админ-баре (верхняя панель)
            'archives'              => __('Архив курсов', 'course-plugin'),                                 // Название архива
            'attributes'            => __('Атрибуты курса', 'course-plugin'),                                // Атрибуты курса
            'parent_item_colon'     => __('Родительский курс:', 'course-plugin'),                           // Родительский элемент (для иерархических типов)
            'all_items'             => __('Все курсы', 'course-plugin'),                                     // Все элементы (в меню)
            'add_new_item'          => __('Добавить новый курс', 'course-plugin'),                          // Добавить новый элемент
            'add_new'               => __('Добавить новый', 'course-plugin'),                               // Добавить новый (кнопка)
            'new_item'              => __('Новый курс', 'course-plugin'),                                   // Новый элемент
            'edit_item'             => __('Редактировать курс', 'course-plugin'),                           // Редактировать элемент
            'update_item'           => __('Обновить курс', 'course-plugin'),                                 // Обновить элемент
            'view_item'             => __('Просмотреть курс', 'course-plugin'),                             // Просмотреть элемент
            'view_items'            => __('Просмотреть курсы', 'course-plugin'),                            // Просмотреть элементы
            'search_items'          => __('Поиск курсов', 'course-plugin'),                                 // Поиск элементов
            'not_found'             => __('Не найдено', 'course-plugin'),                                    // Не найдено
            'not_found_in_trash'    => __('Не найдено в корзине', 'course-plugin'),                         // Не найдено в корзине
            'featured_image'        => __('Изображение курса', 'course-plugin'),                            // Изображение записи
            'set_featured_image'    => __('Установить изображение курса', 'course-plugin'),                 // Установить изображение
            'remove_featured_image' => __('Удалить изображение курса', 'course-plugin'),                    // Удалить изображение
            'use_featured_image'    => __('Использовать как изображение курса', 'course-plugin'),          // Использовать как изображение
            'insert_into_item'      => __('Вставить в курс', 'course-plugin'),                               // Вставить в элемент
            'uploaded_to_this_item' => __('Загружено для этого курса', 'course-plugin'),                   // Загружено для этого элемента
            'items_list'            => __('Список курсов', 'course-plugin'),                                 // Список элементов
            'items_list_navigation' => __('Навигация по списку курсов', 'course-plugin'),                    // Навигация по списку
            'filter_items_list'     => __('Фильтровать список курсов', 'course-plugin'),                    // Фильтровать список
        );
        
        // Массив с параметрами регистрации типа поста
        $args = array(
            'label'                 => __('Курс', 'course-plugin'),                                          // Метка типа поста
            'description'           => __('Управление курсами', 'course-plugin'),                            // Описание типа поста
            'labels'                => $labels,                                                              // Массив с переводами (см. выше)
            'supports'              => array('title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'),   // Поддерживаемые функции: заголовок, редактор, миниатюра, отрывок, произвольные поля
            'hierarchical'          => false,                                                                // Не иерархический тип (как записи, а не как страницы)
            'public'                => true,                                                                // Публичный тип (доступен на фронтенде)
            'show_ui'               => true,                                                                // Показывать интерфейс в админ-панели
            'show_in_menu'          => true,                                                                // Показывать в меню админ-панели
            'menu_position'         => 5,                                                                   // Позиция в меню (5 = после "Записи")
            'menu_icon'             => 'dashicons-book-alt',                                                // Иконка в меню (книга)
            'show_in_admin_bar'     => true,                                                                // Показывать в админ-баре (верхняя панель)
            'show_in_nav_menus'     => true,                                                                // Можно добавлять в меню навигации
            'can_export'            => true,                                                                // Можно экспортировать
            'has_archive'           => true,                                                                // Есть архив (страница со списком всех курсов)
            'exclude_from_search'   => false,                                                               // Не исключать из поиска
            'publicly_queryable'    => true,                                                                // Можно запрашивать через URL
            'capability_type'       => 'post',                                                              // Тип прав доступа (как у записей)
            'map_meta_cap'          => true,                                                                // Автоматически сопоставлять права доступа
            'show_in_rest'          => true,                                                                // Поддержка REST API (Gutenberg редактор)
            'rest_base'             => 'courses',                                                           // Базовый URL для REST API
            'rewrite'               => array('slug' => 'course'),                                          // Правило перезаписи URL (slug для постоянных ссылок)
        );
        
        // Регистрируем тип поста с названием 'course' и указанными параметрами
        // После этого в WordPress появится новый тип записей "Курсы Про"
        register_post_type('course', $args);
    }
}
