<?php
/**
 * Класс для административного интерфейса программ
 * 
 * Этот класс отвечает за улучшение административного интерфейса WordPress
 * для работы с программами. Он добавляет:
 * - Кастомные колонки в списке программ
 * - Фильтры по таксономиям
 * - Функцию дублирования программ
 * - Сортировку по колонкам
 * 
 * @copyright Copyright (c) 2024 Кузьменко Валентин (Valentink2410)
 * @author Кузьменко Валентин (Valentink2410)
 */

// Проверка безопасности: если файл вызывается напрямую, прекращаем выполнение
if (!defined('ABSPATH')) {
    exit;
}

class Program_Admin {
    
    /**
     * Единственный экземпляр класса (Singleton)
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
     * Конструктор класса
     */
    private function __construct() {
        // Добавляем кастомные колонки в список программ
        add_filter('manage_program_posts_columns', array($this, 'add_program_columns'));
        
        // Отображаем содержимое кастомных колонок
        add_action('manage_program_posts_custom_column', array($this, 'render_program_columns'), 10, 2);
        
        // Делаем колонки сортируемыми
        add_filter('manage_edit-program_sortable_columns', array($this, 'make_columns_sortable'));
        
        // Добавляем фильтры по таксономиям над списком программ
        add_action('restrict_manage_posts', array($this, 'add_taxonomy_filters'));
        
        // Применяем фильтры к запросу программ
        add_filter('parse_query', array($this, 'filter_programs_by_taxonomy'));
        
        // Добавляем ссылку "Дублировать" в строку действий каждой программы
        add_filter('post_row_actions', array($this, 'add_duplicate_link'), 10, 2);
        
        // Обрабатываем действие дублирования программы
        add_action('admin_action_duplicate_program', array($this, 'duplicate_program'));
        
        // Подключаем стили и скрипты для админ-панели
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    /**
     * Добавление кастомных колонок в список программ
     */
    public function add_program_columns($columns) {
        $new_columns = array();
        
        $new_columns['cb'] = $columns['cb'];
        $new_columns['thumbnail'] = __('Изображение', 'course-plugin');
        $new_columns['title'] = $columns['title'];
        $new_columns['course_specialization'] = __('Программы', 'course-plugin');
        $new_columns['course_level'] = __('Уровень', 'course-plugin');
        $new_columns['course_topic'] = __('Тема', 'course-plugin');
        $new_columns['course_teacher'] = __('Преподаватель', 'course-plugin');
        $new_columns['program_price'] = __('Стоимость', 'course-plugin');
        $new_columns['program_courses_count'] = __('Курсов', 'course-plugin');
        $new_columns['date'] = $columns['date'];
        
        return $new_columns;
    }
    
    /**
     * Отображение содержимого кастомных колонок
     */
    public function render_program_columns($column, $post_id) {
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
                
            case 'program_price':
                $price = get_post_meta($post_id, '_program_price', true);
                if ($price) {
                    echo number_format($price, 2, ',', ' ') . ' Р';
                } else {
                    echo '—';
                }
                break;
                
            case 'program_courses_count':
                $courses_count = get_post_meta($post_id, '_program_courses_count', true);
                echo $courses_count ? esc_html($courses_count) : '0';
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
        $columns['program_price'] = 'program_price';
        $columns['program_courses_count'] = 'program_courses_count';
        
        return $columns;
    }
    
    /**
     * Добавление фильтров по таксономиям над списком программ
     */
    public function add_taxonomy_filters() {
        global $typenow;
        
        if ($typenow === 'program') {
            // Фильтр по специализации
            $selected_specialization = isset($_GET['course_specialization']) ? $_GET['course_specialization'] : '';
            wp_dropdown_categories(array(
                'show_option_all' => __('Все специализации', 'course-plugin'),
                'taxonomy' => 'course_specialization',
                'name' => 'course_specialization',
                'selected' => $selected_specialization,
                'value_field' => 'slug',
            ));
            
            // Фильтр по уровню
            $selected_level = isset($_GET['course_level']) ? $_GET['course_level'] : '';
            wp_dropdown_categories(array(
                'show_option_all' => __('Все уровни', 'course-plugin'),
                'taxonomy' => 'course_level',
                'name' => 'course_level',
                'selected' => $selected_level,
                'value_field' => 'slug',
            ));
            
            // Фильтр по теме
            $selected_topic = isset($_GET['course_topic']) ? $_GET['course_topic'] : '';
            wp_dropdown_categories(array(
                'show_option_all' => __('Все темы', 'course-plugin'),
                'taxonomy' => 'course_topic',
                'name' => 'course_topic',
                'selected' => $selected_topic,
                'value_field' => 'slug',
            ));
            
            // Фильтр по преподавателю
            $selected_teacher = isset($_GET['course_teacher']) ? $_GET['course_teacher'] : '';
            wp_dropdown_categories(array(
                'show_option_all' => __('Все преподаватели', 'course-plugin'),
                'taxonomy' => 'course_teacher',
                'name' => 'course_teacher',
                'selected' => $selected_teacher,
                'value_field' => 'slug',
            ));
        }
    }
    
    /**
     * Применение фильтров к запросу программ
     */
    public function filter_programs_by_taxonomy($query) {
        global $pagenow, $typenow;
        
        if ($pagenow === 'edit.php' && $typenow === 'program' && $query->is_main_query()) {
            $tax_query = array();
            
            if (isset($_GET['course_specialization']) && $_GET['course_specialization'] !== '') {
                $tax_query[] = array(
                    'taxonomy' => 'course_specialization',
                    'field' => 'slug',
                    'terms' => sanitize_text_field($_GET['course_specialization']),
                );
            }
            
            if (isset($_GET['course_level']) && $_GET['course_level'] !== '') {
                $tax_query[] = array(
                    'taxonomy' => 'course_level',
                    'field' => 'slug',
                    'terms' => sanitize_text_field($_GET['course_level']),
                );
            }
            
            if (isset($_GET['course_topic']) && $_GET['course_topic'] !== '') {
                $tax_query[] = array(
                    'taxonomy' => 'course_topic',
                    'field' => 'slug',
                    'terms' => sanitize_text_field($_GET['course_topic']),
                );
            }
            
            if (isset($_GET['course_teacher']) && $_GET['course_teacher'] !== '') {
                $tax_query[] = array(
                    'taxonomy' => 'course_teacher',
                    'field' => 'slug',
                    'terms' => sanitize_text_field($_GET['course_teacher']),
                );
            }
            
            if (!empty($tax_query)) {
                if (count($tax_query) > 1) {
                    $tax_query['relation'] = 'AND';
                }
                $query->set('tax_query', $tax_query);
            }
        }
    }
    
    /**
     * Добавление ссылки "Дублировать" в строку действий программы
     */
    public function add_duplicate_link($actions, $post) {
        if ($post->post_type === 'program' && current_user_can('edit_posts')) {
            $actions['duplicate'] = '<a href="' . wp_nonce_url(
                admin_url('admin.php?action=duplicate_program&post=' . $post->ID),
                'duplicate_program_' . $post->ID
            ) . '" title="' . esc_attr__('Дублировать эту программу', 'course-plugin') . '">' . __('Дублировать', 'course-plugin') . '</a>';
        }
        return $actions;
    }
    
    /**
     * Дублирование программы
     */
    public function duplicate_program() {
        if (!isset($_GET['post']) || !isset($_GET['action']) || $_GET['action'] !== 'duplicate_program') {
            wp_die(__('Недостаточно данных для дублирования программы.', 'course-plugin'));
        }
        
        $post_id = intval($_GET['post']);
        
        // Проверка nonce
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'duplicate_program_' . $post_id)) {
            wp_die(__('Ошибка безопасности.', 'course-plugin'));
        }
        
        // Проверка прав доступа
        if (!current_user_can('edit_posts')) {
            wp_die(__('У вас нет прав для дублирования программ.', 'course-plugin'));
        }
        
        // Получаем оригинальную программу
        $post = get_post($post_id);
        
        if (!$post || $post->post_type !== 'program') {
            wp_die(__('Программа не найдена.', 'course-plugin'));
        }
        
        // Создаем новую программу
        $new_post_id = wp_insert_post(array(
            'post_title' => $post->post_title . ' (копия)',
            'post_content' => $post->post_content,
            'post_excerpt' => $post->post_excerpt,
            'post_status' => 'draft',
            'post_type' => 'program',
            'post_author' => get_current_user_id(),
        ));
        
        if (is_wp_error($new_post_id)) {
            wp_die(__('Ошибка при создании копии программы.', 'course-plugin'));
        }
        
        // Копируем метаполя
        $meta_keys = array(
            '_program_price',
            '_program_old_price',
            '_program_duration',
            '_program_courses_count',
            '_program_start_date',
            '_program_end_date',
            '_program_certificate',
            '_program_related_courses',
        );
        
        foreach ($meta_keys as $meta_key) {
            $meta_value = get_post_meta($post_id, $meta_key, true);
            if ($meta_value !== '') {
                update_post_meta($new_post_id, $meta_key, $meta_value);
            }
        }
        
        // Копируем таксономии
        $taxonomies = array('course_specialization', 'course_level', 'course_topic', 'course_teacher');
        foreach ($taxonomies as $taxonomy) {
            $terms = wp_get_post_terms($post_id, $taxonomy, array('fields' => 'ids'));
            if (!empty($terms) && !is_wp_error($terms)) {
                wp_set_post_terms($new_post_id, $terms, $taxonomy);
            }
        }
        
        // Копируем миниатюру
        $thumbnail_id = get_post_thumbnail_id($post_id);
        if ($thumbnail_id) {
            set_post_thumbnail($new_post_id, $thumbnail_id);
        }
        
        // Перенаправляем на страницу редактирования новой программы
        wp_redirect(admin_url('post.php?action=edit&post=' . $new_post_id));
        exit;
    }
    
    /**
     * Подключение стилей и скриптов для админ-панели
     */
    public function enqueue_admin_assets($hook) {
        global $post_type;
        
        if ($post_type === 'program' && ($hook === 'edit.php' || $hook === 'post.php' || $hook === 'post-new.php')) {
            wp_enqueue_style(
                'course-admin-style',
                COURSE_PLUGIN_URL . 'assets/css/frontend.css',
                array(),
                COURSE_PLUGIN_VERSION
            );
        }
    }
}
