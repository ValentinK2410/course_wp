<?php
/**
 * Базовый класс для виджетов Course Builder
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Абстрактный класс виджета
 */
abstract class Course_Builder_Widget {
    
    /**
     * Тип виджета
     */
    protected $type;
    
    /**
     * Настройки виджета
     */
    protected $settings = array();
    
    /**
     * ID виджета
     */
    protected $widget_id;
    
    /**
     * Конструктор
     */
    public function __construct($widget_id = '', $settings = array()) {
        $this->widget_id = $widget_id;
        $this->settings = wp_parse_args($settings, $this->get_defaults());
    }
    
    /**
     * Получить название виджета
     */
    abstract public function get_name();
    
    /**
     * Получить описание виджета
     */
    abstract public function get_description();
    
    /**
     * Получить иконку виджета (dashicons класс)
     */
    abstract public function get_icon();
    
    /**
     * Получить настройки по умолчанию
     */
    abstract public function get_defaults();
    
    /**
     * Получить настройки виджета для админки
     */
    abstract public function get_settings_fields();
    
    /**
     * Рендеринг виджета на фронтенде
     */
    abstract public function render($settings = null);
    
    /**
     * Рендеринг виджета в редакторе
     */
    public function render_editor($settings = null) {
        // По умолчанию используем тот же рендеринг, что и на фронтенде
        return $this->render($settings);
    }
    
    /**
     * Получить тип виджета
     */
    public function get_type() {
        return $this->type;
    }
    
    /**
     * Получить настройки
     */
    public function get_settings() {
        return $this->settings;
    }
    
    /**
     * Получить ID виджета
     */
    public function get_widget_id() {
        return $this->widget_id;
    }
    
    /**
     * Установить настройки
     */
    public function set_settings($settings) {
        $this->settings = wp_parse_args($settings, $this->get_defaults());
    }
    
    /**
     * Получить значение настройки
     */
    protected function get_setting($key, $default = '') {
        return isset($this->settings[$key]) ? $this->settings[$key] : $default;
    }
    
    /**
     * Рендеринг поля настроек
     */
    protected function render_settings_field($field) {
        $field_id = 'widget_' . $this->widget_id . '_' . $field['name'];
        $field_name = 'widgets[' . $this->widget_id . '][settings][' . $field['name'] . ']';
        $field_value = $this->get_setting($field['name'], isset($field['default']) ? $field['default'] : '');
        
        echo '<div class="course-builder-field course-builder-field-' . esc_attr($field['type']) . '">';
        echo '<label for="' . esc_attr($field_id) . '">' . esc_html($field['label']) . '</label>';
        
        switch ($field['type']) {
            case 'text':
            case 'url':
            case 'email':
                echo '<input type="' . esc_attr($field['type']) . '" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '" value="' . esc_attr($field_value) . '" class="widefat">';
                break;
                
            case 'textarea':
                echo '<textarea id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '" class="widefat" rows="5">' . esc_textarea($field_value) . '</textarea>';
                break;
                
            case 'select':
                echo '<select id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '" class="widefat">';
                if (isset($field['options']) && is_array($field['options'])) {
                    foreach ($field['options'] as $value => $label) {
                        echo '<option value="' . esc_attr($value) . '" ' . selected($field_value, $value, false) . '>' . esc_html($label) . '</option>';
                    }
                }
                echo '</select>';
                break;
                
            case 'checkbox':
                echo '<input type="checkbox" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '" value="1" ' . checked($field_value, 1, false) . '>';
                break;
                
            case 'number':
                $min = isset($field['min']) ? $field['min'] : '';
                $max = isset($field['max']) ? $field['max'] : '';
                $step = isset($field['step']) ? $field['step'] : '1';
                echo '<input type="number" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '" value="' . esc_attr($field_value) . '" class="widefat" min="' . esc_attr($min) . '" max="' . esc_attr($max) . '" step="' . esc_attr($step) . '">';
                break;
                
            case 'color':
                echo '<input type="color" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '" value="' . esc_attr($field_value) . '">';
                break;
                
            case 'image':
                echo '<div class="course-builder-image-field">';
                echo '<input type="hidden" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '" value="' . esc_attr($field_value) . '">';
                echo '<button type="button" class="button course-builder-select-image" data-target="' . esc_attr($field_id) . '">' . __('Выбрать изображение', 'course-plugin') . '</button>';
                if ($field_value) {
                    echo '<div class="course-builder-image-preview">';
                    echo wp_get_attachment_image($field_value, 'thumbnail');
                    echo '</div>';
                }
                echo '</div>';
                break;
        }
        
        if (isset($field['description'])) {
            echo '<p class="description">' . esc_html($field['description']) . '</p>';
        }
        
        echo '</div>';
    }
}
