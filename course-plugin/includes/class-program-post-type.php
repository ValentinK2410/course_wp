<?php
/**
 * Класс для регистрации Custom Post Type "Программы"
 * 
 * Этот класс отвечает за создание нового типа записей WordPress "program" (Программы)
 * Custom Post Type позволяет создавать специальные типы контента с собственными настройками
 * 
 * @copyright Copyright (c) 2024 Кузьменко Валентин (Valentink2410)
 * @author Кузьменко Валентин (Valentink2410)
 */

// Проверка безопасности: если файл вызывается напрямую, прекращаем выполнение
if (!defined('ABSPATH')) {
    exit;
}

class Program_Post_Type {
    
    /**
     * Единственный экземпляр класса (Singleton)
     * Хранит объект класса, если он уже был создан
     */
    private static $instance = null;
    
    /**
     * Получить экземпляр класса
     * Паттерн Singleton: гарантирует создание только одного экземпляра класса
     * 
     * @return Program_Post_Type Экземпляр класса
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
     * Регистрация Custom Post Type "Программы"
     * Создает новый тип записей WordPress с названием "program"
     * Вызывается на хуке 'init'
     */
    public function register_post_type() {
        // Проверяем, не зарегистрирован ли уже тип поста "program"
        // Это предотвращает повторную регистрацию и возможные конфликты
        if (post_type_exists('program')) {
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
            'name'                  => _x('Программы', 'Post Type General Name', 'course-plugin'),              // Множественное название (используется в меню)
            'singular_name'         => _x('Программа', 'Post Type Singular Name', 'course-plugin'),              // Единственное название
            'menu_name'             => __('Программы', 'course-plugin'),                                    // Название в меню админ-панели
            'name_admin_bar'        => __('Программа', 'course-plugin'),                                          // Название в админ-баре (верхняя панель)
            'archives'              => __('Архив программ', 'course-plugin'),                                 // Название архива
            'attributes'            => __('Атрибуты программы', 'course-plugin'),                                // Атрибуты программы
            'parent_item_colon'     => __('Родительская программа:', 'course-plugin'),                           // Родительский элемент (для иерархических типов)
            'all_items'             => __('Все программы', 'course-plugin'),                                     // Все элементы (в меню)
            'add_new_item'          => __('Добавить новую программу', 'course-plugin'),                          // Добавить новый элемент
            'add_new'               => __('Добавить новую', 'course-plugin'),                               // Добавить новый (кнопка)
            'new_item'              => __('Новая программа', 'course-plugin'),                                   // Новый элемент
            'edit_item'             => __('Редактировать программу', 'course-plugin'),                           // Редактировать элемент
            'update_item'           => __('Обновить программу', 'course-plugin'),                                 // Обновить элемент
            'view_item'             => __('Просмотреть программу', 'course-plugin'),                             // Просмотреть элемент
            'view_items'            => __('Просмотреть программы', 'course-plugin'),                            // Просмотреть элементы
            'search_items'          => __('Поиск программ', 'course-plugin'),                                 // Поиск элементов
            'not_found'             => __('Не найдено', 'course-plugin'),                                    // Не найдено
            'not_found_in_trash'    => __('Не найдено в корзине', 'course-plugin'),                         // Не найдено в корзине
            'featured_image'        => __('Изображение программы', 'course-plugin'),                            // Изображение записи
            'set_featured_image'    => __('Установить изображение программы', 'course-plugin'),                 // Установить изображение
            'remove_featured_image' => __('Удалить изображение программы', 'course-plugin'),                    // Удалить изображение
            'use_featured_image'    => __('Использовать как изображение программы', 'course-plugin'),          // Использовать как изображение
            'insert_into_item'      => __('Вставить в программу', 'course-plugin'),                               // Вставить в элемент
            'uploaded_to_this_item' => __('Загружено для этой программы', 'course-plugin'),                   // Загружено для этого элемента
            'items_list'            => __('Список программ', 'course-plugin'),                                 // Список элементов
            'items_list_navigation' => __('Навигация по списку программ', 'course-plugin'),                    // Навигация по списку
            'filter_items_list'     => __('Фильтровать список программ', 'course-plugin'),                    // Фильтровать список
        );
        
        // Массив с параметрами регистрации типа поста
        $args = array(
            'label'                 => __('Программа', 'course-plugin'),                                          // Метка типа поста
            'description'           => __('Управление программами', 'course-plugin'),                            // Описание типа поста
            'labels'                => $labels,                                                              // Массив с переводами (см. выше)
            'supports'              => array('title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'),   // Поддерживаемые функции: заголовок, редактор, миниатюра, отрывок, произвольные поля
            'hierarchical'          => false,                                                                // Не иерархический тип (как записи, а не как страницы)
            'public'                => true,                                                                // Публичный тип (доступен на фронтенде)
            'show_ui'               => true,                                                                // Показывать интерфейс в админ-панели
            'show_in_menu'          => true,                                                                // Показывать в меню админ-панели
            'menu_position'         => 6,                                                                   // Позиция в меню (6 = после курсов)
            'menu_icon'             => 'dashicons-welcome-learn-more',                                                // Иконка в меню
            'show_in_admin_bar'     => true,                                                                // Показывать в админ-баре (верхняя панель)
            'show_in_nav_menus'     => true,                                                                // Можно добавлять в меню навигации
            'can_export'            => true,                                                                // Можно экспортировать
            'has_archive'           => true,                                                                // Есть архив (страница со списком всех программ)
            'exclude_from_search'   => false,                                                               // Не исключать из поиска
            'publicly_queryable'    => true,                                                                // Можно запрашивать через URL
            'capability_type'       => 'post',                                                              // Тип прав доступа (как у записей)
            'map_meta_cap'          => true,                                                                // Автоматически сопоставлять права доступа
            'show_in_rest'          => true,                                                                // Поддержка REST API (Gutenberg редактор)
            'rest_base'             => 'programs',                                                           // Базовый URL для REST API
            'rewrite'               => array('slug' => 'programs'),                                         // Правило перезаписи URL (slug для постоянных ссылок)
        );
        
        // Регистрируем тип поста с названием 'program' и указанными параметрами
        // После этого в WordPress появится новый тип записей "Программы"
        register_post_type('program', $args);
        
        // Регистрируем таксономии для программ (используем те же, что и для курсов)
        // Это делается после регистрации типа поста, чтобы таксономии были связаны с ним
        if (taxonomy_exists('course_specialization')) {
            register_taxonomy_for_object_type('course_specialization', 'program');
        }
        if (taxonomy_exists('course_level')) {
            register_taxonomy_for_object_type('course_level', 'program');
        }
        if (taxonomy_exists('course_topic')) {
            register_taxonomy_for_object_type('course_topic', 'program');
        }
        if (taxonomy_exists('course_teacher')) {
            register_taxonomy_for_object_type('course_teacher', 'program');
        }
    }
}
