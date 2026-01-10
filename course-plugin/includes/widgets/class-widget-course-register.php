<?php
/**
 * Виджет Форма регистрации
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once COURSE_PLUGIN_DIR . 'includes/class-course-builder-widgets.php';

class Course_Builder_Widget_Course_Register extends Course_Builder_Widget {
    
    protected $type = 'course_register';
    
    public function get_name() {
        return __('Форма регистрации', 'course-plugin');
    }
    
    public function get_description() {
        return __('Форма регистрации пользователей на курс', 'course-plugin');
    }
    
    public function get_icon() {
        return 'dashicons-groups';
    }
    
    public function get_defaults() {
        return array(
            'form_title' => __('Регистрация', 'course-plugin'),
            'show_title' => true,
            'form_style' => 'default',
            'css_class' => ''
        );
    }
    
    public function get_settings_fields() {
        return array(
            array(
                'name' => 'form_title',
                'label' => __('Заголовок формы', 'course-plugin'),
                'type' => 'text'
            ),
            array(
                'name' => 'show_title',
                'label' => __('Показывать заголовок', 'course-plugin'),
                'type' => 'checkbox'
            ),
            array(
                'name' => 'form_style',
                'label' => __('Стиль формы', 'course-plugin'),
                'type' => 'select',
                'options' => array(
                    'default' => __('По умолчанию', 'course-plugin'),
                    'compact' => __('Компактный', 'course-plugin'),
                    'inline' => __('В одну строку', 'course-plugin')
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
        
        $form_title = isset($settings['form_title']) ? $settings['form_title'] : __('Регистрация', 'course-plugin');
        $show_title = isset($settings['show_title']) ? (bool)$settings['show_title'] : true;
        $form_style = isset($settings['form_style']) ? $settings['form_style'] : 'default';
        $css_class = isset($settings['css_class']) ? $settings['css_class'] : '';
        
        // Проверяем, есть ли класс Course_Registration
        if (!class_exists('Course_Registration')) {
            return '<p>' . __('Класс Course_Registration не найден', 'course-plugin') . '</p>';
        }
        
        $class = 'course-builder-course-register course-builder-course-register-' . esc_attr($form_style);
        if ($css_class) {
            $class .= ' ' . esc_attr($css_class);
        }
        
        ob_start();
        ?>
        <div class="<?php echo esc_attr($class); ?>">
            <?php if ($show_title && $form_title) : ?>
                <h3 class="course-register-title"><?php echo esc_html($form_title); ?></h3>
            <?php endif; ?>
            
            <?php
            // Используем шорткод формы регистрации, если он доступен
            if (shortcode_exists('course_register')) {
                echo do_shortcode('[course_register]');
            } else {
                // Или создаем простую форму
                $registration = Course_Registration::get_instance();
                if (method_exists($registration, 'render_form')) {
                    echo $registration->render_form();
                } else {
                    echo '<p>' . __('Форма регистрации недоступна', 'course-plugin') . '</p>';
                }
            }
            ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
