<?php
/**
 * Админка: HTML содержимое модального («окна записи») перед переходом к регистрации.
 *
 * @package Course_Plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Course_Enroll_Modal_Admin {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function register_menu() {
        add_submenu_page(
            'edit.php?post_type=course',
            __('Модальное окно записи', 'course-plugin'),
            __('Модальное окно записи', 'course-plugin'),
            'manage_options',
            'course-enroll-modal',
            array($this, 'render_page')
        );
    }

    public function register_settings() {
        register_setting(
            'course_enroll_modal_settings',
            'course_plugin_enroll_modal_html',
            array(
                'type' => 'string',
                'sanitize_callback' => 'wp_kses_post',
                'default' => '',
            )
        );
        register_setting(
            'course_enroll_modal_settings',
            'course_plugin_enroll_modal_cta_text',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => '',
            )
        );
    }

    /**
     * Текст на кнопке-переходе под текстом модалки (к шлюзу / регистрации).
     *
     * @return string
     */
    public static function get_cta_label() {
        $v = get_option('course_plugin_enroll_modal_cta_text', '');
        $v = is_string($v) ? trim($v) : '';
        if ($v === '') {
            return __('Приступить к регистрации', 'course-plugin');
        }
        return $v;
    }

    /**
     * Кастомный HTML тела модалки или пустая строка = встроенный шаблон по умолчанию.
     *
     * @return string
     */
    public static function get_custom_html() {
        $h = get_option('course_plugin_enroll_modal_html', '');
        return is_string($h) ? trim($h) : '';
    }

    public static function uses_custom_html() {
        return self::get_custom_html() !== '';
    }

    public function render_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Модальное окно записи', 'course-plugin'); ?></h1>
            <p class="description">
                <?php esc_html_e('Текст показывается гостям на страницах программ при нажатии «Записаться» (перед переходом по ссылке записи). Разрешены обычные HTML-теги (абзацы, списки, ссылки, заголовки). Скрипты не вставляются.', 'course-plugin'); ?>
            </p>

            <form method="post" action="options.php">
                <?php settings_fields('course_enroll_modal_settings'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="course_plugin_enroll_modal_html"><?php esc_html_e('HTML содержимое', 'course-plugin'); ?></label>
                        </th>
                        <td>
                            <textarea name="course_plugin_enroll_modal_html" id="course_plugin_enroll_modal_html" class="large-text code" rows="18" cols="80"><?php echo esc_textarea(get_option('course_plugin_enroll_modal_html', '')); ?></textarea>
                            <p class="description"><?php esc_html_e('Оставьте пустым, чтобы использовать стандартный текст с двумя шагами (как раньше).', 'course-plugin'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="course_plugin_enroll_modal_cta_text"><?php esc_html_e('Текст кнопки внизу окна', 'course-plugin'); ?></label>
                        </th>
                        <td>
                            <input type="text" class="regular-text" name="course_plugin_enroll_modal_cta_text" id="course_plugin_enroll_modal_cta_text" value="<?php echo esc_attr(get_option('course_plugin_enroll_modal_cta_text', '')); ?>" placeholder="<?php echo esc_attr__('Приступить к регистрации', 'course-plugin'); ?>" />
                            <p class="description"><?php esc_html_e('Пустое поле — подставится стандартная подпись «Приступить к регистрации».', 'course-plugin'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Сохранить', 'course-plugin')); ?>
            </form>

            <hr />
            <h2><?php esc_html_e('Пример разметки (можно скопировать как основу)', 'course-plugin'); ?></h2>
            <?php
            $example_html = '<h2 id="course-program-reg-modal-title" class="course-program-reg-modal__title">' . esc_html__('ПРОЦЕСС РЕГИСТРАЦИИ НА ПРОГРАММУ', 'course-plugin') . "</h2>\n"
                . '<p class="course-program-reg-modal__lead">' . esc_html__('Процесс включает в себя два шага', 'course-plugin') . "</p>\n\n"
                . '<div class="course-program-reg-modal__step">' . "\n"
                . '  <h3 class="course-program-reg-modal__step-title">' . esc_html__('ШАГ 1. Вход в аккаунт', 'course-plugin') . "</h3>\n"
                . '  <p class="course-program-reg-modal__text">…</p>' . "\n"
                . "</div>\n\n"
                . '<div class="course-program-reg-modal__step">' . "\n"
                . '  <h3 class="course-program-reg-modal__step-title">' . esc_html__('ШАГ 2. Получение доступа на платформу и предоставление необходимых данных', 'course-plugin') . "</h3>\n"
                . '  <p class="course-program-reg-modal__text">…</p>' . "\n"
                . '</div>';
            ?>
            <p><textarea readonly class="large-text code" rows="14" onclick="this.select();" style="font-family:monospace;"><?php echo esc_textarea($example_html); ?></textarea></p>
        </div>
        <?php
    }
}
