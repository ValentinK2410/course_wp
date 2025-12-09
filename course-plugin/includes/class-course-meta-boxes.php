<?php
/**
 * Класс для метабоксов курсов
 */

if (!defined('ABSPATH')) {
    exit;
}

class Course_Meta_Boxes {
    
    /**
     * Единственный экземпляр класса
     */
    private static $instance = null;
    
    /**
     * Получить экземпляр класса
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Конструктор
     */
    private function __construct() {
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_boxes'));
    }
    
    /**
     * Добавление метабоксов
     */
    public function add_meta_boxes() {
        add_meta_box(
            'course_details',
            __('Детали курса', 'course-plugin'),
            array($this, 'render_course_details_meta_box'),
            'course',
            'normal',
            'high'
        );
        
        add_meta_box(
            'course_duration',
            __('Продолжительность и стоимость', 'course-plugin'),
            array($this, 'render_course_duration_meta_box'),
            'course',
            'side',
            'default'
        );
    }
    
    /**
     * Рендеринг метабокса деталей курса
     */
    public function render_course_details_meta_box($post) {
        wp_nonce_field('course_meta_box', 'course_meta_box_nonce');
        
        $course_duration = get_post_meta($post->ID, '_course_duration', true);
        $course_price = get_post_meta($post->ID, '_course_price', true);
        $course_start_date = get_post_meta($post->ID, '_course_start_date', true);
        $course_end_date = get_post_meta($post->ID, '_course_end_date', true);
        $course_capacity = get_post_meta($post->ID, '_course_capacity', true);
        $course_enrolled = get_post_meta($post->ID, '_course_enrolled', true);
        
        ?>
        <table class="form-table">
            <tr>
                <th>
                    <label for="course_start_date"><?php _e('Дата начала', 'course-plugin'); ?></label>
                </th>
                <td>
                    <input type="date" id="course_start_date" name="course_start_date" value="<?php echo esc_attr($course_start_date); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th>
                    <label for="course_end_date"><?php _e('Дата окончания', 'course-plugin'); ?></label>
                </th>
                <td>
                    <input type="date" id="course_end_date" name="course_end_date" value="<?php echo esc_attr($course_end_date); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th>
                    <label for="course_capacity"><?php _e('Вместимость (количество мест)', 'course-plugin'); ?></label>
                </th>
                <td>
                    <input type="number" id="course_capacity" name="course_capacity" value="<?php echo esc_attr($course_capacity); ?>" class="small-text" min="1" />
                </td>
            </tr>
            <tr>
                <th>
                    <label for="course_enrolled"><?php _e('Записано студентов', 'course-plugin'); ?></label>
                </th>
                <td>
                    <input type="number" id="course_enrolled" name="course_enrolled" value="<?php echo esc_attr($course_enrolled); ?>" class="small-text" min="0" />
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Рендеринг метабокса продолжительности и стоимости
     */
    public function render_course_duration_meta_box($post) {
        $course_duration = get_post_meta($post->ID, '_course_duration', true);
        $course_price = get_post_meta($post->ID, '_course_price', true);
        $course_old_price = get_post_meta($post->ID, '_course_old_price', true);
        $course_rating = get_post_meta($post->ID, '_course_rating', true);
        $course_reviews_count = get_post_meta($post->ID, '_course_reviews_count', true);
        
        ?>
        <p>
            <label for="course_duration"><?php _e('Продолжительность (часов)', 'course-plugin'); ?></label>
            <input type="number" id="course_duration" name="course_duration" value="<?php echo esc_attr($course_duration); ?>" class="small-text" min="1" step="0.5" />
        </p>
        <p>
            <label for="course_price"><?php _e('Стоимость (руб.)', 'course-plugin'); ?></label>
            <input type="number" id="course_price" name="course_price" value="<?php echo esc_attr($course_price); ?>" class="small-text" min="0" step="0.01" />
        </p>
        <p>
            <label for="course_old_price"><?php _e('Старая цена (руб.)', 'course-plugin'); ?></label>
            <input type="number" id="course_old_price" name="course_old_price" value="<?php echo esc_attr($course_old_price); ?>" class="small-text" min="0" step="0.01" />
            <span class="description"><?php _e('Для отображения скидки', 'course-plugin'); ?></span>
        </p>
        <p>
            <label for="course_rating"><?php _e('Рейтинг (1-5)', 'course-plugin'); ?></label>
            <input type="number" id="course_rating" name="course_rating" value="<?php echo esc_attr($course_rating); ?>" class="small-text" min="0" max="5" step="0.1" />
        </p>
        <p>
            <label for="course_reviews_count"><?php _e('Количество отзывов', 'course-plugin'); ?></label>
            <input type="number" id="course_reviews_count" name="course_reviews_count" value="<?php echo esc_attr($course_reviews_count); ?>" class="small-text" min="0" />
        </p>
        <?php
    }
    
    /**
     * Сохранение метабоксов
     */
    public function save_meta_boxes($post_id) {
        // Проверка nonce
        if (!isset($_POST['course_meta_box_nonce']) || !wp_verify_nonce($_POST['course_meta_box_nonce'], 'course_meta_box')) {
            return;
        }
        
        // Проверка автсохранения
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Проверка прав
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Проверка типа поста
        if (get_post_type($post_id) !== 'course') {
            return;
        }
        
        // Сохранение полей
        $fields = array(
            'course_duration',
            'course_price',
            'course_old_price',
            'course_start_date',
            'course_end_date',
            'course_capacity',
            'course_enrolled',
            'course_rating',
            'course_reviews_count',
        );
        
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                $value = sanitize_text_field($_POST[$field]);
                update_post_meta($post_id, '_' . $field, $value);
            } else {
                delete_post_meta($post_id, '_' . $field);
            }
        }
    }
}

