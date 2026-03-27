<?php
/**
 * Расширение стандартной формы регистрации WordPress (wp-login.php?action=register)
 *
 * Добавляет поля Имя / Фамилия, генерирует пароль, синхронизирует с Moodle
 * и отправляет кастомное письмо с логином, паролем и ссылкой на виртуальный класс.
 *
 * @copyright Copyright (c) 2024 Кузьменко Валентин (Valentink2410)
 * @author Кузьменко Валентин (Valentink2410)
 */

if (!defined('ABSPATH')) {
    exit;
}

class Course_WP_Login_Registration {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Поля Имя / Фамилия в стандартной форме регистрации
        add_action('register_form', array($this, 'render_extra_fields'));
        add_filter('registration_errors', array($this, 'validate_extra_fields'), 10, 3);
        add_action('user_register', array($this, 'save_extra_fields'));

        // Перехватываем стандартное уведомление WordPress, чтобы отправить своё
        add_filter('wp_new_user_notification_email', array($this, 'custom_new_user_email'), 10, 3);

        // Подключаем стили для дополнительных полей на странице регистрации
        add_action('login_enqueue_scripts', array($this, 'enqueue_register_styles'));
    }

    /**
     * Вывод полей Имя / Фамилия в форме wp-login.php?action=register
     */
    public function render_extra_fields() {
        $first_name = isset($_POST['first_name']) ? sanitize_text_field($_POST['first_name']) : '';
        $last_name  = isset($_POST['last_name'])  ? sanitize_text_field($_POST['last_name'])  : '';
        ?>
        <p>
            <label for="first_name"><?php _e('Имя', 'course-plugin'); ?><br />
                <input type="text" name="first_name" id="first_name" class="input"
                       value="<?php echo esc_attr($first_name); ?>" size="25" />
            </label>
        </p>
        <p>
            <label for="last_name"><?php _e('Фамилия', 'course-plugin'); ?> <span class="required" style="color:#c00;">*</span><br />
                <input type="text" name="last_name" id="last_name" class="input"
                       value="<?php echo esc_attr($last_name); ?>" size="25" required />
            </label>
        </p>
        <?php
    }

    /**
     * Валидация: Фамилия обязательна
     */
    public function validate_extra_fields($errors, $sanitized_user_login, $user_email) {
        if (empty($_POST['last_name']) || trim($_POST['last_name']) === '') {
            $errors->add('last_name_error', __('<strong>Ошибка</strong>: Введите фамилию.', 'course-plugin'));
        }
        return $errors;
    }

    /**
     * Сохранение Имя / Фамилия + синхронизация с Moodle
     */
    public function save_extra_fields($user_id) {
        $first_name = isset($_POST['first_name']) ? sanitize_text_field($_POST['first_name']) : '';
        $last_name  = isset($_POST['last_name'])  ? sanitize_text_field($_POST['last_name'])  : '';

        if ($first_name) {
            update_user_meta($user_id, 'first_name', $first_name);
        }
        if ($last_name) {
            update_user_meta($user_id, 'last_name', $last_name);
        }

        $user = get_userdata($user_id);
        if ($user && ($first_name || $last_name)) {
            $display = trim($first_name . ' ' . $last_name);
            if ($display) {
                wp_update_user(array('ID' => $user_id, 'display_name' => $display));
            }
        }

        // Генерируем пароль для пользователя
        $password = wp_generate_password(12, true, false);

        // Делаем пароль совместимым с Moodle (спецсимвол + цифра)
        if (!preg_match('/[*\-#]/', $password)) {
            $password .= '-';
        }
        if (!preg_match('/[0-9]/', $password)) {
            $password .= '1';
        }

        wp_set_password($password, $user_id);

        // Сохраняем пароль для письма и Moodle-синхронизации
        update_user_meta($user_id, 'pending_moodle_password', $password);
        $user = get_userdata($user_id);
        if ($user) {
            $GLOBALS['moodle_user_sync_password'][$user->user_login] = $password;
        }

        // Сохраняем пароль в transient для использования в фильтре письма
        set_transient('course_reg_password_' . $user_id, $password, 300);

        error_log('WP Login Registration: Пароль сгенерирован для user_id=' . $user_id);

        // Синхронизируем с Moodle
        if (class_exists('Course_Moodle_User_Sync')) {
            try {
                $sync = Course_Moodle_User_Sync::get_instance();
                $sync->sync_user($user_id, $password);
                error_log('WP Login Registration: Moodle-синхронизация выполнена для user_id=' . $user_id);
            } catch (\Exception $e) {
                error_log('WP Login Registration: Ошибка Moodle-синхронизации: ' . $e->getMessage());
            } catch (\Error $e) {
                error_log('WP Login Registration: Фатальная ошибка Moodle-синхронизации: ' . $e->getMessage());
            }
        }
    }

    /**
     * Подменяем стандартное письмо WordPress на кастомное с паролем и ссылками
     */
    public function custom_new_user_email($wp_new_user_notification_email, $user, $blogname) {
        $password = get_transient('course_reg_password_' . $user->ID);

        if (!$password) {
            $password = get_user_meta($user->ID, 'pending_moodle_password', true);
        }

        if (!$password) {
            return $wp_new_user_notification_email;
        }

        delete_transient('course_reg_password_' . $user->ID);

        $moodle_url  = get_option('moodle_sync_url', '');
        $laravel_url = get_option('laravel_api_url', '');
        $moodle_user_id = get_user_meta($user->ID, 'moodle_user_id', true);

        $message  = sprintf(__('Здравствуйте, %s!', 'course-plugin'), $user->display_name) . "\r\n\r\n";
        $message .= sprintf(__('Добро пожаловать на сайт %s!', 'course-plugin'), $blogname) . "\r\n\r\n";
        $message .= __('Ваши данные для входа:', 'course-plugin') . "\r\n";
        $message .= sprintf(__('Логин: %s', 'course-plugin'), $user->user_login) . "\r\n";
        $message .= sprintf(__('Email: %s', 'course-plugin'), $user->user_email) . "\r\n";
        $message .= sprintf(__('Пароль: %s', 'course-plugin'), $password) . "\r\n\r\n";

        $message .= __('Вы можете использовать эти данные для входа на:', 'course-plugin') . "\r\n";
        $message .= sprintf(__('- Сайт МБС: %s', 'course-plugin'), wp_login_url()) . "\r\n";
        $message .= sprintf(__('- Виртуальный класс МБС: %s', 'course-plugin'), 'https://class.russianseminary.org/login/index.php') . "\r\n";

        if ($moodle_user_id && !empty($moodle_url)) {
            $moodle_login = rtrim($moodle_url, '/') . '/login/index.php';
            if ($moodle_login !== 'https://class.russianseminary.org/login/index.php') {
                $message .= sprintf(__('- Moodle: %s', 'course-plugin'), $moodle_login) . "\r\n";
            }
        }
        if (!empty($laravel_url)) {
            $message .= sprintf(__('- Система управления: %s', 'course-plugin'), rtrim($laravel_url, '/') . '/login') . "\r\n";
        }
        $message .= "\r\n";

        $message .= __('С уважением,', 'course-plugin') . "\r\n";
        $message .= sprintf(__('Команда %s', 'course-plugin'), $blogname) . "\r\n";

        $wp_new_user_notification_email['subject'] = sprintf(__('[%s] Добро пожаловать!', 'course-plugin'), $blogname);
        $wp_new_user_notification_email['message'] = $message;
        $wp_new_user_notification_email['headers'] = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $blogname . ' <mbs@russianseminary.org>',
        );

        error_log('WP Login Registration: Кастомное письмо подготовлено для ' . $user->user_email);

        return $wp_new_user_notification_email;
    }

    /**
     * Стили для дополнительных полей на странице регистрации
     */
    public function enqueue_register_styles() {
        if (!isset($_GET['action']) || $_GET['action'] !== 'register') {
            return;
        }
        ?>
        <style>
            #registerform .required { color: #c00; }
            #registerform p label { display: block; margin-bottom: 8px; }
        </style>
        <?php
    }
}
