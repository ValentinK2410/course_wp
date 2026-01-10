<?php
/**
 * Виджет Видео
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once COURSE_PLUGIN_DIR . 'includes/class-course-builder-widgets.php';

class Course_Builder_Widget_Video extends Course_Builder_Widget {
    
    protected $type = 'video';
    
    public function get_name() {
        return __('Видео', 'course-plugin');
    }
    
    public function get_description() {
        return __('Добавить видео (YouTube, Vimeo или прямой URL)', 'course-plugin');
    }
    
    public function get_icon() {
        return 'dashicons-video-alt3';
    }
    
    public function get_defaults() {
        return array(
            'video_url' => '',
            'video_type' => 'youtube',
            'width' => '100%',
            'height' => '500',
            'autoplay' => false,
            'controls' => true,
            'css_class' => ''
        );
    }
    
    public function get_settings_fields() {
        return array(
            array(
                'name' => 'video_url',
                'label' => __('URL видео', 'course-plugin'),
                'type' => 'url',
                'required' => true,
                'description' => __('Вставьте ссылку на YouTube, Vimeo или прямой URL видео', 'course-plugin')
            ),
            array(
                'name' => 'width',
                'label' => __('Ширина', 'course-plugin'),
                'type' => 'text',
                'description' => __('Например: 100% или 800px', 'course-plugin')
            ),
            array(
                'name' => 'height',
                'label' => __('Высота (px)', 'course-plugin'),
                'type' => 'number',
                'min' => 100,
                'max' => 2000,
                'description' => __('Высота видео в пикселях', 'course-plugin')
            ),
            array(
                'name' => 'autoplay',
                'label' => __('Автозапуск', 'course-plugin'),
                'type' => 'checkbox'
            ),
            array(
                'name' => 'controls',
                'label' => __('Показывать контролы', 'course-plugin'),
                'type' => 'checkbox'
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
        
        $video_url = isset($settings['video_url']) ? $settings['video_url'] : '';
        $width = isset($settings['width']) ? $settings['width'] : '100%';
        $height = isset($settings['height']) ? intval($settings['height']) : 500;
        $autoplay = isset($settings['autoplay']) ? (bool)$settings['autoplay'] : false;
        $controls = isset($settings['controls']) ? (bool)$settings['controls'] : true;
        $css_class = isset($settings['css_class']) ? $settings['css_class'] : '';
        
        if (empty($video_url)) {
            return '';
        }
        
        $class = 'course-builder-video';
        if ($css_class) {
            $class .= ' ' . esc_attr($css_class);
        }
        
        $output = '<div class="' . esc_attr($class) . '">';
        
        // Определяем тип видео
        $video_type = $this->detect_video_type($video_url);
        
        if ($video_type === 'youtube') {
            $output .= $this->render_youtube($video_url, $width, $height, $autoplay, $controls);
        } elseif ($video_type === 'vimeo') {
            $output .= $this->render_vimeo($video_url, $width, $height, $autoplay, $controls);
        } else {
            $output .= $this->render_direct($video_url, $width, $height, $autoplay, $controls);
        }
        
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Определить тип видео по URL
     */
    private function detect_video_type($url) {
        if (strpos($url, 'youtube.com') !== false || strpos($url, 'youtu.be') !== false) {
            return 'youtube';
        } elseif (strpos($url, 'vimeo.com') !== false) {
            return 'vimeo';
        } else {
            return 'direct';
        }
    }
    
    /**
     * Рендеринг YouTube видео
     */
    private function render_youtube($url, $width, $height, $autoplay, $controls) {
        // Извлекаем ID видео
        preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $url, $matches);
        $video_id = isset($matches[1]) ? $matches[1] : '';
        
        if (!$video_id) {
            return '<p>' . __('Неверный URL YouTube', 'course-plugin') . '</p>';
        }
        
        $embed_url = 'https://www.youtube.com/embed/' . $video_id;
        
        $params = array();
        if ($autoplay) {
            $params[] = 'autoplay=1';
        }
        if (!$controls) {
            $params[] = 'controls=0';
        }
        
        if (!empty($params)) {
            $embed_url .= '?' . implode('&', $params);
        }
        
        $output = '<div class="course-builder-video-wrapper">';
        $output .= '<iframe width="' . esc_attr($width) . '" height="' . esc_attr($height) . '" src="' . esc_url($embed_url) . '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Рендеринг Vimeo видео
     */
    private function render_vimeo($url, $width, $height, $autoplay, $controls) {
        // Извлекаем ID видео
        preg_match('/vimeo.com\/(\d+)/', $url, $matches);
        $video_id = isset($matches[1]) ? $matches[1] : '';
        
        if (!$video_id) {
            return '<p>' . __('Неверный URL Vimeo', 'course-plugin') . '</p>';
        }
        
        $embed_url = 'https://player.vimeo.com/video/' . $video_id;
        
        $params = array();
        if ($autoplay) {
            $params[] = 'autoplay=1';
        }
        if (!$controls) {
            $params[] = 'controls=0';
        }
        
        if (!empty($params)) {
            $embed_url .= '?' . implode('&', $params);
        }
        
        $output = '<div class="course-builder-video-wrapper">';
        $output .= '<iframe width="' . esc_attr($width) . '" height="' . esc_attr($height) . '" src="' . esc_url($embed_url) . '" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>';
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Рендеринг прямого видео URL
     */
    private function render_direct($url, $width, $height, $autoplay, $controls) {
        $autoplay_attr = $autoplay ? 'autoplay' : '';
        $controls_attr = $controls ? 'controls' : '';
        
        $output = '<div class="course-builder-video-wrapper">';
        $output .= '<video width="' . esc_attr($width) . '" height="' . esc_attr($height) . '" ' . $autoplay_attr . ' ' . $controls_attr . '>';
        $output .= '<source src="' . esc_url($url) . '" type="video/mp4">';
        $output .= __('Ваш браузер не поддерживает видео.', 'course-plugin');
        $output .= '</video>';
        $output .= '</div>';
        
        return $output;
    }
}
