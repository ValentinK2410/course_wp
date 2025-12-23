<?php
/**
 * Класс для админ-панели настроек защиты от ботов
 */

// Проверка безопасности: если файл вызывается напрямую, прекращаем выполнение
if (!defined('ABSPATH')) {
    exit;
}

class Course_Anti_Bot_Admin {
    
    /**
     * Единственный экземпляр класса (Singleton)
     */
    private static $instance = null;
    
    /**
     * Получить экземпляр класса
     * 
     * @return Course_Anti_Bot_Admin Экземпляр класса
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
        add_action('admin_menu', array($this, 'add_admin_menu'));
    }
    
    /**
     * Добавление пункта меню в админку
     */
    public function add_admin_menu() {
        // Используем то же меню, что и настройки Moodle Sync
        // Если меню настроек Moodle существует, добавляем подменю к нему
        // Иначе создаем отдельное меню
        $parent_slug = 'course-plugin-settings';
        
        add_submenu_page(
            $parent_slug,
            __('Защита от ботов', 'course-plugin'),
            __('Защита от ботов', 'course-plugin'),
            'manage_options',
            'course-anti-bot',
            array($this, 'render_admin_page')
        );
    }
    
    /**
     * Отображение страницы настроек
     */
    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('У вас нет прав для доступа к этой странице.', 'course-plugin'));
        }
        
        // Сохранение настроек
        if (isset($_POST['submit']) && isset($_POST['option_page']) && $_POST['option_page'] === 'course_anti_bot_settings') {
            check_admin_referer('course_anti_bot_settings-options');
        }
        
        $recaptcha_site_key = get_option('recaptcha_site_key', '');
        $recaptcha_secret_key = get_option('recaptcha_secret_key', '');
        $anti_bot_enabled = get_option('anti_bot_enabled', true);
        $rate_limit_enabled = get_option('rate_limit_enabled', true);
        $rate_limit_attempts = get_option('rate_limit_attempts', 5);
        $rate_limit_period = get_option('rate_limit_period', 3600);
        
        ?>
        <div class="wrap">
            <h1><?php _e('Настройки защиты от ботов', 'course-plugin'); ?></h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('course_anti_bot_settings'); ?>
                
                <h2><?php _e('Общие настройки', 'course-plugin'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="anti_bot_enabled"><?php _e('Включить защиту от ботов', 'course-plugin'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" 
                                   id="anti_bot_enabled" 
                                   name="anti_bot_enabled" 
                                   value="1" 
                                   <?php checked(1, $anti_bot_enabled); ?> />
                            <p class="description">
                                <?php _e('Включает все методы защиты от ботов (honeypot, rate limiting, reCAPTCHA).', 'course-plugin'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <h2><?php _e('Google reCAPTCHA v3', 'course-plugin'); ?></h2>
                <p class="description">
                    <?php _e('reCAPTCHA v3 работает в фоновом режиме и не требует действий от пользователя. Получите ключи на', 'course-plugin'); ?>
                    <a href="https://www.google.com/recaptcha/admin" target="_blank"><?php _e('Google reCAPTCHA', 'course-plugin'); ?></a>
                </p>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="recaptcha_site_key"><?php _e('Site Key', 'course-plugin'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="recaptcha_site_key" 
                                   name="recaptcha_site_key" 
                                   value="<?php echo esc_attr($recaptcha_site_key); ?>" 
                                   class="regular-text" />
                            <p class="description">
                                <?php _e('Публичный ключ reCAPTCHA (Site Key).', 'course-plugin'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="recaptcha_secret_key"><?php _e('Secret Key', 'course-plugin'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="recaptcha_secret_key" 
                                   name="recaptcha_secret_key" 
                                   value="<?php echo esc_attr($recaptcha_secret_key); ?>" 
                                   class="regular-text" />
                            <p class="description">
                                <?php _e('Секретный ключ reCAPTCHA (Secret Key). Не показывайте его никому!', 'course-plugin'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <h2><?php _e('Ограничение попыток регистрации (Rate Limiting)', 'course-plugin'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="rate_limit_enabled"><?php _e('Включить ограничение попыток', 'course-plugin'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" 
                                   id="rate_limit_enabled" 
                                   name="rate_limit_enabled" 
                                   value="1" 
                                   <?php checked(1, $rate_limit_enabled); ?> />
                            <p class="description">
                                <?php _e('Ограничивает количество попыток регистрации с одного IP адреса.', 'course-plugin'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="rate_limit_attempts"><?php _e('Максимум попыток', 'course-plugin'); ?></label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="rate_limit_attempts" 
                                   name="rate_limit_attempts" 
                                   value="<?php echo esc_attr($rate_limit_attempts); ?>" 
                                   class="small-text" 
                                   min="1" 
                                   max="20" />
                            <p class="description">
                                <?php _e('Максимальное количество попыток регистрации за период.', 'course-plugin'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="rate_limit_period"><?php _e('Период (в секундах)', 'course-plugin'); ?></label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="rate_limit_period" 
                                   name="rate_limit_period" 
                                   value="<?php echo esc_attr($rate_limit_period); ?>" 
                                   class="small-text" 
                                   min="60" 
                                   step="60" />
                            <p class="description">
                                <?php _e('Период времени для подсчета попыток (в секундах). Например: 3600 = 1 час, 86400 = 24 часа.', 'course-plugin'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <h2><?php _e('Информация о защите', 'course-plugin'); ?></h2>
            <div class="card">
                <h3><?php _e('Методы защиты:', 'course-plugin'); ?></h3>
                <ul>
                    <li><strong><?php _e('Honeypot поле', 'course-plugin'); ?></strong> - <?php _e('Скрытое поле, которое заполняют только боты. Всегда включено.', 'course-plugin'); ?></li>
                    <li><strong><?php _e('Проверка времени заполнения', 'course-plugin'); ?></strong> - <?php _e('Форма должна заполняться минимум 2 секунды. Всегда включено.', 'course-plugin'); ?></li>
                    <li><strong><?php _e('reCAPTCHA v3', 'course-plugin'); ?></strong> - <?php _e('Невидимая проверка от Google. Требует настройки ключей.', 'course-plugin'); ?></li>
                    <li><strong><?php _e('Rate Limiting', 'course-plugin'); ?></strong> - <?php _e('Ограничение попыток регистрации с одного IP адреса.', 'course-plugin'); ?></li>
                </ul>
            </div>
        </div>
        <?php
    }
}

