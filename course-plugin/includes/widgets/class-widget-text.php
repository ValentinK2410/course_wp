<?php
/**
 * Виджет Текст
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once COURSE_PLUGIN_DIR . 'includes/class-course-builder-widgets.php';

class Course_Builder_Widget_Text extends Course_Builder_Widget {
    
    protected $type = 'text';
    
    public function get_name() {
        return __('Текст', 'course-plugin');
    }
    
    public function get_description() {
        return __('Добавить текстовый блок с форматированием', 'course-plugin');
    }
    
    public function get_icon() {
        return 'dashicons-text';
    }
    
    public function get_defaults() {
        return array(
            'content' => '',
            'text_align' => 'left',
            'font_size' => '',
            'text_color' => '',
            'css_class' => ''
        );
    }
    
    public function get_settings_fields() {
        return array(
            array(
                'name' => 'content',
                'label' => __('Содержимое', 'course-plugin'),
                'type' => 'textarea',
                'description' => __('Введите текст. Поддерживается HTML.', 'course-plugin')
            ),
            array(
                'name' => 'text_align',
                'label' => __('Выравнивание', 'course-plugin'),
                'type' => 'select',
                'options' => array(
                    'left' => __('Слева', 'course-plugin'),
                    'center' => __('По центру', 'course-plugin'),
                    'right' => __('Справа', 'course-plugin'),
                    'justify' => __('По ширине', 'course-plugin')
                )
            ),
            array(
                'name' => 'font_size',
                'label' => __('Размер шрифта (px)', 'course-plugin'),
                'type' => 'number',
                'min' => 10,
                'max' => 72,
                'description' => __('Оставьте пустым для размера по умолчанию', 'course-plugin')
            ),
            array(
                'name' => 'text_color',
                'label' => __('Цвет текста', 'course-plugin'),
                'type' => 'color'
            ),
            array(
                'name' => 'css_class',
                'label' => __('CSS класс', 'course-plugin'),
                'type' => 'text',
                'description' => __('Дополнительные CSS классы', 'course-plugin')
            )
        );
    }
    
    public function render($settings = null) {
        if ($settings === null) {
            $settings = $this->settings;
        }
        
        $content = isset($settings['content']) ? $settings['content'] : '';
        $text_align = isset($settings['text_align']) ? $settings['text_align'] : 'left';
        $font_size = isset($settings['font_size']) ? intval($settings['font_size']) : '';
        $text_color = isset($settings['text_color']) ? $settings['text_color'] : '';
        $css_class = isset($settings['css_class']) ? $settings['css_class'] : '';
        
        if (empty($content)) {
            return '';
        }
        
        $style = '';
        if ($text_align) {
            $style .= 'text-align: ' . esc_attr($text_align) . ';';
        }
        if ($font_size) {
            $style .= 'font-size: ' . esc_attr($font_size) . 'px;';
        }
        if ($text_color) {
            $style .= 'color: ' . esc_attr($text_color) . ';';
        }
        
        $class = 'course-builder-text';
        if ($css_class) {
            $class .= ' ' . esc_attr($css_class);
        }
        
        $output = '<div class="' . esc_attr($class) . '"';
        if ($style) {
            $output .= ' style="' . esc_attr($style) . '"';
        }
        $output .= '>';
        $output .= wpautop(do_shortcode($content));
        $output .= '</div>';
        
        return $output;
    }
}
