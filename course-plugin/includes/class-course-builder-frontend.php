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
        
        return Course_Builder::get_instance()->is_builder_enabled($post->ID);
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
            return '';
        }
        
        $builder = Course_Builder::get_instance();
        $data = $builder->get_builder_data($post_id);
        
        if (empty($data['sections'])) {
            return '';
        }
        
        ob_start();
        $this->render_sections($data['sections']);
        return ob_get_clean();
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
        
        if (!$widget_type) {
            return;
        }
        
        // Получаем класс виджета
        $widget_class = Course_Builder::get_widget_class($widget_type);
        
        if (!$widget_class || !class_exists($widget_class)) {
            return;
        }
        
        // Создаем экземпляр виджета и рендерим
        try {
            $widget_instance = new $widget_class($widget_id, $widget_settings);
            
            echo '<div class="course-builder-widget course-builder-widget-' . esc_attr($widget_type) . '" id="' . esc_attr($widget_id) . '">';
            echo $widget_instance->render($widget_settings);
            echo '</div>';
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Course Builder: Ошибка рендеринга виджета ' . $widget_type . ': ' . $e->getMessage());
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
