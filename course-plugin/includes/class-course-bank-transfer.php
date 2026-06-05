<?php
/**
 * Шорткод перевода / онлайн-оплаты через PayKeeper (стиль Сбербанк).
 *
 * @package Course_Plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Course_Bank_Transfer {

    /**
     * @var self|null
     */
    private static $instance = null;

    /**
     * Шорткод был выведен на странице (для позднего подключения ассетов).
     *
     * @var bool
     */
    private static $shortcode_rendered = false;

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
        add_shortcode('mbs_bank_transfer', array($this, 'render_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'maybe_enqueue_assets'));
    }

    /**
     * Настройки по умолчанию (можно переопределить фильтром).
     *
     * @return array<string, mixed>
     */
    public static function get_default_settings() {
        $defaults = array(
            'paykeeper_url' => 'https://seminary.server.paykeeper.ru/create/',
            'bank_name'     => 'ПАО Сбербанк, г. Москва',
            'account'       => '40703810638120101749',
            'corr_account'  => '30101810400000000225',
            'bik'           => '044525225',
            'inn'           => '7726081186',
            'recipient'     => 'Московская богословская семинария',
            'default_sum'   => 1000,
            'preset_sums'   => array(500, 1000, 2000, 3000, 5000, 10000),
        );

        return apply_filters('course_plugin_bank_transfer_settings', $defaults);
    }

    /**
     * @return void
     */
    public function maybe_enqueue_assets() {
        if (self::$shortcode_rendered || $this->page_has_shortcode()) {
            $this->enqueue_assets();
        }
    }

    /**
     * @return bool
     */
    private function page_has_shortcode() {
        if (!is_singular()) {
            return false;
        }
        $post = get_post();
        if (!$post || empty($post->post_content)) {
            return false;
        }
        return has_shortcode($post->post_content, 'mbs_bank_transfer');
    }

    /**
     * @return void
     */
    private function enqueue_assets() {
        wp_enqueue_style(
            'course-bank-transfer',
            COURSE_PLUGIN_URL . 'assets/css/bank-transfer.css',
            array(),
            COURSE_PLUGIN_VERSION
        );

        wp_enqueue_script(
            'course-bank-transfer',
            COURSE_PLUGIN_URL . 'assets/js/bank-transfer.js',
            array(),
            COURSE_PLUGIN_VERSION,
            true
        );

        wp_localize_script(
            'course-bank-transfer',
            'courseBankTransfer',
            array(
                'copied'       => __('Скопировано', 'course-plugin'),
                'copyFailed'   => __('Не удалось скопировать', 'course-plugin'),
                'invalidSum'   => __('Укажите сумму больше 0', 'course-plugin'),
                'invalidName'  => __('Укажите ФИО', 'course-plugin'),
                'invalidEmail' => __('Укажите корректный email', 'course-plugin'),
            )
        );
    }

    /**
     * @param array<string, string>|string $atts
     * @return string
     */
    public function render_shortcode($atts) {
        self::$shortcode_rendered = true;
        $this->enqueue_assets();

        $settings = self::get_default_settings();
        $atts     = shortcode_atts(
            array(
                'paykeeper_url' => $settings['paykeeper_url'],
                'bank_name'     => $settings['bank_name'],
                'account'       => $settings['account'],
                'corr_account'  => $settings['corr_account'],
                'bik'           => $settings['bik'],
                'inn'           => $settings['inn'],
                'recipient'     => $settings['recipient'],
                'default_sum'   => (string) $settings['default_sum'],
            ),
            $atts,
            'mbs_bank_transfer'
        );

        $preset_sums = array_map('intval', (array) $settings['preset_sums']);
        $preset_sums = array_values(array_filter($preset_sums, static function ($sum) {
            return $sum > 0;
        }));
        if (empty($preset_sums)) {
            $preset_sums = array(500, 1000, 2000, 3000, 5000);
        }

        $context = array(
            'paykeeper_url' => esc_url($atts['paykeeper_url']),
            'bank_name'     => sanitize_text_field($atts['bank_name']),
            'account'       => preg_replace('/\D+/', '', $atts['account']),
            'corr_account'  => preg_replace('/\D+/', '', $atts['corr_account']),
            'bik'           => preg_replace('/\D+/', '', $atts['bik']),
            'inn'           => preg_replace('/\D+/', '', $atts['inn']),
            'recipient'     => sanitize_text_field($atts['recipient']),
            'default_sum'   => max(1, (int) $atts['default_sum']),
            'preset_sums'   => $preset_sums,
        );

        ob_start();
        include COURSE_PLUGIN_DIR . 'templates/bank-transfer-shortcode.php';
        return (string) ob_get_clean();
    }
}
