<?php
/**
 * Класс для метабоксов программ
 * 
 * Метабоксы - это дополнительные блоки с полями на странице редактирования программы
 * Этот класс добавляет метабоксы с полями для программ:
 * 1. "Детали программы" - стоимость, длительность, количество курсов, даты
 * 2. "Связанные курсы" - выбор курсов, входящих в программу
 * 3. "Сертификат" - информация о сертификате
 * 
 * @copyright Copyright (c) 2024 Кузьменко Валентин (Valentink2410)
 * @author Кузьменко Валентин (Valentink2410)
 */

// Проверка безопасности: если файл вызывается напрямую, прекращаем выполнение
if (!defined('ABSPATH')) {
    exit;
}

class Program_Meta_Boxes {
    
    /**
     * Единственный экземпляр класса (Singleton)
     */
    private static $instance = null;
    
    /**
     * Получить экземпляр класса
     * 
     * @return Program_Meta_Boxes Экземпляр класса
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Конструктор класса
     * Регистрирует хуки для добавления и сохранения метабоксов
     */
    private function __construct() {
        // Регистрируем метод для добавления метабоксов
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        
        // Регистрируем метод для сохранения данных метабоксов
        add_action('save_post', array($this, 'save_meta_boxes'));
        
        // Подключаем скрипты для выбора курсов
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * Подключение скриптов для админ-панели
     */
    public function enqueue_admin_scripts($hook) {
        global $post_type;
        
        if ($post_type === 'program' && ($hook === 'post.php' || $hook === 'post-new.php')) {
            wp_enqueue_script('jquery-ui-sortable');
        }
    }
    
    /**
     * Добавление метабоксов на страницу редактирования программы
     */
    public function add_meta_boxes() {
        // Метабокс "Детали программы"
        add_meta_box(
            'program_details',
            __('Детали программы', 'course-plugin'),
            array($this, 'render_program_details_meta_box'),
            'program',
            'normal',
            'high'
        );
        
        // Метабокс "Связанные курсы"
        add_meta_box(
            'program_related_courses',
            __('Связанные курсы', 'course-plugin'),
            array($this, 'render_program_related_courses_meta_box'),
            'program',
            'normal',
            'default'
        );
        
        // Метабокс "Сертификат"
        add_meta_box(
            'program_certificate',
            __('Информация о сертификате', 'course-plugin'),
            array($this, 'render_program_certificate_meta_box'),
            'program',
            'side',
            'default'
        );
    }
    
    /**
     * Рендеринг метабокса "Детали программы"
     */
    public function render_program_details_meta_box($post) {
        wp_nonce_field('program_meta_box', 'program_meta_box_nonce');
        
        $program_price = get_post_meta($post->ID, '_program_price', true);
        $program_old_price = get_post_meta($post->ID, '_program_old_price', true);
        $program_duration = get_post_meta($post->ID, '_program_duration', true);
        $program_courses_count = get_post_meta($post->ID, '_program_courses_count', true);
        $program_start_date = get_post_meta($post->ID, '_program_start_date', true);
        $program_end_date = get_post_meta($post->ID, '_program_end_date', true);
        
        ?>
        <table class="form-table">
            <tr>
                <th>
                    <label for="program_price"><?php _e('Стоимость программы (руб.)', 'course-plugin'); ?></label>
                </th>
                <td>
                    <input type="number" id="program_price" name="program_price" value="<?php echo esc_attr($program_price); ?>" class="regular-text" min="0" step="0.01" />
                </td>
            </tr>
            
            <tr>
                <th>
                    <label for="program_old_price"><?php _e('Старая стоимость (руб.)', 'course-plugin'); ?></label>
                </th>
                <td>
                    <input type="number" id="program_old_price" name="program_old_price" value="<?php echo esc_attr($program_old_price); ?>" class="regular-text" min="0" step="0.01" />
                    <span class="description"><?php _e('Для отображения скидки', 'course-plugin'); ?></span>
                </td>
            </tr>
            
            <tr>
                <th>
                    <label for="program_duration"><?php _e('Длительность программы', 'course-plugin'); ?></label>
                </th>
                <td>
                    <input type="text" id="program_duration" name="program_duration" value="<?php echo esc_attr($program_duration); ?>" class="regular-text" placeholder="<?php _e('Например: 6 месяцев', 'course-plugin'); ?>" />
                    <span class="description"><?php _e('Например: 6 месяцев, 1 год, 120 часов', 'course-plugin'); ?></span>
                </td>
            </tr>
            
            <tr>
                <th>
                    <label for="program_courses_count"><?php _e('Количество курсов в программе', 'course-plugin'); ?></label>
                </th>
                <td>
                    <input type="number" id="program_courses_count" name="program_courses_count" value="<?php echo esc_attr($program_courses_count); ?>" class="small-text" min="0" />
                    <span class="description"><?php _e('Можно указать вручную или будет автоматически подсчитано из связанных курсов', 'course-plugin'); ?></span>
                </td>
            </tr>
            
            <tr>
                <th>
                    <label for="program_start_date"><?php _e('Дата начала программы', 'course-plugin'); ?></label>
                </th>
                <td>
                    <input type="date" id="program_start_date" name="program_start_date" value="<?php echo esc_attr($program_start_date); ?>" class="regular-text" />
                </td>
            </tr>
            
            <tr>
                <th>
                    <label for="program_end_date"><?php _e('Дата окончания программы', 'course-plugin'); ?></label>
                </th>
                <td>
                    <input type="date" id="program_end_date" name="program_end_date" value="<?php echo esc_attr($program_end_date); ?>" class="regular-text" />
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Рендеринг метабокса "Связанные курсы"
     */
    public function render_program_related_courses_meta_box($post) {
        $related_courses = get_post_meta($post->ID, '_program_related_courses', true);
        
        if (!is_array($related_courses)) {
            $related_courses = array();
        }
        
        // Получаем все опубликованные курсы
        $courses = get_posts(array(
            'post_type' => 'course',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ));
        
        ?>
        <p>
            <label for="program_related_courses"><?php _e('Выберите курсы, входящие в программу:', 'course-plugin'); ?></label>
        </p>
        <div style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px;">
            <?php if (!empty($courses)) : ?>
                <?php foreach ($courses as $course) : ?>
                    <label style="display: block; margin-bottom: 8px;">
                        <input type="checkbox" name="program_related_courses[]" value="<?php echo esc_attr($course->ID); ?>" <?php checked(in_array($course->ID, $related_courses)); ?> />
                        <?php echo esc_html($course->post_title); ?>
                    </label>
                <?php endforeach; ?>
            <?php else : ?>
                <p><?php _e('Курсы не найдены. Сначала создайте курсы.', 'course-plugin'); ?></p>
            <?php endif; ?>
        </div>
        <p class="description">
            <?php _e('Выберите курсы, которые входят в эту программу. Количество курсов будет автоматически обновлено при сохранении.', 'course-plugin'); ?>
        </p>
        <?php
    }
    
    /**
     * Рендеринг метабокса "Сертификат"
     */
    public function render_program_certificate_meta_box($post) {
        $program_certificate = get_post_meta($post->ID, '_program_certificate', true);
        
        ?>
        <p>
            <label for="program_certificate"><?php _e('Информация о сертификате:', 'course-plugin'); ?></label>
        </p>
        <textarea id="program_certificate" name="program_certificate" rows="5" class="large-text"><?php echo esc_textarea($program_certificate); ?></textarea>
        <p class="description">
            <?php _e('Опишите, какой сертификат выдается по окончании программы.', 'course-plugin'); ?>
        </p>
        <?php
    }
    
    /**
     * Сохранение данных метабоксов
     */
    public function save_meta_boxes($post_id) {
        // Проверка безопасности
        if (!isset($_POST['program_meta_box_nonce']) || !wp_verify_nonce($_POST['program_meta_box_nonce'], 'program_meta_box')) {
            return;
        }
        
        // Проверка автозагрузки
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Проверка прав доступа
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Проверка типа поста
        if (get_post_type($post_id) !== 'program') {
            return;
        }
        
        // Сохранение стоимости
        if (isset($_POST['program_price'])) {
            update_post_meta($post_id, '_program_price', sanitize_text_field($_POST['program_price']));
        }
        
        // Сохранение старой стоимости
        if (isset($_POST['program_old_price'])) {
            update_post_meta($post_id, '_program_old_price', sanitize_text_field($_POST['program_old_price']));
        }
        
        // Сохранение длительности
        if (isset($_POST['program_duration'])) {
            update_post_meta($post_id, '_program_duration', sanitize_text_field($_POST['program_duration']));
        }
        
        // Сохранение количества курсов
        if (isset($_POST['program_courses_count'])) {
            update_post_meta($post_id, '_program_courses_count', intval($_POST['program_courses_count']));
        }
        
        // Сохранение даты начала
        if (isset($_POST['program_start_date'])) {
            update_post_meta($post_id, '_program_start_date', sanitize_text_field($_POST['program_start_date']));
        }
        
        // Сохранение даты окончания
        if (isset($_POST['program_end_date'])) {
            update_post_meta($post_id, '_program_end_date', sanitize_text_field($_POST['program_end_date']));
        }
        
        // Сохранение связанных курсов
        if (isset($_POST['program_related_courses']) && is_array($_POST['program_related_courses'])) {
            $related_courses = array_map('intval', $_POST['program_related_courses']);
            update_post_meta($post_id, '_program_related_courses', $related_courses);
            
            // Автоматически обновляем количество курсов, если оно не указано вручную
            if (empty($_POST['program_courses_count']) || !isset($_POST['program_courses_count'])) {
                update_post_meta($post_id, '_program_courses_count', count($related_courses));
            }
        } else {
            // Если курсы не выбраны, очищаем мета-поле
            delete_post_meta($post_id, '_program_related_courses');
            update_post_meta($post_id, '_program_courses_count', 0);
        }
        
        // Сохранение информации о сертификате
        if (isset($_POST['program_certificate'])) {
            update_post_meta($post_id, '_program_certificate', sanitize_textarea_field($_POST['program_certificate']));
        }
    }
}
