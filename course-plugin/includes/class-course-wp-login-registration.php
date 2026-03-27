<?php
/**
 * Расширение стандартной формы регистрации WordPress (wp-login.php?action=register)
 *
 * - Скрывает поле «Логин» и автоматически формирует его из email (часть до @).
 * - Добавляет поля Имя / Фамилия.
 * - Генерирует Moodle-совместимый пароль.
 * - Отправляет письмо с логином, паролем и ссылкой подтверждения email.
 * - Создаёт аккаунт в Moodle только после подтверждения email по ссылке.
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
        // Автогенерация логина из email ДО обработки формы WordPress
        add_action('login_form_register', array($this, 'auto_generate_login_from_email'));

        add_action('register_form', array($this, 'render_extra_fields'));
        add_filter('registration_errors', array($this, 'validate_extra_fields'), 10, 3);
        add_action('user_register', array($this, 'save_extra_fields'));

        add_filter('wp_new_user_notification_email', array($this, 'custom_new_user_email'), 10, 3);

        add_action('login_enqueue_scripts', array($this, 'enqueue_register_styles'));

        // Обработка ссылки подтверждения email
        add_action('init', array($this, 'handle_email_confirmation'));

        // Сообщение на странице входа после подтверждения
        add_filter('login_message', array($this, 'login_confirmation_message'));
    }

    /**
     * Автоматически формирует user_login из email при POST-запросе регистрации.
     * Срабатывает до register_new_user(), подставляя логин в $_POST.
     */
    public function auto_generate_login_from_email() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }
        $email = isset($_POST['user_email']) ? sanitize_email($_POST['user_email']) : '';
        if (empty($email)) {
            return;
        }
        $login = $this->generate_login_from_email($email);
        $_POST['user_login'] = $login;
    }

    // ------------------------------------------------------------------
    // 1. Форма регистрации: скрытие логина, поля Имя / Фамилия + JS
    // ------------------------------------------------------------------

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
        <script>
        (function(){
            var loginField = document.getElementById('user_login');
            var emailField = document.getElementById('user_email');
            if (loginField && emailField) {
                loginField.closest('p').style.display = 'none';
                function syncLogin() {
                    var email = emailField.value || '';
                    var at = email.indexOf('@');
                    var base = at > 0 ? email.substring(0, at) : email;
                    base = base.toLowerCase().replace(/[^a-z0-9._-]/g, '');
                    if (!base) base = 'user';
                    loginField.value = base;
                }
                emailField.addEventListener('input', syncLogin);
                emailField.addEventListener('change', syncLogin);
                syncLogin();
            }
        })();
        </script>
        <?php
    }

    // ------------------------------------------------------------------
    // 2. Валидация: фамилия обязательна + серверная генерация логина
    // ------------------------------------------------------------------

    public function validate_extra_fields($errors, $sanitized_user_login, $user_email) {
        if (empty($_POST['last_name']) || trim($_POST['last_name']) === '') {
            $errors->add('last_name_error', __('<strong>Ошибка</strong>: Введите фамилию.', 'course-plugin'));
        }

        if (!empty($user_email)) {
            $base = $this->generate_login_from_email($user_email);
            $_POST['user_login'] = $base;
            $_POST['log'] = $base;

            $existing_errors = $errors->get_error_codes();
            if (in_array('empty_username', $existing_errors, true)) {
                $errors->remove('empty_username');
            }
            if (in_array('invalid_username', $existing_errors, true)) {
                $errors->remove('invalid_username');
            }
            if (in_array('username_exists', $existing_errors, true)) {
                $errors->remove('username_exists');
            }
        }

        return $errors;
    }

    /**
     * Генерирует уникальный логин из email: часть до @, транслитерация, дедупликация.
     */
    private function generate_login_from_email($email) {
        $parts = explode('@', $email);
        $base  = strtolower($parts[0]);

        $base = $this->transliterate_string($base);
        $base = preg_replace('/[^a-z0-9._-]/', '', $base);

        if (empty($base)) {
            $base = 'user';
        }

        $login = sanitize_user($base, true);
        if (empty($login)) {
            $login = 'user';
        }

        if (!username_exists($login)) {
            return $login;
        }

        $i = 2;
        while (username_exists($login . $i)) {
            $i++;
        }
        return $login . $i;
    }

    /**
     * Транслитерация кириллицы в латиницу (для логинов).
     */
    private function transliterate_string($text) {
        $table = array(
            'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'yo',
            'ж'=>'zh','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m',
            'н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u',
            'ф'=>'f','х'=>'kh','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'shch',
            'ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya',
        );
        return strtr($text, $table);
    }

    // ------------------------------------------------------------------
    // 3. Хук user_register: пароль + токен подтверждения (без Moodle)
    // ------------------------------------------------------------------

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

        $password = wp_generate_password(12, true, false);
        if (!preg_match('/[*\-#]/', $password)) {
            $password .= '-';
        }
        if (!preg_match('/[0-9]/', $password)) {
            $password .= '1';
        }

        wp_set_password($password, $user_id);

        update_user_meta($user_id, 'pending_moodle_password', $password);
        set_transient('course_reg_password_' . $user_id, $password, 300);

        // Токен подтверждения email
        $token = wp_generate_password(32, false);
        update_user_meta($user_id, 'email_confirm_token', $token);
        update_user_meta($user_id, 'email_confirmed', '0');

        error_log('WP Login Registration: Пользователь создан user_id=' . $user_id . ', ожидает подтверждения email');
    }

    // ------------------------------------------------------------------
    // 4. Кастомное письмо: логин, пароль, ссылка подтверждения
    // ------------------------------------------------------------------

    public function custom_new_user_email($wp_new_user_notification_email, $user, $blogname) {
        $password = get_transient('course_reg_password_' . $user->ID);
        if (!$password) {
            $password = get_user_meta($user->ID, 'pending_moodle_password', true);
        }
        if (!$password) {
            return $wp_new_user_notification_email;
        }

        delete_transient('course_reg_password_' . $user->ID);

        $token = get_user_meta($user->ID, 'email_confirm_token', true);
        $confirm_url = site_url('?course_confirm_email=' . urlencode($token));

        $message  = sprintf(__('Здравствуйте, %s!', 'course-plugin'), $user->display_name) . "\r\n\r\n";
        $message .= sprintf(__('Добро пожаловать на сайт %s!', 'course-plugin'), $blogname) . "\r\n\r\n";

        $message .= __('Ваши данные для входа:', 'course-plugin') . "\r\n";
        $message .= sprintf(__('Логин: %s', 'course-plugin'), $user->user_login) . "\r\n";
        $message .= sprintf(__('Email: %s', 'course-plugin'), $user->user_email) . "\r\n";
        $message .= sprintf(__('Пароль: %s', 'course-plugin'), $password) . "\r\n\r\n";

        $message .= __('Для получения доступа к виртуальному классу подтвердите ваш email, перейдя по ссылке:', 'course-plugin') . "\r\n";
        $message .= $confirm_url . "\r\n\r\n";

        $message .= __('После подтверждения вы сможете использовать эти данные для входа на:', 'course-plugin') . "\r\n";
        $message .= sprintf(__('- Сайт МБС: %s', 'course-plugin'), wp_login_url()) . "\r\n";
        $message .= sprintf(__('- Виртуальный класс МБС: %s', 'course-plugin'), 'https://class.russianseminary.org/login/index.php') . "\r\n";

        $moodle_url  = get_option('moodle_sync_url', '');
        $laravel_url = get_option('laravel_api_url', '');
        if (!empty($moodle_url)) {
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

        $wp_new_user_notification_email['subject'] = sprintf(__('[%s] Подтвердите ваш email', 'course-plugin'), $blogname);
        $wp_new_user_notification_email['message'] = $message;
        $wp_new_user_notification_email['headers'] = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $blogname . ' <mbs@russianseminary.org>',
        );

        error_log('WP Login Registration: Письмо с подтверждением подготовлено для ' . $user->user_email);

        return $wp_new_user_notification_email;
    }

    // ------------------------------------------------------------------
    // 5. Обработка ссылки подтверждения email
    // ------------------------------------------------------------------

    public function handle_email_confirmation() {
        if (!isset($_GET['course_confirm_email']) || empty($_GET['course_confirm_email'])) {
            return;
        }

        $token = sanitize_text_field($_GET['course_confirm_email']);

        $users = get_users(array(
            'meta_key'   => 'email_confirm_token',
            'meta_value' => $token,
            'number'     => 1,
        ));

        if (empty($users)) {
            wp_safe_redirect(add_query_arg('email_confirm_status', 'invalid', wp_login_url()));
            exit;
        }

        $user = $users[0];
        $already_confirmed = get_user_meta($user->ID, 'email_confirmed', true);

        if ($already_confirmed === '1') {
            wp_safe_redirect(add_query_arg('email_confirm_status', 'already', wp_login_url()));
            exit;
        }

        // Подтверждаем email
        update_user_meta($user->ID, 'email_confirmed', '1');
        delete_user_meta($user->ID, 'email_confirm_token');

        error_log('WP Login Registration: Email подтверждён для user_id=' . $user->ID);

        // Синхронизируем с Moodle
        $password = get_user_meta($user->ID, 'pending_moodle_password', true);
        if ($password && class_exists('Course_Moodle_User_Sync')) {
            try {
                $GLOBALS['moodle_user_sync_password'][$user->user_login] = $password;
                $sync = Course_Moodle_User_Sync::get_instance();
                $sync->sync_user($user->ID, $password);
                error_log('WP Login Registration: Moodle-синхронизация выполнена после подтверждения email для user_id=' . $user->ID);
            } catch (\Exception $e) {
                error_log('WP Login Registration: Ошибка Moodle-синхронизации: ' . $e->getMessage());
            } catch (\Error $e) {
                error_log('WP Login Registration: Фатальная ошибка Moodle-синхронизации: ' . $e->getMessage());
            }
        }

        delete_user_meta($user->ID, 'pending_moodle_password');

        wp_safe_redirect(add_query_arg('email_confirm_status', 'confirmed', wp_login_url()));
        exit;
    }

    // ------------------------------------------------------------------
    // 6. Сообщение на странице входа
    // ------------------------------------------------------------------

    public function login_confirmation_message($message) {
        if (!isset($_GET['email_confirm_status'])) {
            return $message;
        }

        $status = sanitize_text_field($_GET['email_confirm_status']);

        switch ($status) {
            case 'confirmed':
                $message .= '<p class="message course-email-confirmed">'
                    . __('Email подтверждён! Ваш аккаунт в виртуальном классе создан. Теперь вы можете войти.', 'course-plugin')
                    . '</p>';
                break;
            case 'already':
                $message .= '<p class="message">'
                    . __('Ваш email уже был подтверждён ранее. Вы можете войти.', 'course-plugin')
                    . '</p>';
                break;
            case 'invalid':
                $message .= '<p class="message" style="border-left-color:#dc3232;">'
                    . __('Ссылка подтверждения недействительна или устарела.', 'course-plugin')
                    . '</p>';
                break;
        }

        return $message;
    }

    // ------------------------------------------------------------------
    // 7. Стили
    // ------------------------------------------------------------------

    public function enqueue_register_styles() {
        if (!isset($_GET['action']) || $_GET['action'] !== 'register') {
            return;
        }
        ?>
        <style>
            #registerform > p:first-child { display: none !important; }
            #registerform .required { color: #c00; }
            #registerform p label { display: block; margin-bottom: 8px; }
        </style>
        <?php
    }
}
