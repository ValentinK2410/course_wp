<?php
/**
 * Главный класс Course Builder
 * Управляет жизненным циклом builder и хранением данных
 */

if (!defined('ABSPATH')) {
    exit;
}

class Course_Builder {
    
    /**
     * Единственный экземпляр класса
     */
    private static $instance = null;
    
    /**
     * Зарегистрированные виджеты
     */
    private static $widgets = array();
    
    /**
     * Мета ключ для хранения данных builder
     */
    const META_KEY = '_course_builder_data';
    
    /**
     * Мета ключ для флага использования builder
     */
    const USE_BUILDER_META_KEY = '_use_builder';
    
    /**
     * Версия формата данных
     */
    const DATA_VERSION = '1.0.0';
    
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
        // Регистрация хуков
        add_action('init', array($this, 'register_widgets_hook'));
        
        // AJAX обработчики
        add_action('wp_ajax_course_builder_save', array($this, 'ajax_save_builder_data'));
        add_action('wp_ajax_course_builder_load', array($this, 'ajax_load_builder_data'));
        
        // Фильтры для проверки использования builder
        add_filter('course_builder_is_enabled', array($this, 'is_builder_enabled'), 10, 1);
    }
    
    /**
     * Хук для регистрации виджетов
     */
    public function register_widgets_hook() {
        do_action('course_builder_register_widgets');
    }
    
    /**
     * Регистрация виджета
     */
    public static function register_widget($type, $class_name) {
        if (!class_exists($class_name)) {
            return false;
        }
        
        self::$widgets[$type] = $class_name;
        return true;
    }
    
    /**
     * Получить зарегистрированные виджеты
     */
    public static function get_widgets() {
        return self::$widgets;
    }
    
    /**
     * Получить класс виджета по типу
     */
    public static function get_widget_class($type) {
        return isset(self::$widgets[$type]) ? self::$widgets[$type] : null;
    }
    
    /**
     * Проверка, используется ли builder для поста
     */
    public function is_builder_enabled($post_id = null) {
        if (!$post_id) {
            global $post;
            $post_id = $post ? $post->ID : 0;
        }
        
        if (!$post_id) {
            return false;
        }
        
        return (bool) get_post_meta($post_id, self::USE_BUILDER_META_KEY, true);
    }
    
    /**
     * Включить builder для поста
     */
    public function enable_builder($post_id) {
        return update_post_meta($post_id, self::USE_BUILDER_META_KEY, true);
    }
    
    /**
     * Выключить builder для поста
     */
    public function disable_builder($post_id) {
        return delete_post_meta($post_id, self::USE_BUILDER_META_KEY);
    }
    
    /**
     * Получить данные builder для поста
     */
    public function get_builder_data($post_id) {
        $data = get_post_meta($post_id, self::META_KEY, true);
        
        if (empty($data)) {
            return $this->get_default_data();
        }
        
        // Декодируем JSON, если это строка
        if (is_string($data)) {
            $data = json_decode($data, true);
        }
        
        // Проверяем версию и мигрируем при необходимости
        if (isset($data['version']) && $data['version'] !== self::DATA_VERSION) {
            $data = $this->migrate_data($data);
        }
        
        return $data;
    }
    
    /**
     * Сохранить данные builder для поста
     */
    public function save_builder_data($post_id, $data) {
        // Валидация данных
        $data = $this->validate_data($data);
        
        // Добавляем версию
        $data['version'] = self::DATA_VERSION;
        $data['updated_at'] = current_time('mysql');
        
        // Сохраняем как JSON
        $json_data = wp_json_encode($data);
        
        return update_post_meta($post_id, self::META_KEY, $json_data);
    }
    
    /**
     * Получить данные по умолчанию
     */
    private function get_default_data() {
        return array(
            'version' => self::DATA_VERSION,
            'sections' => array()
        );
    }
    
    /**
     * Валидация данных builder
     */
    private function validate_data($data) {
        if (!is_array($data)) {
            return $this->get_default_data();
        }
        
        // Убеждаемся, что есть секции
        if (!isset($data['sections']) || !is_array($data['sections'])) {
            $data['sections'] = array();
        }
        
        // Валидация секций
        foreach ($data['sections'] as $key => $section) {
            if (!isset($section['id'])) {
                $data['sections'][$key]['id'] = 'section_' . uniqid();
            }
            
            if (!isset($section['columns']) || !is_array($section['columns'])) {
                $data['sections'][$key]['columns'] = array();
            }
            
            // Валидация колонок
            foreach ($data['sections'][$key]['columns'] as $col_key => $column) {
                if (!isset($column['id'])) {
                    $data['sections'][$key]['columns'][$col_key]['id'] = 'col_' . uniqid();
                }
                
                if (!isset($column['width'])) {
                    $data['sections'][$key]['columns'][$col_key]['width'] = 100;
                }
                
                if (!isset($column['widgets']) || !is_array($column['widgets'])) {
                    $data['sections'][$key]['columns'][$col_key]['widgets'] = array();
                }
                
                // Валидация виджетов
                foreach ($data['sections'][$key]['columns'][$col_key]['widgets'] as $widget_key => $widget) {
                    if (!isset($widget['id'])) {
                        $data['sections'][$key]['columns'][$col_key]['widgets'][$widget_key]['id'] = 'widget_' . uniqid();
                    }
                    
                    if (!isset($widget['type'])) {
                        unset($data['sections'][$key]['columns'][$col_key]['widgets'][$widget_key]);
                        continue;
                    }
                    
                    // Проверяем, что тип виджета зарегистрирован
                    if (!isset(self::$widgets[$widget['type']])) {
                        unset($data['sections'][$key]['columns'][$col_key]['widgets'][$widget_key]);
                        continue;
                    }
                    
                    if (!isset($widget['settings'])) {
                        $data['sections'][$key]['columns'][$col_key]['widgets'][$widget_key]['settings'] = array();
                    }
                }
            }
        }
        
        return $data;
    }
    
    /**
     * Миграция данных при изменении версии
     */
    private function migrate_data($data) {
        // Пока версия одна, просто возвращаем данные
        // В будущем здесь можно добавить логику миграции
        return $data;
    }
    
    /**
     * AJAX сохранение данных builder
     */
    public function ajax_save_builder_data() {
        // Проверка nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'course_builder_save')) {
            wp_send_json_error(array('message' => __('Ошибка безопасности', 'course-plugin')));
        }
        
        // Проверка прав
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Недостаточно прав', 'course-plugin')));
        }
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $data = isset($_POST['data']) ? $_POST['data'] : array();
        
        if (!$post_id) {
            wp_send_json_error(array('message' => __('Не указан ID поста', 'course-plugin')));
        }
        
        // Декодируем JSON данные, если они пришли как строка
        if (is_string($data)) {
            $data = json_decode(stripslashes($data), true);
        }
        
        // Сохраняем данные
        $result = $this->save_builder_data($post_id, $data);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => __('Данные сохранены', 'course-plugin'),
                'data' => $this->get_builder_data($post_id)
            ));
        } else {
            wp_send_json_error(array('message' => __('Ошибка сохранения', 'course-plugin')));
        }
    }
    
    /**
     * AJAX загрузка данных builder
     */
    public function ajax_load_builder_data() {
        // Проверка nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'course_builder_load')) {
            wp_send_json_error(array('message' => __('Ошибка безопасности', 'course-plugin')));
        }
        
        // Проверка прав
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Недостаточно прав', 'course-plugin')));
        }
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        
        if (!$post_id) {
            wp_send_json_error(array('message' => __('Не указан ID поста', 'course-plugin')));
        }
        
        $data = $this->get_builder_data($post_id);
        
        wp_send_json_success(array('data' => $data));
    }
}
