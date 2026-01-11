<?php
/**
 * Фронтенд рендеринг Course Builder
 */

if (!defined('ABSPATH')) {
    exit;
}

class Course_Builder_Frontend {
    
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
        $this->init();
    }
    
    /**
     * Инициализация
     */
    private function init() {
        // Подключаем стили и скрипты
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        
        // Шорткод для рендеринга builder контента
        add_shortcode('course_builder_content', array($this, 'render_builder_content_shortcode'));
        
        // Убеждаемся, что виджеты зарегистрированы перед рендерингом
        add_action('wp', array($this, 'ensure_widgets_registered'));
    }
    
    /**
     * Убедиться, что виджеты зарегистрированы
     */
    public function ensure_widgets_registered() {
        // Регистрируем виджеты, если они еще не зарегистрированы
        do_action('course_builder_register_widgets');
        
        // Если виджеты все еще не зарегистрированы, регистрируем вручную
        if (class_exists('Course_Builder_Register')) {
            Course_Builder_Register::register_widgets();
        }
    }
    
    /**
     * Подключение стилей и скриптов
     */
    public function enqueue_assets() {
        // Подключаем только на страницах с builder
        if (!$this->is_builder_page()) {
            return;
        }
        
        // Стили
        wp_enqueue_style(
            'course-builder-frontend',
            COURSE_PLUGIN_URL . 'assets/css/builder-frontend.css',
            array(),
            COURSE_PLUGIN_VERSION
        );
        
        // Скрипты
        wp_enqueue_script(
            'course-builder-frontend',
            COURSE_PLUGIN_URL . 'assets/js/builder-frontend.js',
            array('jquery'),
            COURSE_PLUGIN_VERSION,
            true
        );
    }
    
    /**
     * Проверка, является ли текущая страница страницей с builder
     */
    private function is_builder_page() {
        global $post;
        
        if (!$post) {
            return false;
        }
        
        // Проверяем, включен ли builder или есть ли данные builder
        $builder = Course_Builder::get_instance();
        if ($builder->is_builder_enabled($post->ID)) {
            return true;
        }
        
        // Проверяем, есть ли данные builder (даже если формально не включен)
        $data = $builder->get_builder_data($post->ID);
        if (!empty($data['sections'])) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Рендеринг контента builder
     */
    public function render($post_id = null) {
        if (!$post_id) {
            global $post;
            $post_id = $post ? $post->ID : 0;
        }
        
        if (!$post_id) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Course Builder Frontend: No post ID provided');
            }
            return '';
        }
        
        $builder = Course_Builder::get_instance();
        $data = $builder->get_builder_data($post_id);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Course Builder Frontend: Rendering for post ' . $post_id);
            error_log('Course Builder Frontend: Data sections count: ' . (isset($data['sections']) ? count($data['sections']) : 0));
        }
        
        if (empty($data['sections'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Course Builder Frontend: No sections found, returning empty');
            }
            return '';
        }
        
        ob_start();
        $this->render_sections($data['sections']);
        $content = ob_get_clean();
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Course Builder Frontend: Rendered content length: ' . strlen($content));
        }
        
        return $content;
    }
    
    /**
     * Рендеринг секций
     */
    private function render_sections($sections) {
        foreach ($sections as $section) {
            $this->render_section($section);
        }
    }
    
    /**
     * Рендеринг секции
     */
    private function render_section($section) {
        $section_id = isset($section['id']) ? $section['id'] : '';
        $section_settings = isset($section['settings']) ? $section['settings'] : array();
        $columns = isset($section['columns']) ? $section['columns'] : array();
        
        // Стили секции
        $section_style = '';
        if (isset($section_settings['background_color'])) {
            $section_style .= 'background-color: ' . esc_attr($section_settings['background_color']) . ';';
        }
        if (isset($section_settings['padding_top'])) {
            $section_style .= 'padding-top: ' . esc_attr($section_settings['padding_top']) . 'px;';
        }
        if (isset($section_settings['padding_bottom'])) {
            $section_style .= 'padding-bottom: ' . esc_attr($section_settings['padding_bottom']) . 'px;';
        }
        
        $section_class = 'course-builder-section';
        if (isset($section_settings['css_class'])) {
            $section_class .= ' ' . esc_attr($section_settings['css_class']);
        }
        
        echo '<div class="' . esc_attr($section_class) . '" id="' . esc_attr($section_id) . '"' . ($section_style ? ' style="' . esc_attr($section_style) . '"' : '') . '>';
        echo '<div class="course-builder-container">';
        echo '<div class="course-builder-row">';
        
        // Рендеринг колонок
        foreach ($columns as $column) {
            $this->render_column($column);
        }
        
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Рендеринг колонки
     */
    private function render_column($column) {
        $column_id = isset($column['id']) ? $column['id'] : '';
        $width = isset($column['width']) ? intval($column['width']) : 100;
        $widgets = isset($column['widgets']) ? $column['widgets'] : array();
        
        $column_class = 'course-builder-column';
        if (isset($column['settings']['css_class'])) {
            $column_class .= ' ' . esc_attr($column['settings']['css_class']);
        }
        
        echo '<div class="' . esc_attr($column_class) . '" id="' . esc_attr($column_id) . '" style="width: ' . esc_attr($width) . '%;">';
        
        // Рендеринг виджетов
        foreach ($widgets as $widget) {
            $this->render_widget($widget);
        }
        
        echo '</div>';
    }
    
    /**
     * Рендеринг виджета
     */
    private function render_widget($widget) {
        $widget_id = isset($widget['id']) ? $widget['id'] : '';
        $widget_type = isset($widget['type']) ? $widget['type'] : '';
        $widget_settings = isset($widget['settings']) ? $widget['settings'] : array();
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Course Builder Frontend: Rendering widget ' . $widget_id . ' of type ' . $widget_type);
        }
        
        if (!$widget_type) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Course Builder Frontend: Widget has no type');
            }
            return;
        }
        
        // Получаем класс виджета
        $widget_class = Course_Builder::get_widget_class($widget_type);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Course Builder Frontend: Widget class: ' . ($widget_class ? $widget_class : 'not found'));
        }
        
        if (!$widget_class || !class_exists($widget_class)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Course Builder Frontend: Widget class not found or does not exist: ' . ($widget_class ? $widget_class : 'null'));
            }
            return;
        }
        
        // Создаем экземпляр виджета и рендерим
        try {
            $widget_instance = new $widget_class($widget_id, $widget_settings);
            
            // Добавляем data-атрибуты для редактирования в админке
            $is_admin = is_admin() || (defined('DOING_AJAX') && DOING_AJAX && isset($_POST['action']) && $_POST['action'] === 'course_builder_preview_page');
            $data_attrs = 'class="course-builder-widget course-builder-widget-' . esc_attr($widget_type) . '" id="' . esc_attr($widget_id) . '"';
            $data_attrs .= ' data-widget-id="' . esc_attr($widget_id) . '"';
            $data_attrs .= ' data-widget-type="' . esc_attr($widget_type) . '"';
            $data_attrs .= ' data-widget-settings="' . esc_attr(json_encode($widget_settings)) . '"';
            
            echo '<div ' . $data_attrs . '>';
            $widget_content = $widget_instance->render($widget_settings);
            echo $widget_content;
            echo '</div>';
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Course Builder Frontend: Widget rendered successfully, content length: ' . strlen($widget_content));
            }
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Course Builder Frontend: Ошибка рендеринга виджета ' . $widget_type . ': ' . $e->getMessage());
                error_log('Course Builder Frontend: Stack trace: ' . $e->getTraceAsString());
            }
        }
    }
    
    /**
     * Шорткод для рендеринга builder контента
     */
    public function render_builder_content_shortcode($atts) {
        $atts = shortcode_atts(array(
            'post_id' => 0
        ), $atts);
        
        $post_id = intval($atts['post_id']);
        if (!$post_id) {
            global $post;
            $post_id = $post ? $post->ID : 0;
        }
        
        return $this->render($post_id);
    }
}
