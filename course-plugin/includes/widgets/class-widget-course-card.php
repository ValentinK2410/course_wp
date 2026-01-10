<?php
/**
 * Виджет Карточка курса
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once COURSE_PLUGIN_DIR . 'includes/class-course-builder-widgets.php';

class Course_Builder_Widget_Course_Card extends Course_Builder_Widget {
    
    protected $type = 'course_card';
    
    public function get_name() {
        return __('Карточка курса', 'course-plugin');
    }
    
    public function get_description() {
        return __('Отображение карточки курса с информацией', 'course-plugin');
    }
    
    public function get_icon() {
        return 'dashicons-book-alt';
    }
    
    public function get_defaults() {
        return array(
            'course_id' => 0,
            'show_image' => true,
            'show_price' => true,
            'show_rating' => true,
            'show_teacher' => true,
            'show_excerpt' => true,
            'card_style' => 'default',
            'css_class' => ''
        );
    }
    
    public function get_settings_fields() {
        return array(
            array(
                'name' => 'course_id',
                'label' => __('ID курса', 'course-plugin'),
                'type' => 'number',
                'description' => __('Оставьте 0 для текущего курса', 'course-plugin')
            ),
            array(
                'name' => 'show_image',
                'label' => __('Показывать изображение', 'course-plugin'),
                'type' => 'checkbox'
            ),
            array(
                'name' => 'show_price',
                'label' => __('Показывать цену', 'course-plugin'),
                'type' => 'checkbox'
            ),
            array(
                'name' => 'show_rating',
                'label' => __('Показывать рейтинг', 'course-plugin'),
                'type' => 'checkbox'
            ),
            array(
                'name' => 'show_teacher',
                'label' => __('Показывать преподавателя', 'course-plugin'),
                'type' => 'checkbox'
            ),
            array(
                'name' => 'show_excerpt',
                'label' => __('Показывать описание', 'course-plugin'),
                'type' => 'checkbox'
            ),
            array(
                'name' => 'card_style',
                'label' => __('Стиль карточки', 'course-plugin'),
                'type' => 'select',
                'options' => array(
                    'default' => __('По умолчанию', 'course-plugin'),
                    'compact' => __('Компактный', 'course-plugin'),
                    'detailed' => __('Подробный', 'course-plugin')
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
        
        $course_id = isset($settings['course_id']) ? intval($settings['course_id']) : 0;
        
        // Если не указан ID, пытаемся получить текущий курс
        if (!$course_id) {
            global $post;
            if ($post && $post->post_type === 'course') {
                $course_id = $post->ID;
            } else {
                return '<p>' . __('Курс не найден', 'course-plugin') . '</p>';
            }
        }
        
        $course = get_post($course_id);
        if (!$course || $course->post_type !== 'course') {
            return '<p>' . __('Курс не найден', 'course-plugin') . '</p>';
        }
        
        // Получаем метаполя курса
        $price = get_post_meta($course_id, '_course_price', true);
        $old_price = get_post_meta($course_id, '_course_old_price', true);
        $rating = get_post_meta($course_id, '_course_rating', true) ?: 0;
        $reviews_count = get_post_meta($course_id, '_course_reviews_count', true) ?: 0;
        $start_date = get_post_meta($course_id, '_course_start_date', true);
        
        // Получаем преподавателя
        $teachers = get_the_terms($course_id, 'course_teacher');
        $teacher_name = '';
        if ($teachers && !is_wp_error($teachers) && !empty($teachers)) {
            $teacher_name = $teachers[0]->name;
        }
        
        // Вычисляем скидку
        $discount = 0;
        if ($old_price && $price && $price < $old_price) {
            $discount = round((($old_price - $price) / $old_price) * 100);
        }
        
        $show_image = isset($settings['show_image']) ? (bool)$settings['show_image'] : true;
        $show_price = isset($settings['show_price']) ? (bool)$settings['show_price'] : true;
        $show_rating = isset($settings['show_rating']) ? (bool)$settings['show_rating'] : true;
        $show_teacher = isset($settings['show_teacher']) ? (bool)$settings['show_teacher'] : true;
        $show_excerpt = isset($settings['show_excerpt']) ? (bool)$settings['show_excerpt'] : true;
        $card_style = isset($settings['card_style']) ? $settings['card_style'] : 'default';
        $css_class = isset($settings['css_class']) ? $settings['css_class'] : '';
        
        $class = 'course-builder-course-card course-builder-course-card-' . esc_attr($card_style);
        if ($css_class) {
            $class .= ' ' . esc_attr($css_class);
        }
        
        ob_start();
        ?>
        <div class="<?php echo esc_attr($class); ?>">
            <?php if ($show_image && has_post_thumbnail($course_id)) : ?>
                <div class="course-card-thumbnail">
                    <a href="<?php echo get_permalink($course_id); ?>">
                        <?php echo get_the_post_thumbnail($course_id, 'medium_large'); ?>
                    </a>
                    <?php if ($discount > 0) : ?>
                        <span class="course-card-discount">-<?php echo $discount; ?>%</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="course-card-content">
                <h3 class="course-card-title">
                    <a href="<?php echo get_permalink($course_id); ?>"><?php echo get_the_title($course_id); ?></a>
                </h3>
                
                <?php if ($show_excerpt) : ?>
                    <div class="course-card-excerpt">
                        <?php echo wp_trim_words(get_the_excerpt($course_id), 20); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($show_teacher && $teacher_name) : ?>
                    <div class="course-card-teacher">
                        <span class="dashicons dashicons-admin-users"></span>
                        <?php echo esc_html($teacher_name); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($show_rating && $rating > 0) : ?>
                    <div class="course-card-rating">
                        <div class="stars-rating">
                            <?php
                            for ($i = 1; $i <= 5; $i++) {
                                $star_class = $i <= $rating ? 'star-filled' : 'star-empty';
                                echo '<span class="star ' . $star_class . '">★</span>';
                            }
                            ?>
                        </div>
                        <?php if ($reviews_count > 0) : ?>
                            <span class="reviews-count">(<?php echo $reviews_count; ?>)</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($show_price) : ?>
                    <div class="course-card-price">
                        <?php if ($old_price && $price < $old_price) : ?>
                            <span class="course-card-old-price"><?php echo number_format($old_price, 2, ',', ' '); ?> Р</span>
                        <?php endif; ?>
                        <span class="course-card-current-price"><?php echo $price ? number_format($price, 2, ',', ' ') : '0,00'; ?> Р</span>
                    </div>
                <?php endif; ?>
                
                <?php if ($start_date) : ?>
                    <div class="course-card-start-date">
                        <?php _e('Начало:', 'course-plugin'); ?> <?php echo date_i18n('d.m.Y', strtotime($start_date)); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
