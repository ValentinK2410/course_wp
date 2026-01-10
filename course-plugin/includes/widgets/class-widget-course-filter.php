<?php
/**
 * Виджет Фильтр курсов
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once COURSE_PLUGIN_DIR . 'includes/class-course-builder-widgets.php';

class Course_Builder_Widget_Course_Filter extends Course_Builder_Widget {
    
    protected $type = 'course_filter';
    
    public function get_name() {
        return __('Фильтр курсов', 'course-plugin');
    }
    
    public function get_description() {
        return __('Фильтр для поиска и фильтрации курсов', 'course-plugin');
    }
    
    public function get_icon() {
        return 'dashicons-filter';
    }
    
    public function get_defaults() {
        return array(
            'show_teacher' => true,
            'show_level' => true,
            'show_specialization' => true,
            'show_topic' => true,
            'filter_style' => 'sidebar',
            'ajax_enabled' => true,
            'css_class' => ''
        );
    }
    
    public function get_settings_fields() {
        return array(
            array(
                'name' => 'show_teacher',
                'label' => __('Показывать фильтр по преподавателю', 'course-plugin'),
                'type' => 'checkbox'
            ),
            array(
                'name' => 'show_level',
                'label' => __('Показывать фильтр по уровню', 'course-plugin'),
                'type' => 'checkbox'
            ),
            array(
                'name' => 'show_specialization',
                'label' => __('Показывать фильтр по специализации', 'course-plugin'),
                'type' => 'checkbox'
            ),
            array(
                'name' => 'show_topic',
                'label' => __('Показывать фильтр по теме', 'course-plugin'),
                'type' => 'checkbox'
            ),
            array(
                'name' => 'filter_style',
                'label' => __('Стиль фильтра', 'course-plugin'),
                'type' => 'select',
                'options' => array(
                    'sidebar' => __('Боковая панель', 'course-plugin'),
                    'horizontal' => __('Горизонтальный', 'course-plugin'),
                    'dropdown' => __('Выпадающий список', 'course-plugin')
                )
            ),
            array(
                'name' => 'ajax_enabled',
                'label' => __('AJAX фильтрация', 'course-plugin'),
                'type' => 'checkbox',
                'description' => __('Обновлять результаты без перезагрузки страницы', 'course-plugin')
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
        
        $show_teacher = isset($settings['show_teacher']) ? (bool)$settings['show_teacher'] : true;
        $show_level = isset($settings['show_level']) ? (bool)$settings['show_level'] : true;
        $show_specialization = isset($settings['show_specialization']) ? (bool)$settings['show_specialization'] : true;
        $show_topic = isset($settings['show_topic']) ? (bool)$settings['show_topic'] : true;
        $filter_style = isset($settings['filter_style']) ? $settings['filter_style'] : 'sidebar';
        $ajax_enabled = isset($settings['ajax_enabled']) ? (bool)$settings['ajax_enabled'] : true;
        $css_class = isset($settings['css_class']) ? $settings['css_class'] : '';
        
        $class = 'course-builder-course-filter course-builder-course-filter-' . esc_attr($filter_style);
        if ($css_class) {
            $class .= ' ' . esc_attr($css_class);
        }
        
        $archive_url = get_post_type_archive_link('course');
        if (!$archive_url) {
            $archive_url = home_url('/courses/');
        }
        
        ob_start();
        ?>
        <div class="<?php echo esc_attr($class); ?>" data-ajax="<?php echo $ajax_enabled ? '1' : '0'; ?>">
            <form method="get" action="<?php echo esc_url($archive_url); ?>" class="course-filter-form">
                <?php if ($show_teacher) : ?>
                    <div class="filter-field filter-field-teacher">
                        <label><?php _e('Преподаватель', 'course-plugin'); ?></label>
                        <?php
                        wp_dropdown_categories(array(
                            'show_option_all' => __('Все', 'course-plugin'),
                            'taxonomy' => 'course_teacher',
                            'name' => 'teacher',
                            'selected' => isset($_GET['teacher']) ? $_GET['teacher'] : '',
                            'value_field' => 'slug',
                            'hide_empty' => false,
                            'class' => 'filter-select',
                        ));
                        ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($show_level) : ?>
                    <div class="filter-field filter-field-level">
                        <label><?php _e('Уровень', 'course-plugin'); ?></label>
                        <div class="filter-checkboxes">
                            <?php
                            $levels = get_terms(array(
                                'taxonomy' => 'course_level',
                                'hide_empty' => false,
                            ));
                            
                            if ($levels && !is_wp_error($levels)) {
                                $selected_levels = isset($_GET['level']) ? (array)$_GET['level'] : array();
                                foreach ($levels as $level) {
                                    $checked = in_array($level->slug, $selected_levels) ? 'checked' : '';
                                    ?>
                                    <label class="filter-checkbox-label">
                                        <input type="checkbox" name="level[]" value="<?php echo esc_attr($level->slug); ?>" <?php echo $checked; ?>>
                                        <span><?php echo esc_html($level->name); ?></span>
                                    </label>
                                    <?php
                                }
                            }
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($show_specialization) : ?>
                    <div class="filter-field filter-field-specialization">
                        <label><?php _e('Программа', 'course-plugin'); ?></label>
                        <div class="filter-checkboxes">
                            <?php
                            $specializations = get_terms(array(
                                'taxonomy' => 'course_specialization',
                                'hide_empty' => false,
                            ));
                            
                            if ($specializations && !is_wp_error($specializations)) {
                                $selected_specs = isset($_GET['specialization']) ? (array)$_GET['specialization'] : array();
                                foreach ($specializations as $spec) {
                                    $checked = in_array($spec->slug, $selected_specs) ? 'checked' : '';
                                    ?>
                                    <label class="filter-checkbox-label">
                                        <input type="checkbox" name="specialization[]" value="<?php echo esc_attr($spec->slug); ?>" <?php echo $checked; ?>>
                                        <span><?php echo esc_html($spec->name); ?></span>
                                    </label>
                                    <?php
                                }
                            }
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($show_topic) : ?>
                    <div class="filter-field filter-field-topic">
                        <label><?php _e('Тема', 'course-plugin'); ?></label>
                        <div class="filter-checkboxes">
                            <?php
                            $topics = get_terms(array(
                                'taxonomy' => 'course_topic',
                                'hide_empty' => false,
                            ));
                            
                            if ($topics && !is_wp_error($topics)) {
                                $selected_topics = isset($_GET['topic']) ? (array)$_GET['topic'] : array();
                                foreach ($topics as $topic) {
                                    $checked = in_array($topic->slug, $selected_topics) ? 'checked' : '';
                                    ?>
                                    <label class="filter-checkbox-label">
                                        <input type="checkbox" name="topic[]" value="<?php echo esc_attr($topic->slug); ?>" <?php echo $checked; ?>>
                                        <span><?php echo esc_html($topic->name); ?></span>
                                    </label>
                                    <?php
                                }
                            }
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="filter-actions">
                    <button type="submit" class="filter-submit-btn"><?php _e('Применить', 'course-plugin'); ?></button>
                    <a href="<?php echo esc_url($archive_url); ?>" class="filter-reset-btn"><?php _e('Сбросить', 'course-plugin'); ?></a>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
}
