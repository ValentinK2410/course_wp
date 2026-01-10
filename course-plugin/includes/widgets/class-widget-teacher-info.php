<?php
/**
 * Виджет Информация о преподавателе
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once COURSE_PLUGIN_DIR . 'includes/class-course-builder-widgets.php';

class Course_Builder_Widget_Teacher_Info extends Course_Builder_Widget {
    
    protected $type = 'teacher_info';
    
    public function get_name() {
        return __('Информация о преподавателе', 'course-plugin');
    }
    
    public function get_description() {
        return __('Отображение информации о преподавателе курса', 'course-plugin');
    }
    
    public function get_icon() {
        return 'dashicons-admin-users';
    }
    
    public function get_defaults() {
        return array(
            'teacher_id' => 0,
            'show_photo' => true,
            'show_name' => true,
            'show_position' => true,
            'show_description' => true,
            'show_specializations' => true,
            'layout' => 'horizontal',
            'css_class' => ''
        );
    }
    
    public function get_settings_fields() {
        return array(
            array(
                'name' => 'teacher_id',
                'label' => __('ID преподавателя', 'course-plugin'),
                'type' => 'number',
                'description' => __('Оставьте 0 для автоматического определения', 'course-plugin')
            ),
            array(
                'name' => 'show_photo',
                'label' => __('Показывать фото', 'course-plugin'),
                'type' => 'checkbox'
            ),
            array(
                'name' => 'show_name',
                'label' => __('Показывать имя', 'course-plugin'),
                'type' => 'checkbox'
            ),
            array(
                'name' => 'show_position',
                'label' => __('Показывать должность', 'course-plugin'),
                'type' => 'checkbox'
            ),
            array(
                'name' => 'show_description',
                'label' => __('Показывать описание', 'course-plugin'),
                'type' => 'checkbox'
            ),
            array(
                'name' => 'show_specializations',
                'label' => __('Показывать специализации', 'course-plugin'),
                'type' => 'checkbox'
            ),
            array(
                'name' => 'layout',
                'label' => __('Расположение', 'course-plugin'),
                'type' => 'select',
                'options' => array(
                    'horizontal' => __('Горизонтальное', 'course-plugin'),
                    'vertical' => __('Вертикальное', 'course-plugin')
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
        
        $teacher_id = isset($settings['teacher_id']) ? intval($settings['teacher_id']) : 0;
        
        // Если не указан ID, пытаемся получить преподавателя текущего курса
        if (!$teacher_id) {
            global $post;
            if ($post && $post->post_type === 'course') {
                $teachers = get_the_terms($post->ID, 'course_teacher');
                if ($teachers && !is_wp_error($teachers) && !empty($teachers)) {
                    $teacher_id = $teachers[0]->term_id;
                }
            }
        }
        
        if (!$teacher_id) {
            return '<p>' . __('Преподаватель не найден', 'course-plugin') . '</p>';
        }
        
        $teacher = get_term($teacher_id, 'course_teacher');
        if (!$teacher || is_wp_error($teacher)) {
            return '<p>' . __('Преподаватель не найден', 'course-plugin') . '</p>';
        }
        
        // Получаем метаполя преподавателя
        $teacher_photo = get_term_meta($teacher_id, 'teacher_photo', true);
        $teacher_position = get_term_meta($teacher_id, 'teacher_position', true);
        $teacher_description = get_term_meta($teacher_id, 'teacher_description', true);
        
        // Получаем специализации преподавателя
        $specializations = array();
        $courses = get_posts(array(
            'post_type' => 'course',
            'posts_per_page' => -1,
            'tax_query' => array(
                array(
                    'taxonomy' => 'course_teacher',
                    'field' => 'term_id',
                    'terms' => $teacher_id
                )
            )
        ));
        
        foreach ($courses as $course) {
            $course_specs = get_the_terms($course->ID, 'course_specialization');
            if ($course_specs && !is_wp_error($course_specs)) {
                foreach ($course_specs as $spec) {
                    if (!in_array($spec->name, $specializations)) {
                        $specializations[] = $spec->name;
                    }
                }
            }
        }
        
        $show_photo = isset($settings['show_photo']) ? (bool)$settings['show_photo'] : true;
        $show_name = isset($settings['show_name']) ? (bool)$settings['show_name'] : true;
        $show_position = isset($settings['show_position']) ? (bool)$settings['show_position'] : true;
        $show_description = isset($settings['show_description']) ? (bool)$settings['show_description'] : true;
        $show_specializations = isset($settings['show_specializations']) ? (bool)$settings['show_specializations'] : true;
        $layout = isset($settings['layout']) ? $settings['layout'] : 'horizontal';
        $css_class = isset($settings['css_class']) ? $settings['css_class'] : '';
        
        $class = 'course-builder-teacher-info course-builder-teacher-info-' . esc_attr($layout);
        if ($css_class) {
            $class .= ' ' . esc_attr($css_class);
        }
        
        ob_start();
        ?>
        <div class="<?php echo esc_attr($class); ?>">
            <?php if ($show_photo && $teacher_photo) : ?>
                <div class="teacher-photo">
                    <img src="<?php echo esc_url($teacher_photo); ?>" alt="<?php echo esc_attr($teacher->name); ?>">
                </div>
            <?php endif; ?>
            
            <div class="teacher-content">
                <?php if ($show_name) : ?>
                    <h3 class="teacher-name">
                        <a href="<?php echo esc_url(get_term_link($teacher_id, 'course_teacher')); ?>">
                            <?php echo esc_html($teacher->name); ?>
                        </a>
                    </h3>
                <?php endif; ?>
                
                <?php if ($show_position && $teacher_position) : ?>
                    <div class="teacher-position"><?php echo esc_html($teacher_position); ?></div>
                <?php endif; ?>
                
                <?php if ($show_description && $teacher_description) : ?>
                    <div class="teacher-description">
                        <?php echo wpautop($teacher_description); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($show_specializations && !empty($specializations)) : ?>
                    <div class="teacher-specializations">
                        <strong><?php _e('Специализации:', 'course-plugin'); ?></strong>
                        <?php echo esc_html(implode(', ', $specializations)); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
