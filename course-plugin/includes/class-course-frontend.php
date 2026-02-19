<?php
/**
 * Класс для фронтенда курсов
 * 
 * @copyright Copyright (c) 2024 Кузьменко Валентин (Valentink2410)
 * @author Кузьменко Валентин (Valentink2410)
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
        // Rewrite rule для страницы всех преподавателей /teachers/
        add_action('init', array($this, 'add_teachers_rewrite_rule'));
        add_filter('query_vars', array($this, 'add_teachers_query_var'));
        add_filter('request', array($this, 'teachers_archive_request'), 1);
        
        // Подключаем шаблоны
        add_filter('template_include', array($this, 'course_template_loader'));
        add_action('template_redirect', array($this, 'teachers_archive_redirect'), 1);
        
        // Добавляем фильтры в запрос
        add_action('pre_get_posts', array($this, 'filter_courses_query'));
        
        // Подключаем стили и скрипты
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        
        // Добавляем шорткоды для отображения курсов и преподавателей
        add_shortcode('courses', array($this, 'courses_shortcode'));
        add_shortcode('teachers', array($this, 'teachers_shortcode'));
    }
    
    /**
     * Добавляет правило перезаписи URL для страницы преподавателей
     */
    public function add_teachers_rewrite_rule() {
        add_rewrite_rule('^teachers/?$', 'index.php?teachers_archive=1', 'top');
    }
    
    /**
     * Регистрирует query var для страницы преподавателей
     */
    public function add_teachers_query_var($vars) {
        $vars[] = 'teachers_archive';
        return $vars;
    }
    
    /**
     * Fallback: устанавливает teachers_archive=1 при запросе /teachers/,
     * если rewrite rules ещё не применены (404)
     */
    public function teachers_archive_request($query_vars) {
        if (isset($query_vars['teachers_archive'])) {
            return $query_vars;
        }
        $uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
        $path = parse_url($uri, PHP_URL_PATH);
        $path = $path ? trim($path, '/') : '';
        if (preg_match('#^teachers(/page/\d+)?/?$#', $path)) {
            $query_vars['teachers_archive'] = '1';
        }
        return $query_vars;
    }
    
    /**
     * Снимает флаг 404 для страницы /teachers/, чтобы отображался наш шаблон
     */
    public function teachers_archive_redirect() {
        if ((int) get_query_var('teachers_archive') === 1) {
            global $wp_query;
            $wp_query->is_404 = false;
            status_header(200);
        }
    }
    
    /**
     * Загрузка шаблонов для курсов и преподавателей
     * Определяет, какой шаблон использовать для отображения курсов
     */
    public function course_template_loader($template) {
        // Проверяем, используется ли builder для текущей страницы
        // Course Builder удален
        // global $post;
        // if ($post && (is_singular('course') || is_page())) {
        //     $builder = Course_Builder::get_instance();
        //     if ($builder->is_builder_enabled($post->ID)) {
        //         $builder_template = COURSE_PLUGIN_DIR . 'templates/builder-template.php';
        //         if (file_exists($builder_template)) {
        //             return $builder_template;
        //         }
        //     }
        // }
        
        // Шаблон архива курсов
        // Проверяем несколько условий для определения архива курсов
        $is_course_archive = false;
        
        // Проверка через is_post_type_archive
        if (is_post_type_archive('course')) {
            $is_course_archive = true;
        }
        // Проверка через URL (если URL содержит /course/ или /courses/)
        elseif (isset($_SERVER['REQUEST_URI'])) {
            $request_uri = $_SERVER['REQUEST_URI'];
            // Убираем query string для проверки
            $path = parse_url($request_uri, PHP_URL_PATH);
            if (preg_match('#/(course|courses)(/page/\d+)?/?$#', $path)) {
                $is_course_archive = true;
            }
        }
        // Проверка через query_var
        elseif (get_query_var('post_type') === 'course' && !is_singular()) {
            $is_course_archive = true;
        }
        
        if ($is_course_archive) {
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
        
        // Шаблон архива всех преподавателей /teachers/
        if ((int) get_query_var('teachers_archive') === 1) {
            $template_path = COURSE_PLUGIN_DIR . 'templates/archive-teachers.php';
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
        
        // Проверяем несколько условий для определения архива курсов
        // 1. Проверяем через is_post_type_archive
        // 2. Проверяем через query_vars (post_type = 'course')
        // 3. Проверяем через URL (если URL содержит /course/)
        $is_course_archive = false;
        
        // Проверка через URL (самый надежный способ)
        // Поддерживаем как /course/, так и /courses/ (с учетом пагинации и параметров)
        if (isset($_SERVER['REQUEST_URI'])) {
            $request_uri = $_SERVER['REQUEST_URI'];
            // Убираем query string для проверки
            $path = parse_url($request_uri, PHP_URL_PATH);
            if (preg_match('#/(course|courses)(/page/\d+)?/?$#', $path)) {
                $is_course_archive = true;
                // Принудительно устанавливаем post_type для запроса
                $query->set('post_type', 'course');
            }
        }
        
        if (!$is_course_archive && is_post_type_archive('course')) {
            $is_course_archive = true;
        }
        
        if (!$is_course_archive && $query->get('post_type') === 'course') {
            $is_course_archive = true;
        }
        
        if ($is_course_archive) {
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
            
            // Собираем все meta_query в один массив
            $meta_query = array();
            
            // Фильтр по месту прохождения (мета-поле)
            if (isset($_GET['location']) && !empty($_GET['location'])) {
                $locations = is_array($_GET['location']) ? $_GET['location'] : array($_GET['location']);
                $locations = array_map('sanitize_text_field', $locations);
                
                $meta_query[] = array(
                    'key'     => '_course_location',
                    'value'   => $locations,
                    'compare' => 'IN',
                );
            }
            
            // Фильтр по дате начала курса
            $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
            $date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
            
            if (!empty($date_from) || !empty($date_to)) {
                if (!empty($date_from) && !empty($date_to)) {
                    // Диапазон дат
                    $meta_query[] = array(
                        'key'     => '_course_start_date',
                        'value'   => array($date_from, $date_to),
                        'compare' => 'BETWEEN',
                        'type'    => 'DATE',
                    );
                } elseif (!empty($date_from)) {
                    // От даты
                    $meta_query[] = array(
                        'key'     => '_course_start_date',
                        'value'   => $date_from,
                        'compare' => '>=',
                        'type'    => 'DATE',
                    );
                } elseif (!empty($date_to)) {
                    // До даты
                    $meta_query[] = array(
                        'key'     => '_course_start_date',
                        'value'   => $date_to,
                        'compare' => '<=',
                        'type'    => 'DATE',
                    );
                }
            }
            
            // Фильтр по стоимости (бесплатные/платные)
            if (isset($_GET['price']) && !empty($_GET['price'])) {
                $price_filter = sanitize_text_field($_GET['price']);
                
                if ($price_filter === 'free') {
                    // Бесплатные: цена = 0 или пусто или не существует
                    $meta_query[] = array(
                        'relation' => 'OR',
                        array(
                            'key'     => '_course_price',
                            'value'   => '0',
                            'compare' => '=',
                        ),
                        array(
                            'key'     => '_course_price',
                            'value'   => '',
                            'compare' => '=',
                        ),
                        array(
                            'key'     => '_course_price',
                            'compare' => 'NOT EXISTS',
                        ),
                    );
                } elseif ($price_filter === 'paid') {
                    // Платные: цена > 0
                    $meta_query[] = array(
                        'key'     => '_course_price',
                        'value'   => '0',
                        'compare' => '>',
                        'type'    => 'NUMERIC',
                    );
                }
            }
            
            // Устанавливаем meta_query если есть фильтры
            if (!empty($meta_query)) {
                if (count($meta_query) > 1) {
                    $meta_query['relation'] = 'AND';
                }
                $query->set('meta_query', $meta_query);
            }
            
            // Сортировка (по умолчанию: дата начала — сначала ближайшие)
            $sort = !empty($_GET['sort']) ? sanitize_text_field($_GET['sort']) : 'date_start_asc';
            if ($sort !== 'default') {
                switch ($sort) {
                    case 'date_start_asc':
                        // Дата начала: сначала ближайшие (по возрастанию даты начала)
                        $query->set('meta_key', '_course_start_date');
                        $query->set('orderby', 'meta_value');
                        $query->set('order', 'ASC');
                        $query->set('meta_type', 'DATE');
                        break;
                    case 'price_asc':
                        // Цена: по возрастанию
                        $query->set('meta_key', '_course_price');
                        $query->set('orderby', 'meta_value_num');
                        $query->set('order', 'ASC');
                        break;
                    case 'level_asc':
                        // Уровень: сначала проще (по названию термина таксономии)
                        // Используем кастомную сортировку через фильтр
                        $query->set('orderby', 'none'); // Отключаем стандартную сортировку
                        add_filter('posts_orderby', array($this, 'orderby_level_term'), 10, 2);
                        break;
                    case 'title_asc':
                        // По названию А-Я
                        $query->set('orderby', 'title');
                        $query->set('order', 'ASC');
                        break;
                    // Старые варианты для обратной совместимости
                    case 'price_desc':
                        $query->set('meta_key', '_course_price');
                        $query->set('orderby', 'meta_value_num');
                        $query->set('order', 'DESC');
                        break;
                    case 'date_desc':
                        $query->set('orderby', 'date');
                        $query->set('order', 'DESC');
                        break;
                    case 'title_desc':
                        $query->set('orderby', 'title');
                        $query->set('order', 'DESC');
                        break;
                    default:
                        // Для date_start_asc и неизвестных значений — дата начала по возрастанию
                        $query->set('meta_key', '_course_start_date');
                        $query->set('orderby', 'meta_value');
                        $query->set('order', 'ASC');
                        $query->set('meta_type', 'DATE');
                        break;
                }
            }
        }
    }
    
    /**
     * Сортировка по термину таксономии уровня
     * Используется для сортировки курсов по уровню сложности
     * Сортирует по названию термина таксономии (алфавитно)
     */
    public function orderby_level_term($orderby, $query) {
        global $wpdb;
        
        // Проверяем, что это запрос курсов
        if ($query->get('post_type') !== 'course') {
            return $orderby;
        }
        
        // Получаем все термины таксономии course_level отсортированные по названию
        $terms = get_terms(array(
            'taxonomy' => 'course_level',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
        ));
        
        if (empty($terms) || is_wp_error($terms)) {
            // Если терминов нет, сортируем по названию курса
            return "{$wpdb->posts}.post_title ASC";
        }
        
        // Создаем маппинг терминов к порядку (по алфавиту)
        $term_order = array();
        $order = 1;
        foreach ($terms as $term) {
            $term_order[$term->term_id] = $order++;
        }
        
        // Формируем CASE для сортировки
        $case = "CASE ";
        foreach ($term_order as $term_id => $order) {
            $term_id = absint($term_id);
            $case .= "WHEN EXISTS (
                SELECT 1 FROM {$wpdb->term_relationships} tr
                INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                WHERE tr.object_id = {$wpdb->posts}.ID 
                AND tt.term_id = {$term_id}
                AND tt.taxonomy = 'course_level'
            ) THEN {$order} ";
        }
        $case .= "ELSE 999 END";
        
        // Добавляем сортировку по термину, затем по названию
        $orderby = $case . ", {$wpdb->posts}.post_title ASC";
        
        // Удаляем фильтр после использования, чтобы не применялся к другим запросам
        remove_filter('posts_orderby', array($this, 'orderby_level_term'), 10);
        
        return $orderby;
    }
    
    /**
     * Подключение стилей и скриптов
     */
    public function enqueue_assets() {
        // Проверяем, какие шорткоды используются на странице
        $is_teachers_shortcode = false;
        $is_courses_shortcode = false;
        $is_programs_shortcode = false;
        if (is_singular() || is_front_page()) {
            $post_id = is_front_page() && get_option('page_on_front') ? get_option('page_on_front') : get_queried_object_id();
            $post = $post_id ? get_post($post_id) : null;
            if ($post) {
                $is_teachers_shortcode = has_shortcode($post->post_content, 'teachers');
                $is_courses_shortcode = has_shortcode($post->post_content, 'courses');
                $is_programs_shortcode = has_shortcode($post->post_content, 'programs');
            }
        }
        
        // Подключаем стили и скрипты на страницах курсов и преподавателей
        $is_teachers_archive = (int) get_query_var('teachers_archive') === 1;
        if (is_post_type_archive('course') || is_singular('course') || is_tax('course_teacher') || $is_teachers_archive || $is_teachers_shortcode || $is_courses_shortcode || $is_programs_shortcode) {
            wp_enqueue_style(
                'course-frontend-style',
                COURSE_PLUGIN_URL . 'assets/css/frontend.css',
                array(),
                COURSE_PLUGIN_VERSION
            );
            
            // Премиальный дизайн для архивов (курсы и преподаватели)
            if (is_post_type_archive('course') || $is_teachers_archive || $is_teachers_shortcode) {
                wp_enqueue_style(
                    'course-premium-style',
                    COURSE_PLUGIN_URL . 'assets/css/premium-design.css',
                    array('course-frontend-style'),
                    COURSE_PLUGIN_VERSION
                );
                
                // Дополнительные критические стили для сетки преподавателей
                if ($is_teachers_archive || $is_teachers_shortcode) {
                    wp_enqueue_style(
                        'teachers-grid-fix',
                        COURSE_PLUGIN_URL . 'assets/css/teachers-grid-fix.css',
                        array('course-premium-style'),
                        COURSE_PLUGIN_VERSION,
                        'all'
                    );
                }
            }
            
            // Премиальный дизайн для страницы преподавателя
            if (is_tax('course_teacher')) {
                wp_enqueue_style(
                    'teacher-single-style',
                    COURSE_PLUGIN_URL . 'assets/css/teacher-single.css',
                    array('course-frontend-style'),
                    COURSE_PLUGIN_VERSION
                );
            }
            
            // Премиальный дизайн для страницы курса
            if (is_singular('course')) {
                wp_enqueue_style(
                    'single-course-premium-style',
                    COURSE_PLUGIN_URL . 'assets/css/single-course-premium.css',
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
    
    /**
     * Шорткод для отображения преподавателей
     * 
     * Параметры:
     * - per_page (число) — количество преподавателей (по умолчанию 6)
     * - columns (число) — колонок в сетке на десктопе (2, 3, 4)
     * 
     * Пример: [teachers per_page="8"]
     */
    public function teachers_shortcode($atts) {
        $atts = shortcode_atts(array(
            'per_page' => 6,
            'columns' => 3,
        ), $atts);
        
        $teachers_args = array(
            'taxonomy' => 'course_teacher',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
            'number' => intval($atts['per_page']),
        );
        
        $teachers = get_terms($teachers_args);
        
        if (is_wp_error($teachers) || empty($teachers)) {
            return '<p>' . __('Преподаватели не найдены.', 'course-plugin') . '</p>';
        }
        
        $teachers_with_data = array();
        foreach ($teachers as $term) {
            $teacher_photo = get_term_meta($term->term_id, 'teacher_photo', true);
            $teacher_position = get_term_meta($term->term_id, 'teacher_position', true);
            $teacher_description = get_term_meta($term->term_id, 'teacher_description', true);
            
            $courses_query = new WP_Query(array(
                'post_type' => 'course',
                'posts_per_page' => -1,
                'post_status' => 'publish',
                'tax_query' => array(
                    array(
                        'taxonomy' => 'course_teacher',
                        'field' => 'term_id',
                        'terms' => $term->term_id,
                    ),
                ),
            ));
            
            $courses_count = $courses_query->found_posts;
            $specializations = get_terms(array(
                'taxonomy' => 'course_specialization',
                'object_ids' => wp_list_pluck($courses_query->posts, 'ID'),
                'hide_empty' => true,
            ));
            
            wp_reset_postdata();
            
            $teachers_with_data[] = array(
                'term' => $term,
                'photo' => $teacher_photo,
                'position' => $teacher_position,
                'description' => $teacher_description,
                'courses_count' => $courses_count,
                'specializations' => is_wp_error($specializations) ? array() : $specializations,
            );
        }
        
        ob_start();
        $shortcode_atts = $atts;
        include COURSE_PLUGIN_DIR . 'templates/teachers-shortcode.php';
        return ob_get_clean();
    }
}

