<?php
/**
 * Класс для фронтенда программ
 * 
 * @copyright Copyright (c) 2024 Кузьменко Валентин (Valentink2410)
 * @author Кузьменко Валентин (Valentink2410)
 */

if (!defined('ABSPATH')) {
    exit;
}

class Program_Frontend {
    
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
        // Подключаем шаблоны
        add_filter('template_include', array($this, 'program_template_loader'));
        
        // Добавляем фильтры в запрос
        add_action('pre_get_posts', array($this, 'filter_programs_query'));
        
        // Подключаем стили и скрипты
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        
        // Добавляем шорткод для отображения программ
        add_shortcode('programs', array($this, 'programs_shortcode'));
    }
    
    /**
     * Загрузка шаблонов для программ
     * Определяет, какой шаблон использовать для отображения программ
     */
    public function program_template_loader($template) {
        // Шаблон архива программ
        $is_program_archive = false;
        
        // Проверка через is_post_type_archive
        if (is_post_type_archive('program')) {
            $is_program_archive = true;
        }
        // Проверка через URL (если URL содержит /programs/)
        elseif (isset($_SERVER['REQUEST_URI'])) {
            $request_uri = $_SERVER['REQUEST_URI'];
            // Убираем query string для проверки
            $path = parse_url($request_uri, PHP_URL_PATH);
            if (preg_match('#/programs(/page/\d+)?/?$#', $path)) {
                $is_program_archive = true;
            }
        }
        // Проверка через query_var
        elseif (get_query_var('post_type') === 'program' && !is_singular()) {
            $is_program_archive = true;
        }
        
        if ($is_program_archive) {
            $template_path = COURSE_PLUGIN_DIR . 'templates/archive-program.php';
            if (file_exists($template_path)) {
                return $template_path;
            }
        }
        
        // Шаблон отдельной программы
        if (is_singular('program')) {
            $template_path = COURSE_PLUGIN_DIR . 'templates/single-program.php';
            if (file_exists($template_path)) {
                return $template_path;
            }
        }
        
        return $template;
    }
    
    /**
     * Фильтрация запроса программ
     * Настраивает запрос для правильного отображения программ на странице архива
     */
    public function filter_programs_query($query) {
        // Проверяем, что это не админка и основной запрос
        if (is_admin() || !$query->is_main_query()) {
            return;
        }
        
        // Проверяем несколько условий для определения архива программ
        $is_program_archive = false;
        
        // Проверка через URL (самый надежный способ)
        if (isset($_SERVER['REQUEST_URI'])) {
            $request_uri = $_SERVER['REQUEST_URI'];
            // Убираем query string для проверки
            $path = parse_url($request_uri, PHP_URL_PATH);
            if (preg_match('#/programs(/page/\d+)?/?$#', $path)) {
                $is_program_archive = true;
                // Принудительно устанавливаем post_type для запроса
                $query->set('post_type', 'program');
            }
        }
        
        if (!$is_program_archive && is_post_type_archive('program')) {
            $is_program_archive = true;
        }
        
        if (!$is_program_archive && $query->get('post_type') === 'program') {
            $is_program_archive = true;
        }
        
        if ($is_program_archive) {
            // Устанавливаем параметры запроса для программ
            $query->set('post_type', 'program');
            $query->set('post_status', 'publish');  // Только опубликованные программы
            
            // Количество программ на странице (из GET параметра или по умолчанию 15)
            $posts_per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 15;
            $query->set('posts_per_page', $posts_per_page);
            
            $tax_query = array();
            
            // Фильтр по специализации (может быть массивом)
            if (isset($_GET['specialization']) && !empty($_GET['specialization'])) {
                $specializations = is_array($_GET['specialization']) ? $_GET['specialization'] : array($_GET['specialization']);
                $specializations = array_map('sanitize_text_field', $specializations);
                $tax_query[] = array(
                    'taxonomy' => 'course_specialization',
                    'field'    => 'slug',
                    'terms'    => $specializations,
                    'operator' => 'IN',
                );
            }
            
            // Фильтр по уровню образования (может быть массивом)
            if (isset($_GET['level']) && !empty($_GET['level'])) {
                $levels = is_array($_GET['level']) ? $_GET['level'] : array($_GET['level']);
                $levels = array_map('sanitize_text_field', $levels);
                $tax_query[] = array(
                    'taxonomy' => 'course_level',
                    'field'    => 'slug',
                    'terms'    => $levels,
                    'operator' => 'IN',
                );
            }
            
            // Фильтр по теме (может быть массивом)
            if (isset($_GET['topic']) && !empty($_GET['topic'])) {
                $topics = is_array($_GET['topic']) ? $_GET['topic'] : array($_GET['topic']);
                $topics = array_map('sanitize_text_field', $topics);
                $tax_query[] = array(
                    'taxonomy' => 'course_topic',
                    'field'    => 'slug',
                    'terms'    => $topics,
                    'operator' => 'IN',
                );
            }
            
            // Фильтр по преподавателю
            if (isset($_GET['teacher']) && !empty($_GET['teacher'])) {
                $tax_query[] = array(
                    'taxonomy' => 'course_teacher',
                    'field'    => 'slug',
                    'terms'    => sanitize_text_field($_GET['teacher']),
                );
            }
            
            // Устанавливаем tax_query если есть фильтры
            if (!empty($tax_query)) {
                if (count($tax_query) > 1) {
                    $tax_query['relation'] = 'AND';
                }
                $query->set('tax_query', $tax_query);
            }
            
            // Сортировка
            if (isset($_GET['sort']) && !empty($_GET['sort'])) {
                $sort = sanitize_text_field($_GET['sort']);
                
                switch ($sort) {
                    case 'price_asc':
                        $query->set('meta_key', '_program_price');
                        $query->set('orderby', 'meta_value_num');
                        $query->set('order', 'ASC');
                        break;
                    case 'price_desc':
                        $query->set('meta_key', '_program_price');
                        $query->set('orderby', 'meta_value_num');
                        $query->set('order', 'DESC');
                        break;
                    case 'date_asc':
                        $query->set('orderby', 'date');
                        $query->set('order', 'ASC');
                        break;
                    case 'date_desc':
                        $query->set('orderby', 'date');
                        $query->set('order', 'DESC');
                        break;
                    case 'title_asc':
                        $query->set('orderby', 'title');
                        $query->set('order', 'ASC');
                        break;
                    case 'title_desc':
                        $query->set('orderby', 'title');
                        $query->set('order', 'DESC');
                        break;
                }
            }
        }
    }
    
    /**
     * Подключение стилей и скриптов
     */
    public function enqueue_assets() {
        // Подключаем стили и скрипты на страницах программ
        if (is_post_type_archive('program') || is_singular('program')) {
            wp_enqueue_style(
                'course-frontend-style',
                COURSE_PLUGIN_URL . 'assets/css/frontend.css',
                array(),
                COURSE_PLUGIN_VERSION
            );
            
            // Премиальный дизайн для архивов
            if (is_post_type_archive('program')) {
                wp_enqueue_style(
                    'program-premium-style',
                    COURSE_PLUGIN_URL . 'assets/css/premium-design.css',
                    array('course-frontend-style'),
                    COURSE_PLUGIN_VERSION
                );
            }
            
            // Премиальный дизайн для страницы программы
            if (is_singular('program')) {
                wp_enqueue_style(
                    'single-program-premium-style',
                    COURSE_PLUGIN_URL . 'assets/css/single-program-premium.css',
                    array('course-frontend-style'),
                    COURSE_PLUGIN_VERSION
                );
            }
            
            wp_enqueue_script(
                'course-frontend-script',
                COURSE_PLUGIN_URL . 'assets/js/frontend.js',
                array('jquery'),
                COURSE_PLUGIN_VERSION,
                true
            );
            
            wp_localize_script('course-frontend-script', 'courseFrontend', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('course_frontend_nonce'),
            ));
        }
    }
    
    /**
     * Шорткод для отображения программ
     */
    public function programs_shortcode($atts) {
        $atts = shortcode_atts(array(
            'per_page' => 12,
            'specialization' => '',
            'level' => '',
            'topic' => '',
            'teacher' => '',
            'view' => 'grid',
            'button_style' => 'default',
            'theme_class' => '',
            // Сортировка по дате: asc — от ранних к поздним, desc — от поздних к ранним
            'order' => 'asc',
            // orderby: date — дата публикации, start_date — дата начала программы
            'orderby' => 'start_date',
            // Фильтр по дате начала: date_from и date_to в формате Y-m-d
            'date_from' => '',
            'date_to' => '',
        ), $atts);
        
        $args = array(
            'post_type' => 'program',
            'posts_per_page' => intval($atts['per_page']),
            'post_status' => 'publish',
        );
        
        $tax_query = array();
        
        if (!empty($atts['specialization'])) {
            $tax_query[] = array(
                'taxonomy' => 'course_specialization',
                'field' => 'slug',
                'terms' => $atts['specialization'],
            );
        }
        
        if (!empty($atts['level'])) {
            $tax_query[] = array(
                'taxonomy' => 'course_level',
                'field' => 'slug',
                'terms' => $atts['level'],
            );
        }
        
        if (!empty($atts['topic'])) {
            $tax_query[] = array(
                'taxonomy' => 'course_topic',
                'field' => 'slug',
                'terms' => $atts['topic'],
            );
        }
        
        if (!empty($atts['teacher'])) {
            $tax_query[] = array(
                'taxonomy' => 'course_teacher',
                'field' => 'slug',
                'terms' => $atts['teacher'],
            );
        }
        
        if (!empty($tax_query)) {
            if (count($tax_query) > 1) {
                $tax_query['relation'] = 'AND';
            }
            $args['tax_query'] = $tax_query;
        }
        
        // Сортировка по дате
        $order = strtolower($atts['order']);
        if (!in_array($order, array('asc', 'desc'))) {
            $order = 'asc';
        }
        $orderby = strtolower($atts['orderby']);
        
        if ($orderby === 'start_date') {
            $args['meta_key'] = '_program_start_date';
            $args['orderby'] = 'meta_value';
            $args['order'] = strtoupper($order);
            // Программы без даты в конец
            $args['meta_query'] = array(
                array(
                    'key' => '_program_start_date',
                    'compare' => 'EXISTS',
                ),
            );
        } else {
            $args['orderby'] = 'date';
            $args['order'] = strtoupper($order);
        }
        
        // Фильтр по диапазону дат (date_from, date_to)
        $date_from = trim($atts['date_from']);
        $date_to = trim($atts['date_to']);
        if (!empty($date_from) || !empty($date_to)) {
            $date_meta = array('relation' => 'AND');
            if (!empty($date_from)) {
                $date_meta[] = array(
                    'key' => '_program_start_date',
                    'value' => $date_from,
                    'compare' => '>=',
                    'type' => 'DATE',
                );
            }
            if (!empty($date_to)) {
                $date_meta[] = array(
                    'key' => '_program_start_date',
                    'value' => $date_to,
                    'compare' => '<=',
                    'type' => 'DATE',
                );
            }
            if (isset($args['meta_query'])) {
                $args['meta_query'] = array(
                    'relation' => 'AND',
                    $args['meta_query'],
                    $date_meta,
                );
            } else {
                $args['meta_query'] = $date_meta;
            }
        }
        
        $programs = new WP_Query($args);
        $courses = $programs; // шаблон ожидает переменную $courses
        
        $shortcode_context = array(
            'post_type' => 'program',
            'more_text' => __('Все программы', 'course-plugin'),
            'more_url' => get_post_type_archive_link('program'),
            'button_style' => isset($atts['button_style']) ? sanitize_key($atts['button_style']) : 'default',
            'theme_class' => !empty($atts['theme_class']) ? sanitize_html_class($atts['theme_class']) : '',
        );
        
        ob_start();
        include COURSE_PLUGIN_DIR . 'templates/courses-shortcode.php';
        return ob_get_clean();
    }
}
