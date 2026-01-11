<?php
/**
 * Класс для административного интерфейса курсов
 * 
 * Этот класс отвечает за улучшение административного интерфейса WordPress
 * для работы с курсами. Он добавляет:
 * - Кастомные колонки в списке курсов
 * - Фильтры по таксономиям
 * - Функцию дублирования курсов
 * - Сортировку по колонкам
 * 
 * @copyright Copyright (c) 2024 Кузьменко Валентин (Valentink2410)
 * @author Кузьменко Валентин (Valentink2410)
 */

// Проверка безопасности: если файл вызывается напрямую, прекращаем выполнение
if (!defined('ABSPATH')) {
    exit;
}

class Course_Admin {
    
    /**
     * Единственный экземпляр класса (Singleton)
     * Хранит объект класса, если он уже был создан
     */
    private static $instance = null;
    
    /**
     * Получить экземпляр класса
     * Паттерн Singleton: гарантирует создание только одного экземпляра класса
     * 
     * @return Course_Admin Экземпляр класса
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Конструктор класса
     * Приватный, чтобы предотвратить создание экземпляра напрямую
     * Регистрирует все хуки WordPress для административного интерфейса
     */
    private function __construct() {
        // Добавляем кастомные колонки в список курсов
        // Фильтр 'manage_course_posts_columns' позволяет изменить список колонок
        add_filter('manage_course_posts_columns', array($this, 'add_course_columns'));
        
        // Отображаем содержимое кастомных колонок
        // Хук 'manage_course_posts_custom_column' вызывается для каждой строки в списке курсов
        // Параметры: 10 - приоритет, 2 - количество аргументов (колонка и ID поста)
        add_action('manage_course_posts_custom_column', array($this, 'render_course_columns'), 10, 2);
        
        // Делаем колонки сортируемыми (можно кликнуть на заголовок колонки для сортировки)
        // Фильтр 'manage_edit-course_sortable_columns' определяет, какие колонки можно сортировать
        add_filter('manage_edit-course_sortable_columns', array($this, 'make_columns_sortable'));
        
        // Добавляем фильтры по таксономиям над списком курсов
        // Хук 'restrict_manage_posts' позволяет добавить выпадающие списки для фильтрации
        add_action('restrict_manage_posts', array($this, 'add_taxonomy_filters'));
        
        // Применяем фильтры к запросу курсов
        // Фильтр 'parse_query' позволяет изменить параметры запроса перед выполнением
        add_filter('parse_query', array($this, 'filter_courses_by_taxonomy'));
        
        // Добавляем ссылку "Дублировать" в строку действий каждого курса
        // Фильтр 'post_row_actions' позволяет добавить действия в контекстное меню курса
        add_filter('post_row_actions', array($this, 'add_duplicate_link'), 10, 2);
        
        // Обрабатываем действие дублирования курса
        // Хук 'admin_action_duplicate_course' срабатывает при переходе по ссылке дублирования
        add_action('admin_action_duplicate_course', array($this, 'duplicate_course'));
        
        // Подключаем стили и скрипты для админ-панели
        // Хук 'admin_enqueue_scripts' позволяет добавить CSS и JS только на нужных страницах
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    /**
     * Добавление кастомных колонок в список курсов
     * Изменяет структуру таблицы со списком курсов в админ-панели
     * 
     * @param array $columns Массив существующих колонок
     * @return array Массив колонок с добавленными кастомными колонками
     */
    public function add_course_columns($columns) {
        // Создаем новый массив для колонок
        $new_columns = array();
        
        // Добавляем чекбокс для массовых действий (выбор нескольких курсов)
        $new_columns['cb'] = $columns['cb'];
        
        // Добавляем колонку с миниатюрой курса (изображение 50x50 пикселей)
        $new_columns['thumbnail'] = __('Изображение', 'course-plugin');
        
        // Добавляем колонку с заголовком курса
        $new_columns['title'] = $columns['title'];
        
        // Добавляем колонки с таксономиями для быстрого просмотра информации о курсе
        $new_columns['course_specialization'] = __('Специализация', 'course-plugin');
        $new_columns['course_level'] = __('Уровень', 'course-plugin');
        $new_columns['course_topic'] = __('Тема', 'course-plugin');
        $new_columns['course_teacher'] = __('Преподаватель', 'course-plugin');
        
        // Добавляем колонку с датой публикации курса
        $new_columns['date'] = $columns['date'];
        
        // Возвращаем новый массив колонок
        return $new_columns;
    }
    
    /**
     * Отображение содержимого кастомных колонок
     * Вызывается для каждой строки в списке курсов
     * 
     * @param string $column Название колонки (например, 'thumbnail', 'course_specialization')
     * @param int $post_id ID курса
     */
    public function render_course_columns($column, $post_id) {
        // Используем switch для определения, какую колонку нужно отобразить
        switch ($column) {
            // Колонка с миниатюрой курса
            case 'thumbnail':
                // Проверяем, есть ли у курса установленное изображение записи (featured image)
                if (has_post_thumbnail($post_id)) {
                    // Выводим миниатюру размером 50x50 пикселей
                    echo get_the_post_thumbnail($post_id, array(50, 50));
                } else {
                    // Если изображения нет, выводим прочерк
                    echo '—';
                }
                break;
                
            // Колонка со специализацией курса
            case 'course_specialization':
                // Получаем все термины таксономии 'course_specialization' для этого курса
                $terms = get_the_terms($post_id, 'course_specialization');
                
                // Проверяем, что термины получены и нет ошибки
                if ($terms && !is_wp_error($terms)) {
                    // Создаем массив для хранения названий терминов
                    $term_names = array();
                    
                    // Проходим по всем терминам и собираем их названия
                    foreach ($terms as $term) {
                        $term_names[] = $term->name;
                    }
                    
                    // Выводим названия терминов через запятую
                    // esc_html() экранирует HTML для безопасности
                    echo esc_html(implode(', ', $term_names));
                } else {
                    // Если терминов нет, выводим прочерк
                    echo '—';
                }
                break;
                
            // Колонка с уровнем образования курса
            case 'course_level':
                // Получаем все термины таксономии 'course_level' для этого курса
                $terms = get_the_terms($post_id, 'course_level');
                
                if ($terms && !is_wp_error($terms)) {
                    $term_names = array();
                    foreach ($terms as $term) {
                        $term_names[] = $term->name;
                    }
                    echo esc_html(implode(', ', $term_names));
                } else {
                    echo '—';
                }
                break;
                
            // Колонка с темой курса
            case 'course_topic':
                // Получаем все термины таксономии 'course_topic' для этого курса
                $terms = get_the_terms($post_id, 'course_topic');
                
                if ($terms && !is_wp_error($terms)) {
                    $term_names = array();
                    foreach ($terms as $term) {
                        $term_names[] = $term->name;
                    }
                    echo esc_html(implode(', ', $term_names));
                } else {
                    echo '—';
                }
                break;
                
            // Колонка с преподавателем курса
            case 'course_teacher':
                // Получаем все термины таксономии 'course_teacher' для этого курса
                $terms = get_the_terms($post_id, 'course_teacher');
                
                if ($terms && !is_wp_error($terms)) {
                    $term_names = array();
                    foreach ($terms as $term) {
                        $term_names[] = $term->name;
                    }
                    echo esc_html(implode(', ', $term_names));
                } else {
                    echo '—';
                }
                break;
        }
    }
    
    /**
     * Делаем колонки сортируемыми
     * Позволяет сортировать курсы по таксономиям, кликая на заголовок колонки
     * 
     * @param array $columns Массив колонок
     * @return array Массив колонок с указанием, какие можно сортировать
     */
    public function make_columns_sortable($columns) {
        // Добавляем колонки таксономий в список сортируемых
        // Значение - это название метаполя или таксономии для сортировки
        $columns['course_specialization'] = 'course_specialization';
        $columns['course_level'] = 'course_level';
        $columns['course_topic'] = 'course_topic';
        $columns['course_teacher'] = 'course_teacher';
        
        return $columns;
    }
    
    /**
     * Добавление фильтров по таксономиям над списком курсов
     * Создает выпадающие списки для фильтрации курсов по таксономиям
     * 
     * @param string $post_type Тип поста (должен быть 'course')
     */
    public function add_taxonomy_filters($post_type) {
        // Проверяем, что мы на странице списка курсов
        if ('course' !== $post_type) {
            return; // Если не курс, выходим из функции
        }
        
        // Добавляем фильтр по специализации
        // Вызываем метод для отображения выпадающего списка с терминами таксономии
        $this->render_taxonomy_filter('course_specialization', __('Все специализации', 'course-plugin'));
        
        // Добавляем фильтр по уровню образования
        $this->render_taxonomy_filter('course_level', __('Все уровни', 'course-plugin'));
        
        // Добавляем фильтр по теме
        $this->render_taxonomy_filter('course_topic', __('Все темы', 'course-plugin'));
        
        // Добавляем фильтр по преподавателю
        $this->render_taxonomy_filter('course_teacher', __('Все преподаватели', 'course-plugin'));
    }
    
    /**
     * Рендеринг фильтра таксономии (выпадающий список)
     * Создает HTML-элемент <select> с терминами таксономии
     * 
     * @param string $taxonomy Название таксономии (например, 'course_specialization')
     * @param string $all_label Текст для опции "Все" (например, "Все специализации")
     */
    private function render_taxonomy_filter($taxonomy, $all_label) {
        // Получаем выбранное значение из URL (если пользователь уже применил фильтр)
        $selected = isset($_GET[$taxonomy]) ? $_GET[$taxonomy] : '';
        
        // Получаем информацию о таксономии (нужно для проверки существования)
        $info_taxonomy = get_taxonomy($taxonomy);
        
        // Создаем выпадающий список с терминами таксономии
        // wp_dropdown_categories() - стандартная функция WordPress для создания списка категорий
        wp_dropdown_categories(array(
            'show_option_all' => $all_label,        // Текст для опции "Все" (показать все курсы)
            'taxonomy'        => $taxonomy,         // Название таксономии
            'name'            => $taxonomy,         // Атрибут name для <select> (используется в URL)
            'orderby'         => 'name',            // Сортировка терминов по названию
            'selected'        => $selected,         // Выбранное значение (если фильтр уже применен)
            'show_count'      => true,              // Показывать количество курсов в каждой категории
            'hide_empty'      => true,              // Скрывать категории без курсов
            'value_field'     => 'slug',            // Использовать slug термина как значение (не ID)
        ));
    }
    
    /**
     * Фильтрация курсов по таксономии
     * Применяет выбранные фильтры к запросу курсов
     * 
     * @param WP_Query $query Объект запроса WordPress
     */
    public function filter_courses_by_taxonomy($query) {
        // Получаем глобальную переменную с текущей страницей админ-панели
        global $pagenow;
        
        // Тип поста, с которым работаем
        $post_type = 'course';
        
        // Массив всех таксономий, по которым можно фильтровать
        $taxonomies = array('course_specialization', 'course_level', 'course_topic', 'course_teacher');
        
        // Проверяем, что мы на странице списка курсов (edit.php)
        // и что в URL указан тип поста 'course'
        if ($pagenow == 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] == $post_type) {
            // Проходим по всем таксономиям
            foreach ($taxonomies as $taxonomy) {
                // Проверяем, есть ли в URL параметр для этой таксономии (пользователь выбрал фильтр)
                if (isset($_GET[$taxonomy]) && $_GET[$taxonomy] != '') {
                    // Добавляем фильтр к запросу
                    // WordPress автоматически применит этот фильтр при выборке курсов
                    $query->query_vars[$taxonomy] = $_GET[$taxonomy];
                }
            }
        }
    }
    
    /**
     * Добавление ссылки "Дублировать" в строку действий курса
     * Добавляет действие "Дублировать" в контекстное меню каждого курса в списке
     * 
     * @param array $actions Массив существующих действий (Редактировать, Удалить и т.д.)
     * @param WP_Post $post Объект курса
     * @return array Массив действий с добавленной ссылкой "Дублировать"
     */
    public function add_duplicate_link($actions, $post) {
        // Проверяем, что это курс (а не другой тип поста)
        if ($post->post_type !== 'course') {
            return $actions; // Если не курс, возвращаем действия без изменений
        }
        
        // Проверяем права пользователя (может ли он редактировать записи)
        if (!current_user_can('edit_posts')) {
            return $actions; // Если нет прав, не показываем ссылку дублирования
        }
        
        // Создаем безопасную URL для дублирования курса
        // wp_nonce_url() добавляет токен безопасности для защиты от CSRF-атак
        $duplicate_url = wp_nonce_url(
            admin_url('admin.php?action=duplicate_course&post=' . $post->ID),  // URL с действием и ID курса
            'duplicate_course_' . $post->ID,                                    // Название nonce (уникальное для каждого курса)
            'duplicate_nonce'                                                   // Название параметра nonce в URL
        );
        
        // Добавляем ссылку "Дублировать" в массив действий
        // esc_url() экранирует URL для безопасности
        // esc_attr__() переводит и экранирует текст для атрибута title
        $actions['duplicate'] = '<a href="' . esc_url($duplicate_url) . '" title="' . esc_attr__('Дублировать этот курс', 'course-plugin') . '">' . __('Дублировать', 'course-plugin') . '</a>';
        
        // Возвращаем обновленный массив действий
        return $actions;
    }
    
    /**
     * Дублирование курса
     * Создает точную копию курса со всеми метаполями, таксономиями и миниатюрой
     * Вызывается при переходе по ссылке "Дублировать"
     */
    public function duplicate_course() {
        // Проверка безопасности: проверяем наличие и валидность nonce
        // nonce - это токен безопасности, который защищает от CSRF-атак
        if (!isset($_GET['duplicate_nonce']) || !wp_verify_nonce($_GET['duplicate_nonce'], 'duplicate_course_' . $_GET['post'])) {
            // Если nonce неверный, останавливаем выполнение и показываем сообщение об ошибке
            wp_die(__('Ошибка безопасности. Пожалуйста, попробуйте снова.', 'course-plugin'));
        }
        
        // Проверка прав доступа: может ли пользователь редактировать записи
        if (!current_user_can('edit_posts')) {
            // Если нет прав, останавливаем выполнение
            wp_die(__('У вас нет прав для выполнения этого действия.', 'course-plugin'));
        }
        
        // Получаем ID оригинального курса из URL
        // intval() преобразует значение в целое число (защита от инъекций)
        $post_id = isset($_GET['post']) ? intval($_GET['post']) : 0;
        
        // Проверяем, что ID курса получен
        if (!$post_id) {
            wp_die(__('Курс не найден.', 'course-plugin'));
        }
        
        // Получаем данные оригинального курса из базы данных
        $original_post = get_post($post_id);
        
        // Проверяем, что курс найден и это действительно курс
        if (!$original_post || $original_post->post_type !== 'course') {
            wp_die(__('Курс не найден.', 'course-plugin'));
        }
        
        // Подготавливаем данные для нового курса (копии)
        $new_post_data = array(
            'post_title'     => $original_post->post_title . ' (копия)',  // Добавляем "(копия)" к названию
            'post_content'   => $original_post->post_content,              // Копируем содержимое курса
            'post_excerpt'   => $original_post->post_excerpt,              // Копируем отрывок
            'post_status'    => 'draft',                                    // Создаем как черновик (не публикуем сразу)
            'post_type'      => 'course',                                    // Тип поста - курс
            'post_author'    => get_current_user_id(),                      // Автор - текущий пользователь
            'post_date'      => current_time('mysql'),                      // Текущая дата (локальное время)
            'post_date_gmt'  => current_time('mysql', 1),                    // Текущая дата (GMT время)
        );
        
        // Создаем новый курс в базе данных
        // wp_insert_post() возвращает ID нового курса или объект WP_Error при ошибке
        $new_post_id = wp_insert_post($new_post_data);
        
        // Проверяем, что курс создан успешно
        if (is_wp_error($new_post_id)) {
            wp_die(__('Ошибка при создании копии курса.', 'course-plugin'));
        }
        
        // Копируем все метаполя (дополнительные данные курса)
        // get_post_custom_keys() получает список всех ключей метаполей для курса
        $meta_keys = get_post_custom_keys($post_id);
        
        if ($meta_keys) {
            // Проходим по каждому метаполю
            foreach ($meta_keys as $meta_key) {
                // Пропускаем служебные метаполя WordPress
                // Они начинаются с '_edit_' или '_wp_' и не должны копироваться
                if (strpos($meta_key, '_edit_') === 0 || strpos($meta_key, '_wp_') === 0) {
                    continue; // Переходим к следующему метаполю
                }
                
                // Получаем все значения метаполя (метаполя могут иметь несколько значений)
                $meta_values = get_post_custom_values($meta_key, $post_id);
                
                // Копируем каждое значение метаполя в новый курс
                foreach ($meta_values as $meta_value) {
                    // maybe_unserialize() преобразует сериализованные данные обратно в массив/объект
                    // add_post_meta() добавляет метаполе к новому курсу
                    add_post_meta($new_post_id, $meta_key, maybe_unserialize($meta_value));
                }
            }
        }
        
        // Копируем все таксономии (специализация, уровень, тема, преподаватель)
        // get_object_taxonomies() получает список всех таксономий для типа поста 'course'
        $taxonomies = get_object_taxonomies('course');
        
        foreach ($taxonomies as $taxonomy) {
            // Получаем все термины таксономии для оригинального курса
            // 'fields' => 'slugs' означает, что нам нужны только slug'и терминов (не полные объекты)
            $terms = wp_get_post_terms($post_id, $taxonomy, array('fields' => 'slugs'));
            
            // Проверяем, что термины получены и нет ошибки
            if ($terms && !is_wp_error($terms)) {
                // Присваиваем те же термины новому курсу
                wp_set_post_terms($new_post_id, $terms, $taxonomy);
            }
        }
        
        // Копируем миниатюру (featured image) курса
        // get_post_thumbnail_id() получает ID изображения записи
        $thumbnail_id = get_post_thumbnail_id($post_id);
        
        if ($thumbnail_id) {
            // Устанавливаем то же изображение для нового курса
            set_post_thumbnail($new_post_id, $thumbnail_id);
        }
        
        // Перенаправляем пользователя на страницу редактирования нового курса
        // wp_redirect() отправляет HTTP-заголовок для перенаправления
        wp_redirect(admin_url('post.php?action=edit&post=' . $new_post_id));
        
        // Останавливаем выполнение скрипта после перенаправления
        exit;
    }
    
    /**
     * Подключение стилей и скриптов для админ-панели
     * Загружает CSS и JS файлы только на страницах, связанных с курсами
     * 
     * @param string $hook Название текущей страницы админ-панели
     */
    public function enqueue_admin_assets($hook) {
        // Подключаем стили и скрипты только на страницах курсов:
        // 'edit.php' - список всех курсов
        // 'post.php' - редактирование существующего курса
        // 'post-new.php' - создание нового курса
        if ('edit.php' !== $hook && 'post.php' !== $hook && 'post-new.php' !== $hook) {
            return; // Если не одна из этих страниц, выходим из функции
        }
        
        // Получаем глобальную переменную с типом поста текущей страницы
        global $post_type;
        
        // Проверяем, что мы работаем с курсами
        if ('course' !== $post_type) {
            return; // Если не курс, выходим из функции
        }
        
        // Здесь можно добавить подключение стилей и скриптов для админ-панели
        // Примеры закомментированы, так как файлы еще не созданы:
        // wp_enqueue_style('course-admin-style', COURSE_PLUGIN_URL . 'assets/css/admin.css', array(), COURSE_PLUGIN_VERSION);
        // wp_enqueue_script('course-admin-script', COURSE_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), COURSE_PLUGIN_VERSION, true);
    }
}
