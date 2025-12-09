<?php
/**
 * Класс для административного интерфейса курсов
 */

if (!defined('ABSPATH')) {
    exit;
}

class Course_Admin {
    
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
        // Добавляем колонки в список курсов
        add_filter('manage_course_posts_columns', array($this, 'add_course_columns'));
        add_action('manage_course_posts_custom_column', array($this, 'render_course_columns'), 10, 2);
        
        // Делаем колонки сортируемыми
        add_filter('manage_edit-course_sortable_columns', array($this, 'make_columns_sortable'));
        
        // Добавляем фильтры по таксономиям
        add_action('restrict_manage_posts', array($this, 'add_taxonomy_filters'));
        add_filter('parse_query', array($this, 'filter_courses_by_taxonomy'));
        
        // Добавляем функцию дублирования курса
        add_filter('post_row_actions', array($this, 'add_duplicate_link'), 10, 2);
        add_action('admin_action_duplicate_course', array($this, 'duplicate_course'));
        
        // Добавляем стили и скрипты для админки
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    /**
     * Добавление колонок в список курсов
     */
    public function add_course_columns($columns) {
        $new_columns = array();
        
        // Добавляем чекбокс
        $new_columns['cb'] = $columns['cb'];
        
        // Добавляем миниатюру
        $new_columns['thumbnail'] = __('Изображение', 'course-plugin');
        
        // Добавляем заголовок
        $new_columns['title'] = $columns['title'];
        
        // Добавляем таксономии
        $new_columns['course_specialization'] = __('Специализация', 'course-plugin');
        $new_columns['course_level'] = __('Уровень', 'course-plugin');
        $new_columns['course_topic'] = __('Тема', 'course-plugin');
        $new_columns['course_teacher'] = __('Преподаватель', 'course-plugin');
        
        // Добавляем дату
        $new_columns['date'] = $columns['date'];
        
        return $new_columns;
    }
    
    /**
     * Отображение содержимого колонок
     */
    public function render_course_columns($column, $post_id) {
        switch ($column) {
            case 'thumbnail':
                if (has_post_thumbnail($post_id)) {
                    echo get_the_post_thumbnail($post_id, array(50, 50));
                } else {
                    echo '—';
                }
                break;
                
            case 'course_specialization':
                $terms = get_the_terms($post_id, 'course_specialization');
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
                
            case 'course_level':
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
                
            case 'course_topic':
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
                
            case 'course_teacher':
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
     */
    public function make_columns_sortable($columns) {
        $columns['course_specialization'] = 'course_specialization';
        $columns['course_level'] = 'course_level';
        $columns['course_topic'] = 'course_topic';
        $columns['course_teacher'] = 'course_teacher';
        return $columns;
    }
    
    /**
     * Добавление фильтров по таксономиям
     */
    public function add_taxonomy_filters($post_type) {
        if ('course' !== $post_type) {
            return;
        }
        
        // Фильтр по специализации
        $this->render_taxonomy_filter('course_specialization', __('Все специализации', 'course-plugin'));
        
        // Фильтр по уровню образования
        $this->render_taxonomy_filter('course_level', __('Все уровни', 'course-plugin'));
        
        // Фильтр по теме
        $this->render_taxonomy_filter('course_topic', __('Все темы', 'course-plugin'));
        
        // Фильтр по преподавателю
        $this->render_taxonomy_filter('course_teacher', __('Все преподаватели', 'course-plugin'));
    }
    
    /**
     * Рендеринг фильтра таксономии
     */
    private function render_taxonomy_filter($taxonomy, $all_label) {
        $selected = isset($_GET[$taxonomy]) ? $_GET[$taxonomy] : '';
        $info_taxonomy = get_taxonomy($taxonomy);
        
        wp_dropdown_categories(array(
            'show_option_all' => $all_label,
            'taxonomy'        => $taxonomy,
            'name'            => $taxonomy,
            'orderby'         => 'name',
            'selected'        => $selected,
            'show_count'      => true,
            'hide_empty'      => true,
            'value_field'     => 'slug',
        ));
    }
    
    /**
     * Фильтрация курсов по таксономии
     */
    public function filter_courses_by_taxonomy($query) {
        global $pagenow;
        $post_type = 'course';
        $taxonomies = array('course_specialization', 'course_level', 'course_topic', 'course_teacher');
        
        if ($pagenow == 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] == $post_type) {
            foreach ($taxonomies as $taxonomy) {
                if (isset($_GET[$taxonomy]) && $_GET[$taxonomy] != '') {
                    $query->query_vars[$taxonomy] = $_GET[$taxonomy];
                }
            }
        }
    }
    
    /**
     * Добавление ссылки "Дублировать" в строку действий
     */
    public function add_duplicate_link($actions, $post) {
        if ($post->post_type !== 'course') {
            return $actions;
        }
        
        if (!current_user_can('edit_posts')) {
            return $actions;
        }
        
        $duplicate_url = wp_nonce_url(
            admin_url('admin.php?action=duplicate_course&post=' . $post->ID),
            'duplicate_course_' . $post->ID,
            'duplicate_nonce'
        );
        
        $actions['duplicate'] = '<a href="' . esc_url($duplicate_url) . '" title="' . esc_attr__('Дублировать этот курс', 'course-plugin') . '">' . __('Дублировать', 'course-plugin') . '</a>';
        
        return $actions;
    }
    
    /**
     * Дублирование курса
     */
    public function duplicate_course() {
        // Проверка nonce
        if (!isset($_GET['duplicate_nonce']) || !wp_verify_nonce($_GET['duplicate_nonce'], 'duplicate_course_' . $_GET['post'])) {
            wp_die(__('Ошибка безопасности. Пожалуйста, попробуйте снова.', 'course-plugin'));
        }
        
        // Проверка прав
        if (!current_user_can('edit_posts')) {
            wp_die(__('У вас нет прав для выполнения этого действия.', 'course-plugin'));
        }
        
        // Получаем ID оригинального курса
        $post_id = isset($_GET['post']) ? intval($_GET['post']) : 0;
        
        if (!$post_id) {
            wp_die(__('Курс не найден.', 'course-plugin'));
        }
        
        // Получаем оригинальный курс
        $original_post = get_post($post_id);
        
        if (!$original_post || $original_post->post_type !== 'course') {
            wp_die(__('Курс не найден.', 'course-plugin'));
        }
        
        // Подготавливаем данные для нового курса
        $new_post_data = array(
            'post_title'     => $original_post->post_title . ' (копия)',
            'post_content'   => $original_post->post_content,
            'post_excerpt'   => $original_post->post_excerpt,
            'post_status'    => 'draft', // Создаем как черновик
            'post_type'      => 'course',
            'post_author'    => get_current_user_id(),
            'post_date'      => current_time('mysql'),
            'post_date_gmt'  => current_time('mysql', 1),
        );
        
        // Создаем новый курс
        $new_post_id = wp_insert_post($new_post_data);
        
        if (is_wp_error($new_post_id)) {
            wp_die(__('Ошибка при создании копии курса.', 'course-plugin'));
        }
        
        // Копируем метаполя
        $meta_keys = get_post_custom_keys($post_id);
        if ($meta_keys) {
            foreach ($meta_keys as $meta_key) {
                // Пропускаем служебные метаполя
                if (strpos($meta_key, '_edit_') === 0 || strpos($meta_key, '_wp_') === 0) {
                    continue;
                }
                
                $meta_values = get_post_custom_values($meta_key, $post_id);
                foreach ($meta_values as $meta_value) {
                    add_post_meta($new_post_id, $meta_key, maybe_unserialize($meta_value));
                }
            }
        }
        
        // Копируем таксономии
        $taxonomies = get_object_taxonomies('course');
        foreach ($taxonomies as $taxonomy) {
            $terms = wp_get_post_terms($post_id, $taxonomy, array('fields' => 'slugs'));
            if ($terms && !is_wp_error($terms)) {
                wp_set_post_terms($new_post_id, $terms, $taxonomy);
            }
        }
        
        // Копируем миниатюру (featured image)
        $thumbnail_id = get_post_thumbnail_id($post_id);
        if ($thumbnail_id) {
            set_post_thumbnail($new_post_id, $thumbnail_id);
        }
        
        // Перенаправляем на страницу редактирования нового курса
        wp_redirect(admin_url('post.php?action=edit&post=' . $new_post_id));
        exit;
    }
    
    /**
     * Подключение стилей и скриптов для админки
     */
    public function enqueue_admin_assets($hook) {
        // Подключаем только на страницах курсов
        if ('edit.php' !== $hook && 'post.php' !== $hook && 'post-new.php' !== $hook) {
            return;
        }
        
        global $post_type;
        if ('course' !== $post_type) {
            return;
        }
        
        // Можно добавить свои стили и скрипты здесь
        // wp_enqueue_style('course-admin-style', COURSE_PLUGIN_URL . 'assets/css/admin.css', array(), COURSE_PLUGIN_VERSION);
        // wp_enqueue_script('course-admin-script', COURSE_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), COURSE_PLUGIN_VERSION, true);
    }
}

