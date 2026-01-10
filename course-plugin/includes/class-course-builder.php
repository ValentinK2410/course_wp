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
        $raw_data = get_post_meta($post_id, self::META_KEY, true);
        
        error_log('Course Builder: Getting data for post ' . $post_id . ', raw data type: ' . gettype($raw_data) . ', empty: ' . (empty($raw_data) ? 'yes' : 'no'));
        
        if (empty($raw_data)) {
            error_log('Course Builder: No data found, returning default');
            return $this->get_default_data();
        }
        
        // Декодируем JSON, если это строка
        if (is_string($raw_data)) {
            error_log('Course Builder: Decoding JSON string, length: ' . strlen($raw_data));
            $data = json_decode($raw_data, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('Course Builder: JSON decode error: ' . json_last_error_msg());
                return $this->get_default_data();
            }
        } else {
            $data = $raw_data;
        }
        
        error_log('Course Builder: Decoded data sections count: ' . (isset($data['sections']) ? count($data['sections']) : 0));
        
        // Проверяем версию и мигрируем при необходимости
        if (isset($data['version']) && $data['version'] !== self::DATA_VERSION) {
            error_log('Course Builder: Data version mismatch, migrating');
            $data = $this->migrate_data($data);
        }
        
        return $data;
    }
    
    /**
     * Сохранить данные builder для поста
     */
    public function save_builder_data($post_id, $data) {
        // Проверяем, что пост существует
        $post = get_post($post_id);
        if (!$post) {
            error_log('Course Builder: Post ' . $post_id . ' does not exist');
            return false;
        }
        
        // Проверяем права доступа
        if (!current_user_can('edit_post', $post_id)) {
            error_log('Course Builder: User does not have permission to edit post ' . $post_id);
            return false;
        }
        
        // Валидация данных
        $data = $this->validate_data($data);
        
        // Добавляем версию
        $data['version'] = self::DATA_VERSION;
        $data['updated_at'] = current_time('mysql');
        
        error_log('Course Builder: Validated data before save for post ' . $post_id);
        error_log('Course Builder: Sections count: ' . (isset($data['sections']) ? count($data['sections']) : 0));
        
        // Сохраняем как JSON
        $json_data = wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        if ($json_data === false) {
            error_log('Course Builder: JSON encode error: ' . json_last_error_msg());
            return false;
        }
        
        error_log('Course Builder: JSON data length: ' . strlen($json_data));
        
        // Используем update_post_meta, который обновит или создаст мета-поле
        $result = update_post_meta($post_id, self::META_KEY, $json_data);
        
        // Если update_post_meta вернул false, это может означать, что значение не изменилось
        // Но мы все равно проверим, что данные сохранились
        $saved = get_post_meta($post_id, self::META_KEY, true);
        
        if ($saved === false || $saved === '') {
            error_log('Course Builder: Data verification failed - meta is empty');
            // Пробуем добавить через add_post_meta с unique = false
            $add_result = add_post_meta($post_id, self::META_KEY, $json_data, true);
            if ($add_result === false) {
                // Если не получилось добавить (возможно, уже существует), обновляем принудительно
                delete_post_meta($post_id, self::META_KEY);
                $add_result = add_post_meta($post_id, self::META_KEY, $json_data, true);
            }
            $result = $add_result !== false;
        } else {
            // Проверяем, что сохраненные данные совпадают
            if ($saved !== $json_data) {
                error_log('Course Builder: Saved data does not match, retrying...');
                delete_post_meta($post_id, self::META_KEY);
                $result = add_post_meta($post_id, self::META_KEY, $json_data, true);
                $result = $result !== false;
            } else {
                $result = true;
            }
        }
        
        // Финальная проверка
        $final_check = get_post_meta($post_id, self::META_KEY, true);
        if ($final_check && $final_check === $json_data) {
            error_log('Course Builder: Data saved successfully for post ' . $post_id);
            return true;
        } else {
            error_log('Course Builder: Data save verification failed for post ' . $post_id);
            error_log('Course Builder: Expected length: ' . strlen($json_data) . ', Got length: ' . ($final_check ? strlen($final_check) : 0));
            return false;
        }
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
            error_log('Course Builder: Nonce verification failed');
            wp_send_json_error(array('message' => __('Ошибка безопасности', 'course-plugin')));
        }
        
        // Проверка прав
        if (!current_user_can('edit_posts')) {
            error_log('Course Builder: User does not have edit_posts capability');
            wp_send_json_error(array('message' => __('Недостаточно прав', 'course-plugin')));
        }
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $data = isset($_POST['data']) ? $_POST['data'] : array();
        
        if (!$post_id) {
            error_log('Course Builder: Post ID is missing');
            wp_send_json_error(array('message' => __('Не указан ID поста', 'course-plugin')));
        }
        
        error_log('Course Builder: Saving data for post ' . $post_id);
        error_log('Course Builder: Raw data: ' . print_r($data, true));
        
        // Декодируем JSON данные, если они пришли как строка
        if (is_string($data)) {
            $data = json_decode(stripslashes($data), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('Course Builder: JSON decode error: ' . json_last_error_msg());
                wp_send_json_error(array('message' => __('Ошибка декодирования данных', 'course-plugin')));
            }
        }
        
        error_log('Course Builder: Decoded data: ' . print_r($data, true));
        
        // Сохраняем данные
        $result = $this->save_builder_data($post_id, $data);
        
        error_log('Course Builder: Save result: ' . ($result ? 'success' : 'failed'));
        
        if ($result) {
            // Проверяем, что данные действительно сохранились
            $saved_data = $this->get_builder_data($post_id);
            error_log('Course Builder: Saved data retrieved: ' . print_r($saved_data, true));
            
            wp_send_json_success(array(
                'message' => __('Данные сохранены', 'course-plugin'),
                'data' => $saved_data
            ));
        } else {
            error_log('Course Builder: Failed to save data');
            wp_send_json_error(array('message' => __('Ошибка сохранения', 'course-plugin')));
        }
    }
    
    /**
     * AJAX загрузка данных builder
     */
    public function ajax_load_builder_data() {
        // Проверка nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'course_builder_load')) {
            error_log('Course Builder: Load nonce verification failed');
            wp_send_json_error(array('message' => __('Ошибка безопасности', 'course-plugin')));
        }
        
        // Проверка прав
        if (!current_user_can('edit_posts')) {
            error_log('Course Builder: User does not have edit_posts capability for load');
            wp_send_json_error(array('message' => __('Недостаточно прав', 'course-plugin')));
        }
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        
        if (!$post_id) {
            error_log('Course Builder: Post ID is missing for load');
            wp_send_json_error(array('message' => __('Не указан ID поста', 'course-plugin')));
        }
        
        error_log('Course Builder: Loading data for post ' . $post_id);
        
        // Получаем сырые данные из базы
        $raw_data = get_post_meta($post_id, self::META_KEY, true);
        error_log('Course Builder: Raw meta data: ' . ($raw_data ? 'exists (type: ' . gettype($raw_data) . ', length: ' . (is_string($raw_data) ? strlen($raw_data) : 'N/A') . ')' : 'missing'));
        
        $data = $this->get_builder_data($post_id);
        error_log('Course Builder: Processed data sections: ' . (isset($data['sections']) ? count($data['sections']) : 0));
        
        // Возвращаем данные напрямую, без дополнительного вложения
        wp_send_json_success($data);
    }
}
