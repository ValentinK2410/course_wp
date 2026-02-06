<?php
/**
 * Класс для метабоксов курсов
 * 
 * Метабоксы - это дополнительные блоки с полями на странице редактирования курса
 * Этот класс добавляет два метабокса:
 * 1. "Детали курса" - даты, вместимость, количество записанных студентов
 * 2. "Продолжительность и стоимость" - продолжительность, цена, старая цена, рейтинг, отзывы
 * 
 * @copyright Copyright (c) 2024 Кузьменко Валентин (Valentink2410)
 * @author Кузьменко Валентин (Valentink2410)
 */

// Проверка безопасности: если файл вызывается напрямую, прекращаем выполнение
if (!defined('ABSPATH')) {
    exit;
}

class Course_Meta_Boxes {
    
    /**
     * Единственный экземпляр класса (Singleton)
     */
    private static $instance = null;
    
    /**
     * Получить экземпляр класса
     * 
     * @return Course_Meta_Boxes Экземпляр класса
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
        // Хук 'add_meta_boxes' срабатывает при загрузке страницы редактирования поста
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        
        // Регистрируем метод для сохранения данных метабоксов
        // Хук 'save_post' срабатывает при сохранении поста (любого типа)
        add_action('save_post', array($this, 'save_meta_boxes'));
    }
    
    /**
     * Добавление метабоксов на страницу редактирования курса
     * Создает три метабокса с дополнительными полями для курса
     */
    public function add_meta_boxes() {
        // Добавляем метабокс "Детали курса"
        // add_meta_box() - стандартная функция WordPress для создания метабоксов
        add_meta_box(
            'course_details',                                    // ID метабокса (уникальный идентификатор)
            __('Детали курса', 'course-plugin'),                // Заголовок метабокса
            array($this, 'render_course_details_meta_box'),     // Функция для отображения содержимого
            'course',                                            // Тип поста, для которого показывать метабокс
            'normal',                                            // Контекст: 'normal' - основная область (под редактором)
            'high'                                               // Приоритет: 'high' - показывать вверху
        );
        
        // Добавляем метабокс "Продолжительность и стоимость"
        add_meta_box(
            'course_duration',                                    // ID метабокса
            __('Продолжительность и стоимость', 'course-plugin'), // Заголовок
            array($this, 'render_course_duration_meta_box'),    // Функция для отображения
            'course',                                             // Тип поста
            'side',                                               // Контекст: 'side' - боковая панель (справа)
            'default'                                             // Приоритет: 'default' - стандартный
        );
        
        // Добавляем метабокс "Ссылки на кнопки курса"
        add_meta_box(
            'course_action_buttons',                              // ID метабокса
            __('Ссылки на кнопки курса', 'course-plugin'),      // Заголовок
            array($this, 'render_course_action_buttons_meta_box'), // Функция для отображения
            'course',                                             // Тип поста
            'side',                                               // Контекст: 'side' - боковая панель (справа)
            'default'                                             // Приоритет: 'default' - стандартный
        );
        
        // Добавляем метабокс "Настройка текстов страницы"
        add_meta_box(
            'course_page_texts',                                  // ID метабокса
            __('Настройка текстов страницы', 'course-plugin'),  // Заголовок
            array($this, 'render_course_page_texts_meta_box'),   // Функция для отображения
            'course',                                             // Тип поста
            'normal',                                             // Контекст: 'normal' - основная область
            'default'                                             // Приоритет
        );
    }
    
    /**
     * Рендеринг метабокса "Детали курса"
     * Отображает форму с полями: дата начала, дата окончания, вместимость, записано студентов
     * 
     * @param WP_Post $post Объект текущего курса
     */
    public function render_course_details_meta_box($post) {
        // Добавляем поле nonce для защиты от CSRF-атак
        // wp_nonce_field() создает скрытое поле с токеном безопасности
        wp_nonce_field('course_meta_box', 'course_meta_box_nonce');
        
        // Получаем значения метаполей из базы данных
        // get_post_meta() получает значение метаполя для поста
        // Второй параметр 'true' означает, что нужно вернуть одно значение (не массив)
        $course_duration = get_post_meta($post->ID, '_course_duration', true);
        $course_price = get_post_meta($post->ID, '_course_price', true);
        $course_start_date = get_post_meta($post->ID, '_course_start_date', true);
        $course_end_date = get_post_meta($post->ID, '_course_end_date', true);
        $course_capacity = get_post_meta($post->ID, '_course_capacity', true);
        $course_enrolled = get_post_meta($post->ID, '_course_enrolled', true);
        
        // Начинаем вывод HTML-формы
        ?>
        <table class="form-table">
            <!-- Поле "Дата начала курса" -->
            <tr>
                <th>
                    <label for="course_start_date"><?php _e('Дата начала', 'course-plugin'); ?></label>
                </th>
                <td>
                    <!-- Поле ввода типа date (календарь) -->
                    <!-- esc_attr() экранирует значение для безопасного вывода в HTML -->
                    <input type="date" id="course_start_date" name="course_start_date" value="<?php echo esc_attr($course_start_date); ?>" class="regular-text" />
                </td>
            </tr>
            
            <!-- Поле "Дата окончания курса" -->
            <tr>
                <th>
                    <label for="course_end_date"><?php _e('Дата окончания', 'course-plugin'); ?></label>
                </th>
                <td>
                    <input type="date" id="course_end_date" name="course_end_date" value="<?php echo esc_attr($course_end_date); ?>" class="regular-text" />
                </td>
            </tr>
            
            <!-- Поле "Вместимость курса" (количество мест) -->
            <tr>
                <th>
                    <label for="course_capacity"><?php _e('Вместимость (количество мест)', 'course-plugin'); ?></label>
                </th>
                <td>
                    <!-- Поле ввода типа number (только числа) -->
                    <!-- min="1" означает минимальное значение 1 -->
                    <input type="number" id="course_capacity" name="course_capacity" value="<?php echo esc_attr($course_capacity); ?>" class="small-text" min="1" />
                </td>
            </tr>
            
            <!-- Поле "Количество записанных студентов" -->
            <tr>
                <th>
                    <label for="course_enrolled"><?php _e('Записано студентов', 'course-plugin'); ?></label>
                </th>
                <td>
                    <!-- min="0" означает, что можно указать 0 или больше -->
                    <input type="number" id="course_enrolled" name="course_enrolled" value="<?php echo esc_attr($course_enrolled); ?>" class="small-text" min="0" />
                </td>
            </tr>
        </table>
        <?php
        // Заканчиваем вывод HTML
    }
    
    /**
     * Рендеринг метабокса "Продолжительность и стоимость"
     * Отображает форму с полями: продолжительность, цена, старая цена, рейтинг, количество отзывов
     * 
     * @param WP_Post $post Объект текущего курса
     */
    public function render_course_duration_meta_box($post) {
        // Получаем значения метаполей из базы данных
        $course_duration = get_post_meta($post->ID, '_course_duration', true);
        $course_price = get_post_meta($post->ID, '_course_price', true);
        $course_old_price = get_post_meta($post->ID, '_course_old_price', true);
        $course_rating = get_post_meta($post->ID, '_course_rating', true);
        $course_reviews_count = get_post_meta($post->ID, '_course_reviews_count', true);
        
        ?>
        <!-- Поле "Продолжительность курса в часах" -->
        <p>
            <label for="course_duration"><?php _e('Продолжительность (часов)', 'course-plugin'); ?></label>
            <!-- step="0.5" позволяет вводить дробные числа (например, 1.5 часа) -->
            <input type="number" id="course_duration" name="course_duration" value="<?php echo esc_attr($course_duration); ?>" class="small-text" min="1" step="0.5" />
        </p>
        
        <!-- Поле "Стоимость курса" -->
        <p>
            <label for="course_price"><?php _e('Стоимость (руб.)', 'course-plugin'); ?></label>
            <!-- step="0.01" позволяет вводить копейки (например, 1000.50 рублей) -->
            <input type="number" id="course_price" name="course_price" value="<?php echo esc_attr($course_price); ?>" class="small-text" min="0" step="0.01" />
        </p>
        
        <!-- Поле "Старая цена" (для отображения скидки) -->
        <p>
            <label for="course_old_price"><?php _e('Старая цена (руб.)', 'course-plugin'); ?></label>
            <input type="number" id="course_old_price" name="course_old_price" value="<?php echo esc_attr($course_old_price); ?>" class="small-text" min="0" step="0.01" />
            <!-- Подсказка для пользователя -->
            <span class="description"><?php _e('Для отображения скидки', 'course-plugin'); ?></span>
        </p>
        
        <!-- Поле "Рейтинг курса" (от 1 до 5) -->
        <p>
            <label for="course_rating"><?php _e('Рейтинг (1-5)', 'course-plugin'); ?></label>
            <!-- min="0" max="5" ограничивает диапазон от 0 до 5 -->
            <!-- step="0.1" позволяет вводить дробные значения (например, 4.5) -->
            <input type="number" id="course_rating" name="course_rating" value="<?php echo esc_attr($course_rating); ?>" class="small-text" min="0" max="5" step="0.1" />
        </p>
        
        <!-- Поле "Количество отзывов" -->
        <p>
            <label for="course_reviews_count"><?php _e('Количество отзывов', 'course-plugin'); ?></label>
            <input type="number" id="course_reviews_count" name="course_reviews_count" value="<?php echo esc_attr($course_reviews_count); ?>" class="small-text" min="0" />
        </p>
        
        <!-- Поле "Тег курса" (для отображения на карточке) -->
        <?php
        $course_tag = get_post_meta($post->ID, '_course_tag', true);
        ?>
        <p>
            <label for="course_tag"><?php _e('Тег курса', 'course-plugin'); ?></label>
            <input type="text" id="course_tag" name="course_tag" value="<?php echo esc_attr($course_tag); ?>" class="regular-text" placeholder="<?php _e('Например: С нуля, Новый, Можно без опыта', 'course-plugin'); ?>" />
            <span class="description"><?php _e('Отображается на карточке курса как цветная метка', 'course-plugin'); ?></span>
        </p>
        
        <!-- Поле "Дополнительный текст" -->
        <?php
        $course_additional_text = get_post_meta($post->ID, '_course_additional_text', true);
        ?>
        <p>
            <label for="course_additional_text"><?php _e('Дополнительный текст', 'course-plugin'); ?></label>
            <input type="text" id="course_additional_text" name="course_additional_text" value="<?php echo esc_attr($course_additional_text); ?>" class="regular-text" placeholder="<?php _e('Например: Учись в своем темпе, 9 месяцев', 'course-plugin'); ?>" />
            <span class="description"><?php _e('Дополнительная информация на карточке курса', 'course-plugin'); ?></span>
        </p>
        <?php
    }
    
    /**
     * Рендеринг метабокса со ссылками на кнопки курса
     * 
     * @param WP_Post $post Объект текущего курса
     */
    public function render_course_action_buttons_meta_box($post) {
        // Получаем значения метаполей для ссылок кнопок
        $course_seminary_new_url = get_post_meta($post->ID, '_course_seminary_new_url', true);
        $course_seminary_student_url = get_post_meta($post->ID, '_course_seminary_student_url', true);
        $course_lite_course_url = get_post_meta($post->ID, '_course_lite_course_url', true);
        
        ?>
        <p>
            <label for="course_seminary_new_url">
                <strong><?php _e('Курс на семинарском уровне (не студент SEMINARY)', 'course-plugin'); ?></strong>
            </label><br />
            <input type="url" id="course_seminary_new_url" name="course_seminary_new_url" value="<?php echo esc_url($course_seminary_new_url); ?>" class="large-text" placeholder="https://..." />
            <span class="description"><?php _e('Если поле пустое, кнопка не будет отображаться', 'course-plugin'); ?></span>
        </p>
        
        <p>
            <label for="course_seminary_student_url">
                <strong><?php _e('Курс на семинарском уровне (студент SEMINARY)', 'course-plugin'); ?></strong>
            </label><br />
            <input type="url" id="course_seminary_student_url" name="course_seminary_student_url" value="<?php echo esc_url($course_seminary_student_url); ?>" class="large-text" placeholder="https://..." />
            <span class="description"><?php _e('Если поле пустое, кнопка не будет отображаться', 'course-plugin'); ?></span>
        </p>
        
        <p>
            <label for="course_lite_course_url">
                <strong><?php _e('Лайт курс', 'course-plugin'); ?></strong>
            </label><br />
            <input type="url" id="course_lite_course_url" name="course_lite_course_url" value="<?php echo esc_url($course_lite_course_url); ?>" class="large-text" placeholder="https://..." />
            <span class="description"><?php _e('Если поле пустое, кнопка не будет отображаться', 'course-plugin'); ?></span>
        </p>
        <?php
    }
    
    /**
     * Рендеринг метабокса "Настройка текстов страницы"
     * Позволяет редактировать тексты шапки и подвала страницы курса
     * 
     * @param WP_Post $post Объект текущего курса
     */
    public function render_course_page_texts_meta_box($post) {
        // Получаем сохраненные значения
        $cta_title = get_post_meta($post->ID, '_course_cta_title', true);
        $cta_text = get_post_meta($post->ID, '_course_cta_text', true);
        $cta_button_text = get_post_meta($post->ID, '_course_cta_button_text', true);
        
        // Значения по умолчанию
        $default_cta_title = __('Готовы начать обучение?', 'course-plugin');
        $default_cta_text = __('Запишитесь на курс и начните свой путь к новым знаниям!', 'course-plugin');
        $default_cta_button_text = __('Записаться на курс', 'course-plugin');
        ?>
        
        <h4 style="margin-top: 0; padding-top: 0; border-bottom: 1px solid #ddd; padding-bottom: 10px;">
            <?php _e('Блок призыва к действию (CTA) внизу страницы', 'course-plugin'); ?>
        </h4>
        
        <table class="form-table">
            <tr>
                <th>
                    <label for="course_cta_title"><?php _e('Заголовок CTA блока', 'course-plugin'); ?></label>
                </th>
                <td>
                    <input type="text" id="course_cta_title" name="course_cta_title" value="<?php echo esc_attr($cta_title); ?>" class="large-text" placeholder="<?php echo esc_attr($default_cta_title); ?>" />
                    <p class="description"><?php printf(__('По умолчанию: %s', 'course-plugin'), $default_cta_title); ?></p>
                </td>
            </tr>
            
            <tr>
                <th>
                    <label for="course_cta_text"><?php _e('Текст CTA блока', 'course-plugin'); ?></label>
                </th>
                <td>
                    <textarea id="course_cta_text" name="course_cta_text" rows="3" class="large-text" placeholder="<?php echo esc_attr($default_cta_text); ?>"><?php echo esc_textarea($cta_text); ?></textarea>
                    <p class="description"><?php printf(__('По умолчанию: %s', 'course-plugin'), $default_cta_text); ?></p>
                </td>
            </tr>
            
            <tr>
                <th>
                    <label for="course_cta_button_text"><?php _e('Текст кнопки CTA', 'course-plugin'); ?></label>
                </th>
                <td>
                    <input type="text" id="course_cta_button_text" name="course_cta_button_text" value="<?php echo esc_attr($cta_button_text); ?>" class="regular-text" placeholder="<?php echo esc_attr($default_cta_button_text); ?>" />
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
     * Сохранение данных метабоксов при сохранении курса
     * Вызывается автоматически при нажатии кнопки "Сохранить" или "Опубликовать"
     * 
     * @param int $post_id ID сохраняемого курса
     */
    public function save_meta_boxes($post_id) {
        // Проверка безопасности: проверяем наличие и валидность nonce
        // nonce защищает от CSRF-атак (подделка межсайтовых запросов)
        if (!isset($_POST['course_meta_box_nonce']) || !wp_verify_nonce($_POST['course_meta_box_nonce'], 'course_meta_box')) {
            return; // Если nonce неверный, прекращаем выполнение функции
        }
        
        // Проверка автсохранения: WordPress автоматически сохраняет черновики
        // Мы не хотим сохранять метаполя при автсохранении, только при ручном сохранении
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return; // Если это автсохранение, выходим из функции
        }
        
        // Проверка прав доступа: может ли пользователь редактировать этот пост
        if (!current_user_can('edit_post', $post_id)) {
            return; // Если нет прав, прекращаем выполнение
        }
        
        // Проверка типа поста: сохраняем метаполя только для курсов
        if (get_post_type($post_id) !== 'course') {
            return; // Если это не курс, выходим из функции
        }
        
        // Массив названий полей, которые нужно сохранить
        $fields = array(
            'course_duration',            // Продолжительность курса
            'course_price',               // Стоимость курса
            'course_old_price',           // Старая цена (для скидки)
            'course_start_date',          // Дата начала курса
            'course_end_date',            // Дата окончания курса
            'course_capacity',            // Вместимость (количество мест)
            'course_enrolled',            // Количество записанных студентов
            'course_rating',              // Рейтинг курса (1-5)
            'course_reviews_count',       // Количество отзывов
            'course_tag',                 // Тег курса (для отображения на карточке)
            'course_additional_text',     // Дополнительный текст на карточке
            'course_seminary_new_url',    // Ссылка на кнопку "Курс на семинарском уровне (не студент)"
            'course_seminary_student_url', // Ссылка на кнопку "Курс на семинарском уровне (студент)"
            'course_lite_course_url',      // Ссылка на кнопку "Лайт курс"
            'course_cta_title',           // Заголовок CTA блока
            'course_cta_text',            // Текст CTA блока
            'course_cta_button_text',     // Текст кнопки CTA
        );
        
        // Обрабатываем URL поля отдельно (нужна специальная очистка)
        $url_fields = array(
            'course_seminary_new_url',
            'course_seminary_student_url',
            'course_lite_course_url',
        );
        
        // Сохраняем URL поля с правильной очисткой
        foreach ($url_fields as $field) {
            if (isset($_POST[$field])) {
                // esc_url_raw() очищает URL и сохраняет его в безопасном виде
                $value = esc_url_raw($_POST[$field]);
                update_post_meta($post_id, '_' . $field, $value);
            } else {
                delete_post_meta($post_id, '_' . $field);
            }
        }
        
        // Удаляем URL поля из основного массива, чтобы не обрабатывать их дважды
        $fields = array_diff($fields, $url_fields);
        
        // Проходим по каждому полю
        foreach ($fields as $field) {
            // Проверяем, было ли поле заполнено в форме
            if (isset($_POST[$field])) {
                // Очищаем и валидируем значение поля
                // sanitize_text_field() удаляет опасные символы и HTML-теги
                $value = sanitize_text_field($_POST[$field]);
                
                // Сохраняем значение в базу данных как метаполе
                // Префикс '_' означает, что поле скрыто (не показывается в стандартном списке метаполей)
                // update_post_meta() обновляет существующее значение или создает новое
                update_post_meta($post_id, '_' . $field, $value);
            } else {
                // Если поле не заполнено, удаляем метаполе из базы данных
                // Это нужно для очистки старых значений
                delete_post_meta($post_id, '_' . $field);
            }
        }
    }
}
