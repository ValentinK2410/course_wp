<?php
/**
 * Шорткод перевода / оплаты через платёжный шлюз Сбербанка (REST register.do).
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
        add_action('wp_ajax_mbs_sberbank_register', array($this, 'ajax_register_order'));
        add_action('wp_ajax_nopriv_mbs_sberbank_register', array($this, 'ajax_register_order'));
    }

    /**
     * @return array<string, mixed>
     */
    public static function get_default_settings() {
        $defaults = array(
            'payment_page_url' => 'https://mbs.ru/help_sberb/',
            'bank_name'        => 'ПАО Сбербанк, г. Москва',
            'account'          => '40703810638120101749',
            'corr_account'     => '30101810400000000225',
            'bik'              => '044525225',
            'inn'              => '7726081186',
            'recipient'        => 'Московская богословская семинария',
            'default_sum'      => 1000,
            'preset_sums'      => array(500, 1000, 2000, 3000, 5000, 10000),
        );

        return apply_filters('course_plugin_bank_transfer_settings', $defaults);
    }

    /**
     * Базовый URL шлюза по мануалу Сбербанка.
     *
     * @return string
     */
    public static function get_gateway_base_url() {
        $custom = trim((string) get_option('course_sberbank_gateway_base_url', ''));
        if ($custom !== '') {
            return trailingslashit(esc_url_raw($custom));
        }

        $test_mode = (bool) get_option('course_sberbank_test_mode', true);
        if ($test_mode) {
            return 'https://3dsec.sberbank.ru/payment/';
        }

        // Боевой шлюз МБС (как на ch67149.tmweb.ru): api.securepaymentgateway.ru
        return 'https://api.securepaymentgateway.ru/payment/';
    }

    /**
     * @return string
     */
    public static function get_register_url() {
        return self::get_gateway_base_url() . 'rest/register.do';
    }

    /**
     * @return bool
     */
    public static function is_configured() {
        $user = (string) get_option('course_sberbank_user_name', '');
        $pass = (string) get_option('course_sberbank_password', '');
        return $user !== '' && $pass !== '';
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
                'ajaxurl'      => admin_url('admin-ajax.php'),
                'nonce'        => wp_create_nonce('mbs_sberbank_payment'),
                'copied'       => __('Скопировано', 'course-plugin'),
                'copyFailed'   => __('Не удалось скопировать', 'course-plugin'),
                'invalidSum'   => __('Укажите сумму больше 0', 'course-plugin'),
                'invalidName'  => __('Укажите ФИО', 'course-plugin'),
                'invalidEmail' => __('Укажите корректный email', 'course-plugin'),
                'processing'   => __('Подготовка оплаты…', 'course-plugin'),
                'payLabel'     => __('Перейти к оплате в Сбербанке', 'course-plugin'),
                'errorGeneric' => __('Не удалось зарегистрировать заказ. Попробуйте позже.', 'course-plugin'),
            )
        );
    }

    /**
     * Страница пожертвований / оплаты через Сбербанк.
     *
     * @return string
     */
    public static function get_payment_page_url() {
        $settings = self::get_default_settings();
        $url      = isset($settings['payment_page_url']) ? trim((string) $settings['payment_page_url']) : '';
        if ($url !== '') {
            return esc_url_raw($url);
        }

        $page = get_page_by_path('help_sberb');
        if ($page instanceof WP_Post) {
            return get_permalink($page);
        }

        return home_url('/help_sberb/');
    }

    /**
     * URL возврата после оплаты.
     *
     * @param string $status success|fail
     * @return string
     */
    private function get_return_url($status) {
        $option_key = ($status === 'fail') ? 'course_sberbank_fail_url' : 'course_sberbank_return_url';
        $saved      = trim((string) get_option($option_key, ''));
        if ($saved !== '') {
            return $saved;
        }

        return add_query_arg('payment', $status, self::get_payment_page_url());
    }

    /**
     * Регистрация заказа в шлюзе Сбербанка (раздел 8.2.1 мануала).
     *
     * @param int    $amount_rub
     * @param string $order_number
     * @param string $description
     * @param string $client_name
     * @param string $email
     * @return array<string, mixed>|WP_Error
     */
    public function register_order($amount_rub, $order_number, $description, $client_name, $email) {
        if (!self::is_configured()) {
            return new WP_Error('not_configured', __('Платёжный шлюз не настроен.', 'course-plugin'));
        }

        $amount_kopecks = (int) round($amount_rub * 100);
        if ($amount_kopecks <= 0) {
            return new WP_Error('invalid_amount', __('Некорректная сумма.', 'course-plugin'));
        }

        $description = wp_strip_all_tags($description);
        if ($description === '') {
            $description = __('Пожертвование МБС', 'course-plugin');
        }
        $description = mb_substr($description, 0, 512);

        $body = array(
            'userName'    => (string) get_option('course_sberbank_user_name', ''),
            'password'    => (string) get_option('course_sberbank_password', ''),
            'orderNumber' => $order_number,
            'amount'      => (string) $amount_kopecks,
            'currency'    => '643',
            'language'    => 'ru',
            'returnUrl'   => $this->get_return_url('success'),
            'failUrl'     => $this->get_return_url('fail'),
            'description' => $description,
            'pageView'    => function_exists('wp_is_mobile') && wp_is_mobile() ? 'MOBILE' : 'DESKTOP',
            'jsonParams'  => wp_json_encode(
                array(
                    'email'      => $email,
                    'clientName' => $client_name,
                )
            ),
        );

        $response = wp_remote_post(
            self::get_register_url(),
            array(
                'timeout' => 30,
                'body'    => $body,
            )
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $raw  = wp_remote_retrieve_body($response);
        $data = json_decode($raw, true);

        if (!is_array($data)) {
            return new WP_Error('bad_response', __('Некорректный ответ платёжного шлюза.', 'course-plugin'), array('http_code' => $code));
        }

        $error_code = isset($data['errorCode']) ? (string) $data['errorCode'] : '';
        if ($error_code !== '' && $error_code !== '0') {
            $message = isset($data['errorMessage']) ? (string) $data['errorMessage'] : __('Ошибка регистрации заказа.', 'course-plugin');
            return new WP_Error('gateway_error', $message, $data);
        }

        if (empty($data['formUrl'])) {
            return new WP_Error('no_form_url', __('Шлюз не вернул адрес платёжной формы.', 'course-plugin'), $data);
        }

        return $data;
    }

    /**
     * @return void
     */
    public function ajax_register_order() {
        check_ajax_referer('mbs_sberbank_payment', 'nonce');

        $amount_rub = isset($_POST['sum']) ? (int) preg_replace('/\D+/', '', wp_unslash($_POST['sum'])) : 0;
        $clientid   = isset($_POST['clientid']) ? sanitize_text_field(wp_unslash($_POST['clientid'])) : '';
        $email      = isset($_POST['client_email']) ? sanitize_email(wp_unslash($_POST['client_email'])) : '';
        $comment    = isset($_POST['service_name']) ? sanitize_text_field(wp_unslash($_POST['service_name'])) : '';

        if ($amount_rub <= 0) {
            wp_send_json_error(array('message' => __('Укажите сумму больше 0', 'course-plugin')), 400);
        }
        if ($clientid === '') {
            wp_send_json_error(array('message' => __('Укажите ФИО', 'course-plugin')), 400);
        }
        if ($email === '' || !is_email($email)) {
            wp_send_json_error(array('message' => __('Укажите корректный email', 'course-plugin')), 400);
        }

        $order_number = 'mbs-' . gmdate('YmdHis') . '-' . wp_generate_password(6, false, false);
        $description  = $comment !== '' ? $comment : sprintf(__('Пожертвование от %s', 'course-plugin'), $clientid);

        $result = $this->register_order($amount_rub, $order_number, $description, $clientid, $email);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 500);
        }

        wp_send_json_success(
            array(
                'formUrl'     => esc_url_raw($result['formUrl']),
                'orderId'     => isset($result['orderId']) ? sanitize_text_field($result['orderId']) : '',
                'orderNumber' => $order_number,
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
                'bank_name'   => $settings['bank_name'],
                'account'     => $settings['account'],
                'corr_account'=> $settings['corr_account'],
                'bik'         => $settings['bik'],
                'inn'         => $settings['inn'],
                'recipient'   => $settings['recipient'],
                'default_sum' => (string) $settings['default_sum'],
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

        $payment_notice = '';
        $payment_status = '';
        if (isset($_GET['payment'])) {
            $payment_status = sanitize_key(wp_unslash($_GET['payment']));
            if ($payment_status === 'success') {
                $payment_notice = __('Оплата успешно завершена. Спасибо!', 'course-plugin');
            } elseif ($payment_status === 'fail') {
                $payment_notice = __('Оплата не была завершена. Вы можете попробовать снова.', 'course-plugin');
            }
        }

        $context = array(
            'bank_name'        => sanitize_text_field($atts['bank_name']),
            'account'          => preg_replace('/\D+/', '', $atts['account']),
            'corr_account'     => preg_replace('/\D+/', '', $atts['corr_account']),
            'bik'              => preg_replace('/\D+/', '', $atts['bik']),
            'inn'              => preg_replace('/\D+/', '', $atts['inn']),
            'recipient'        => sanitize_text_field($atts['recipient']),
            'default_sum'      => max(1, (int) $atts['default_sum']),
            'preset_sums'      => $preset_sums,
            'is_configured'    => self::is_configured(),
            'payment_notice'   => $payment_notice,
            'payment_status'   => $payment_status,
            'gateway_test'     => (bool) get_option('course_sberbank_test_mode', true),
        );

        ob_start();
        include COURSE_PLUGIN_DIR . 'templates/bank-transfer-shortcode.php';
        return (string) ob_get_clean();
    }
}
