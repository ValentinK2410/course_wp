<?php
/**
 * Класс для метаполей преподавателей
 * 
 * Добавляет дополнительные поля для таксономии "Преподаватель":
 * - Фото преподавателя
 * - Описание преподавателя
 * - Должность
 * - Образование
 * - Email
 * - Телефон
 * - Социальные сети
 * 
 * @copyright Copyright (c) 2024 Кузьменко Валентин (Valentink2410)
 * @author Кузьменко Валентин (Valentink2410)
 */

// Проверка безопасности: если файл вызывается напрямую, прекращаем выполнение
if (!defined('ABSPATH')) {
    exit;
}

class Course_Teacher_Meta {
    
    /**
     * Единственный экземпляр класса (Singleton)
     */
    private static $instance = null;
    
    /**
     * Получить экземпляр класса
     * 
     * @return Course_Teacher_Meta Экземпляр класса
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Конструктор класса
     * Регистрирует хуки для добавления полей в форму редактирования преподавателя
     */
    private function __construct() {
        // Добавляем поля при редактировании преподавателя
        add_action('course_teacher_edit_form_fields', array($this, 'edit_teacher_fields'), 10, 2);
        add_action('course_teacher_add_form_fields', array($this, 'add_teacher_fields'), 10, 1);
        
        // Сохраняем поля при сохранении преподавателя
        add_action('edited_course_teacher', array($this, 'save_teacher_fields'), 10, 2);
        add_action('created_course_teacher', array($this, 'save_teacher_fields'), 10, 2);
        
        // Добавляем колонку с фото в список преподавателей
        add_filter('manage_edit-course_teacher_columns', array($this, 'add_teacher_columns'));
        add_filter('manage_course_teacher_custom_column', array($this, 'render_teacher_columns'), 10, 3);
        
        // Подключаем скрипты WordPress Media для работы с медиа-библиотекой
        add_action('admin_enqueue_scripts', array($this, 'enqueue_media_scripts'));
        
        // Синхронизация TinyMCE с textarea перед отправкой формы (wp_editor может не передать контент)
        add_action('admin_footer-edit-tags.php', array($this, 'add_tinymce_save_before_submit'));
    }
    
    /**
     * Подключение скриптов WordPress Media для выбора изображений
     * 
     * @param string $hook Название текущей страницы админ-панели
     */
    public function enqueue_media_scripts($hook) {
        // Подключаем только на страницах редактирования таксономии преподавателей
        if ($hook === 'edit-tags.php' || $hook === 'term.php') {
            $screen = get_current_screen();
            if ($screen && $screen->taxonomy === 'course_teacher') {
                // Подключаем скрипты WordPress Media API
                wp_enqueue_media();
            }
        }
    }
    
    /**
     * Добавление полей при редактировании существующего преподавателя
     * 
     * @param WP_Term $term Объект термина (преподавателя)
     * @param string $taxonomy Название таксономии
     */
    public function edit_teacher_fields($term, $taxonomy) {
        // Получаем значения метаполей
        $photo = get_term_meta($term->term_id, 'teacher_photo', true);
        $description = get_term_meta($term->term_id, 'teacher_description', true);
        $position = get_term_meta($term->term_id, 'teacher_position', true);
        $education = get_term_meta($term->term_id, 'teacher_education', true);
        $email = get_term_meta($term->term_id, 'teacher_email', true);
        $phone = get_term_meta($term->term_id, 'teacher_phone', true);
        $website = get_term_meta($term->term_id, 'teacher_website', true);
        $facebook = get_term_meta($term->term_id, 'teacher_facebook', true);
        $twitter = get_term_meta($term->term_id, 'teacher_twitter', true);
        $linkedin = get_term_meta($term->term_id, 'teacher_linkedin', true);
        ?>
        <tr class="form-field">
            <th scope="row">
                <label for="teacher_photo"><?php _e('Фото преподавателя', 'course-plugin'); ?></label>
            </th>
            <td>
                <input type="text" id="teacher_photo" name="teacher_photo" value="<?php echo esc_attr($photo); ?>" class="regular-text" />
                <button type="button" class="button button-secondary" id="teacher_photo_button"><?php _e('Выбрать изображение', 'course-plugin'); ?></button>
                <?php if ($photo) : ?>
                    <p class="description">
                        <img src="<?php echo esc_url($photo); ?>" style="max-width: 200px; margin-top: 10px; display: block;" />
                    </p>
                <?php endif; ?>
            </td>
        </tr>
        
        <tr class="form-field">
            <th scope="row">
                <label for="teacher_description"><?php _e('Описание преподавателя', 'course-plugin'); ?></label>
            </th>
            <td>
                <?php
                wp_editor($description, 'teacher_description', array(
                    'textarea_name' => 'teacher_description',
                    'textarea_rows' => 10,
                    'media_buttons' => false,
                ));
                ?>
            </td>
        </tr>
        
        <tr class="form-field">
            <th scope="row">
                <label for="teacher_position"><?php _e('Должность', 'course-plugin'); ?></label>
            </th>
            <td>
                <input type="text" id="teacher_position" name="teacher_position" value="<?php echo esc_attr($position); ?>" class="regular-text" />
            </td>
        </tr>
        
        <tr class="form-field">
            <th scope="row">
                <label for="teacher_education"><?php _e('Образование', 'course-plugin'); ?></label>
            </th>
            <td>
                <textarea id="teacher_education" name="teacher_education" rows="3" class="large-text"><?php echo esc_textarea($education); ?></textarea>
            </td>
        </tr>
        
        <tr class="form-field">
            <th scope="row">
                <label for="teacher_email"><?php _e('Email', 'course-plugin'); ?></label>
            </th>
            <td>
                <input type="email" id="teacher_email" name="teacher_email" value="<?php echo esc_attr($email); ?>" class="regular-text" />
            </td>
        </tr>
        
        <tr class="form-field">
            <th scope="row">
                <label for="teacher_phone"><?php _e('Телефон', 'course-plugin'); ?></label>
            </th>
            <td>
                <input type="text" id="teacher_phone" name="teacher_phone" value="<?php echo esc_attr($phone); ?>" class="regular-text" />
            </td>
        </tr>
        
        <tr class="form-field">
            <th scope="row">
                <label for="teacher_website"><?php _e('Веб-сайт', 'course-plugin'); ?></label>
            </th>
            <td>
                <input type="url" id="teacher_website" name="teacher_website" value="<?php echo esc_attr($website); ?>" class="regular-text" />
            </td>
        </tr>
        
        <tr class="form-field">
            <th scope="row">
                <label><?php _e('Социальные сети', 'course-plugin'); ?></label>
            </th>
            <td>
                <p>
                    <label for="teacher_facebook">Facebook:</label><br />
                    <input type="url" id="teacher_facebook" name="teacher_facebook" value="<?php echo esc_attr($facebook); ?>" class="regular-text" />
                </p>
                <p>
                    <label for="teacher_twitter">Twitter:</label><br />
                    <input type="url" id="teacher_twitter" name="teacher_twitter" value="<?php echo esc_attr($twitter); ?>" class="regular-text" />
                </p>
                <p>
                    <label for="teacher_linkedin">LinkedIn:</label><br />
                    <input type="url" id="teacher_linkedin" name="teacher_linkedin" value="<?php echo esc_attr($linkedin); ?>" class="regular-text" />
                </p>
            </td>
        </tr>
        
        <script>
        jQuery(document).ready(function($) {
            $('#teacher_photo_button').on('click', function(e) {
                e.preventDefault();
                var button = $(this);
                var input = $('#teacher_photo');
                
                var frame = wp.media({
                    title: '<?php _e('Выберите фото преподавателя', 'course-plugin'); ?>',
                    button: {
                        text: '<?php _e('Использовать это изображение', 'course-plugin'); ?>'
                    },
                    multiple: false
                });
                
                frame.on('select', function() {
                    var attachment = frame.state().get('selection').first().toJSON();
                    input.val(attachment.url);
                    if (input.next('.description').length === 0) {
                        input.after('<p class="description"><img src="' + attachment.url + '" style="max-width: 200px; margin-top: 10px; display: block;" /></p>');
                    } else {
                        input.next('.description').find('img').attr('src', attachment.url);
                    }
                });
                
                frame.open();
            });
        });
        </script>
        <?php
    }
    
    /**
     * Добавление полей при создании нового преподавателя
     * 
     * @param string $taxonomy Название таксономии
     */
    public function add_teacher_fields($taxonomy) {
        ?>
        <div class="form-field">
            <label for="teacher_photo"><?php _e('Фото преподавателя', 'course-plugin'); ?></label>
            <input type="text" id="teacher_photo" name="teacher_photo" value="" class="regular-text" />
            <button type="button" class="button button-secondary" id="teacher_photo_button"><?php _e('Выбрать изображение', 'course-plugin'); ?></button>
            <p class="description"><?php _e('URL изображения преподавателя', 'course-plugin'); ?></p>
        </div>
        
        <div class="form-field">
            <label for="teacher_description"><?php _e('Описание преподавателя', 'course-plugin'); ?></label>
            <?php
            wp_editor('', 'teacher_description', array(
                'textarea_name' => 'teacher_description',
                'textarea_rows' => 10,
                'media_buttons' => false,
            ));
            ?>
        </div>
        
        <div class="form-field">
            <label for="teacher_position"><?php _e('Должность', 'course-plugin'); ?></label>
            <input type="text" id="teacher_position" name="teacher_position" value="" class="regular-text" />
        </div>
        
        <div class="form-field">
            <label for="teacher_education"><?php _e('Образование', 'course-plugin'); ?></label>
            <textarea id="teacher_education" name="teacher_education" rows="3" class="large-text"></textarea>
        </div>
        
        <div class="form-field">
            <label for="teacher_email"><?php _e('Email', 'course-plugin'); ?></label>
            <input type="email" id="teacher_email" name="teacher_email" value="" class="regular-text" />
        </div>
        
        <div class="form-field">
            <label for="teacher_phone"><?php _e('Телефон', 'course-plugin'); ?></label>
            <input type="text" id="teacher_phone" name="teacher_phone" value="" class="regular-text" />
        </div>
        
        <div class="form-field">
            <label for="teacher_website"><?php _e('Веб-сайт', 'course-plugin'); ?></label>
            <input type="url" id="teacher_website" name="teacher_website" value="" class="regular-text" />
        </div>
        
        <div class="form-field">
            <label><?php _e('Социальные сети', 'course-plugin'); ?></label>
            <p>
                <label for="teacher_facebook">Facebook:</label><br />
                <input type="url" id="teacher_facebook" name="teacher_facebook" value="" class="regular-text" />
            </p>
            <p>
                <label for="teacher_twitter">Twitter:</label><br />
                <input type="url" id="teacher_twitter" name="teacher_twitter" value="" class="regular-text" />
            </p>
            <p>
                <label for="teacher_linkedin">LinkedIn:</label><br />
                <input type="url" id="teacher_linkedin" name="teacher_linkedin" value="" class="regular-text" />
            </p>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#teacher_photo_button').on('click', function(e) {
                e.preventDefault();
                var button = $(this);
                var input = $('#teacher_photo');
                
                var frame = wp.media({
                    title: '<?php _e('Выберите фото преподавателя', 'course-plugin'); ?>',
                    button: {
                        text: '<?php _e('Использовать это изображение', 'course-plugin'); ?>'
                    },
                    multiple: false
                });
                
                frame.on('select', function() {
                    var attachment = frame.state().get('selection').first().toJSON();
                    input.val(attachment.url);
                    if (input.next('.description').length === 0) {
                        input.after('<p class="description"><img src="' + attachment.url + '" style="max-width: 200px; margin-top: 10px; display: block;" /></p>');
                    } else {
                        input.next('.description').find('img').attr('src', attachment.url);
                    }
                });
                
                frame.open();
            });
        });
        </script>
        <?php
    }
    
    /**
     * Сохранение полей преподавателя
     * 
     * @param int $term_id ID термина (преподавателя)
     * @param int $tt_id ID таксономии
     */
    public function save_teacher_fields($term_id, $tt_id) {
        // Массив полей для сохранения
        $fields = array(
            'teacher_photo',
            'teacher_description',
            'teacher_position',
            'teacher_education',
            'teacher_email',
            'teacher_phone',
            'teacher_website',
            'teacher_facebook',
            'teacher_twitter',
            'teacher_linkedin',
        );
        
        // Сохраняем каждое поле
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                // Для описания используем wp_kses_post для очистки HTML
                if ($field === 'teacher_description') {
                    $value = wp_kses_post($_POST[$field]);
                } elseif ($field === 'teacher_education') {
                    $value = sanitize_textarea_field($_POST[$field]);
                } else {
                    $value = sanitize_text_field($_POST[$field]);
                }
                update_term_meta($term_id, $field, $value);
            } elseif ($field !== 'teacher_description') {
                // Удаляем только не-описание при отсутствии в POST (описание не трогаем — wp_editor может не передать при некоторых сценариях)
                delete_term_meta($term_id, $field);
            }
        }
    }
    
    /**
     * Синхронизация содержимого TinyMCE в textarea перед отправкой формы редактирования преподавателя
     */
    public function add_tinymce_save_before_submit() {
        $screen = get_current_screen();
        if (!$screen || $screen->taxonomy !== 'course_teacher') {
            return;
        }
        ?>
        <script>
        (function() {
            var formIds = ['edittag', 'addtag'];
            formIds.forEach(function(id) {
                var form = document.getElementById(id);
                if (form) {
                    form.addEventListener('submit', function() {
                        if (typeof tinymce !== 'undefined') {
                            tinymce.triggerSave();
                        }
                    });
                }
            });
        })();
        </script>
        <?php
    }
    
    /**
     * Добавление колонки с фото в список преподавателей
     * 
     * @param array $columns Массив колонок
     * @return array Обновленный массив колонок
     */
    public function add_teacher_columns($columns) {
        $new_columns = array();
        $new_columns['cb'] = $columns['cb'];
        $new_columns['teacher_photo'] = __('Фото', 'course-plugin');
        $new_columns['name'] = $columns['name'];
        $new_columns['description'] = $columns['description'];
        $new_columns['slug'] = $columns['slug'];
        $new_columns['posts'] = $columns['posts'];
        return $new_columns;
    }
    
    /**
     * Отображение содержимого колонки с фото
     * 
     * @param string $content Содержимое колонки
     * @param string $column_name Название колонки
     * @param int $term_id ID термина
     * @return string Содержимое колонки
     */
    public function render_teacher_columns($content, $column_name, $term_id) {
        if ($column_name === 'teacher_photo') {
            $photo = get_term_meta($term_id, 'teacher_photo', true);
            if ($photo) {
                return '<img src="' . esc_url($photo) . '" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;" />';
            } else {
                return '—';
            }
        }
        return $content;
    }
}

