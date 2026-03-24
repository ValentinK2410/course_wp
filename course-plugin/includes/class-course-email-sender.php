<?php
/**
 * Класс для улучшенной отправки email с поддержкой SMTP и альтернативных методов
 * SMTP настраивается через хук phpmailer_init (совместимо с WordPress 6.x / namespaced PHPMailer).
 *
 * Внешний SMTP обязателен для доставки на Gmail и др., если у IP сервера нет PTR.
 * Настройки: «Настройки → Email (SMTP)» или константы в wp-config.php (имеют приоритет над опциями):
 *
 * define('COURSE_SMTP_HOST', 'smtp.yandex.ru');
 * define('COURSE_SMTP_PORT', 465); // или 587 + TLS
 * define('COURSE_SMTP_USERNAME', 'robot@yandex.ru');
 * define('COURSE_SMTP_PASSWORD', 'пароль-приложения');
 * define('COURSE_SMTP_ENCRYPTION', 'ssl'); // для 465 — ssl; для 587 — tls
 * define('COURSE_SMTP_FROM_EMAIL', 'robot@example.org');
 * define('COURSE_SMTP_FROM_NAME', 'Богословская семинария');
 *
 * @copyright Copyright (c) 2024 Кузьменко Валентин (Valentink2410)
 * @author Кузьменко Валентин (Valentink2410)
 */

if (!defined('ABSPATH')) {
    exit;
}

class Course_Email_Sender {

    /**
     * Единственный экземпляр класса (Singleton)
     */
    private static $instance = null;

    /**
     * Получить экземпляр класса
     *
     * @return Course_Email_Sender Экземпляр класса
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Конструктор класса
     */
    private function __construct() {
        add_action('phpmailer_init', array($this, 'configure_phpmailer_smtp'), 10, 1);
    }

    /**
     * SMTP включён (опции или константы wp-config).
     */
    public static function is_smtp_fully_configured() {
        $h = self::smtp_host();
        $u = self::smtp_username();
        $p = self::smtp_password();
        return $h !== '' && $u !== '' && $p !== '';
    }

    /**
     * @param string $option_key Ключ get_option без префикса course_smtp_
     * @param mixed  $default
     * @return mixed
     */
    private static function smtp_const_or_option($option_key, $default = '') {
        $map = array(
            'host' => 'COURSE_SMTP_HOST',
            'port' => 'COURSE_SMTP_PORT',
            'username' => 'COURSE_SMTP_USERNAME',
            'password' => 'COURSE_SMTP_PASSWORD',
            'encryption' => 'COURSE_SMTP_ENCRYPTION',
            'from_email' => 'COURSE_SMTP_FROM_EMAIL',
            'from_name' => 'COURSE_SMTP_FROM_NAME',
        );
        if (!isset($map[$option_key])) {
            return $default;
        }
        $cname = $map[$option_key];
        if (defined($cname)) {
            $v = constant($cname);
            if ($v !== null && $v !== '') {
                return $v;
            }
        }
        return get_option('course_smtp_' . $option_key, $default);
    }

    private static function smtp_host() {
        return (string) self::smtp_const_or_option('host', '');
    }

    private static function smtp_username() {
        return (string) self::smtp_const_or_option('username', '');
    }

    private static function smtp_password() {
        return (string) self::smtp_const_or_option('password', '');
    }

    private static function smtp_port() {
        $p = self::smtp_const_or_option('port', 587);
        return absint($p) > 0 ? absint($p) : 587;
    }

    private static function smtp_encryption() {
        $e = self::smtp_const_or_option('encryption', 'tls');
        return (string) $e;
    }

    private static function smtp_from_email() {
        $v = self::smtp_const_or_option('from_email', '');
        return $v !== '' ? (string) $v : (string) get_option('admin_email');
    }

    private static function smtp_from_name() {
        $v = self::smtp_const_or_option('from_name', '');
        return $v !== '' ? (string) $v : (string) get_bloginfo('name');
    }

    /**
     * Подключение SMTP к экземпляру PHPMailer, который использует wp_mail() (WP 5.5+).
     *
     * @param \PHPMailer\PHPMailer\PHPMailer $phpmailer Объект из WordPress.
     */
    public function configure_phpmailer_smtp($phpmailer) {
        if (get_option('disable_email_sending')) {
            return;
        }

        $smtp_host = self::smtp_host();
        $smtp_username = self::smtp_username();
        $smtp_password = self::smtp_password();

        if (empty($smtp_host) || empty($smtp_username) || empty($smtp_password)) {
            return;
        }

        $phpmailer->isSMTP();
        $phpmailer->Host = $smtp_host;
        $phpmailer->SMTPAuth = true;
        $phpmailer->Username = $smtp_username;
        $phpmailer->Password = $smtp_password;
        $phpmailer->Port = self::smtp_port();
        $phpmailer->CharSet = 'UTF-8';

        $enc = self::smtp_encryption();
        if ('' === $enc) {
            $phpmailer->SMTPSecure = '';
        } elseif ('ssl' === strtolower((string) $enc)) {
            $phpmailer->SMTPSecure = 'ssl';
        } else {
            $phpmailer->SMTPSecure = 'tls';
        }

        if (defined('COURSE_SMTP_DISABLE_SSL_VERIFY') && COURSE_SMTP_DISABLE_SSL_VERIFY) {
            $phpmailer->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ),
            );
        }

        $from_email = self::smtp_from_email();
        $from_name = self::smtp_from_name();
        if (!empty($from_email)) {
            $phpmailer->setFrom($from_email, $from_name);
            $phpmailer->addReplyTo($from_email, $from_name);
        }
    }

    /**
     * Отправка email с использованием лучшего доступного метода
     *
     * @param string $to Email получателя
     * @param string $subject Тема письма
     * @param string $message Текст письма
     * @param array  $headers Дополнительные заголовки
     * @return array Результат отправки ['success' => bool, 'message' => string, 'method' => string]
     */
    public function send_email($to, $subject, $message, $headers = array()) {
        $disable_email_sending = get_option('disable_email_sending', false);
        if ($disable_email_sending) {
            error_log("Course Email: Отправка писем отключена в настройках. Письмо не отправлено на {$to}. Тема: {$subject}");
            return array(
                'success' => true,
                'message' => 'Отправка писем отключена в настройках (режим тестирования)',
                'method' => 'disabled',
            );
        }

        if (!self::is_smtp_fully_configured()) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Course Email: SMTP не настроен (Настройки или константы COURSE_SMTP_* в wp-config.php). Прямая отправка с сервера может блокироваться Gmail (PTR).');
            }
        }

        $wp_mail_result = $this->send_via_wp_mail($to, $subject, $message, $headers);
        if ($wp_mail_result['success']) {
            return $wp_mail_result;
        }

        $direct_result = $this->send_via_direct($to, $subject, $message, $headers);
        if ($direct_result['success']) {
            return $direct_result;
        }

        return array(
            'success' => false,
            'message' => 'Все методы отправки не сработали. Последняя ошибка: ' . $direct_result['message'],
            'method' => 'none',
        );
    }

    /**
     * Отправка через стандартный wp_mail (PHPMailer + phpmailer_init для SMTP)
     *
     * @param string $to Email получателя
     * @param string $subject Тема письма
     * @param string $message Текст письма
     * @param array  $headers Дополнительные заголовки
     * @return array Результат отправки
     */
    private function send_via_wp_mail($to, $subject, $message, $headers = array()) {
        if (!function_exists('wp_mail')) {
            return array('success' => false, 'message' => 'wp_mail недоступен', 'method' => 'wp_mail');
        }

        $improved_headers = $this->improve_headers_for_gmail($headers, $to);

        $result = wp_mail($to, $subject, $message, $improved_headers);

        if ($result) {
            error_log("Course Email: Письмо успешно отправлено через wp_mail на {$to}");
            return array('success' => true, 'message' => 'Письмо отправлено через wp_mail', 'method' => 'wp_mail');
        }

        global $phpmailer;
        $error = 'Неизвестная ошибка';
        if (isset($phpmailer) && is_object($phpmailer) && isset($phpmailer->ErrorInfo)) {
            $error = $phpmailer->ErrorInfo;
        }
        error_log("Course Email: Ошибка wp_mail отправки на {$to}: {$error}");
        return array('success' => false, 'message' => 'wp_mail ошибка: ' . $error, 'method' => 'wp_mail');
    }

    /**
     * Прямая отправка через mail() с улучшенными заголовками
     *
     * @param string $to Email получателя
     * @param string $subject Тема письма
     * @param string $message Текст письма
     * @param array  $headers Дополнительные заголовки
     * @return array Результат отправки
     */
    private function send_via_direct($to, $subject, $message, $headers = array()) {
        if (!function_exists('mail')) {
            return array('success' => false, 'message' => 'mail() недоступен', 'method' => 'direct');
        }

        $improved_headers = $this->improve_headers_for_gmail($headers, $to);
        $headers_string = implode("\r\n", $improved_headers);

        $result = @mail($to, $subject, $message, $headers_string);

        if ($result) {
            error_log("Course Email: Письмо успешно отправлено через mail() на {$to}");
            return array('success' => true, 'message' => 'Письмо отправлено через mail()', 'method' => 'direct');
        }

        $err = error_get_last();
        $error_msg = $err ? $err['message'] : 'Неизвестная ошибка';
        error_log("Course Email: Ошибка mail() отправки на {$to}: {$error_msg}");
        return array('success' => false, 'message' => 'mail() ошибка: ' . $error_msg, 'method' => 'direct');
    }

    /**
     * Улучшение заголовков для лучшей доставляемости
     *
     * @param array  $headers Исходные заголовки
     * @param string $to Email получателя
     * @return array Улучшенные заголовки
     */
    private function improve_headers_for_gmail($headers, $to) {
        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');
        $site_url = home_url();

        $email_domain = substr(strrchr($to, '@'), 1);
        $is_gmail = (strpos(strtolower((string) $email_domain), 'gmail.com') !== false);

        $improved_headers = array();

        $improved_headers[] = 'Content-Type: text/plain; charset=UTF-8';

        $has_smtp = self::is_smtp_fully_configured();

        if ($has_smtp) {
            $from_email = self::smtp_from_email();
            $from_name = self::smtp_from_name();
        } else {
            $from_name = !empty($site_name) ? $site_name : 'WordPress';
            $from_email = !empty($admin_email) ? $admin_email : 'noreply@' . parse_url($site_url, PHP_URL_HOST);
        }

        $improved_headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
        $improved_headers[] = 'Reply-To: ' . $from_name . ' <' . $from_email . '>';

        $improved_headers[] = 'MIME-Version: 1.0';
        $improved_headers[] = 'X-Mailer: WordPress/' . get_bloginfo('version');
        $improved_headers[] = 'X-Priority: 3';

        if ($is_gmail) {
            $improved_headers[] = 'List-Unsubscribe: <' . $site_url . '>, <mailto:' . $from_email . '?subject=unsubscribe>';
            $improved_headers[] = 'Precedence: bulk';
        }

        foreach ($headers as $header) {
            $header_lower = strtolower($header);
            if (strpos($header_lower, 'from:') === 0 ||
                strpos($header_lower, 'reply-to:') === 0 ||
                strpos($header_lower, 'content-type:') === 0) {
                continue;
            }
            $improved_headers[] = $header;
        }

        return $improved_headers;
    }

    /**
     * Тестовая отправка email для проверки настроек
     *
     * @param string $test_email Email для теста
     * @return array Результат теста
     */
    public function test_email_sending($test_email) {
        $subject = 'Тест отправки email - ' . date('Y-m-d H:i:s');
        $message = "Это тестовое письмо для проверки настроек отправки email.\n\n";
        $message .= "Если вы получили это письмо, значит настройки работают корректно.\n\n";
        $message .= 'Время отправки: ' . date('Y-m-d H:i:s') . "\n";
        $message .= 'Сервер: ' . (isset($_SERVER['SERVER_NAME']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_NAME'])) : '') . "\n";

        return $this->send_email($test_email, $subject, $message);
    }
}
