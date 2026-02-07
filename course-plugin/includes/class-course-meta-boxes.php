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
        
        // Добавляем метабокс "Цели и задачи курса"
        add_meta_box(
            'course_goals',                                        // ID метабокса
            __('Цели и задачи курса', 'course-plugin'),          // Заголовок
            array($this, 'render_course_goals_meta_box'),        // Функция для отображения
            'course',                                             // Тип поста
            'normal',                                             // Контекст: 'normal' - основная область
            'default'                                             // Приоритет
        );
        
        // Добавляем метабокс "Контент курса" (содержание и видео)
        add_meta_box(
            'course_content',                                      // ID метабокса
            __('Контент курса', 'course-plugin'),               // Заголовок
            array($this, 'render_course_content_meta_box'),       // Функция для отображения
            'course',                                             // Тип поста
            'normal',                                             // Контекст: 'normal' - основная область
            'default'                                             // Приоритет
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
        
        // Добавляем метабокс "Настройка карточки курса"
        add_meta_box(
            'course_card_settings',                                // ID метабокса
            __('Настройка карточки курса', 'course-plugin'),    // Заголовок
            array($this, 'render_course_card_settings_meta_box'), // Функция для отображения
            'course',                                             // Тип поста
            'side',                                               // Контекст: 'side' - боковая панель
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
        
        // Дополнительные поля для сайдбара
        $course_weeks = get_post_meta($post->ID, '_course_weeks', true);
        $course_credits = get_post_meta($post->ID, '_course_credits', true);
        $course_hours_per_week = get_post_meta($post->ID, '_course_hours_per_week', true);
        $course_language = get_post_meta($post->ID, '_course_language', true);
        $course_certificate = get_post_meta($post->ID, '_course_certificate', true);
        $course_location = get_post_meta($post->ID, '_course_location', true);
        
        // Начинаем вывод HTML-формы
        ?>
        <table class="form-table">
            <!-- Поле "Место прохождения" -->
            <tr>
                <th>
                    <label for="course_location"><?php _e('Место прохождения', 'course-plugin'); ?></label>
                </th>
                <td>
                    <select id="course_location" name="course_location" class="regular-text">
                        <option value=""><?php _e('-- Выберите место --', 'course-plugin'); ?></option>
                        <option value="online" <?php selected($course_location, 'online'); ?>><?php _e('Онлайн-курсы', 'course-plugin'); ?></option>
                        <option value="zoom" <?php selected($course_location, 'zoom'); ?>><?php _e('Зум', 'course-plugin'); ?></option>
                        <option value="moscow" <?php selected($course_location, 'moscow'); ?>><?php _e('Москва (центральный кампус)', 'course-plugin'); ?></option>
                        <option value="prokhladny" <?php selected($course_location, 'prokhladny'); ?>><?php _e('Прохладный', 'course-plugin'); ?></option>
                        <option value="nizhny-novgorod" <?php selected($course_location, 'nizhny-novgorod'); ?>><?php _e('Нижний Новгород', 'course-plugin'); ?></option>
                        <option value="chelyabinsk" <?php selected($course_location, 'chelyabinsk'); ?>><?php _e('Челябинск', 'course-plugin'); ?></option>
                        <option value="norilsk" <?php selected($course_location, 'norilsk'); ?>><?php _e('Норильск', 'course-plugin'); ?></option>
                        <option value="izhevsk" <?php selected($course_location, 'izhevsk'); ?>><?php _e('Ижевск', 'course-plugin'); ?></option>
                        <option value="yug" <?php selected($course_location, 'yug'); ?>><?php _e('Юг', 'course-plugin'); ?></option>
                        <option value="novokuznetsk" <?php selected($course_location, 'novokuznetsk'); ?>><?php _e('Новокузнецк', 'course-plugin'); ?></option>
                    </select>
                </td>
            </tr>
            
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
            
            <!-- Поле "Количество недель" -->
            <tr>
                <th>
                    <label for="course_weeks"><?php _e('Количество недель', 'course-plugin'); ?></label>
                </th>
                <td>
                    <input type="number" id="course_weeks" name="course_weeks" value="<?php echo esc_attr($course_weeks); ?>" class="small-text" min="1" />
                    <span class="description"><?php _e('Отображается в сайдбаре "Краткий обзор курса"', 'course-plugin'); ?></span>
                </td>
            </tr>
            
            <!-- Поле "Кредиты" -->
            <tr>
                <th>
                    <label for="course_credits"><?php _e('Кредиты', 'course-plugin'); ?></label>
                </th>
                <td>
                    <input type="number" id="course_credits" name="course_credits" value="<?php echo esc_attr($course_credits); ?>" class="small-text" min="0" step="0.5" />
                    <span class="description"><?php _e('Отображается в сайдбаре "Краткий обзор курса"', 'course-plugin'); ?></span>
                </td>
            </tr>
            
            <!-- Поле "Часов в неделю" -->
            <tr>
                <th>
                    <label for="course_hours_per_week"><?php _e('Часов в неделю', 'course-plugin'); ?></label>
                </th>
                <td>
                    <input type="number" id="course_hours_per_week" name="course_hours_per_week" value="<?php echo esc_attr($course_hours_per_week); ?>" class="small-text" min="0" step="0.5" />
                    <span class="description"><?php _e('Отображается в сайдбаре "Краткий обзор курса"', 'course-plugin'); ?></span>
                </td>
            </tr>
            
            <!-- Поле "Язык курса" -->
            <tr>
                <th>
                    <label for="course_language"><?php _e('Язык курса', 'course-plugin'); ?></label>
                </th>
                <td>
                    <input type="text" id="course_language" name="course_language" value="<?php echo esc_attr($course_language); ?>" class="regular-text" placeholder="<?php _e('Например: Русский, English', 'course-plugin'); ?>" />
                    <span class="description"><?php _e('Отображается в сайдбаре "Краткий обзор курса" и в шапке страницы', 'course-plugin'); ?></span>
                </td>
            </tr>
            
            <!-- Поле "Сертификат" -->
            <tr>
                <th>
                    <label for="course_certificate"><?php _e('Сертификат', 'course-plugin'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" id="course_certificate" name="course_certificate" value="1" <?php checked($course_certificate, '1'); ?> />
                        <?php _e('Выдается сертификат по окончании курса', 'course-plugin'); ?>
                    </label>
                    <span class="description"><?php _e('Отображается в сайдбаре "Краткий обзор курса" и в шапке страницы', 'course-plugin'); ?></span>
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
        $course_code = get_post_meta($post->ID, '_course_code', true);
        $course_duration = get_post_meta($post->ID, '_course_duration', true);
        $course_price = get_post_meta($post->ID, '_course_price', true);
        $course_old_price = get_post_meta($post->ID, '_course_old_price', true);
        $course_rating = get_post_meta($post->ID, '_course_rating', true);
        $course_reviews_count = get_post_meta($post->ID, '_course_reviews_count', true);
        
        ?>
        <!-- Поле "Код курса" -->
        <p>
            <label for="course_code"><?php _e('Код курса', 'course-plugin'); ?></label>
            <input type="text" id="course_code" name="course_code" value="<?php echo esc_attr($course_code); ?>" class="regular-text" placeholder="<?php _e('Например: CS-101, MATH-202', 'course-plugin'); ?>" />
            <span class="description"><?php _e('Отображается в шапке страницы курса', 'course-plugin'); ?></span>
        </p>
        
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
     * Рендеринг метабокса "Цели и задачи курса"
     * Отображает поля для когнитивных, эмоциональных и психомоторных целей
     * 
     * @param WP_Post $post Объект текущего курса
     */
    public function render_course_goals_meta_box($post) {
        // Получаем значения метаполей целей из базы данных
        $course_cognitive_goals = get_post_meta($post->ID, '_course_cognitive_goals', true);
        $course_emotional_goals = get_post_meta($post->ID, '_course_emotional_goals', true);
        $course_psychomotor_goals = get_post_meta($post->ID, '_course_psychomotor_goals', true);
        
        ?>
        <p class="description">
            <?php _e('Заполните цели курса. Эти цели будут отображаться на странице курса в секции "Цели и задачи курса".', 'course-plugin'); ?>
        </p>
        
        <!-- Поле "Когнитивные цели" (Знать) -->
        <p>
            <label for="course_cognitive_goals">
                <strong><?php _e('Когнитивные цели (Знать)', 'course-plugin'); ?></strong>
            </label>
            <?php
            // Используем wp_editor для удобного редактирования текста с форматированием
            wp_editor(
                $course_cognitive_goals,                          // Содержимое редактора
                'course_cognitive_goals',                          // ID поля
                array(
                    'textarea_name' => 'course_cognitive_goals',   // Имя поля для POST
                    'textarea_rows' => 8,                          // Высота редактора (строки)
                    'media_buttons' => false,                      // Отключить кнопку добавления медиа
                    'teeny' => true,                               // Упрощенный режим редактора
                )
            );
            ?>
            <span class="description">
                <?php _e('Опишите, что студенты должны знать после прохождения курса. Можно использовать списки и форматирование.', 'course-plugin'); ?>
            </span>
        </p>
        
        <!-- Поле "Эмоциональные цели" (Чувствовать) -->
        <p>
            <label for="course_emotional_goals">
                <strong><?php _e('Эмоциональные цели (Чувствовать)', 'course-plugin'); ?></strong>
            </label>
            <?php
            wp_editor(
                $course_emotional_goals,
                'course_emotional_goals',
                array(
                    'textarea_name' => 'course_emotional_goals',
                    'textarea_rows' => 8,
                    'media_buttons' => false,
                    'teeny' => true,
                )
            );
            ?>
            <span class="description">
                <?php _e('Опишите, какие эмоции и чувства должны развиться у студентов. Можно использовать списки и форматирование.', 'course-plugin'); ?>
            </span>
        </p>
        
        <!-- Поле "Психомоторные цели" (Уметь) -->
        <p>
            <label for="course_psychomotor_goals">
                <strong><?php _e('Психомоторные цели (Уметь)', 'course-plugin'); ?></strong>
            </label>
            <?php
            wp_editor(
                $course_psychomotor_goals,
                'course_psychomotor_goals',
                array(
                    'textarea_name' => 'course_psychomotor_goals',
                    'textarea_rows' => 8,
                    'media_buttons' => false,
                    'teeny' => true,
                )
            );
            ?>
            <span class="description">
                <?php _e('Опишите, какие практические навыки и умения должны приобрести студенты. Можно использовать списки и форматирование.', 'course-plugin'); ?>
            </span>
        </p>
        <?php
    }
    
    /**
     * Рендеринг метабокса "Контент курса"
     * Отображает поля для содержания курса и видео
     * 
     * @param WP_Post $post Объект текущего курса
     */
    public function render_course_content_meta_box($post) {
        // Получаем значения метаполей контента из базы данных
        $course_content = get_post_meta($post->ID, '_course_content', true);
        $course_video_url = get_post_meta($post->ID, '_course_video_url', true);
        
        ?>
        <p class="description">
            <?php _e('Заполните содержание курса и ссылку на видео. Эти данные будут отображаться на странице курса в соответствующих секциях.', 'course-plugin'); ?>
        </p>
        
        <!-- Поле "Содержание курса" (программа обучения) -->
        <p>
            <label for="course_content">
                <strong><?php _e('Содержание курса (программа обучения)', 'course-plugin'); ?></strong>
            </label>
            <?php
            // Используем wp_editor для удобного редактирования текста с форматированием
            wp_editor(
                $course_content,                                   // Содержимое редактора
                'course_content',                                  // ID поля
                array(
                    'textarea_name' => 'course_content',           // Имя поля для POST
                    'textarea_rows' => 12,                         // Высота редактора (строки)
                    'media_buttons' => false,                      // Отключить кнопку добавления медиа
                    'teeny' => true,                               // Упрощенный режим редактора
                )
            );
            ?>
            <span class="description">
                <?php _e('Опишите программу обучения, темы и модули курса. Можно использовать списки, форматирование и структурированный текст.', 'course-plugin'); ?>
            </span>
        </p>
        
        <!-- Поле "Видео о курсе" (URL) -->
        <p>
            <label for="course_video_url">
                <strong><?php _e('Видео о курсе (URL)', 'course-plugin'); ?></strong>
            </label>
            <input type="url" id="course_video_url" name="course_video_url" value="<?php echo esc_url($course_video_url); ?>" class="large-text" placeholder="<?php _e('https://youtube.com/watch?v=... или https://vimeo.com/...', 'course-plugin'); ?>" />
            <span class="description">
                <?php _e('Вставьте ссылку на видео с YouTube, Vimeo или прямую ссылку на видеофайл. Видео будет отображаться в секции "Видео о курсе" на странице курса.', 'course-plugin'); ?>
            </span>
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
        // Получаем настройки видимости секций (по умолчанию все включены)
        $show_description = get_post_meta($post->ID, '_course_show_description', true);
        $show_goals = get_post_meta($post->ID, '_course_show_goals', true);
        $show_content = get_post_meta($post->ID, '_course_show_content', true);
        $show_video = get_post_meta($post->ID, '_course_show_video', true);
        $show_related = get_post_meta($post->ID, '_course_show_related', true);
        $show_sidebar = get_post_meta($post->ID, '_course_show_sidebar', true);
        $show_cta = get_post_meta($post->ID, '_course_show_cta', true);
        $show_price = get_post_meta($post->ID, '_course_show_price', true);
        $show_teacher = get_post_meta($post->ID, '_course_show_teacher', true);
        
        // Настройки видимости полей в сайдбаре
        $show_field_language = get_post_meta($post->ID, '_course_show_field_language', true);
        $show_field_weeks = get_post_meta($post->ID, '_course_show_field_weeks', true);
        $show_field_credits = get_post_meta($post->ID, '_course_show_field_credits', true);
        $show_field_hours = get_post_meta($post->ID, '_course_show_field_hours', true);
        $show_field_certificate = get_post_meta($post->ID, '_course_show_field_certificate', true);
        
        // Настройки видимости полей в hero
        $show_hero_code = get_post_meta($post->ID, '_course_show_hero_code', true);
        $show_hero_level = get_post_meta($post->ID, '_course_show_hero_level', true);
        $show_hero_dates = get_post_meta($post->ID, '_course_show_hero_dates', true);
        $show_hero_duration = get_post_meta($post->ID, '_course_show_hero_duration', true);
        $show_hero_language = get_post_meta($post->ID, '_course_show_hero_language', true);
        $show_hero_certificate = get_post_meta($post->ID, '_course_show_hero_certificate', true);
        $show_hero_location = get_post_meta($post->ID, '_course_show_hero_location', true);
        // Инициализация по умолчанию для всех hero полей, если значение не установлено
        if ($show_hero_code === '') $show_hero_code = '1';
        if ($show_hero_level === '') $show_hero_level = '1';
        if ($show_hero_dates === '') $show_hero_dates = '1';
        if ($show_hero_duration === '') $show_hero_duration = '1';
        if ($show_hero_language === '') $show_hero_language = '1';
        if ($show_hero_certificate === '') $show_hero_certificate = '1';
        if ($show_hero_location === '') $show_hero_location = '1';
        
        // Получаем сохраненные значения заголовков секций
        $section_description_title = get_post_meta($post->ID, '_course_section_description_title', true);
        $section_goals_title = get_post_meta($post->ID, '_course_section_goals_title', true);
        $section_goals_intro = get_post_meta($post->ID, '_course_section_goals_intro', true);
        $section_content_title = get_post_meta($post->ID, '_course_section_content_title', true);
        $section_video_title = get_post_meta($post->ID, '_course_section_video_title', true);
        $section_related_title = get_post_meta($post->ID, '_course_section_related_title', true);
        $sidebar_overview_title = get_post_meta($post->ID, '_course_sidebar_overview_title', true);
        
        // Тексты для целей
        $goal_cognitive_title = get_post_meta($post->ID, '_course_goal_cognitive_title', true);
        $goal_cognitive_subtitle = get_post_meta($post->ID, '_course_goal_cognitive_subtitle', true);
        $goal_emotional_title = get_post_meta($post->ID, '_course_goal_emotional_title', true);
        $goal_emotional_subtitle = get_post_meta($post->ID, '_course_goal_emotional_subtitle', true);
        $goal_psychomotor_title = get_post_meta($post->ID, '_course_goal_psychomotor_title', true);
        $goal_psychomotor_subtitle = get_post_meta($post->ID, '_course_goal_psychomotor_subtitle', true);
        
        // Тексты кнопок
        $btn_enroll_text = get_post_meta($post->ID, '_course_btn_enroll_text', true);
        $btn_student_text = get_post_meta($post->ID, '_course_btn_student_text', true);
        $btn_lite_text = get_post_meta($post->ID, '_course_btn_lite_text', true);
        
        // Получаем сохраненные значения CTA
        $cta_title = get_post_meta($post->ID, '_course_cta_title', true);
        $cta_text = get_post_meta($post->ID, '_course_cta_text', true);
        $cta_button_text = get_post_meta($post->ID, '_course_cta_button_text', true);
        
        // Дополнительные блоки контента
        $extra_blocks = get_post_meta($post->ID, '_course_extra_blocks', true);
        if (!is_array($extra_blocks)) {
            $extra_blocks = array();
        }
        
        // Кастомные блоки сайдбара
        $sidebar_blocks = get_post_meta($post->ID, '_course_sidebar_blocks', true);
        if (!is_array($sidebar_blocks)) {
            $sidebar_blocks = array();
        }
        
        // Значения по умолчанию для заголовков
        $defaults = array(
            'section_description' => __('Описание курса:', 'course-plugin'),
            'section_goals' => __('Цели и задачи курса:', 'course-plugin'),
            'section_goals_intro' => __('Изучив этот курс, студенты смогут:', 'course-plugin'),
            'section_content' => __('Содержание курса', 'course-plugin'),
            'section_video' => __('Видео о курсе', 'course-plugin'),
            'section_related' => __('Другие курсы по теме', 'course-plugin'),
            'sidebar_overview' => __('Краткий обзор курса', 'course-plugin'),
            'goal_cognitive' => __('Когнитивные цели', 'course-plugin'),
            'goal_cognitive_sub' => __('Знать', 'course-plugin'),
            'goal_emotional' => __('Эмоциональные цели', 'course-plugin'),
            'goal_emotional_sub' => __('Чувствовать', 'course-plugin'),
            'goal_psychomotor' => __('Психомоторные цели', 'course-plugin'),
            'goal_psychomotor_sub' => __('Уметь', 'course-plugin'),
            'btn_enroll' => __('Записаться на курс', 'course-plugin'),
            'btn_student' => __('Для студентов семинарии', 'course-plugin'),
            'btn_lite' => __('Лайт курс', 'course-plugin'),
            'cta_title' => __('Готовы начать обучение?', 'course-plugin'),
            'cta_text' => __('Запишитесь на курс и начните свой путь к новым знаниям!', 'course-plugin'),
            'cta_button' => __('Записаться на курс', 'course-plugin'),
        );
        ?>
        
        <style>
            .course-section-toggle { 
                background: #f9f9f9; 
                border: 1px solid #ddd; 
                border-radius: 8px; 
                margin-bottom: 15px; 
                overflow: hidden;
            }
            .course-section-toggle .section-header { 
                padding: 12px 15px; 
                background: #fff; 
                border-bottom: 1px solid #eee;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .course-section-toggle .section-header label {
                font-weight: 600;
                flex: 1;
                cursor: pointer;
            }
            .course-section-toggle .section-content { 
                padding: 15px; 
                display: none;
            }
            .course-section-toggle.active .section-content { 
                display: block; 
            }
            .course-section-toggle .toggle-arrow {
                transition: transform 0.2s;
                cursor: pointer;
            }
            .course-section-toggle.active .toggle-arrow {
                transform: rotate(90deg);
            }
            .field-row {
                display: flex;
                align-items: center;
                gap: 10px;
                margin-bottom: 10px;
                padding: 8px;
                background: #fff;
                border-radius: 4px;
            }
            .field-row input[type="text"],
            .field-row textarea {
                flex: 1;
            }
            .extra-block {
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 6px;
                padding: 15px;
                margin-bottom: 10px;
            }
            .extra-block .block-header {
                display: flex;
                align-items: center;
                gap: 10px;
                margin-bottom: 10px;
            }
            .extra-block .remove-block {
                color: #dc3545;
                cursor: pointer;
                padding: 5px;
            }
            #add-extra-block {
                margin-top: 10px;
            }
            .sidebar-block-item {
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 6px;
                padding: 15px;
                margin-bottom: 10px;
            }
            .sidebar-block-item .block-header {
                display: flex;
                align-items: center;
                gap: 10px;
                margin-bottom: 10px;
                padding-bottom: 10px;
                border-bottom: 1px solid #eee;
            }
            .sidebar-block-item .remove-sidebar-block {
                color: #dc3545;
                cursor: pointer;
                margin-left: auto;
            }
            #add-sidebar-block {
                margin-top: 10px;
            }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Переключение секций
            $('.course-section-toggle .section-header').on('click', function(e) {
                if ($(e.target).is('input[type="checkbox"]')) return;
                $(this).closest('.course-section-toggle').toggleClass('active');
            });
            
            // Добавление дополнительного блока
            var blockIndex = <?php echo count($extra_blocks); ?>;
            $('#add-extra-block').on('click', function() {
                var html = '<div class="extra-block">' +
                    '<div class="block-header">' +
                        '<span class="dashicons dashicons-menu" style="cursor: move;"></span>' +
                        '<input type="text" name="course_extra_blocks[' + blockIndex + '][title]" placeholder="<?php esc_attr_e('Заголовок блока', 'course-plugin'); ?>" class="regular-text" />' +
                        '<span class="remove-block dashicons dashicons-trash"></span>' +
                    '</div>' +
                    '<textarea name="course_extra_blocks[' + blockIndex + '][content]" rows="4" class="large-text" placeholder="<?php esc_attr_e('Содержимое блока (поддерживается HTML)', 'course-plugin'); ?>"></textarea>' +
                '</div>';
                $('#extra-blocks-container').append(html);
                blockIndex++;
            });
            
            // Удаление блока
            $(document).on('click', '.remove-block', function() {
                $(this).closest('.extra-block').remove();
            });
            
            // Добавление блока сайдбара
            var sidebarBlockIndex = <?php echo count($sidebar_blocks); ?>;
            $('#add-sidebar-block').on('click', function() {
                var html = '<div class="sidebar-block-item" style="background: #fff; border: 1px solid #ddd; border-radius: 6px; padding: 15px; margin-bottom: 10px;">' +
                    '<div class="block-header" style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #eee;">' +
                        '<span class="dashicons dashicons-menu" style="cursor: move;"></span>' +
                        '<strong><?php _e('Блок', 'course-plugin'); ?> ' + (sidebarBlockIndex + 1) + '</strong>' +
                        '<span class="remove-sidebar-block dashicons dashicons-trash" style="color: #dc3545; cursor: pointer; margin-left: auto;"></span>' +
                    '</div>' +
                    '<table class="form-table" style="margin-top: 10px;">' +
                        '<tr>' +
                            '<th><label><?php _e('Заголовок блока', 'course-plugin'); ?></label></th>' +
                            '<td><input type="text" name="course_sidebar_blocks[' + sidebarBlockIndex + '][title]" class="regular-text" placeholder="<?php esc_attr_e('Например: Дополнительная информация', 'course-plugin'); ?>" /></td>' +
                        '</tr>' +
                        '<tr>' +
                            '<th><label><?php _e('Содержимое', 'course-plugin'); ?></label></th>' +
                            '<td><textarea name="course_sidebar_blocks[' + sidebarBlockIndex + '][content]" rows="4" class="large-text" placeholder="<?php esc_attr_e('HTML контент блока', 'course-plugin'); ?>"></textarea>' +
                            '<p class="description"><?php _e('Поддерживается HTML. Можно использовать для списков, ссылок, форматированного текста и т.д.', 'course-plugin'); ?></p></td>' +
                        '</tr>' +
                        '<tr>' +
                            '<th><label><?php _e('Тип блока', 'course-plugin'); ?></label></th>' +
                            '<td><select name="course_sidebar_blocks[' + sidebarBlockIndex + '][type]" class="regular-text">' +
                                '<option value="card"><?php _e('Карточка (с рамкой и фоном)', 'course-plugin'); ?></option>' +
                                '<option value="simple"><?php _e('Простой блок (без рамки)', 'course-plugin'); ?></option>' +
                                '<option value="info"><?php _e('Информационный блок (с иконкой)', 'course-plugin'); ?></option>' +
                            '</select></td>' +
                        '</tr>' +
                        '<tr>' +
                            '<th><label><?php _e('Иконка (опционально)', 'course-plugin'); ?></label></th>' +
                            '<td><input type="text" name="course_sidebar_blocks[' + sidebarBlockIndex + '][icon]" class="regular-text" placeholder="<?php esc_attr_e('CSS класс иконки или SVG код', 'course-plugin'); ?>" />' +
                            '<p class="description"><?php _e('Например: dashicons-info или вставьте SVG код', 'course-plugin'); ?></p></td>' +
                        '</tr>' +
                    '</table>' +
                '</div>';
                $('#sidebar-blocks-container').append(html);
                sidebarBlockIndex++;
            });
            
            // Удаление блока сайдбара
            $(document).on('click', '.remove-sidebar-block', function() {
                $(this).closest('.sidebar-block-item').remove();
            });
        });
        </script>
        
        <!-- Управление видимостью секций -->
        <div class="course-section-toggle active">
            <div class="section-header">
                <span class="toggle-arrow dashicons dashicons-arrow-right-alt2"></span>
                <label><?php _e('Управление видимостью секций', 'course-plugin'); ?></label>
            </div>
            <div class="section-content">
                <!-- Скрытое поле для отслеживания, что секция была обработана -->
                <input type="hidden" name="course_visibility_processed" value="1" />
                <p class="description"><?php _e('Отметьте секции, которые должны отображаться на странице курса:', 'course-plugin'); ?></p>
                <table class="form-table">
                    <tr>
                        <td style="width: 50%;">
                            <label><input type="checkbox" name="course_show_description" value="1" <?php checked($show_description !== '0'); ?> /> <?php _e('Секция "Описание курса"', 'course-plugin'); ?></label><br>
                            <label><input type="checkbox" name="course_show_goals" value="1" <?php checked($show_goals !== '0'); ?> /> <?php _e('Секция "Цели и задачи"', 'course-plugin'); ?></label><br>
                            <label><input type="checkbox" name="course_show_content" value="1" <?php checked($show_content !== '0'); ?> /> <?php _e('Секция "Содержание курса"', 'course-plugin'); ?></label><br>
                            <label><input type="checkbox" name="course_show_video" value="1" <?php checked($show_video !== '0'); ?> /> <?php _e('Секция "Видео о курсе"', 'course-plugin'); ?></label><br>
                            <label><input type="checkbox" name="course_show_related" value="1" <?php checked($show_related !== '0'); ?> /> <?php _e('Секция "Другие курсы"', 'course-plugin'); ?></label>
                        </td>
                        <td>
                            <label><input type="checkbox" name="course_show_sidebar" value="1" <?php checked($show_sidebar !== '0'); ?> /> <?php _e('Сайдбар с обзором', 'course-plugin'); ?></label><br>
                            <label><input type="checkbox" name="course_show_cta" value="1" <?php checked($show_cta !== '0'); ?> /> <?php _e('CTA блок внизу страницы', 'course-plugin'); ?></label><br>
                            <label><input type="checkbox" name="course_show_price" value="1" <?php checked($show_price !== '0'); ?> /> <?php _e('Блок с ценой', 'course-plugin'); ?></label><br>
                            <label><input type="checkbox" name="course_show_teacher" value="1" <?php checked($show_teacher !== '0'); ?> /> <?php _e('Карточка преподавателя', 'course-plugin'); ?></label>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <!-- Поля в шапке (Hero) -->
        <div class="course-section-toggle">
            <div class="section-header">
                <span class="toggle-arrow dashicons dashicons-arrow-right-alt2"></span>
                <label><?php _e('Поля в шапке страницы (Hero)', 'course-plugin'); ?></label>
            </div>
            <div class="section-content">
                <p class="description"><?php _e('Выберите, какие теги и статистика показываются в шапке:', 'course-plugin'); ?></p>
                <label><input type="checkbox" name="course_show_hero_code" value="1" <?php checked($show_hero_code !== '0'); ?> /> <?php _e('Код курса', 'course-plugin'); ?></label><br>
                <label><input type="checkbox" name="course_show_hero_level" value="1" <?php checked($show_hero_level !== '0'); ?> /> <?php _e('Уровень сложности', 'course-plugin'); ?></label><br>
                <label><input type="checkbox" name="course_show_hero_dates" value="1" <?php checked($show_hero_dates !== '0'); ?> /> <?php _e('Даты проведения', 'course-plugin'); ?></label><br>
                <label><input type="checkbox" name="course_show_hero_duration" value="1" <?php checked($show_hero_duration !== '0'); ?> /> <?php _e('Длительность', 'course-plugin'); ?></label><br>
                <label><input type="checkbox" name="course_show_hero_language" value="1" <?php checked($show_hero_language !== '0'); ?> /> <?php _e('Язык курса', 'course-plugin'); ?></label><br>
                <label><input type="checkbox" name="course_show_hero_certificate" value="1" <?php checked($show_hero_certificate !== '0'); ?> /> <?php _e('Наличие сертификата', 'course-plugin'); ?></label><br>
                <label><input type="checkbox" name="course_show_hero_location" value="1" <?php checked($show_hero_location !== '0'); ?> /> <?php _e('Место проведения', 'course-plugin'); ?></label>
            </div>
        </div>
        
        <!-- Поля в сайдбаре -->
        <div class="course-section-toggle">
            <div class="section-header">
                <span class="toggle-arrow dashicons dashicons-arrow-right-alt2"></span>
                <label><?php _e('Поля в сайдбаре "Краткий обзор"', 'course-plugin'); ?></label>
            </div>
            <div class="section-content">
                <p class="description"><?php _e('Выберите, какие поля показывать в карточке обзора:', 'course-plugin'); ?></p>
                <label><input type="checkbox" name="course_show_field_language" value="1" <?php checked($show_field_language !== '0'); ?> /> <?php _e('Язык курса', 'course-plugin'); ?></label><br>
                <label><input type="checkbox" name="course_show_field_weeks" value="1" <?php checked($show_field_weeks !== '0'); ?> /> <?php _e('Количество недель', 'course-plugin'); ?></label><br>
                <label><input type="checkbox" name="course_show_field_credits" value="1" <?php checked($show_field_credits !== '0'); ?> /> <?php _e('Кредиты', 'course-plugin'); ?></label><br>
                <label><input type="checkbox" name="course_show_field_hours" value="1" <?php checked($show_field_hours !== '0'); ?> /> <?php _e('Часов в неделю', 'course-plugin'); ?></label><br>
                <label><input type="checkbox" name="course_show_field_certificate" value="1" <?php checked($show_field_certificate !== '0'); ?> /> <?php _e('Сертификат', 'course-plugin'); ?></label>
                
                <hr style="margin: 15px 0;">
                <p><strong><?php _e('Заголовок сайдбара:', 'course-plugin'); ?></strong></p>
                <input type="text" name="course_sidebar_overview_title" value="<?php echo esc_attr($sidebar_overview_title); ?>" class="large-text" placeholder="<?php echo esc_attr($defaults['sidebar_overview']); ?>" />
            </div>
        </div>
        
        <!-- Кастомные блоки сайдбара -->
        <div class="course-section-toggle">
            <div class="section-header">
                <span class="toggle-arrow dashicons dashicons-arrow-right-alt2"></span>
                <label><?php _e('Кастомные блоки сайдбара', 'course-plugin'); ?></label>
            </div>
            <div class="section-content">
                <p class="description"><?php _e('Добавьте дополнительные блоки в сайдбар страницы курса. Вы можете создать неограниченное количество блоков с произвольным содержимым:', 'course-plugin'); ?></p>
                
                <div id="sidebar-blocks-container">
                    <?php foreach ($sidebar_blocks as $index => $block) : ?>
                        <div class="sidebar-block-item">
                            <div class="block-header">
                                <span class="dashicons dashicons-menu" style="cursor: move;"></span>
                                <strong><?php _e('Блок', 'course-plugin'); ?> <?php echo $index + 1; ?></strong>
                                <span class="remove-sidebar-block dashicons dashicons-trash"></span>
                            </div>
                            <table class="form-table" style="margin-top: 10px;">
                                <tr>
                                    <th><label><?php _e('Заголовок блока', 'course-plugin'); ?></label></th>
                                    <td>
                                        <input type="text" name="course_sidebar_blocks[<?php echo $index; ?>][title]" value="<?php echo esc_attr($block['title']); ?>" class="regular-text" placeholder="<?php esc_attr_e('Например: Дополнительная информация', 'course-plugin'); ?>" />
                                    </td>
                                </tr>
                                <tr>
                                    <th><label><?php _e('Содержимое', 'course-plugin'); ?></label></th>
                                    <td>
                                        <textarea name="course_sidebar_blocks[<?php echo $index; ?>][content]" rows="4" class="large-text" placeholder="<?php esc_attr_e('HTML контент блока', 'course-plugin'); ?>"><?php echo esc_textarea($block['content']); ?></textarea>
                                        <p class="description"><?php _e('Поддерживается HTML. Можно использовать для списков, ссылок, форматированного текста и т.д.', 'course-plugin'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label><?php _e('Тип блока', 'course-plugin'); ?></label></th>
                                    <td>
                                        <select name="course_sidebar_blocks[<?php echo $index; ?>][type]" class="regular-text">
                                            <option value="card" <?php selected($block['type'], 'card'); ?>><?php _e('Карточка (с рамкой и фоном)', 'course-plugin'); ?></option>
                                            <option value="simple" <?php selected($block['type'], 'simple'); ?>><?php _e('Простой блок (без рамки)', 'course-plugin'); ?></option>
                                            <option value="info" <?php selected($block['type'], 'info'); ?>><?php _e('Информационный блок (с иконкой)', 'course-plugin'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label><?php _e('Иконка (опционально)', 'course-plugin'); ?></label></th>
                                    <td>
                                        <input type="text" name="course_sidebar_blocks[<?php echo $index; ?>][icon]" value="<?php echo esc_attr($block['icon']); ?>" class="regular-text" placeholder="<?php esc_attr_e('CSS класс иконки или SVG код', 'course-plugin'); ?>" />
                                        <p class="description"><?php _e('Например: dashicons-info или вставьте SVG код', 'course-plugin'); ?></p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <button type="button" id="add-sidebar-block" class="button"><?php _e('+ Добавить блок в сайдбар', 'course-plugin'); ?></button>
            </div>
        </div>
        
        <!-- Секция описания -->
        <div class="course-section-toggle">
            <div class="section-header">
                <span class="toggle-arrow dashicons dashicons-arrow-right-alt2"></span>
                <label><?php _e('Секция "Описание курса"', 'course-plugin'); ?></label>
            </div>
            <div class="section-content">
                <p><strong><?php _e('Заголовок секции:', 'course-plugin'); ?></strong></p>
                <input type="text" name="course_section_description_title" value="<?php echo esc_attr($section_description_title); ?>" class="large-text" placeholder="<?php echo esc_attr($defaults['section_description']); ?>" />
            </div>
        </div>
        
        <!-- Секция целей -->
        <div class="course-section-toggle">
            <div class="section-header">
                <span class="toggle-arrow dashicons dashicons-arrow-right-alt2"></span>
                <label><?php _e('Секция "Цели и задачи"', 'course-plugin'); ?></label>
            </div>
            <div class="section-content">
                <p><strong><?php _e('Заголовок секции:', 'course-plugin'); ?></strong></p>
                <input type="text" name="course_section_goals_title" value="<?php echo esc_attr($section_goals_title); ?>" class="large-text" placeholder="<?php echo esc_attr($defaults['section_goals']); ?>" />
                
                <p><strong><?php _e('Подзаголовок:', 'course-plugin'); ?></strong></p>
                <input type="text" name="course_section_goals_intro" value="<?php echo esc_attr($section_goals_intro); ?>" class="large-text" placeholder="<?php echo esc_attr($defaults['section_goals_intro']); ?>" />
                
                <hr style="margin: 15px 0;">
                <p><strong><?php _e('Названия блоков целей:', 'course-plugin'); ?></strong></p>
                
                <div class="field-row">
                    <span><?php _e('Когнитивные:', 'course-plugin'); ?></span>
                    <input type="text" name="course_goal_cognitive_title" value="<?php echo esc_attr($goal_cognitive_title); ?>" placeholder="<?php echo esc_attr($defaults['goal_cognitive']); ?>" />
                    <input type="text" name="course_goal_cognitive_subtitle" value="<?php echo esc_attr($goal_cognitive_subtitle); ?>" style="width: 100px;" placeholder="<?php echo esc_attr($defaults['goal_cognitive_sub']); ?>" />
                </div>
                
                <div class="field-row">
                    <span><?php _e('Эмоциональные:', 'course-plugin'); ?></span>
                    <input type="text" name="course_goal_emotional_title" value="<?php echo esc_attr($goal_emotional_title); ?>" placeholder="<?php echo esc_attr($defaults['goal_emotional']); ?>" />
                    <input type="text" name="course_goal_emotional_subtitle" value="<?php echo esc_attr($goal_emotional_subtitle); ?>" style="width: 100px;" placeholder="<?php echo esc_attr($defaults['goal_emotional_sub']); ?>" />
                </div>
                
                <div class="field-row">
                    <span><?php _e('Психомоторные:', 'course-plugin'); ?></span>
                    <input type="text" name="course_goal_psychomotor_title" value="<?php echo esc_attr($goal_psychomotor_title); ?>" placeholder="<?php echo esc_attr($defaults['goal_psychomotor']); ?>" />
                    <input type="text" name="course_goal_psychomotor_subtitle" value="<?php echo esc_attr($goal_psychomotor_subtitle); ?>" style="width: 100px;" placeholder="<?php echo esc_attr($defaults['goal_psychomotor_sub']); ?>" />
                </div>
            </div>
        </div>
        
        <!-- Секция содержания -->
        <div class="course-section-toggle">
            <div class="section-header">
                <span class="toggle-arrow dashicons dashicons-arrow-right-alt2"></span>
                <label><?php _e('Секция "Содержание курса"', 'course-plugin'); ?></label>
            </div>
            <div class="section-content">
                <p><strong><?php _e('Заголовок секции:', 'course-plugin'); ?></strong></p>
                <input type="text" name="course_section_content_title" value="<?php echo esc_attr($section_content_title); ?>" class="large-text" placeholder="<?php echo esc_attr($defaults['section_content']); ?>" />
            </div>
        </div>
        
        <!-- Секция видео -->
        <div class="course-section-toggle">
            <div class="section-header">
                <span class="toggle-arrow dashicons dashicons-arrow-right-alt2"></span>
                <label><?php _e('Секция "Видео о курсе"', 'course-plugin'); ?></label>
            </div>
            <div class="section-content">
                <p><strong><?php _e('Заголовок секции:', 'course-plugin'); ?></strong></p>
                <input type="text" name="course_section_video_title" value="<?php echo esc_attr($section_video_title); ?>" class="large-text" placeholder="<?php echo esc_attr($defaults['section_video']); ?>" />
            </div>
        </div>
        
        <!-- Секция похожих курсов -->
        <div class="course-section-toggle">
            <div class="section-header">
                <span class="toggle-arrow dashicons dashicons-arrow-right-alt2"></span>
                <label><?php _e('Секция "Другие курсы"', 'course-plugin'); ?></label>
            </div>
            <div class="section-content">
                <p><strong><?php _e('Заголовок секции:', 'course-plugin'); ?></strong></p>
                <input type="text" name="course_section_related_title" value="<?php echo esc_attr($section_related_title); ?>" class="large-text" placeholder="<?php echo esc_attr($defaults['section_related']); ?>" />
            </div>
        </div>
        
        <!-- Кнопки действий -->
        <div class="course-section-toggle">
            <div class="section-header">
                <span class="toggle-arrow dashicons dashicons-arrow-right-alt2"></span>
                <label><?php _e('Кнопки действий', 'course-plugin'); ?></label>
            </div>
            <div class="section-content">
                <div class="field-row">
                    <span style="min-width: 150px;"><?php _e('Кнопка "Записаться":', 'course-plugin'); ?></span>
                    <input type="text" name="course_btn_enroll_text" value="<?php echo esc_attr($btn_enroll_text); ?>" placeholder="<?php echo esc_attr($defaults['btn_enroll']); ?>" />
                </div>
                <div class="field-row">
                    <span style="min-width: 150px;"><?php _e('Кнопка "Для студентов":', 'course-plugin'); ?></span>
                    <input type="text" name="course_btn_student_text" value="<?php echo esc_attr($btn_student_text); ?>" placeholder="<?php echo esc_attr($defaults['btn_student']); ?>" />
                </div>
                <div class="field-row">
                    <span style="min-width: 150px;"><?php _e('Кнопка "Лайт курс":', 'course-plugin'); ?></span>
                    <input type="text" name="course_btn_lite_text" value="<?php echo esc_attr($btn_lite_text); ?>" placeholder="<?php echo esc_attr($defaults['btn_lite']); ?>" />
                </div>
            </div>
        </div>
        
        <!-- CTA блок -->
        <div class="course-section-toggle">
            <div class="section-header">
                <span class="toggle-arrow dashicons dashicons-arrow-right-alt2"></span>
                <label><?php _e('CTA блок (призыв к действию)', 'course-plugin'); ?></label>
            </div>
            <div class="section-content">
                <p><strong><?php _e('Заголовок:', 'course-plugin'); ?></strong></p>
                <input type="text" name="course_cta_title" value="<?php echo esc_attr($cta_title); ?>" class="large-text" placeholder="<?php echo esc_attr($defaults['cta_title']); ?>" />
                
                <p><strong><?php _e('Текст:', 'course-plugin'); ?></strong></p>
                <textarea name="course_cta_text" rows="2" class="large-text" placeholder="<?php echo esc_attr($defaults['cta_text']); ?>"><?php echo esc_textarea($cta_text); ?></textarea>
                
                <p><strong><?php _e('Текст кнопки:', 'course-plugin'); ?></strong></p>
                <input type="text" name="course_cta_button_text" value="<?php echo esc_attr($cta_button_text); ?>" class="regular-text" placeholder="<?php echo esc_attr($defaults['cta_button']); ?>" />
            </div>
        </div>
        
        <!-- Дополнительные блоки контента -->
        <div class="course-section-toggle">
            <div class="section-header">
                <span class="toggle-arrow dashicons dashicons-arrow-right-alt2"></span>
                <label><?php _e('Дополнительные блоки контента', 'course-plugin'); ?></label>
            </div>
            <div class="section-content">
                <p class="description"><?php _e('Добавьте дополнительные секции с произвольным контентом:', 'course-plugin'); ?></p>
                
                <div id="extra-blocks-container">
                    <?php foreach ($extra_blocks as $index => $block) : ?>
                        <div class="extra-block">
                            <div class="block-header">
                                <span class="dashicons dashicons-menu" style="cursor: move;"></span>
                                <input type="text" name="course_extra_blocks[<?php echo $index; ?>][title]" value="<?php echo esc_attr($block['title']); ?>" placeholder="<?php esc_attr_e('Заголовок блока', 'course-plugin'); ?>" class="regular-text" />
                                <span class="remove-block dashicons dashicons-trash"></span>
                            </div>
                            <textarea name="course_extra_blocks[<?php echo $index; ?>][content]" rows="4" class="large-text" placeholder="<?php esc_attr_e('Содержимое блока (поддерживается HTML)', 'course-plugin'); ?>"><?php echo esc_textarea($block['content']); ?></textarea>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <button type="button" id="add-extra-block" class="button"><?php _e('+ Добавить блок', 'course-plugin'); ?></button>
            </div>
        </div>
        
        <p class="description" style="margin-top: 15px; padding: 10px; background: #f0f0f1; border-radius: 4px;">
            <strong><?php _e('Подсказка:', 'course-plugin'); ?></strong> 
            <?php _e('Оставьте текстовые поля пустыми, чтобы использовать значения по умолчанию. Снимите галочки, чтобы скрыть соответствующие элементы.', 'course-plugin'); ?>
        </p>
        <?php
    }
    
    /**
     * Рендеринг метабокса "Настройка карточки курса"
     * Отображает форму с настройками отображения карточки курса в архиве
     * 
     * @param WP_Post $post Объект текущего курса
     */
    public function render_course_card_settings_meta_box($post) {
        // Получаем значения метаполей из базы данных
        $show_card_icon = get_post_meta($post->ID, '_course_show_card_icon', true);
        $card_icon_type = get_post_meta($post->ID, '_course_card_icon_type', true);
        
        // Инициализация по умолчанию
        if ($show_card_icon === '') {
            $show_card_icon = '1'; // По умолчанию показывать иконку
        }
        if ($card_icon_type === '') {
            $card_icon_type = 'default'; // По умолчанию стандартная иконка
        }
        
        ?>
        <table class="form-table">
            <tr>
                <th>
                    <label for="course_show_card_icon"><?php _e('Показывать иконку на карточке', 'course-plugin'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" id="course_show_card_icon" name="course_show_card_icon" value="1" <?php checked($show_card_icon, '1'); ?> />
                        <?php _e('Отображать иконку на карточке курса в архиве', 'course-plugin'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th>
                    <label for="course_card_icon_type"><?php _e('Тип иконки', 'course-plugin'); ?></label>
                </th>
                <td>
                    <select id="course_card_icon_type" name="course_card_icon_type" class="regular-text">
                        <option value="default" <?php selected($card_icon_type, 'default'); ?>><?php _e('По умолчанию (автоматически)', 'course-plugin'); ?></option>
                        <option value="book" <?php selected($card_icon_type, 'book'); ?>><?php _e('Книга', 'course-plugin'); ?></option>
                        <option value="layers" <?php selected($card_icon_type, 'layers'); ?>><?php _e('Слои', 'course-plugin'); ?></option>
                        <option value="clock" <?php selected($card_icon_type, 'clock'); ?>><?php _e('Часы', 'course-plugin'); ?></option>
                        <option value="home" <?php selected($card_icon_type, 'home'); ?>><?php _e('Дом', 'course-plugin'); ?></option>
                    </select>
                    <p class="description"><?php _e('Выберите тип иконки для отображения на карточке курса', 'course-plugin'); ?></p>
                </td>
            </tr>
        </table>
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
            'course_code',                // Код курса (отображается в шапке)
            'course_duration',            // Продолжительность курса
            'course_price',               // Стоимость курса
            'course_old_price',           // Старая цена (для скидки)
            'course_start_date',          // Дата начала курса
            'course_end_date',            // Дата окончания курса
            'course_location',            // Место прохождения курса
            'course_capacity',            // Вместимость (количество мест)
            'course_enrolled',            // Количество записанных студентов
            'course_rating',              // Рейтинг курса (1-5)
            'course_reviews_count',       // Количество отзывов
            'course_tag',                 // Тег курса (для отображения на карточке)
            'course_additional_text',     // Дополнительный текст на карточке
            'course_seminary_new_url',    // Ссылка на кнопку "Курс на семинарском уровне (не студент)"
            'course_seminary_student_url', // Ссылка на кнопку "Курс на семинарском уровне (студент)"
            'course_lite_course_url',      // Ссылка на кнопку "Лайт курс"
            // Поля для сайдбара "Краткий обзор курса"
            'course_weeks',               // Количество недель
            'course_credits',             // Кредиты
            'course_hours_per_week',      // Часов в неделю
            'course_language',            // Язык курса
            // Тексты страницы
            'course_cta_title',           // Заголовок CTA блока
            'course_cta_text',            // Текст CTA блока
            'course_cta_button_text',     // Текст кнопки CTA
            // Заголовки секций
            'course_section_description_title',
            'course_section_goals_title',
            'course_section_goals_intro',
            'course_section_content_title',
            'course_section_video_title',
            'course_section_related_title',
            'course_sidebar_overview_title',
            // Названия целей
            'course_goal_cognitive_title',
            'course_goal_cognitive_subtitle',
            'course_goal_emotional_title',
            'course_goal_emotional_subtitle',
            'course_goal_psychomotor_title',
            'course_goal_psychomotor_subtitle',
            // Тексты кнопок
            'course_btn_enroll_text',
            'course_btn_student_text',
            'course_btn_lite_text',
        );
        
        // Чекбоксы видимости секций
        $visibility_fields = array(
            'course_show_description',
            'course_show_goals',
            'course_show_content',
            'course_show_video',
            'course_show_related',
            'course_show_sidebar',
            'course_show_cta',
            'course_show_price',
            'course_show_teacher',
            // Поля в hero
            'course_show_hero_code',
            'course_show_hero_level',
            'course_show_hero_dates',
            'course_show_hero_duration',
            'course_show_hero_language',
            'course_show_hero_certificate',
            'course_show_hero_location',
            // Поля в сайдбаре
            'course_show_field_language',
            'course_show_field_weeks',
            'course_show_field_credits',
            'course_show_field_hours',
            'course_show_field_certificate',
        );
        
        // Сохраняем чекбоксы видимости
        // Сохраняем только если секция управления видимостью была обработана
        // Если чекбокс отмечен - сохраняем '1', если снят - сохраняем '0'
        // Если секция не была обработана (старый курс), не трогаем мета-поля (оставляем пустыми = видимо по умолчанию)
        if (isset($_POST['course_visibility_processed']) && $_POST['course_visibility_processed'] === '1') {
            foreach ($visibility_fields as $field) {
                // Если чекбокс отмечен, он будет в POST со значением '1'
                // Если чекбокс снят, его не будет в POST вообще
                $value = isset($_POST[$field]) && $_POST[$field] === '1' ? '1' : '0';
                update_post_meta($post_id, '_' . $field, $value);
            }
        }
        // Если секция не была обработана, не трогаем мета-поля (оставляем пустыми = видимо по умолчанию)
        
        // Сохраняем настройки карточки
        // Показывать иконку на карточке
        $show_card_icon = isset($_POST['course_show_card_icon']) && $_POST['course_show_card_icon'] === '1' ? '1' : '0';
        update_post_meta($post_id, '_course_show_card_icon', $show_card_icon);
        
        // Тип иконки
        if (isset($_POST['course_card_icon_type'])) {
            $card_icon_type = sanitize_text_field($_POST['course_card_icon_type']);
            update_post_meta($post_id, '_course_card_icon_type', $card_icon_type);
        }
        
        // Сохраняем дополнительные блоки контента
        if (isset($_POST['course_extra_blocks']) && is_array($_POST['course_extra_blocks'])) {
            $extra_blocks = array();
            foreach ($_POST['course_extra_blocks'] as $block) {
                if (!empty($block['title']) || !empty($block['content'])) {
                    $extra_blocks[] = array(
                        'title' => sanitize_text_field($block['title']),
                        'content' => wp_kses_post($block['content']),
                    );
                }
            }
            update_post_meta($post_id, '_course_extra_blocks', $extra_blocks);
        } else {
            delete_post_meta($post_id, '_course_extra_blocks');
        }
        
        // Сохраняем кастомные блоки сайдбара
        if (isset($_POST['course_sidebar_blocks']) && is_array($_POST['course_sidebar_blocks'])) {
            $sidebar_blocks = array();
            foreach ($_POST['course_sidebar_blocks'] as $block) {
                if (!empty($block['title']) || !empty($block['content'])) {
                    $sidebar_blocks[] = array(
                        'title' => sanitize_text_field($block['title']),
                        'content' => wp_kses_post($block['content']),
                        'type' => sanitize_text_field(isset($block['type']) ? $block['type'] : 'card'),
                        'icon' => sanitize_text_field(isset($block['icon']) ? $block['icon'] : ''),
                    );
                }
            }
            update_post_meta($post_id, '_course_sidebar_blocks', $sidebar_blocks);
        } else {
            delete_post_meta($post_id, '_course_sidebar_blocks');
        }
        
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
        
        // Сохраняем поля целей курса отдельно (содержат HTML, нужна специальная очистка)
        // wp_kses_post() очищает HTML от опасных тегов, но сохраняет безопасные (p, ul, li, strong, em и т.д.)
        $goals_fields = array(
            'course_cognitive_goals',
            'course_emotional_goals',
            'course_psychomotor_goals',
        );
        
        foreach ($goals_fields as $field) {
            if (isset($_POST[$field])) {
                // wp_kses_post() очищает HTML и сохраняет только безопасные теги
                $value = wp_kses_post($_POST[$field]);
                update_post_meta($post_id, '_' . $field, $value);
            } else {
                // Если поле не заполнено, удаляем метаполе
                delete_post_meta($post_id, '_' . $field);
            }
        }
        
        // Сохраняем поле "Содержание курса" (содержит HTML)
        if (isset($_POST['course_content'])) {
            $value = wp_kses_post($_POST['course_content']);
            update_post_meta($post_id, '_course_content', $value);
        } else {
            delete_post_meta($post_id, '_course_content');
        }
        
        // Сохраняем поле "Видео о курсе" (URL)
        if (isset($_POST['course_video_url'])) {
            $value = esc_url_raw($_POST['course_video_url']);
            update_post_meta($post_id, '_course_video_url', $value);
        } else {
            delete_post_meta($post_id, '_course_video_url');
        }
        
        // Сохраняем поле "Сертификат" (чекбокс) отдельно
        // Если чекбокс отмечен, сохраняем '1', если нет - '0'
        $course_certificate = isset($_POST['course_certificate']) && $_POST['course_certificate'] === '1' ? '1' : '0';
        update_post_meta($post_id, '_course_certificate', $course_certificate);
        
        // Проходим по каждому полю из массива $fields
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
