<?php
/**
 * Класс для формы регистрации пользователей
 * 
 * Добавляет шорткод для отображения формы регистрации на любой странице
 */

// Проверка безопасности: если файл вызывается напрямую, прекращаем выполнение
if (!defined('ABSPATH')) {
    exit;
}

class Course_Registration {
    
    /**
     * Единственный экземпляр класса (Singleton)
     */
    private static $instance = null;
    
    /**
     * Получить экземпляр класса
     * 
     * @return Course_Registration Экземпляр класса
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Конструктор класса
     * Регистрирует шорткод для формы регистрации
     */
    private function __construct() {
        // Регистрируем шорткод [course_register] для отображения формы регистрации
        add_shortcode('course_register', array($this, 'registration_form'));
        
        // Обработка формы регистрации
        add_action('wp_ajax_course_register', array($this, 'process_registration'));
        add_action('wp_ajax_nopriv_course_register', array($this, 'process_registration'));
    }
    
    /**
     * Отображение формы регистрации
     * Вызывается при использовании шорткода [course_register]
     * 
     * @param array $atts Атрибуты шорткода
     * @return string HTML код формы регистрации
     */
    public function registration_form($atts) {
        // Если пользователь уже авторизован, показываем сообщение
        if (is_user_logged_in()) {
            $current_user = wp_get_current_user();
            return '<div class="course-registration-message">' . 
                   sprintf(__('Вы уже авторизованы как %s. <a href="%s">Выйти</a>', 'course-plugin'), 
                           esc_html($current_user->display_name),
                           wp_logout_url(home_url())) . 
                   '</div>';
        }
        
        // Проверяем, разрешена ли регистрация в WordPress
        if (!get_option('users_can_register')) {
            return '<div class="course-registration-error">' . 
                   __('Регистрация новых пользователей отключена. Обратитесь к администратору сайта.', 'course-plugin') . 
                   '</div>';
        }
        
        // Обрабатываем атрибуты шорткода
        $atts = shortcode_atts(array(
            'redirect' => '',  // URL для редиректа после регистрации
        ), $atts);
        
        // Получаем URL для редиректа
        $redirect_url = !empty($atts['redirect']) ? esc_url($atts['redirect']) : home_url();
        
        // Начинаем буферизацию вывода
        ob_start();
        ?>
        <div class="course-registration-form-wrapper">
            <form id="course-registration-form" class="course-registration-form" method="post" action="">
                <?php wp_nonce_field('course_register', 'course_register_nonce'); ?>
                <input type="hidden" name="action" value="course_register">
                <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_url); ?>">
                
                <h3><?php _e('Регистрация', 'course-plugin'); ?></h3>
                
                <div class="course-registration-messages" id="course-registration-messages"></div>
                
                <p>
                    <label for="user_login"><?php _e('Логин', 'course-plugin'); ?> <span class="required">*</span></label>
                    <input type="text" name="user_login" id="user_login" class="input" value="" size="20" required />
                    <small><?php _e('Используйте только латинские буквы, цифры и символы - и _', 'course-plugin'); ?></small>
                </p>
                
                <p>
                    <label for="user_email"><?php _e('Email', 'course-plugin'); ?> <span class="required">*</span></label>
                    <input type="email" name="user_email" id="user_email" class="input" value="" size="25" required />
                </p>
                
                <p>
                    <label for="first_name"><?php _e('Имя', 'course-plugin'); ?></label>
                    <input type="text" name="first_name" id="first_name" class="input" value="" size="25" />
                </p>
                
                <p>
                    <label for="last_name"><?php _e('Фамилия', 'course-plugin'); ?></label>
                    <input type="text" name="last_name" id="last_name" class="input" value="" size="25" />
                </p>
                
                <p>
                    <label for="user_pass"><?php _e('Пароль', 'course-plugin'); ?> <span class="required">*</span></label>
                    <input type="password" name="user_pass" id="user_pass" class="input" value="" size="25" required />
                    <small><?php _e('Минимум 8 символов', 'course-plugin'); ?></small>
                </p>
                
                <p>
                    <label for="user_pass_confirm"><?php _e('Подтвердите пароль', 'course-plugin'); ?> <span class="required">*</span></label>
                    <input type="password" name="user_pass_confirm" id="user_pass_confirm" class="input" value="" size="25" required />
                </p>
                
                <p class="submit">
                    <input type="submit" name="wp-submit" id="wp-submit" class="button button-primary" value="<?php esc_attr_e('Зарегистрироваться', 'course-plugin'); ?>" />
                </p>
                
                <p class="course-registration-login-link">
                    <?php _e('Уже есть аккаунт?', 'course-plugin'); ?> 
                    <a href="<?php echo esc_url(wp_login_url()); ?>"><?php _e('Войти', 'course-plugin'); ?></a>
                </p>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#course-registration-form').on('submit', function(e) {
                e.preventDefault();
                
                var $form = $(this);
                var $messages = $('#course-registration-messages');
                var $submit = $('#wp-submit');
                
                // Очищаем предыдущие сообщения
                $messages.html('').removeClass('error success');
                
                // Проверяем совпадение паролей
                if ($('#user_pass').val() !== $('#user_pass_confirm').val()) {
                    $messages.html('<div class="error"><?php echo esc_js(__('Пароли не совпадают!', 'course-plugin')); ?></div>').addClass('error');
                    return false;
                }
                
                // Проверяем длину пароля
                if ($('#user_pass').val().length < 8) {
                    $messages.html('<div class="error"><?php echo esc_js(__('Пароль должен содержать минимум 8 символов!', 'course-plugin')); ?></div>').addClass('error');
                    return false;
                }
                
                // Отключаем кнопку отправки
                $submit.prop('disabled', true).val('<?php echo esc_js(__('Регистрация...', 'course-plugin')); ?>');
                
                // Отправляем AJAX запрос
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: $form.serialize(),
                    success: function(response) {
                        if (response.success) {
                            $messages.html('<div class="success">' + response.data.message + '</div>').addClass('success');
                            $form[0].reset();
                            
                            // Редирект через 2 секунды
                            if (response.data.redirect) {
                                setTimeout(function() {
                                    window.location.href = response.data.redirect;
                                }, 2000);
                            }
                        } else {
                            $messages.html('<div class="error">' + response.data.message + '</div>').addClass('error');
                            $submit.prop('disabled', false).val('<?php echo esc_js(__('Зарегистрироваться', 'course-plugin')); ?>');
                        }
                    },
                    error: function() {
                        $messages.html('<div class="error"><?php echo esc_js(__('Произошла ошибка. Попробуйте позже.', 'course-plugin')); ?></div>').addClass('error');
                        $submit.prop('disabled', false).val('<?php echo esc_js(__('Зарегистрироваться', 'course-plugin')); ?>');
                    }
                });
            });
        });
        </script>
        
        <style>
        .course-registration-form-wrapper {
            max-width: 500px;
            margin: 20px auto;
            padding: 20px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .course-registration-form h3 {
            margin-top: 0;
        }
        .course-registration-form p {
            margin-bottom: 15px;
        }
        .course-registration-form label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .course-registration-form .required {
            color: #dc3232;
        }
        .course-registration-form input[type="text"],
        .course-registration-form input[type="email"],
        .course-registration-form input[type="password"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 3px;
        }
        .course-registration-form small {
            display: block;
            color: #666;
            font-size: 12px;
            margin-top: 5px;
        }
        .course-registration-messages {
            margin-bottom: 15px;
            padding: 10px;
            border-radius: 3px;
        }
        .course-registration-messages.error {
            background: #ffeaea;
            border: 1px solid #dc3232;
            color: #dc3232;
        }
        .course-registration-messages.success {
            background: #eafaea;
            border: 1px solid #46b450;
            color: #46b450;
        }
        .course-registration-login-link {
            text-align: center;
            margin-top: 15px;
        }
        </style>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Обработка формы регистрации
     * Вызывается через AJAX при отправке формы
     */
    public function process_registration() {
        // Проверяем nonce для безопасности
        if (!isset($_POST['course_register_nonce']) || !wp_verify_nonce($_POST['course_register_nonce'], 'course_register')) {
            wp_send_json_error(array('message' => __('Ошибка безопасности. Обновите страницу и попробуйте снова.', 'course-plugin')));
        }
        
        // Проверяем, разрешена ли регистрация
        if (!get_option('users_can_register')) {
            wp_send_json_error(array('message' => __('Регистрация новых пользователей отключена.', 'course-plugin')));
        }
        
        // Получаем данные из формы
        $user_login = isset($_POST['user_login']) ? sanitize_user($_POST['user_login']) : '';
        $user_email = isset($_POST['user_email']) ? sanitize_email($_POST['user_email']) : '';
        $first_name = isset($_POST['first_name']) ? sanitize_text_field($_POST['first_name']) : '';
        $last_name = isset($_POST['last_name']) ? sanitize_text_field($_POST['last_name']) : '';
        $user_pass = isset($_POST['user_pass']) ? $_POST['user_pass'] : '';
        $user_pass_confirm = isset($_POST['user_pass_confirm']) ? $_POST['user_pass_confirm'] : '';
        $redirect_to = isset($_POST['redirect_to']) ? esc_url_raw($_POST['redirect_to']) : home_url();
        
        // Валидация данных
        if (empty($user_login)) {
            wp_send_json_error(array('message' => __('Введите логин.', 'course-plugin')));
        }
        
        if (empty($user_email) || !is_email($user_email)) {
            wp_send_json_error(array('message' => __('Введите корректный email адрес.', 'course-plugin')));
        }
        
        if (empty($user_pass)) {
            wp_send_json_error(array('message' => __('Введите пароль.', 'course-plugin')));
        }
        
        if ($user_pass !== $user_pass_confirm) {
            wp_send_json_error(array('message' => __('Пароли не совпадают.', 'course-plugin')));
        }
        
        if (strlen($user_pass) < 8) {
            wp_send_json_error(array('message' => __('Пароль должен содержать минимум 8 символов.', 'course-plugin')));
        }
        
        // Проверяем, не существует ли уже пользователь с таким логином или email
        if (username_exists($user_login)) {
            wp_send_json_error(array('message' => __('Пользователь с таким логином уже существует.', 'course-plugin')));
        }
        
        if (email_exists($user_email)) {
            wp_send_json_error(array('message' => __('Пользователь с таким email уже существует.', 'course-plugin')));
        }
        
        // Сохраняем пароль во временное хранилище ДО создания пользователя
        // Это необходимо для синхронизации с Moodle
        $GLOBALS['moodle_user_sync_password'][$user_login] = $user_pass;
        
        // Создаем пользователя
        $user_data = array(
            'user_login' => $user_login,
            'user_email' => $user_email,
            'user_pass' => $user_pass,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'role' => 'subscriber'  // Роль по умолчанию
        );
        
        $user_id = wp_insert_user($user_data);
        
        if (is_wp_error($user_id)) {
            // Удаляем пароль из памяти при ошибке
            unset($GLOBALS['moodle_user_sync_password'][$user_login]);
            wp_send_json_error(array('message' => $user_id->get_error_message()));
        }
        
        // Вызываем синхронизацию с Moodle напрямую
        // Это гарантирует, что пользователь будет создан в Moodle даже если хуки не сработают
        error_log('Course Registration: Попытка синхронизации пользователя ID: ' . $user_id . ', логин: ' . $user_login);
        
        // Сохраняем пароль в глобальную переменную ДО вызова синхронизации
        $GLOBALS['moodle_user_sync_password'][$user_login] = $user_pass;
        error_log('Course Registration: Пароль сохранен в глобальную переменную для логина: ' . $user_login);
        
        if (class_exists('Course_Moodle_User_Sync')) {
            error_log('Course Registration: Класс Course_Moodle_User_Sync найден');
            try {
                $sync_instance = Course_Moodle_User_Sync::get_instance();
                error_log('Course Registration: Экземпляр класса получен');
                
                // Вызываем публичный метод синхронизации с паролем
                $result = $sync_instance->sync_user($user_id, $user_pass);
                error_log('Course Registration: Результат синхронизации: ' . ($result ? 'успешно (true)' : 'ошибка (false)'));
                
                if (!$result) {
                    error_log('Course Registration: ВНИМАНИЕ! Синхронизация вернула false. Проверьте логи Moodle User Sync выше.');
                }
            } catch (Exception $e) {
                error_log('Course Registration: ИСКЛЮЧЕНИЕ при синхронизации: ' . $e->getMessage());
            }
        } else {
            error_log('Course Registration: КРИТИЧЕСКАЯ ОШИБКА - Класс Course_Moodle_User_Sync не найден!');
            // Если класс не загружен, вызываем через хук
            do_action('user_register', $user_id);
        }
        
        // Отправляем письмо пользователю о регистрации
        // Проверяем версию WordPress для использования правильной функции
        if (function_exists('wp_send_new_user_notifications')) {
            // WordPress 5.9+ - используем новую функцию
            wp_send_new_user_notifications($user_id, 'user');
        } elseif (function_exists('wp_new_user_notification')) {
            // Старые версии WordPress - используем старую функцию
            wp_new_user_notification($user_id, null, 'user');
        } else {
            // Если функции недоступны, отправляем письмо вручную
            $this->send_registration_email($user_id, $user_pass);
        }
        
        // Автоматически входим пользователя после регистрации
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);
        
        // Отправляем успешный ответ
        wp_send_json_success(array(
            'message' => __('Регистрация успешно завершена! Вы будете перенаправлены...', 'course-plugin'),
            'redirect' => $redirect_to
        ));
    }
    
    /**
     * Отправка письма пользователю о регистрации
     * Используется, если стандартные функции WordPress недоступны
     * 
     * @param int $user_id ID пользователя
     * @param string $password Пароль пользователя
     */
    private function send_registration_email($user_id, $password) {
        $user = get_userdata($user_id);
        
        if (!$user) {
            return;
        }
        
        // Получаем настройки сайта
        $blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
        $admin_email = get_option('admin_email');
        
        // Формируем тему письма
        $subject = sprintf(__('[%s] Добро пожаловать!', 'course-plugin'), $blogname);
        
        // Формируем текст письма
        $message = sprintf(__('Здравствуйте, %s!', 'course-plugin'), $user->display_name) . "\r\n\r\n";
        $message .= sprintf(__('Добро пожаловать на сайт %s!', 'course-plugin'), $blogname) . "\r\n\r\n";
        $message .= __('Ваши данные для входа:', 'course-plugin') . "\r\n";
        $message .= sprintf(__('Логин: %s', 'course-plugin'), $user->user_login) . "\r\n";
        $message .= sprintf(__('Пароль: %s', 'course-plugin'), $password) . "\r\n\r\n";
        $message .= sprintf(__('Войти на сайт: %s', 'course-plugin'), wp_login_url()) . "\r\n\r\n";
        $message .= __('С уважением,', 'course-plugin') . "\r\n";
        $message .= sprintf(__('Команда %s', 'course-plugin'), $blogname) . "\r\n";
        
        // Заголовки письма
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $blogname . ' <' . $admin_email . '>'
        );
        
        // Отправляем письмо
        wp_mail($user->user_email, $subject, $message, $headers);
    }
}

