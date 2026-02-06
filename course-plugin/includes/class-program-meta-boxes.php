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
        
        // Метабокс "Настройка текстов страницы"
        add_meta_box(
            'program_page_texts',
            __('Настройка текстов страницы', 'course-plugin'),
            array($this, 'render_program_page_texts_meta_box'),
            'program',
            'normal',
            'default'
        );
        
        // Метабокс "Ссылка для записи"
        add_meta_box(
            'program_enroll_url',
            __('Ссылка для записи на программу', 'course-plugin'),
            array($this, 'render_program_enroll_url_meta_box'),
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
            
            <tr>
                <th>
                    <label for="program_tag"><?php _e('Тег программы', 'course-plugin'); ?></label>
                </th>
                <td>
                    <?php
                    $program_tag = get_post_meta($post->ID, '_program_tag', true);
                    ?>
                    <input type="text" id="program_tag" name="program_tag" value="<?php echo esc_attr($program_tag); ?>" class="regular-text" placeholder="<?php _e('Например: С нуля, Новый, Можно без опыта', 'course-plugin'); ?>" />
                    <span class="description"><?php _e('Отображается на карточке программы как цветная метка', 'course-plugin'); ?></span>
                </td>
            </tr>
            
            <tr>
                <th>
                    <label for="program_additional_text"><?php _e('Дополнительный текст', 'course-plugin'); ?></label>
                </th>
                <td>
                    <?php
                    $program_additional_text = get_post_meta($post->ID, '_program_additional_text', true);
                    ?>
                    <input type="text" id="program_additional_text" name="program_additional_text" value="<?php echo esc_attr($program_additional_text); ?>" class="regular-text" placeholder="<?php _e('Например: Учись в своем темпе, 9 месяцев', 'course-plugin'); ?>" />
                    <span class="description"><?php _e('Дополнительная информация на карточке программы', 'course-plugin'); ?></span>
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
     * Рендеринг метабокса "Настройка текстов страницы"
     */
    public function render_program_page_texts_meta_box($post) {
        // Получаем сохраненные значения CTA
        $cta_title = get_post_meta($post->ID, '_program_cta_title', true);
        $cta_text = get_post_meta($post->ID, '_program_cta_text', true);
        $cta_button_text = get_post_meta($post->ID, '_program_cta_button_text', true);
        
        // Получаем сохраненные преимущества
        $highlight_1_title = get_post_meta($post->ID, '_program_highlight_1_title', true);
        $highlight_1_text = get_post_meta($post->ID, '_program_highlight_1_text', true);
        $highlight_2_title = get_post_meta($post->ID, '_program_highlight_2_title', true);
        $highlight_2_text = get_post_meta($post->ID, '_program_highlight_2_text', true);
        $highlight_3_title = get_post_meta($post->ID, '_program_highlight_3_title', true);
        $highlight_3_text = get_post_meta($post->ID, '_program_highlight_3_text', true);
        $highlight_4_title = get_post_meta($post->ID, '_program_highlight_4_title', true);
        $highlight_4_text = get_post_meta($post->ID, '_program_highlight_4_text', true);
        $show_highlights = get_post_meta($post->ID, '_program_show_highlights', true);
        
        // Значения по умолчанию
        $default_cta_title = __('Готовы начать обучение?', 'course-plugin');
        $default_cta_text = __('Присоединяйтесь к программе и откройте новые возможности для профессионального роста!', 'course-plugin');
        $default_cta_button_text = __('Записаться сейчас', 'course-plugin');
        
        // Значения по умолчанию для преимуществ
        $defaults = array(
            1 => array('title' => __('Качественное образование', 'course-plugin'), 'text' => __('Программа разработана экспертами с многолетним опытом', 'course-plugin')),
            2 => array('title' => __('Гибкий график', 'course-plugin'), 'text' => __('Учитесь в удобное время и в своём темпе', 'course-plugin')),
            3 => array('title' => __('Официальный сертификат', 'course-plugin'), 'text' => __('Получите документ о повышении квалификации', 'course-plugin')),
            4 => array('title' => __('Поддержка кураторов', 'course-plugin'), 'text' => __('Персональная помощь на протяжении всего обучения', 'course-plugin')),
        );
        ?>
        
        <!-- Блок преимуществ программы -->
        <h4 style="margin-top: 0; padding-top: 0; border-bottom: 1px solid #ddd; padding-bottom: 10px;">
            <?php _e('Блок "Преимущества программы"', 'course-plugin'); ?>
        </h4>
        
        <p>
            <label>
                <input type="checkbox" name="program_show_highlights" value="1" <?php checked($show_highlights !== '0'); ?> />
                <?php _e('Показывать блок преимуществ на странице программы', 'course-plugin'); ?>
            </label>
        </p>
        
        <table class="form-table">
            <?php for ($i = 1; $i <= 4; $i++) : 
                $title_var = "highlight_{$i}_title";
                $text_var = "highlight_{$i}_text";
                $title_value = get_post_meta($post->ID, "_program_highlight_{$i}_title", true);
                $text_value = get_post_meta($post->ID, "_program_highlight_{$i}_text", true);
            ?>
            <tr>
                <th>
                    <label><?php printf(__('Преимущество %d', 'course-plugin'), $i); ?></label>
                </th>
                <td>
                    <p>
                        <input type="text" name="program_highlight_<?php echo $i; ?>_title" value="<?php echo esc_attr($title_value); ?>" class="large-text" placeholder="<?php echo esc_attr($defaults[$i]['title']); ?>" />
                        <span class="description"><?php _e('Заголовок', 'course-plugin'); ?></span>
                    </p>
                    <p>
                        <input type="text" name="program_highlight_<?php echo $i; ?>_text" value="<?php echo esc_attr($text_value); ?>" class="large-text" placeholder="<?php echo esc_attr($defaults[$i]['text']); ?>" />
                        <span class="description"><?php _e('Описание', 'course-plugin'); ?></span>
                    </p>
                </td>
            </tr>
            <?php endfor; ?>
        </table>
        
        <hr style="margin: 30px 0;" />
        
        <!-- Блок CTA -->
        <h4 style="border-bottom: 1px solid #ddd; padding-bottom: 10px;">
            <?php _e('Блок призыва к действию (CTA) внизу страницы', 'course-plugin'); ?>
        </h4>
        
        <table class="form-table">
            <tr>
                <th>
                    <label for="program_cta_title"><?php _e('Заголовок CTA блока', 'course-plugin'); ?></label>
                </th>
                <td>
                    <input type="text" id="program_cta_title" name="program_cta_title" value="<?php echo esc_attr($cta_title); ?>" class="large-text" placeholder="<?php echo esc_attr($default_cta_title); ?>" />
                    <p class="description"><?php printf(__('По умолчанию: %s', 'course-plugin'), $default_cta_title); ?></p>
                </td>
            </tr>
            
            <tr>
                <th>
                    <label for="program_cta_text"><?php _e('Текст CTA блока', 'course-plugin'); ?></label>
                </th>
                <td>
                    <textarea id="program_cta_text" name="program_cta_text" rows="3" class="large-text" placeholder="<?php echo esc_attr($default_cta_text); ?>"><?php echo esc_textarea($cta_text); ?></textarea>
                    <p class="description"><?php printf(__('По умолчанию: %s', 'course-plugin'), $default_cta_text); ?></p>
                </td>
            </tr>
            
            <tr>
                <th>
                    <label for="program_cta_button_text"><?php _e('Текст кнопки CTA', 'course-plugin'); ?></label>
                </th>
                <td>
                    <input type="text" id="program_cta_button_text" name="program_cta_button_text" value="<?php echo esc_attr($cta_button_text); ?>" class="regular-text" placeholder="<?php echo esc_attr($default_cta_button_text); ?>" />
                    <p class="description"><?php printf(__('По умолчанию: %s', 'course-plugin'), $default_cta_button_text); ?></p>
                </td>
            </tr>
        </table>
        
        <p class="description" style="margin-top: 15px; padding: 10px; background: #f0f0f1; border-radius: 4px;">
            <strong><?php _e('Подсказка:', 'course-plugin'); ?></strong> 
            <?php _e('Оставьте поля пустыми, чтобы использовать тексты по умолчанию.', 'course-plugin'); ?>
        </p>
        <?php
    }
    
    /**
     * Рендеринг метабокса "Ссылка для записи"
     */
    public function render_program_enroll_url_meta_box($post) {
        $enroll_url = get_post_meta($post->ID, '_program_enroll_url', true);
        ?>
        <p>
            <label for="program_enroll_url">
                <strong><?php _e('Ссылка для записи на программу', 'course-plugin'); ?></strong>
            </label>
        </p>
        <input type="url" id="program_enroll_url" name="program_enroll_url" value="<?php echo esc_url($enroll_url); ?>" class="large-text" placeholder="https://..." />
        <p class="description">
            <?php _e('Укажите URL, куда будет вести кнопка "Записаться на программу"', 'course-plugin'); ?>
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
        
        // Сохранение тега программы
        if (isset($_POST['program_tag'])) {
            update_post_meta($post_id, '_program_tag', sanitize_text_field($_POST['program_tag']));
        }
        
        // Сохранение дополнительного текста
        if (isset($_POST['program_additional_text'])) {
            update_post_meta($post_id, '_program_additional_text', sanitize_text_field($_POST['program_additional_text']));
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
        
        // Сохранение ссылки для записи
        if (isset($_POST['program_enroll_url'])) {
            update_post_meta($post_id, '_program_enroll_url', esc_url_raw($_POST['program_enroll_url']));
        }
        
        // Сохранение текстов CTA блока
        if (isset($_POST['program_cta_title'])) {
            update_post_meta($post_id, '_program_cta_title', sanitize_text_field($_POST['program_cta_title']));
        }
        
        if (isset($_POST['program_cta_text'])) {
            update_post_meta($post_id, '_program_cta_text', sanitize_textarea_field($_POST['program_cta_text']));
        }
        
        if (isset($_POST['program_cta_button_text'])) {
            update_post_meta($post_id, '_program_cta_button_text', sanitize_text_field($_POST['program_cta_button_text']));
        }
        
        // Сохранение настройки показа блока преимуществ
        $show_highlights = isset($_POST['program_show_highlights']) ? '1' : '0';
        update_post_meta($post_id, '_program_show_highlights', $show_highlights);
        
        // Сохранение преимуществ программы
        for ($i = 1; $i <= 4; $i++) {
            if (isset($_POST["program_highlight_{$i}_title"])) {
                update_post_meta($post_id, "_program_highlight_{$i}_title", sanitize_text_field($_POST["program_highlight_{$i}_title"]));
            }
            if (isset($_POST["program_highlight_{$i}_text"])) {
                update_post_meta($post_id, "_program_highlight_{$i}_text", sanitize_text_field($_POST["program_highlight_{$i}_text"]));
            }
        }
        
        // Автоматически создаем или обновляем термин в таксономии course_specialization на основе программы
        $this->sync_program_to_specialization_taxonomy($post_id);
    }
    
    /**
     * Синхронизация программы с таксономией course_specialization
     * Создает или обновляет термин таксономии на основе программы
     * и связывает курсы из программы с этим термином
     * 
     * @param int $program_id ID программы
     */
    private function sync_program_to_specialization_taxonomy($program_id) {
        // Получаем данные программы
        $program = get_post($program_id);
        if (!$program || $program->post_type !== 'program') {
            return;
        }
        
        // Получаем название программы
        $program_title = $program->post_title;
        if (empty($program_title)) {
            return;
        }
        
        // Проверяем, существует ли уже термин с таким названием
        $existing_term = get_term_by('name', $program_title, 'course_specialization');
        
        if ($existing_term && !is_wp_error($existing_term)) {
            // Термин уже существует, используем его
            $term_id = $existing_term->term_id;
            
            // Сохраняем ID программы в метаполе термина для связи
            update_term_meta($term_id, '_program_id', $program_id);
        } else {
            // Создаем новый термин
            $term_data = wp_insert_term(
                $program_title,
                'course_specialization',
                array(
                    'description' => sprintf(__('Программа: %s', 'course-plugin'), $program_title),
                )
            );
            
            if (is_wp_error($term_data)) {
                error_log('Program Meta Boxes: Ошибка при создании термина таксономии для программы "' . $program_title . '" - ' . $term_data->get_error_message());
                return;
            }
            
            $term_id = $term_data['term_id'];
            
            // Сохраняем ID программы в метаполе термина для связи
            update_term_meta($term_id, '_program_id', $program_id);
        }
        
        // Связываем программу с термином таксономии
        wp_set_post_terms($program_id, array($term_id), 'course_specialization', false);
        
        // Получаем связанные курсы из программы
        $related_courses = get_post_meta($program_id, '_program_related_courses', true);
        
        if (is_array($related_courses) && !empty($related_courses)) {
            // Связываем все курсы из программы с термином таксономии
            foreach ($related_courses as $course_id) {
                $course_id = intval($course_id);
                if ($course_id > 0) {
                    // Получаем текущие термины курса
                    $current_terms = wp_get_post_terms($course_id, 'course_specialization', array('fields' => 'ids'));
                    
                    if (!is_wp_error($current_terms)) {
                        // Добавляем термин программы к существующим терминам курса
                        if (!in_array($term_id, $current_terms)) {
                            $current_terms[] = $term_id;
                            wp_set_post_terms($course_id, $current_terms, 'course_specialization', false);
                        }
                    }
                }
            }
        }
    }
}
