<?php
/**
 * Класс для админ-панели настроек email (SMTP)
 * 
 * @copyright Copyright (c) 2024 Кузьменко Валентин (Valentink2410)
 * @author Кузьменко Валентин (Valentink2410)
 */

// Проверка безопасности: если файл вызывается напрямую, прекращаем выполнение
if (!defined('ABSPATH')) {
    exit;
}

class Course_Email_Admin {
    
    /**
     * Единственный экземпляр класса (Singleton)
     */
    private static $instance = null;
    
    /**
     * Получить экземпляр класса
     * 
     * @return Course_Email_Admin Экземпляр класса
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
        // Добавляем пункт меню в админ-панель
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Регистрируем настройки
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * Добавление пункта меню в админ-панель
     */
    public function add_admin_menu() {
        add_submenu_page(
            'options-general.php',                    // Родительское меню (Настройки)
            'Настройки Email',                        // Заголовок страницы
            'Email (SMTP)',                           // Название в меню
            'manage_options',                         // Права доступа
            'course-email-settings',                  // Slug страницы
            array($this, 'render_settings_page')      // Функция отображения
        );
    }
    
    /**
     * Регистрация настроек (с санитизацией — так надёжнее сохраняется в options.php)
     */
    public function register_settings() {
        register_setting(
            'course_email_settings',
            'course_smtp_host',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => '',
            )
        );
        register_setting(
            'course_email_settings',
            'course_smtp_port',
            array(
                'type' => 'integer',
                'sanitize_callback' => array($this, 'sanitize_smtp_port'),
                'default' => 587,
            )
        );
        register_setting(
            'course_email_settings',
            'course_smtp_username',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => '',
            )
        );
        register_setting(
            'course_email_settings',
            'course_smtp_password',
            array(
                'type' => 'string',
                'sanitize_callback' => array($this, 'sanitize_smtp_password'),
                'default' => '',
            )
        );
        register_setting(
            'course_email_settings',
            'course_smtp_encryption',
            array(
                'type' => 'string',
                'sanitize_callback' => array($this, 'sanitize_smtp_encryption'),
                'default' => 'tls',
            )
        );
        register_setting(
            'course_email_settings',
            'course_smtp_from_email',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_email',
                'default' => '',
            )
        );
        register_setting(
            'course_email_settings',
            'course_smtp_from_name',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => '',
            )
        );
    }

    /**
     * @param mixed $value Значение из формы.
     */
    public function sanitize_smtp_port($value) {
        $p = absint($value);
        return ($p >= 1 && $p <= 65535) ? $p : 587;
    }

    /**
     * Пустое поле пароля не затирает уже сохранённый пароль.
     *
     * @param mixed $value Значение из формы.
     */
    public function sanitize_smtp_password($value) {
        if ($value === null || $value === '') {
            return (string) get_option('course_smtp_password', '');
        }
        return is_string($value) ? $value : '';
    }

    /**
     * @param mixed $value Значение из формы.
     */
    public function sanitize_smtp_encryption($value) {
        $v = is_string($value) ? strtolower(trim($value)) : '';
        if ($v === 'ssl' || $v === 'tls' || $v === '') {
            return $v;
        }
        return 'tls';
    }
    
    /**
     * Отображение страницы настроек
     */
    public function render_settings_page() {
        // Обработка тестовой отправки
        $test_results = array();
        if (isset($_POST['test_email']) && isset($_POST['test_email_addresses'])) {
            check_admin_referer('course_email_test');
            
            $test_addresses = sanitize_textarea_field($_POST['test_email_addresses']);
            $test_subject = isset($_POST['test_email_subject']) ? sanitize_text_field($_POST['test_email_subject']) : 'Тест отправки email';
            $test_message_text = isset($_POST['test_email_message']) ? sanitize_textarea_field($_POST['test_email_message']) : '';
            
            // Разбиваем адреса по строкам и запятым
            $emails = preg_split('/[\s,;]+/', $test_addresses, -1, PREG_SPLIT_NO_EMPTY);
            
            if (empty($emails)) {
                $test_results[] = '<div class="notice notice-error"><p>Не указаны email адреса для теста.</p></div>';
            } else {
                if (class_exists('Course_Email_Sender')) {
                    $email_sender = Course_Email_Sender::get_instance();
                    $success_count = 0;
                    $fail_count = 0;
                    $results_details = array();
                    
                    foreach ($emails as $email) {
                        $email = trim($email);
                        if (is_email($email)) {
                            // Используем пользовательский текст или стандартный
                            if (empty($test_message_text)) {
                                $message = "Это тестовое письмо для проверки настроек отправки email.\n\n";
                                $message .= "Если вы получили это письмо, значит настройки работают корректно.\n\n";
                                $message .= "Время отправки: " . date('Y-m-d H:i:s') . "\n";
                                $message .= "Сервер: " . $_SERVER['SERVER_NAME'] . "\n";
                                $message .= "Email получателя: " . $email . "\n";
                            } else {
                                $message = $test_message_text;
                            }
                            
                            $result = $email_sender->send_email($email, $test_subject, $message);
                            
                            if ($result['success']) {
                                $success_count++;
                                $results_details[] = '<strong>' . esc_html($email) . '</strong>: ✓ ' . esc_html($result['message']) . ' <em>(' . esc_html($result['method']) . ')</em>';
                            } else {
                                $fail_count++;
                                $results_details[] = '<strong>' . esc_html($email) . '</strong>: ✗ Ошибка - ' . esc_html($result['message']);
                            }
                        } else {
                            $fail_count++;
                            $results_details[] = '<strong>' . esc_html($email) . '</strong>: ✗ Неверный email адрес';
                        }
                    }
                    
                    // Формируем итоговое сообщение
                    $total = count($emails);
                    if ($success_count > 0) {
                        $test_results[] = '<div class="notice notice-success"><p><strong>Успешно отправлено:</strong> ' . $success_count . ' из ' . $total . '</p></div>';
                    }
                    if ($fail_count > 0) {
                        $test_results[] = '<div class="notice notice-error"><p><strong>Ошибок:</strong> ' . $fail_count . ' из ' . $total . '</p></div>';
                    }
                    if (!empty($results_details)) {
                        $test_results[] = '<div class="notice notice-info"><p><strong>Детали:</strong><br>' . implode('<br>', $results_details) . '</p></div>';
                    }
                } else {
                    $test_results[] = '<div class="notice notice-error"><p>Класс Course_Email_Sender не найден. Убедитесь, что плагин активирован.</p></div>';
                }
            }
        }
        
        ?>
        <div class="wrap">
            <h1>Настройки Email (SMTP)</h1>
            <p><strong>Основной способ:</strong> заполните поля ниже и нажмите «Сохранить настройки». Рекомендуем <strong>Яндекс Почту</strong> (или Яндекс 360 для домена): внешний SMTP нужен, чтобы письма не шли напрямую с IP сервера (иначе часто блокировка, в т.ч. из‑за PTR).</p>
            <?php if (defined('COURSE_SMTP_HOST') && COURSE_SMTP_HOST !== '') : ?>
                <div class="notice notice-warning"><p>Дополнительно заданы константы <code>COURSE_SMTP_*</code> в <code>wp-config.php</code> — они <strong>перекрывают</strong> значения с этой страницы. Чтобы пользоваться только формой, уберите константы.</p></div>
            <?php endif; ?>
            
            <?php if (!empty($test_results)): ?>
                <?php foreach ($test_results as $result): ?>
                    <?php echo $result; ?>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <?php
            $diag_disable = get_option('disable_email_sending', false);
            $diag_smtp_ok = class_exists('Course_Email_Sender') && Course_Email_Sender::is_smtp_fully_configured();
            ?>
            <div class="notice <?php echo $diag_smtp_ok && !$diag_disable ? 'notice-success' : 'notice-warning'; ?>" style="margin:12px 0;">
                <p><strong><?php esc_html_e('Диагностика перед отправкой:', 'course-plugin'); ?></strong></p>
                <ul style="list-style:disc;padding-left:1.5em;margin:0;">
                    <li><?php echo $diag_disable ? '<span style="color:#b32d2e;">✗</span> ' . esc_html__('Включено «Отключить отправку писем» (Moodle) — письма блокируются.', 'course-plugin') : '✓ ' . esc_html__('Массовая отправка не отключена в настройках Moodle.', 'course-plugin'); ?></li>
                    <li><?php echo $diag_smtp_ok ? '✓ ' . esc_html__('SMTP задан: хост, логин и пароль есть в базе.', 'course-plugin') : '<span style="color:#b32d2e;">✗</span> ' . esc_html__('SMTP неполный: нужны сервер, логин и сохранённый пароль.', 'course-plugin'); ?></li>
                </ul>
                <?php if (!$diag_smtp_ok) : ?>
                    <p><?php esc_html_e('Если пароль не сохранялся: введите пароль от Яндекса и нажмите «Сохранить настройки», затем снова «Отправить тестовое письмо».', 'course-plugin'); ?></p>
                <?php endif; ?>
                <p class="description"><?php esc_html_e('Отладка SMTP в лог: в wp-config.php временно добавьте define(\'COURSE_SMTP_DEBUG\', true); и WP_DEBUG_LOG — в debug.log появятся строки «Course SMTP debug».', 'course-plugin'); ?></p>
            </div>
            
            <form method="post" action="options.php">
                <?php settings_fields('course_email_settings'); ?>
                <?php do_settings_sections('course_email_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="course_smtp_host">SMTP Сервер</label>
                        </th>
                        <td>
                            <input type="text" id="course_smtp_host" name="course_smtp_host" 
                                   value="<?php echo esc_attr(get_option('course_smtp_host', '')); ?>" 
                                   class="regular-text" placeholder="smtp.yandex.ru" />
                            <p class="description"><?php esc_html_e('Для Яндекса: smtp.yandex.ru. Также: smtp.mail.ru, smtp.gmail.com и др.', 'course-plugin'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="course_smtp_port">SMTP Порт</label>
                        </th>
                        <td>
                            <input type="number" id="course_smtp_port" name="course_smtp_port" 
                                   value="<?php echo esc_attr(get_option('course_smtp_port', 587)); ?>" 
                                   class="small-text" min="1" max="65535" />
                            <p class="description"><?php esc_html_e('Яндекс: 465 (SSL) или 587 (TLS). Не смешивайте: к 465 — шифрование SSL, к 587 — TLS.', 'course-plugin'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="course_smtp_username">SMTP Логин</label>
                        </th>
                        <td>
                            <input type="text" id="course_smtp_username" name="course_smtp_username" 
                                   value="<?php echo esc_attr(get_option('course_smtp_username', '')); ?>" 
                                   class="regular-text" placeholder="vasya@yandex.ru" />
                            <p class="description"><?php esc_html_e('Для Яндекса — полный адрес ящика (логин), как при входе в почту.', 'course-plugin'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="course_smtp_password">SMTP Пароль</label>
                        </th>
                        <td>
                            <input type="password" id="course_smtp_password" name="course_smtp_password" 
                                   value="" autocomplete="new-password" class="regular-text" />
                            <?php if (get_option('course_smtp_password', '') !== '') : ?>
                                <p class="description"><?php esc_html_e('Пароль уже сохранён в базе. Оставьте поле пустым, чтобы не менять; введите новый — чтобы заменить.', 'course-plugin'); ?></p>
                            <?php else : ?>
                                <p class="description"><?php esc_html_e('Пароль или пароль приложения для SMTP.', 'course-plugin'); ?></p>
                            <?php endif; ?>
                            <p class="description"><strong>Яндекс:</strong> <?php esc_html_e('пароль от почты. При включённой двухфакторной аутентификации — создайте пароль приложения в настройках аккаунта.', 'course-plugin'); ?> <a href="https://yandex.ru/support/id/authorization/app-passwords.html" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Справка Яндекса', 'course-plugin'); ?></a></p>
                            <p class="description"><strong>Gmail:</strong> <?php esc_html_e('пароль приложения.', 'course-plugin'); ?> <a href="https://support.google.com/accounts/answer/185833" target="_blank" rel="noopener noreferrer">Google</a></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="course_smtp_encryption">Шифрование</label>
                        </th>
                        <td>
                            <select id="course_smtp_encryption" name="course_smtp_encryption">
                                <option value="tls" <?php selected(get_option('course_smtp_encryption', 'tls'), 'tls'); ?>>TLS</option>
                                <option value="ssl" <?php selected(get_option('course_smtp_encryption', 'tls'), 'ssl'); ?>>SSL</option>
                                <option value="" <?php selected(get_option('course_smtp_encryption', 'tls'), ''); ?>>Без шифрования</option>
                            </select>
                            <p class="description">TLS рекомендуется для порта 587, SSL для порта 465</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="course_smtp_from_email">Email отправителя</label>
                        </th>
                        <td>
                            <input type="email" id="course_smtp_from_email" name="course_smtp_from_email" 
                                   value="<?php echo esc_attr(get_option('course_smtp_from_email', get_option('admin_email'))); ?>" 
                                   class="regular-text" />
                            <p class="description"><?php esc_html_e('Для Яндекса лучше указать тот же адрес, что и в «SMTP Логин» (или доверенный алиас в настройках ящика).', 'course-plugin'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="course_smtp_from_name">Имя отправителя</label>
                        </th>
                        <td>
                            <input type="text" id="course_smtp_from_name" name="course_smtp_from_name" 
                                   value="<?php echo esc_attr(get_option('course_smtp_from_name', get_bloginfo('name'))); ?>" 
                                   class="regular-text" />
                            <p class="description">Имя, которое будет отображаться как отправитель</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Сохранить настройки'); ?>
            </form>
            
            <hr>
            
            <h2>Тестовая отправка</h2>
            <p>Отправьте тестовое письмо на один или несколько адресов для проверки настроек отправки email.</p>
            <form method="post" action="">
                <?php wp_nonce_field('course_email_test'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="test_email_addresses">Email адреса для теста</label>
                        </th>
                        <td>
                            <textarea id="test_email_addresses" name="test_email_addresses" 
                                      rows="4" class="large-text" required><?php echo esc_textarea(get_option('admin_email')); ?></textarea>
                            <p class="description">
                                Укажите один или несколько email адресов (разделяйте запятыми, пробелами или переносами строк).<br>
                                Например: test@gmail.com, test@yandex.ru или каждый адрес с новой строки
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="test_email_subject">Тема письма</label>
                        </th>
                        <td>
                            <input type="text" id="test_email_subject" name="test_email_subject" 
                                   value="Тест отправки email - <?php echo date('Y-m-d H:i:s'); ?>" 
                                   class="large-text" />
                            <p class="description">Тема тестового письма</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="test_email_message">Текст письма</label>
                        </th>
                        <td>
                            <textarea id="test_email_message" name="test_email_message" 
                                      rows="8" class="large-text"></textarea>
                            <p class="description">
                                Введите текст письма. Если оставить пустым, будет использован стандартный тестовый текст.<br>
                                Можно использовать для проверки отправки на разные почтовые сервисы (Gmail, Yandex, Mail.ru и т.д.)
                            </p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Отправить тестовое письмо', 'secondary', 'test_email'); ?>
            </form>
            
            <hr>
            
            <h2><?php esc_html_e('Готовые значения для Яндекс Почты', 'course-plugin'); ?></h2>
            <div class="notice notice-info">
                <p><strong><?php esc_html_e('Скопируйте в форму выше (свой ящик и пароль подставьте свои):', 'course-plugin'); ?></strong></p>
                <ul>
                    <li><strong>SMTP:</strong> smtp.yandex.ru</li>
                    <li><strong><?php esc_html_e('Вариант А:', 'course-plugin'); ?></strong> <?php esc_html_e('порт 465, шифрование SSL', 'course-plugin'); ?></li>
                    <li><strong><?php esc_html_e('Вариант Б:', 'course-plugin'); ?></strong> <?php esc_html_e('порт 587, шифрование TLS', 'course-plugin'); ?></li>
                    <li><strong><?php esc_html_e('Логин:', 'course-plugin'); ?></strong> <?php esc_html_e('полный email, например ivan@yandex.ru или user@ваш-домен.ru (если почта на Яндексе для домена)', 'course-plugin'); ?></li>
                    <li><strong><?php esc_html_e('Пароль:', 'course-plugin'); ?></strong> <?php esc_html_e('от ящика; при 2FA — пароль приложения', 'course-plugin'); ?></li>
                    <li><strong><?php esc_html_e('От кого:', 'course-plugin'); ?></strong> <?php esc_html_e('тот же email, что логин (или разрешённый в Яндексе адрес)', 'course-plugin'); ?></li>
                </ul>
                <p><a href="https://yandex.ru/support/yandex-360/customers/mail/ru/mail-clients" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Официальная справка Яндекса про почтовые программы', 'course-plugin'); ?></a></p>
            </div>
            
            <h2><?php esc_html_e('Альтернатива: Gmail', 'course-plugin'); ?></h2>
            <div class="notice notice-info">
                <p><strong>SMTP:</strong> smtp.gmail.com, <?php esc_html_e('порт', 'course-plugin'); ?> 587, TLS. <?php esc_html_e('Пароль — только приложения Google.', 'course-plugin'); ?></p>
            </div>
        </div>
        <?php
    }
}

