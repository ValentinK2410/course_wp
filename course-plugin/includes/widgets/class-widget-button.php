<?php
/**
 * Виджет Кнопка
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once COURSE_PLUGIN_DIR . 'includes/class-course-builder-widgets.php';

class Course_Builder_Widget_Button extends Course_Builder_Widget {
    
    protected $type = 'button';
    
    public function get_name() {
        return __('Кнопка', 'course-plugin');
    }
    
    public function get_description() {
        return __('Добавить кнопку со ссылкой', 'course-plugin');
    }
    
    public function get_icon() {
        return 'dashicons-admin-links';
    }
    
    public function get_defaults() {
        return array(
            'text' => __('Нажмите здесь', 'course-plugin'),
            'link_url' => '#',
            'link_target' => '_self',
            'button_style' => 'primary',
            'button_size' => 'medium',
            'text_align' => 'left',
            'css_class' => ''
        );
    }
    
    public function get_settings_fields() {
        return array(
            array(
                'name' => 'text',
                'label' => __('Текст кнопки', 'course-plugin'),
                'type' => 'text',
                'required' => true
            ),
            array(
                'name' => 'link_url',
                'label' => __('URL ссылки', 'course-plugin'),
                'type' => 'url',
                'required' => true
            ),
            array(
                'name' => 'link_target',
                'label' => __('Открывать ссылку', 'course-plugin'),
                'type' => 'select',
                'options' => array(
                    '_self' => __('В том же окне', 'course-plugin'),
                    '_blank' => __('В новом окне', 'course-plugin')
                )
            ),
            array(
                'name' => 'button_style',
                'label' => __('Стиль', 'course-plugin'),
                'type' => 'select',
                'options' => array(
                    'primary' => __('Основной', 'course-plugin'),
                    'secondary' => __('Вторичный', 'course-plugin'),
                    'success' => __('Успех', 'course-plugin'),
                    'danger' => __('Опасность', 'course-plugin'),
                    'outline' => __('Контур', 'course-plugin')
                )
            ),
            array(
                'name' => 'button_size',
                'label' => __('Размер', 'course-plugin'),
                'type' => 'select',
                'options' => array(
                    'small' => __('Маленький', 'course-plugin'),
                    'medium' => __('Средний', 'course-plugin'),
                    'large' => __('Большой', 'course-plugin')
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
        
        $text = isset($settings['text']) ? $settings['text'] : __('Нажмите здесь', 'course-plugin');
        $link_url = isset($settings['link_url']) ? $settings['link_url'] : '#';
        $link_target = isset($settings['link_target']) ? $settings['link_target'] : '_self';
        $button_style = isset($settings['button_style']) ? $settings['button_style'] : 'primary';
        $button_size = isset($settings['button_size']) ? $settings['button_size'] : 'medium';
        $text_align = isset($settings['text_align']) ? $settings['text_align'] : 'left';
        $css_class = isset($settings['css_class']) ? $settings['css_class'] : '';
        
        $style = '';
        if ($text_align) {
            $style .= 'text-align: ' . esc_attr($text_align) . ';';
        }
        
        $class = 'course-builder-button course-builder-button-' . esc_attr($button_style) . ' course-builder-button-' . esc_attr($button_size);
        if ($css_class) {
            $class .= ' ' . esc_attr($css_class);
        }
        
        $output = '<div class="course-builder-button-wrapper"';
        if ($style) {
            $output .= ' style="' . esc_attr($style) . '"';
        }
        $output .= '>';
        $output .= '<a href="' . esc_url($link_url) . '" target="' . esc_attr($link_target) . '" class="' . esc_attr($class) . '">';
        $output .= esc_html($text);
        $output .= '</a>';
        $output .= '</div>';
        
        return $output;
    }
}
