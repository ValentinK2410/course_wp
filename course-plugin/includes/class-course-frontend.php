<?php
/**
 * Класс для фронтенда курсов
 */

if (!defined('ABSPATH')) {
    exit;
}

class Course_Frontend {
    
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
        add_filter('template_include', array($this, 'course_template_loader'));
        
        // Добавляем фильтры в запрос
        add_action('pre_get_posts', array($this, 'filter_courses_query'));
        
        // Подключаем стили и скрипты
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        
        // Добавляем шорткод для отображения курсов
        add_shortcode('courses', array($this, 'courses_shortcode'));
    }
    
    /**
     * Загрузка шаблонов для курсов и преподавателей
     * Определяет, какой шаблон использовать для отображения курсов
     */
    public function course_template_loader($template) {
        // Шаблон архива курсов
        // is_post_type_archive('course') проверяет, является ли текущая страница архивом типа поста 'course'
        if (is_post_type_archive('course')) {
            $template_path = COURSE_PLUGIN_DIR . 'templates/archive-course.php';
            if (file_exists($template_path)) {
                return $template_path;
            }
        }
        
        // Шаблон отдельного курса
        if (is_singular('course')) {
            $template_path = COURSE_PLUGIN_DIR . 'templates/single-course.php';
            if (file_exists($template_path)) {
                return $template_path;
            }
        }
        
        // Шаблон страницы преподавателя
        if (is_tax('course_teacher')) {
            $template_path = COURSE_PLUGIN_DIR . 'templates/taxonomy-course_teacher.php';
            if (file_exists($template_path)) {
                return $template_path;
            }
        }
        
        return $template;
    }
    
    /**
     * Фильтрация запроса курсов
     * Настраивает запрос для правильного отображения курсов на странице архива
     */
    public function filter_courses_query($query) {
        // Проверяем, что это не админка и основной запрос
        if (is_admin() || !$query->is_main_query()) {
            return;
        }
        
        // Проверяем, является ли это архивом курсов
        // is_post_type_archive('course') проверяет, является ли текущая страница архивом типа поста 'course'
        if (is_post_type_archive('course')) {
            // Устанавливаем параметры запроса для курсов
            $query->set('post_type', 'course');
            $query->set('post_status', 'publish');  // Только опубликованные курсы
            
            // Количество курсов на странице (из GET параметра или по умолчанию 15)
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
                        $query->set('meta_key', '_course_price');
                        $query->set('orderby', 'meta_value_num');
                        $query->set('order', 'ASC');
                        break;
                    case 'price_desc':
                        $query->set('meta_key', '_course_price');
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
        // Подключаем стили и скрипты на страницах курсов и преподавателей
        if (is_post_type_archive('course') || is_singular('course') || is_tax('course_teacher')) {
            wp_enqueue_style(
                'course-frontend-style',
                COURSE_PLUGIN_URL . 'assets/css/frontend.css',
                array(),
                COURSE_PLUGIN_VERSION
            );
            
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
     * Шорткод для отображения курсов
     */
    public function courses_shortcode($atts) {
        $atts = shortcode_atts(array(
            'per_page' => 12,
            'specialization' => '',
            'level' => '',
            'topic' => '',
            'teacher' => '',
            'view' => 'grid',
        ), $atts);
        
        $args = array(
            'post_type' => 'course',
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
        
        $courses = new WP_Query($args);
        
        ob_start();
        include COURSE_PLUGIN_DIR . 'templates/courses-shortcode.php';
        return ob_get_clean();
    }
}

