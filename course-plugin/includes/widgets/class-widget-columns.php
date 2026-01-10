<?php
/**
 * Виджет Колонки
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once COURSE_PLUGIN_DIR . 'includes/class-course-builder-widgets.php';

class Course_Builder_Widget_Columns extends Course_Builder_Widget {
    
    protected $type = 'columns';
    
    public function get_name() {
        return __('Колонки', 'course-plugin');
    }
    
    public function get_description() {
        return __('Создать структуру колонок для размещения виджетов', 'course-plugin');
    }
    
    public function get_icon() {
        return 'dashicons-columns';
    }
    
    public function get_defaults() {
        return array(
            'columns_count' => 2,
            'columns_width' => array(50, 50),
            'gap' => '20px',
            'css_class' => ''
        );
    }
    
    public function get_settings_fields() {
        return array(
            array(
                'name' => 'columns_count',
                'label' => __('Количество колонок', 'course-plugin'),
                'type' => 'select',
                'options' => array(
                    '1' => '1',
                    '2' => '2',
                    '3' => '3',
                    '4' => '4'
                )
            ),
            array(
                'name' => 'gap',
                'label' => __('Отступ между колонками', 'course-plugin'),
                'type' => 'text',
                'description' => __('Например: 20px или 2rem', 'course-plugin')
            ),
            array(
                'name' => 'css_class',
                'label' => __('CSS класс', 'course-plugin'),
                'type' => 'text'
            )
        );
    }
    
    public function render($settings = null) {
        // Виджет колонок обрабатывается на уровне секции
        // Этот виджет используется только в редакторе для создания структуры
        return '';
    }
    
    public function render_editor($settings = null) {
        if ($settings === null) {
            $settings = $this->settings;
        }
        
        $columns_count = isset($settings['columns_count']) ? intval($settings['columns_count']) : 2;
        $gap = isset($settings['gap']) ? $settings['gap'] : '20px';
        $css_class = isset($settings['css_class']) ? $settings['css_class'] : '';
        
        $class = 'course-builder-columns course-builder-columns-' . $columns_count;
        if ($css_class) {
            $class .= ' ' . esc_attr($css_class);
        }
        
        $style = '';
        if ($gap) {
            $style .= 'gap: ' . esc_attr($gap) . ';';
        }
        
        $output = '<div class="' . esc_attr($class) . '"';
        if ($style) {
            $output .= ' style="' . esc_attr($style) . '"';
        }
        $output .= '>';
        $output .= '<p>' . sprintf(__('Колонки (%d)', 'course-plugin'), $columns_count) . '</p>';
        $output .= '</div>';
        
        return $output;
    }
}
