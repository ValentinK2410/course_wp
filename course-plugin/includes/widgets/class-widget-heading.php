<?php
/**
 * Виджет Заголовок
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once COURSE_PLUGIN_DIR . 'includes/class-course-builder-widgets.php';

class Course_Builder_Widget_Heading extends Course_Builder_Widget {
    
    protected $type = 'heading';
    
    public function get_name() {
        return __('Заголовок', 'course-plugin');
    }
    
    public function get_description() {
        return __('Добавить заголовок (H1-H6)', 'course-plugin');
    }
    
    public function get_icon() {
        return 'dashicons-heading';
    }
    
    public function get_defaults() {
        return array(
            'text' => '',
            'level' => 'h2',
            'text_align' => 'left',
            'text_color' => '',
            'css_class' => ''
        );
    }
    
    public function get_settings_fields() {
        return array(
            array(
                'name' => 'text',
                'label' => __('Текст заголовка', 'course-plugin'),
                'type' => 'text',
                'required' => true
            ),
            array(
                'name' => 'level',
                'label' => __('Уровень', 'course-plugin'),
                'type' => 'select',
                'options' => array(
                    'h1' => 'H1',
                    'h2' => 'H2',
                    'h3' => 'H3',
                    'h4' => 'H4',
                    'h5' => 'H5',
                    'h6' => 'H6'
                )
            ),
            array(
                'name' => 'text_align',
                'label' => __('Выравнивание', 'course-plugin'),
                'type' => 'select',
                'options' => array(
                    'left' => __('Слева', 'course-plugin'),
                    'center' => __('По центру', 'course-plugin'),
                    'right' => __('Справа', 'course-plugin')
                )
            ),
            array(
                'name' => 'text_color',
                'label' => __('Цвет текста', 'course-plugin'),
                'type' => 'color'
            ),
            array(
                'name' => 'css_class',
                'label' => __('CSS класс', 'course-plugin'),
                'type' => 'text'
            )
        );
    }
    
    public function render($settings = null) {
        if ($settings === null) {
            $settings = $this->settings;
        }
        
        $text = isset($settings['text']) ? $settings['text'] : '';
        $level = isset($settings['level']) ? $settings['level'] : 'h2';
        $text_align = isset($settings['text_align']) ? $settings['text_align'] : 'left';
        $text_color = isset($settings['text_color']) ? $settings['text_color'] : '';
        $css_class = isset($settings['css_class']) ? $settings['css_class'] : '';
        
        if (empty($text)) {
            return '';
        }
        
        // Валидация уровня заголовка
        if (!in_array($level, array('h1', 'h2', 'h3', 'h4', 'h5', 'h6'))) {
            $level = 'h2';
        }
        
        $style = '';
        if ($text_align) {
            $style .= 'text-align: ' . esc_attr($text_align) . ';';
        }
        if ($text_color) {
            $style .= 'color: ' . esc_attr($text_color) . ';';
        }
        
        $class = 'course-builder-heading';
        if ($css_class) {
            $class .= ' ' . esc_attr($css_class);
        }
        
        $output = '<' . esc_attr($level) . ' class="' . esc_attr($class) . '"';
        if ($style) {
            $output .= ' style="' . esc_attr($style) . '"';
        }
        $output .= '>';
        $output .= esc_html($text);
        $output .= '</' . esc_attr($level) . '>';
        
        return $output;
    }
}
