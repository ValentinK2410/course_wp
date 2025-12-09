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
     * Загрузка шаблонов для курсов
     */
    public function course_template_loader($template) {
        if (is_post_type_archive('course')) {
            $template_path = COURSE_PLUGIN_DIR . 'templates/archive-course.php';
            if (file_exists($template_path)) {
                return $template_path;
            }
        }
        
        if (is_singular('course')) {
            $template_path = COURSE_PLUGIN_DIR . 'templates/single-course.php';
            if (file_exists($template_path)) {
                return $template_path;
            }
        }
        
        return $template;
    }
    
    /**
     * Фильтрация запроса курсов
     */
    public function filter_courses_query($query) {
        if (!is_admin() && $query->is_main_query() && is_post_type_archive('course')) {
            $posts_per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 12;
            $query->set('posts_per_page', $posts_per_page);
            
            // Фильтр по специализации
            if (isset($_GET['specialization']) && !empty($_GET['specialization'])) {
                $query->set('tax_query', array(
                    array(
                        'taxonomy' => 'course_specialization',
                        'field'    => 'slug',
                        'terms'    => sanitize_text_field($_GET['specialization']),
                    ),
                ));
            }
            
            // Фильтр по уровню образования
            if (isset($_GET['level']) && !empty($_GET['level'])) {
                $tax_query = $query->get('tax_query');
                if (!is_array($tax_query)) {
                    $tax_query = array();
                }
                $tax_query[] = array(
                    'taxonomy' => 'course_level',
                    'field'    => 'slug',
                    'terms'    => sanitize_text_field($_GET['level']),
                );
                $query->set('tax_query', $tax_query);
            }
            
            // Фильтр по теме
            if (isset($_GET['topic']) && !empty($_GET['topic'])) {
                $tax_query = $query->get('tax_query');
                if (!is_array($tax_query)) {
                    $tax_query = array();
                }
                $tax_query[] = array(
                    'taxonomy' => 'course_topic',
                    'field'    => 'slug',
                    'terms'    => sanitize_text_field($_GET['topic']),
                );
                $query->set('tax_query', $tax_query);
            }
            
            // Фильтр по преподавателю
            if (isset($_GET['teacher']) && !empty($_GET['teacher'])) {
                $tax_query = $query->get('tax_query');
                if (!is_array($tax_query)) {
                    $tax_query = array();
                }
                $tax_query[] = array(
                    'taxonomy' => 'course_teacher',
                    'field'    => 'slug',
                    'terms'    => sanitize_text_field($_GET['teacher']),
                );
                $query->set('tax_query', $tax_query);
            }
            
            // Добавляем relation для множественных фильтров
            $tax_query = $query->get('tax_query');
            if (is_array($tax_query) && count($tax_query) > 1) {
                $query->set('tax_query', array_merge(array('relation' => 'AND'), $tax_query));
            }
        }
    }
    
    /**
     * Подключение стилей и скриптов
     */
    public function enqueue_assets() {
        if (is_post_type_archive('course') || is_singular('course')) {
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

