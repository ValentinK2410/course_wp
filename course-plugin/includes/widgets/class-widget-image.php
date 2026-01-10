<?php
/**
 * Виджет Изображение
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once COURSE_PLUGIN_DIR . 'includes/class-course-builder-widgets.php';

class Course_Builder_Widget_Image extends Course_Builder_Widget {
    
    protected $type = 'image';
    
    public function get_name() {
        return __('Изображение', 'course-plugin');
    }
    
    public function get_description() {
        return __('Добавить изображение', 'course-plugin');
    }
    
    public function get_icon() {
        return 'dashicons-format-image';
    }
    
    public function get_defaults() {
        return array(
            'image_id' => 0,
            'image_url' => '',
            'alt_text' => '',
            'link_url' => '',
            'link_target' => '_self',
            'image_align' => 'left',
            'image_size' => 'full',
            'css_class' => ''
        );
    }
    
    public function get_settings_fields() {
        return array(
            array(
                'name' => 'image_id',
                'label' => __('Изображение', 'course-plugin'),
                'type' => 'image',
                'description' => __('Выберите изображение из медиабиблиотеки', 'course-plugin')
            ),
            array(
                'name' => 'alt_text',
                'label' => __('Alt текст', 'course-plugin'),
                'type' => 'text',
                'description' => __('Альтернативный текст для изображения', 'course-plugin')
            ),
            array(
                'name' => 'link_url',
                'label' => __('Ссылка', 'course-plugin'),
                'type' => 'url',
                'description' => __('URL для ссылки на изображении (необязательно)', 'course-plugin')
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
                'name' => 'image_align',
                'label' => __('Выравнивание', 'course-plugin'),
                'type' => 'select',
                'options' => array(
                    'left' => __('Слева', 'course-plugin'),
                    'center' => __('По центру', 'course-plugin'),
                    'right' => __('Справа', 'course-plugin')
                )
            ),
            array(
                'name' => 'image_size',
                'label' => __('Размер', 'course-plugin'),
                'type' => 'select',
                'options' => array(
                    'thumbnail' => __('Миниатюра', 'course-plugin'),
                    'medium' => __('Средний', 'course-plugin'),
                    'large' => __('Большой', 'course-plugin'),
                    'full' => __('Полный размер', 'course-plugin')
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
        
        $image_id = isset($settings['image_id']) ? intval($settings['image_id']) : 0;
        $image_url = isset($settings['image_url']) ? $settings['image_url'] : '';
        $alt_text = isset($settings['alt_text']) ? $settings['alt_text'] : '';
        $link_url = isset($settings['link_url']) ? $settings['link_url'] : '';
        $link_target = isset($settings['link_target']) ? $settings['link_target'] : '_self';
        $image_align = isset($settings['image_align']) ? $settings['image_align'] : 'left';
        $image_size = isset($settings['image_size']) ? $settings['image_size'] : 'full';
        $css_class = isset($settings['css_class']) ? $settings['css_class'] : '';
        
        // Получаем URL изображения
        if ($image_id) {
            $image_url = wp_get_attachment_image_url($image_id, $image_size);
            if (empty($alt_text)) {
                $alt_text = get_post_meta($image_id, '_wp_attachment_image_alt', true);
            }
        }
        
        if (empty($image_url)) {
            return '';
        }
        
        $style = '';
        if ($image_align) {
            $style .= 'text-align: ' . esc_attr($image_align) . ';';
        }
        
        $class = 'course-builder-image';
        if ($css_class) {
            $class .= ' ' . esc_attr($css_class);
        }
        
        $output = '<div class="' . esc_attr($class) . '"';
        if ($style) {
            $output .= ' style="' . esc_attr($style) . '"';
        }
        $output .= '>';
        
        $img_tag = '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($alt_text) . '" class="course-builder-image-img">';
        
        if ($link_url) {
            $output .= '<a href="' . esc_url($link_url) . '" target="' . esc_attr($link_target) . '">';
            $output .= $img_tag;
            $output .= '</a>';
        } else {
            $output .= $img_tag;
        }
        
        $output .= '</div>';
        
        return $output;
    }
}
