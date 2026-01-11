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
            'embed_code' => '',
            'input_type' => 'url', // 'url' или 'embed'
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
                'name' => 'input_type',
                'label' => __('Тип ввода', 'course-plugin'),
                'type' => 'select',
                'options' => array(
                    'url' => __('Ссылка на видео', 'course-plugin'),
                    'embed' => __('Код встраивания', 'course-plugin')
                ),
                'description' => __('Выберите способ добавления видео', 'course-plugin')
            ),
            array(
                'name' => 'video_url',
                'label' => __('URL видео', 'course-plugin'),
                'type' => 'url',
                'description' => __('Вставьте ссылку на YouTube, Vimeo или прямой URL видео', 'course-plugin'),
                'condition' => array('input_type' => 'url')
            ),
            array(
                'name' => 'embed_code',
                'label' => __('Код встраивания', 'course-plugin'),
                'type' => 'textarea',
                'description' => __('Вставьте код iframe или embed для встраивания видео', 'course-plugin'),
                'condition' => array('input_type' => 'embed')
            ),
            array(
                'name' => 'width',
                'label' => __('Ширина', 'course-plugin'),
                'type' => 'text',
                'description' => __('Например: 100% или 800px (только для URL)', 'course-plugin'),
                'condition' => array('input_type' => 'url')
            ),
            array(
                'name' => 'height',
                'label' => __('Высота (px)', 'course-plugin'),
                'type' => 'number',
                'min' => 100,
                'max' => 2000,
                'description' => __('Высота видео в пикселях (только для URL)', 'course-plugin'),
                'condition' => array('input_type' => 'url')
            ),
            array(
                'name' => 'autoplay',
                'label' => __('Автозапуск', 'course-plugin'),
                'type' => 'checkbox',
                'condition' => array('input_type' => 'url')
            ),
            array(
                'name' => 'controls',
                'label' => __('Показывать контролы', 'course-plugin'),
                'type' => 'checkbox',
                'condition' => array('input_type' => 'url')
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
        
        $input_type = isset($settings['input_type']) ? $settings['input_type'] : 'url';
        $video_url = isset($settings['video_url']) ? trim($settings['video_url']) : '';
        $embed_code = isset($settings['embed_code']) ? trim($settings['embed_code']) : '';
        $width = isset($settings['width']) ? $settings['width'] : '100%';
        $height = isset($settings['height']) ? intval($settings['height']) : 500;
        $autoplay = isset($settings['autoplay']) ? (bool)$settings['autoplay'] : false;
        $controls = isset($settings['controls']) ? (bool)$settings['controls'] : true;
        $css_class = isset($settings['css_class']) ? $settings['css_class'] : '';
        
        $class = 'course-builder-video';
        if ($css_class) {
            $class .= ' ' . esc_attr($css_class);
        }
        
        $output = '<div class="' . esc_attr($class) . '">';
        
        // Если выбран код встраивания
        if ($input_type === 'embed' && !empty($embed_code)) {
            $output .= $this->render_embed_code($embed_code);
        } 
        // Если выбран URL
        elseif ($input_type === 'url' && !empty($video_url)) {
            // Определяем тип видео
            $video_type = $this->detect_video_type($video_url);
            
            if ($video_type === 'youtube') {
                $output .= $this->render_youtube($video_url, $width, $height, $autoplay, $controls);
            } elseif ($video_type === 'vimeo') {
                $output .= $this->render_vimeo($video_url, $width, $height, $autoplay, $controls);
            } else {
                $output .= $this->render_direct($video_url, $width, $height, $autoplay, $controls);
            }
        } else {
            // Если ничего не заполнено
            return '';
        }
        
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Рендеринг кода встраивания
     */
    private function render_embed_code($embed_code) {
        // Очищаем код от потенциально опасных элементов, но сохраняем iframe и embed
        $embed_code = wp_kses($embed_code, array(
            'iframe' => array(
                'src' => array(),
                'width' => array(),
                'height' => array(),
                'frameborder' => array(),
                'allow' => array(),
                'allowfullscreen' => array(),
                'style' => array(),
                'class' => array(),
                'id' => array(),
            ),
            'embed' => array(
                'src' => array(),
                'width' => array(),
                'height' => array(),
                'type' => array(),
            ),
        ));
        
        // Если код не содержит iframe или embed, пытаемся обернуть в iframe
        if (strpos($embed_code, '<iframe') === false && strpos($embed_code, '<embed') === false) {
            // Пытаемся извлечь URL из кода
            preg_match('/https?:\/\/[^\s<>"]+/', $embed_code, $matches);
            if (!empty($matches[0])) {
                $url = $matches[0];
                $embed_code = '<iframe src="' . esc_url($url) . '" width="100%" height="500" frameborder="0" allowfullscreen></iframe>';
            } else {
                return '<p>' . __('Неверный код встраивания', 'course-plugin') . '</p>';
            }
        }
        
        $output = '<div class="course-builder-video-wrapper course-builder-embed-wrapper">';
        $output .= $embed_code;
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Определить тип видео по URL
     */
    private function detect_video_type($url) {
        // Нормализуем URL
        $url = trim($url);
        if (empty($url)) {
            return 'direct';
        }
        
        // Проверяем YouTube
        if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $url, $matches)) {
            return 'youtube';
        }
        
        // Проверяем Vimeo
        if (preg_match('/vimeo\.com\/(\d+)/', $url, $matches)) {
            return 'vimeo';
        }
        
        // Проверяем другие видеохостинги
        if (strpos($url, 'rutube.ru') !== false) {
            return 'rutube';
        }
        
        // Прямой URL видео
        return 'direct';
    }
    
    /**
     * Рендеринг YouTube видео
     */
    private function render_youtube($url, $width, $height, $autoplay, $controls) {
        // Извлекаем ID видео из различных форматов URL
        $video_id = '';
        
        // Формат: https://www.youtube.com/watch?v=VIDEO_ID
        if (preg_match('/[?&]v=([^"&?\/\s]{11})/', $url, $matches)) {
            $video_id = $matches[1];
        }
        // Формат: https://youtu.be/VIDEO_ID
        elseif (preg_match('/youtu\.be\/([^"&?\/\s]{11})/', $url, $matches)) {
            $video_id = $matches[1];
        }
        // Формат: https://www.youtube.com/embed/VIDEO_ID
        elseif (preg_match('/youtube\.com\/embed\/([^"&?\/\s]{11})/', $url, $matches)) {
            $video_id = $matches[1];
        }
        // Формат: https://www.youtube.com/v/VIDEO_ID
        elseif (preg_match('/youtube\.com\/v\/([^"&?\/\s]{11})/', $url, $matches)) {
            $video_id = $matches[1];
        }
        
        if (empty($video_id)) {
            return '<p class="course-builder-error">' . __('Неверный URL YouTube. Проверьте формат ссылки.', 'course-plugin') . '</p>';
        }
        
        $embed_url = 'https://www.youtube.com/embed/' . esc_attr($video_id);
        
        $params = array();
        if ($autoplay) {
            $params[] = 'autoplay=1';
        }
        if (!$controls) {
            $params[] = 'controls=0';
        }
        // Добавляем параметры для лучшей совместимости
        $params[] = 'rel=0'; // Не показывать похожие видео
        $params[] = 'modestbranding=1'; // Минимальный брендинг
        
        if (!empty($params)) {
            $embed_url .= '?' . implode('&', $params);
        }
        
        $output = '<div class="course-builder-video-wrapper course-builder-youtube">';
        $output .= '<iframe width="' . esc_attr($width) . '" height="' . esc_attr($height) . '" src="' . esc_url($embed_url) . '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen style="max-width: 100%;"></iframe>';
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
