<?php
/**
 * Настройки платёжного шлюза Сбербанка (REST register.do).
 *
 * @package Course_Plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Course_Sberbank_Payment_Admin {

    /**
     * @var self|null
     */
    private static $instance = null;

    /**
     * @return self
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    /**
     * @return void
     */
    public function add_admin_menu() {
        add_submenu_page(
            'options-general.php',
            __('Оплата Сбербанк', 'course-plugin'),
            __('Оплата Сбербанк', 'course-plugin'),
            'manage_options',
            'course-sberbank-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * @return void
     */
    public function register_settings() {
        register_setting('course_sberbank_settings', 'course_sberbank_user_name', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ));
        register_setting('course_sberbank_settings', 'course_sberbank_password', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ));
        register_setting('course_sberbank_settings', 'course_sberbank_test_mode', array(
            'type'              => 'boolean',
            'sanitize_callback' => array($this, 'sanitize_checkbox'),
            'default'           => true,
        ));
        register_setting('course_sberbank_settings', 'course_sberbank_return_url', array(
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default'           => '',
        ));
        register_setting('course_sberbank_settings', 'course_sberbank_fail_url', array(
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default'           => '',
        ));
    }

    /**
     * @param mixed $value
     * @return bool
     */
    public function sanitize_checkbox($value) {
        return !empty($value);
    }

    /**
     * @return void
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $test_mode = (bool) get_option('course_sberbank_test_mode', true);
        $gateway   = $test_mode
            ? 'https://3dsec.sberbank.ru/payment/rest/register.do'
            : 'https://securepayments.sberbank.ru/payment/rest/register.do';
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Платёжный шлюз Сбербанка', 'course-plugin'); ?></h1>
            <p>
                <?php
                printf(
                    /* translators: %s: donations page URL */
                    esc_html__('Укажите логин и пароль магазина из личного кабинета Сбербанка. Страница оплаты: %s (шорткод [mbs_bank_transfer]).', 'course-plugin'),
                    esc_url(Course_Bank_Transfer::get_payment_page_url())
                );
                ?>
            </p>
            <p>
                <strong><?php esc_html_e('Текущий endpoint:', 'course-plugin'); ?></strong>
                <code><?php echo esc_html($gateway); ?></code>
            </p>
            <form method="post" action="options.php">
                <?php settings_fields('course_sberbank_settings'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="course_sberbank_user_name"><?php esc_html_e('Логин (userName)', 'course-plugin'); ?></label></th>
                        <td><input type="text" class="regular-text" id="course_sberbank_user_name" name="course_sberbank_user_name" value="<?php echo esc_attr((string) get_option('course_sberbank_user_name', '')); ?>" autocomplete="off" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="course_sberbank_password"><?php esc_html_e('Пароль (password)', 'course-plugin'); ?></label></th>
                        <td><input type="password" class="regular-text" id="course_sberbank_password" name="course_sberbank_password" value="<?php echo esc_attr((string) get_option('course_sberbank_password', '')); ?>" autocomplete="new-password" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Тестовая среда', 'course-plugin'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="course_sberbank_test_mode" value="1" <?php checked($test_mode); ?> />
                                <?php esc_html_e('Использовать 3dsec.sberbank.ru (снимите для боевого securepayments.sberbank.ru)', 'course-plugin'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="course_sberbank_return_url"><?php esc_html_e('URL успешной оплаты (returnUrl)', 'course-plugin'); ?></label></th>
                        <td>
                            <input type="url" class="large-text" id="course_sberbank_return_url" name="course_sberbank_return_url" value="<?php echo esc_attr((string) get_option('course_sberbank_return_url', '')); ?>" placeholder="https://mbs.ru/help_sberb/?payment=success" />
                            <p class="description"><?php esc_html_e('Полный URL с https://. Если пусто — https://mbs.ru/help_sberb/?payment=success', 'course-plugin'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="course_sberbank_fail_url"><?php esc_html_e('URL неуспешной оплаты (failUrl)', 'course-plugin'); ?></label></th>
                        <td>
                            <input type="url" class="large-text" id="course_sberbank_fail_url" name="course_sberbank_fail_url" value="<?php echo esc_attr((string) get_option('course_sberbank_fail_url', '')); ?>" placeholder="https://mbs.ru/help_sberb/?payment=fail" />
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
