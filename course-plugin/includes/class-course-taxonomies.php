<?php
/**
 * Класс для регистрации таксономий курсов
 * 
 * Таксономии - это способ группировки и классификации записей в WordPress
 * Например: категории и метки для записей
 * 
 * Этот класс регистрирует 4 таксономии для курсов:
 * 1. Специализация и программы (иерархическая, как категории)
 * 2. Уровень образования (иерархическая, как категории)
 * 3. Тема (иерархическая, как категории)
 * 4. Преподаватель (неиерархическая, как метки)
 * 
 * @copyright Copyright (c) 2024 Кузьменко Валентин (Valentink2410)
 * @author Кузьменко Валентин (Valentink2410)
 */

// Проверка безопасности: если файл вызывается напрямую, прекращаем выполнение
if (!defined('ABSPATH')) {
    exit;
}

class Course_Taxonomies {
    
    /**
     * Единственный экземпляр класса (Singleton)
     */
    private static $instance = null;
    
    /**
     * Получить экземпляр класса
     * 
     * @return Course_Taxonomies Экземпляр класса
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Конструктор класса
     * Регистрирует хук для регистрации всех таксономий
     */
    private function __construct() {
        // Регистрируем метод register_taxonomies() на хук 'init' с приоритетом 25
        // Приоритет 25 означает, что таксономии будут зарегистрированы после типа поста (который имеет приоритет 20)
        // Это важно, так как таксономии должны быть связаны с уже существующим типом поста
        add_action('init', array($this, 'register_taxonomies'), 25);
    }
    
    /**
     * Регистрация всех таксономий
     * Вызывает методы регистрации для каждой таксономии
     * Вызывается на хуке 'init'
     */
    public function register_taxonomies() {
        // Регистрируем таксономию "Специализация и программы"
        $this->register_specialization_taxonomy();
        
        // Регистрируем таксономию "Уровень образования"
        $this->register_level_taxonomy();
        
        // Регистрируем таксономию "Тема"
        $this->register_topic_taxonomy();
        
        // Регистрируем таксономию "Преподаватель"
        $this->register_teacher_taxonomy();
    }
    
    /**
     * Регистрация таксономии "Специализация и программы"
     * Иерархическая таксономия (можно создавать подкатегории)
     * Пример: Программирование -> Веб-разработка -> Backend
     */
    private function register_specialization_taxonomy() {
        // Массив с переводами названий для таксономии
        $labels = array(
            'name'                       => _x('Специализации и программы', 'Taxonomy General Name', 'course-plugin'),
            'singular_name'              => _x('Специализация и программа', 'Taxonomy Singular Name', 'course-plugin'),
            'menu_name'                  => __('Специализации и программы', 'course-plugin'),
            'all_items'                  => __('Все специализации', 'course-plugin'),
            'parent_item'                => __('Родительская специализация', 'course-plugin'),
            'parent_item_colon'          => __('Родительская специализация:', 'course-plugin'),
            'new_item_name'              => __('Новая специализация', 'course-plugin'),
            'add_new_item'               => __('Добавить специализацию', 'course-plugin'),
            'edit_item'                  => __('Редактировать специализацию', 'course-plugin'),
            'update_item'                => __('Обновить специализацию', 'course-plugin'),
            'view_item'                  => __('Просмотреть специализацию', 'course-plugin'),
            'separate_items_with_commas' => __('Разделить специализации запятыми', 'course-plugin'),
            'add_or_remove_items'        => __('Добавить или удалить специализации', 'course-plugin'),
            'choose_from_most_used'      => __('Выбрать из наиболее используемых', 'course-plugin'),
            'popular_items'              => __('Популярные специализации', 'course-plugin'),
            'search_items'               => __('Поиск специализаций', 'course-plugin'),
            'not_found'                  => __('Не найдено', 'course-plugin'),
            'no_terms'                   => __('Нет специализаций', 'course-plugin'),
            'items_list'                 => __('Список специализаций', 'course-plugin'),
            'items_list_navigation'      => __('Навигация по списку специализаций', 'course-plugin'),
        );
        
        // Массив с параметрами регистрации таксономии
        $args = array(
            'labels'                     => $labels,                                                         // Массив с переводами
            'hierarchical'               => true,                                                            // Иерархическая таксономия (как категории)
            'public'                     => true,                                                           // Публичная таксономия (доступна на фронтенде)
            'show_ui'                    => true,                                                            // Показывать интерфейс в админ-панели
            'show_admin_column'          => true,                                                            // Показывать колонку в списке курсов
            'show_in_nav_menus'          => true,                                                            // Можно добавлять в меню навигации
            'show_tagcloud'              => true,                                                            // Показывать в облаке тегов
            'show_in_rest'               => true,                                                            // Поддержка REST API (Gutenberg)
            'show_in_quick_edit'         => true,                                                            // Показывать в быстром редактировании
            'meta_box_cb'                => 'post_categories_meta_box',                                    // Использовать стандартный метабокс категорий WordPress
            'rewrite'                    => array('slug' => 'specialization'),                              // Slug для URL (например: /specialization/programming/)
        );
        
        // Регистрируем таксономию 'course_specialization' для типа поста 'course'
        register_taxonomy('course_specialization', array('course'), $args);
    }
    
    /**
     * Регистрация таксономии "Уровень образования"
     * Иерархическая таксономия
     * Пример: Высшее образование -> Бакалавриат
     */
    private function register_level_taxonomy() {
        $labels = array(
            'name'                       => _x('Уровни образования', 'Taxonomy General Name', 'course-plugin'),
            'singular_name'              => _x('Уровень образования', 'Taxonomy Singular Name', 'course-plugin'),
            'menu_name'                  => __('Уровни образования', 'course-plugin'),
            'all_items'                  => __('Все уровни', 'course-plugin'),
            'parent_item'                => __('Родительский уровень', 'course-plugin'),
            'parent_item_colon'          => __('Родительский уровень:', 'course-plugin'),
            'new_item_name'              => __('Новый уровень', 'course-plugin'),
            'add_new_item'               => __('Добавить уровень', 'course-plugin'),
            'edit_item'                  => __('Редактировать уровень', 'course-plugin'),
            'update_item'                => __('Обновить уровень', 'course-plugin'),
            'view_item'                  => __('Просмотреть уровень', 'course-plugin'),
            'separate_items_with_commas' => __('Разделить уровни запятыми', 'course-plugin'),
            'add_or_remove_items'        => __('Добавить или удалить уровни', 'course-plugin'),
            'choose_from_most_used'      => __('Выбрать из наиболее используемых', 'course-plugin'),
            'popular_items'              => __('Популярные уровни', 'course-plugin'),
            'search_items'               => __('Поиск уровней', 'course-plugin'),
            'not_found'                  => __('Не найдено', 'course-plugin'),
            'no_terms'                   => __('Нет уровней', 'course-plugin'),
            'items_list'                 => __('Список уровней', 'course-plugin'),
            'items_list_navigation'      => __('Навигация по списку уровней', 'course-plugin'),
        );
        
        $args = array(
            'labels'                     => $labels,
            'hierarchical'               => true,                                                            // Иерархическая таксономия
            'public'                     => true,
            'show_ui'                    => true,
            'show_admin_column'          => true,
            'show_in_nav_menus'          => true,
            'show_tagcloud'              => true,
            'show_in_rest'               => true,
            'show_in_quick_edit'         => true,
            'meta_box_cb'                => 'post_categories_meta_box',                                    // Стандартный метабокс категорий
            'rewrite'                    => array('slug' => 'education-level'),                             // Slug для URL
        );
        
        // Регистрируем таксономию 'course_level' для типа поста 'course'
        register_taxonomy('course_level', array('course'), $args);
    }
    
    /**
     * Регистрация таксономии "Тема"
     * Иерархическая таксономия
     * Пример: Программирование -> Языки -> PHP
     */
    private function register_topic_taxonomy() {
        $labels = array(
            'name'                       => _x('Темы', 'Taxonomy General Name', 'course-plugin'),
            'singular_name'              => _x('Тема', 'Taxonomy Singular Name', 'course-plugin'),
            'menu_name'                  => __('Темы', 'course-plugin'),
            'all_items'                  => __('Все темы', 'course-plugin'),
            'parent_item'                => __('Родительская тема', 'course-plugin'),
            'parent_item_colon'          => __('Родительская тема:', 'course-plugin'),
            'new_item_name'              => __('Новая тема', 'course-plugin'),
            'add_new_item'               => __('Добавить тему', 'course-plugin'),
            'edit_item'                  => __('Редактировать тему', 'course-plugin'),
            'update_item'                => __('Обновить тему', 'course-plugin'),
            'view_item'                  => __('Просмотреть тему', 'course-plugin'),
            'separate_items_with_commas' => __('Разделить темы запятыми', 'course-plugin'),
            'add_or_remove_items'        => __('Добавить или удалить темы', 'course-plugin'),
            'choose_from_most_used'      => __('Выбрать из наиболее используемых', 'course-plugin'),
            'popular_items'              => __('Популярные темы', 'course-plugin'),
            'search_items'               => __('Поиск тем', 'course-plugin'),
            'not_found'                  => __('Не найдено', 'course-plugin'),
            'no_terms'                   => __('Нет тем', 'course-plugin'),
            'items_list'                 => __('Список тем', 'course-plugin'),
            'items_list_navigation'      => __('Навигация по списку тем', 'course-plugin'),
        );
        
        $args = array(
            'labels'                     => $labels,
            'hierarchical'               => true,                                                            // Иерархическая таксономия
            'public'                     => true,
            'show_ui'                    => true,
            'show_admin_column'          => true,
            'show_in_nav_menus'          => true,
            'show_tagcloud'              => true,
            'show_in_rest'               => true,
            'show_in_quick_edit'         => true,
            'meta_box_cb'                => 'post_categories_meta_box',                                    // Стандартный метабокс категорий
            'rewrite'                    => array('slug' => 'topic'),                                       // Slug для URL
        );
        
        // Регистрируем таксономию 'course_topic' для типа поста 'course'
        register_taxonomy('course_topic', array('course'), $args);
    }
    
    /**
     * Регистрация таксономии "Преподаватель"
     * Неиерархическая таксономия (как метки)
     * К одному курсу можно добавить несколько преподавателей
     */
    private function register_teacher_taxonomy() {
        $labels = array(
            'name'                       => _x('Преподаватели', 'Taxonomy General Name', 'course-plugin'),
            'singular_name'              => _x('Преподаватель', 'Taxonomy Singular Name', 'course-plugin'),
            'menu_name'                  => __('Преподаватели', 'course-plugin'),
            'all_items'                  => __('Все преподаватели', 'course-plugin'),
            'parent_item'                => __('Родительский преподаватель', 'course-plugin'),
            'parent_item_colon'          => __('Родительский преподаватель:', 'course-plugin'),
            'new_item_name'              => __('Новый преподаватель', 'course-plugin'),
            'add_new_item'               => __('Добавить преподавателя', 'course-plugin'),
            'edit_item'                  => __('Редактировать преподавателя', 'course-plugin'),
            'update_item'                => __('Обновить преподавателя', 'course-plugin'),
            'view_item'                  => __('Просмотреть преподавателя', 'course-plugin'),
            'separate_items_with_commas' => __('Разделить преподавателей запятыми', 'course-plugin'),
            'add_or_remove_items'        => __('Добавить или удалить преподавателей', 'course-plugin'),
            'choose_from_most_used'      => __('Выбрать из наиболее используемых', 'course-plugin'),
            'popular_items'              => __('Популярные преподаватели', 'course-plugin'),
            'search_items'               => __('Поиск преподавателей', 'course-plugin'),
            'not_found'                  => __('Не найдено', 'course-plugin'),
            'no_terms'                   => __('Нет преподавателей', 'course-plugin'),
            'items_list'                 => __('Список преподавателей', 'course-plugin'),
            'items_list_navigation'      => __('Навигация по списку преподавателей', 'course-plugin'),
        );
        
        $args = array(
            'labels'                     => $labels,
            'hierarchical'               => false,                                                           // НЕ иерархическая таксономия (как метки)
            'public'                     => true,
            'show_ui'                    => true,
            'show_admin_column'          => true,
            'show_in_nav_menus'          => true,
            'show_tagcloud'              => true,
            'show_in_rest'               => true,
            'show_in_quick_edit'         => true,
            'meta_box_cb'                => 'post_tags_meta_box',                                          // Стандартный метабокс меток (тегов)
            'rewrite'                    => array('slug' => 'teacher'),                                     // Slug для URL
        );
        
        // Регистрируем таксономию 'course_teacher' для типа поста 'course'
        register_taxonomy('course_teacher', array('course'), $args);
    }
}
