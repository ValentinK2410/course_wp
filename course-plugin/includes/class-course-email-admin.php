<?php
/**
 * Класс для админ-панели настроек email (SMTP)
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
     * Регистрация настроек
     */
    public function register_settings() {
        // SMTP настройки
        register_setting('course_email_settings', 'course_smtp_host');
        register_setting('course_email_settings', 'course_smtp_port');
        register_setting('course_email_settings', 'course_smtp_username');
        register_setting('course_email_settings', 'course_smtp_password');
        register_setting('course_email_settings', 'course_smtp_encryption');
        register_setting('course_email_settings', 'course_smtp_from_email');
        register_setting('course_email_settings', 'course_smtp_from_name');
    }
    
    /**
     * Отображение страницы настроек
     */
    public function render_settings_page() {
        // Обработка тестовой отправки
        if (isset($_POST['test_email']) && isset($_POST['test_email_address'])) {
            check_admin_referer('course_email_test');
            $test_email = sanitize_email($_POST['test_email_address']);
            if (is_email($test_email)) {
                if (class_exists('Course_Email_Sender')) {
                    $email_sender = Course_Email_Sender::get_instance();
                    $result = $email_sender->test_email_sending($test_email);
                    $test_message = $result['success'] 
                        ? '<div class="notice notice-success"><p>Тестовое письмо успешно отправлено на ' . esc_html($test_email) . ' методом: ' . esc_html($result['method']) . '</p></div>'
                        : '<div class="notice notice-error"><p>Ошибка отправки тестового письма: ' . esc_html($result['message']) . '</p></div>';
                } else {
                    $test_message = '<div class="notice notice-error"><p>Класс Course_Email_Sender не найден. Убедитесь, что плагин активирован.</p></div>';
                }
            } else {
                $test_message = '<div class="notice notice-error"><p>Неверный email адрес.</p></div>';
            }
        }
        
        ?>
        <div class="wrap">
            <h1>Настройки Email (SMTP)</h1>
            <p>Настройте SMTP для улучшения доставляемости email, особенно для Gmail.</p>
            
            <?php if (isset($test_message)) echo $test_message; ?>
            
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
                                   class="regular-text" placeholder="smtp.gmail.com" />
                            <p class="description">Например: smtp.gmail.com, smtp.yandex.ru, smtp.mail.ru</p>
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
                            <p class="description">Обычно 587 для TLS или 465 для SSL</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="course_smtp_username">SMTP Логин</label>
                        </th>
                        <td>
                            <input type="text" id="course_smtp_username" name="course_smtp_username" 
                                   value="<?php echo esc_attr(get_option('course_smtp_username', '')); ?>" 
                                   class="regular-text" placeholder="your-email@gmail.com" />
                            <p class="description">Email адрес для авторизации на SMTP сервере</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="course_smtp_password">SMTP Пароль</label>
                        </th>
                        <td>
                            <input type="password" id="course_smtp_password" name="course_smtp_password" 
                                   value="<?php echo esc_attr(get_option('course_smtp_password', '')); ?>" 
                                   class="regular-text" />
                            <p class="description">Пароль или пароль приложения для SMTP</p>
                            <p class="description"><strong>Для Gmail:</strong> Используйте пароль приложения, а не обычный пароль. <a href="https://support.google.com/accounts/answer/185833" target="_blank">Как создать пароль приложения</a></p>
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
                            <p class="description">Email адрес, который будет указан как отправитель</p>
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
            <p>Отправьте тестовое письмо для проверки настроек SMTP.</p>
            <form method="post" action="">
                <?php wp_nonce_field('course_email_test'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="test_email_address">Email для теста</label>
                        </th>
                        <td>
                            <input type="email" id="test_email_address" name="test_email_address" 
                                   value="<?php echo esc_attr(get_option('admin_email')); ?>" 
                                   class="regular-text" required />
                            <p class="description">На этот адрес будет отправлено тестовое письмо</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Отправить тестовое письмо', 'secondary', 'test_email'); ?>
            </form>
            
            <hr>
            
            <h2>Рекомендации для Gmail</h2>
            <div class="notice notice-info">
                <p><strong>Для улучшения доставляемости в Gmail:</strong></p>
                <ol>
                    <li>Используйте SMTP настройки выше (рекомендуется)</li>
                    <li>Для Gmail используйте пароль приложения, а не обычный пароль</li>
                    <li>Настройте SPF записи в DNS вашего домена</li>
                    <li>Настройте DKIM подпись для вашего домена</li>
                    <li>Настройте DMARC политику</li>
                    <li>Убедитесь, что email отправителя совпадает с доменом сайта</li>
                </ol>
                <p><strong>SMTP настройки для Gmail:</strong></p>
                <ul>
                    <li>SMTP Сервер: smtp.gmail.com</li>
                    <li>SMTP Порт: 587</li>
                    <li>Шифрование: TLS</li>
                    <li>Логин: ваш Gmail адрес</li>
                    <li>Пароль: пароль приложения (не обычный пароль!)</li>
                </ul>
            </div>
        </div>
        <?php
    }
}

