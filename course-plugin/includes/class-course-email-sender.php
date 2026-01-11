<?php
/**
 * Класс для улучшенной отправки email с поддержкой SMTP и альтернативных методов
 * Решает проблемы с доставляемостью в Gmail
 * 
 * @copyright Copyright (c) 2024 Кузьменко Валентин (Valentink2410)
 * @author Кузьменко Валентин (Valentink2410)
 */

// Проверка безопасности: если файл вызывается напрямую, прекращаем выполнение
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
        // Инициализация при необходимости
    }
    
    /**
     * Отправка email с использованием лучшего доступного метода
     * 
     * @param string $to Email получателя
     * @param string $subject Тема письма
     * @param string $message Текст письма
     * @param array $headers Дополнительные заголовки
     * @return array Результат отправки ['success' => bool, 'message' => string, 'method' => string]
     */
    public function send_email($to, $subject, $message, $headers = array()) {
        // Пробуем разные методы отправки по порядку приоритета
        
        // Метод 1: SMTP через PHPMailer (если доступен и настроен)
        // Проверяем, настроен ли SMTP перед попыткой отправки
        $smtp_host = get_option('course_smtp_host', '');
        $smtp_username = get_option('course_smtp_username', '');
        $smtp_password = get_option('course_smtp_password', '');
        
        if (!empty($smtp_host) && !empty($smtp_username) && !empty($smtp_password)) {
            // SMTP настроен - пробуем использовать его
            $smtp_result = $this->send_via_smtp($to, $subject, $message, $headers);
            if ($smtp_result['success']) {
                return $smtp_result;
            }
            // Если SMTP не сработал, продолжаем пробовать другие методы
            error_log("Course Email: SMTP не сработал, пробуем другие методы. Ошибка: " . $smtp_result['message']);
        } else {
            // SMTP не настроен - пропускаем его и используем стандартные методы
            error_log("Course Email: SMTP не настроен, используем стандартные методы отправки");
        }
        
        // Метод 2: Стандартный wp_mail с улучшенными заголовками
        $wp_mail_result = $this->send_via_wp_mail($to, $subject, $message, $headers);
        if ($wp_mail_result['success']) {
            return $wp_mail_result;
        }
        
        // Метод 3: Прямая отправка через mail() с улучшенными заголовками
        $direct_result = $this->send_via_direct($to, $subject, $message, $headers);
        if ($direct_result['success']) {
            return $direct_result;
        }
        
        // Если все методы не сработали, возвращаем последнюю ошибку
        return array(
            'success' => false,
            'message' => 'Все методы отправки не сработали. Последняя ошибка: ' . $direct_result['message'],
            'method' => 'none'
        );
    }
    
    /**
     * Отправка через SMTP используя PHPMailer
     * 
     * @param string $to Email получателя
     * @param string $subject Тема письма
     * @param string $message Текст письма
     * @param array $headers Дополнительные заголовки
     * @return array Результат отправки
     */
    private function send_via_smtp($to, $subject, $message, $headers = array()) {
        // Загружаем PHPMailer из WordPress
        if (!class_exists('PHPMailer')) {
            require_once ABSPATH . 'wp-includes/class-phpmailer.php';
        }
        
        // Получаем SMTP настройки из опций WordPress
        $smtp_host = get_option('course_smtp_host', '');
        $smtp_port = get_option('course_smtp_port', 587);
        $smtp_username = get_option('course_smtp_username', '');
        $smtp_password = get_option('course_smtp_password', '');
        $smtp_encryption = get_option('course_smtp_encryption', 'tls'); // tls или ssl
        $smtp_from_email = get_option('course_smtp_from_email', get_option('admin_email'));
        $smtp_from_name = get_option('course_smtp_from_name', get_bloginfo('name'));
        
        // Если SMTP не настроен, пропускаем этот метод
        if (empty($smtp_host) || empty($smtp_username) || empty($smtp_password)) {
            return array('success' => false, 'message' => 'SMTP не настроен', 'method' => 'smtp');
        }
        
        try {
            $mail = new PHPMailer(true);
            
            // Настройки SMTP
            $mail->isSMTP();
            $mail->Host = $smtp_host;
            $mail->SMTPAuth = true;
            $mail->Username = $smtp_username;
            $mail->Password = $smtp_password;
            $mail->SMTPSecure = $smtp_encryption;
            $mail->Port = intval($smtp_port);
            $mail->CharSet = 'UTF-8';
            
            // Отправитель
            $mail->setFrom($smtp_from_email, $smtp_from_name);
            $mail->addReplyTo($smtp_from_email, $smtp_from_name);
            
            // Получатель
            $mail->addAddress($to);
            
            // Содержимое
            $mail->isHTML(false);
            $mail->Subject = $subject;
            $mail->Body = $message;
            
            // Дополнительные заголовки
            foreach ($headers as $header) {
                if (strpos($header, 'From:') === 0 || strpos($header, 'Reply-To:') === 0) {
                    continue; // Уже установлены выше
                }
                $mail->addCustomHeader($header);
            }
            
            // Отправка
            $mail->send();
            
            error_log("Course Email: Письмо успешно отправлено через SMTP на {$to}");
            return array('success' => true, 'message' => 'Письмо отправлено через SMTP', 'method' => 'smtp');
            
        } catch (Exception $e) {
            error_log("Course Email: Ошибка SMTP отправки на {$to}: " . $mail->ErrorInfo);
            return array('success' => false, 'message' => 'SMTP ошибка: ' . $mail->ErrorInfo, 'method' => 'smtp');
        }
    }
    
    /**
     * Отправка через стандартный wp_mail с улучшенными заголовками
     * 
     * @param string $to Email получателя
     * @param string $subject Тема письма
     * @param string $message Текст письма
     * @param array $headers Дополнительные заголовки
     * @return array Результат отправки
     */
    private function send_via_wp_mail($to, $subject, $message, $headers = array()) {
        if (!function_exists('wp_mail')) {
            return array('success' => false, 'message' => 'wp_mail недоступен', 'method' => 'wp_mail');
        }
        
        // Улучшаем заголовки для Gmail
        $improved_headers = $this->improve_headers_for_gmail($headers, $to);
        
        $result = wp_mail($to, $subject, $message, $improved_headers);
        
        if ($result) {
            error_log("Course Email: Письмо успешно отправлено через wp_mail на {$to}");
            return array('success' => true, 'message' => 'Письмо отправлено через wp_mail', 'method' => 'wp_mail');
        } else {
            global $phpmailer;
            $error = 'Неизвестная ошибка';
            if (isset($phpmailer) && is_object($phpmailer) && isset($phpmailer->ErrorInfo)) {
                $error = $phpmailer->ErrorInfo;
            }
            error_log("Course Email: Ошибка wp_mail отправки на {$to}: {$error}");
            return array('success' => false, 'message' => 'wp_mail ошибка: ' . $error, 'method' => 'wp_mail');
        }
    }
    
    /**
     * Прямая отправка через mail() с улучшенными заголовками
     * 
     * @param string $to Email получателя
     * @param string $subject Тема письма
     * @param string $message Текст письма
     * @param array $headers Дополнительные заголовки
     * @return array Результат отправки
     */
    private function send_via_direct($to, $subject, $message, $headers = array()) {
        if (!function_exists('mail')) {
            return array('success' => false, 'message' => 'mail() недоступен', 'method' => 'direct');
        }
        
        // Улучшаем заголовки для Gmail
        $improved_headers = $this->improve_headers_for_gmail($headers, $to);
        $headers_string = implode("\r\n", $improved_headers);
        
        $result = @mail($to, $subject, $message, $headers_string);
        
        if ($result) {
            error_log("Course Email: Письмо успешно отправлено через mail() на {$to}");
            return array('success' => true, 'message' => 'Письмо отправлено через mail()', 'method' => 'direct');
        } else {
            $error = error_get_last();
            $error_msg = $error ? $error['message'] : 'Неизвестная ошибка';
            error_log("Course Email: Ошибка mail() отправки на {$to}: {$error_msg}");
            return array('success' => false, 'message' => 'mail() ошибка: ' . $error_msg, 'method' => 'direct');
        }
    }
    
    /**
     * Улучшение заголовков для лучшей доставляемости в Gmail
     * 
     * @param array $headers Исходные заголовки
     * @param string $to Email получателя
     * @return array Улучшенные заголовки
     */
    private function improve_headers_for_gmail($headers, $to) {
        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');
        $site_url = home_url();
        
        // Извлекаем домен из email
        $email_domain = substr(strrchr($to, "@"), 1);
        $is_gmail = (strpos(strtolower($email_domain), 'gmail.com') !== false);
        
        // Базовые заголовки
        $improved_headers = array();
        
        // Content-Type
        $improved_headers[] = 'Content-Type: text/plain; charset=UTF-8';
        
        // From
        $from_name = !empty($site_name) ? $site_name : 'WordPress';
        $from_email = !empty($admin_email) ? $admin_email : 'noreply@' . parse_url($site_url, PHP_URL_HOST);
        $improved_headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
        
        // Reply-To
        $improved_headers[] = 'Reply-To: ' . $from_name . ' <' . $from_email . '>';
        
        // MIME-Version
        $improved_headers[] = 'MIME-Version: 1.0';
        
        // X-Mailer
        $improved_headers[] = 'X-Mailer: WordPress/' . get_bloginfo('version');
        
        // X-Priority
        $improved_headers[] = 'X-Priority: 3';
        
        // Для Gmail добавляем специальные заголовки
        if ($is_gmail) {
            $improved_headers[] = 'List-Unsubscribe: <' . $site_url . '>, <mailto:' . $from_email . '?subject=unsubscribe>';
            $improved_headers[] = 'Precedence: bulk';
        }
        
        // Добавляем пользовательские заголовки (если они не конфликтуют)
        foreach ($headers as $header) {
            $header_lower = strtolower($header);
            // Пропускаем заголовки, которые уже установлены
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
        $message .= "Время отправки: " . date('Y-m-d H:i:s') . "\n";
        $message .= "Сервер: " . $_SERVER['SERVER_NAME'] . "\n";
        
        $result = $this->send_email($test_email, $subject, $message);
        
        return $result;
    }
}

