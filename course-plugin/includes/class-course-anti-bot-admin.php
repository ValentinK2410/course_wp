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
        // Добавляем страницу настроек в раздел "Настройки" (Settings)
        // Так же, как и настройки Moodle Sync
        add_options_page(
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
        
        $anti_bot_enabled = get_option('anti_bot_enabled', true);
        $rate_limit_enabled = get_option('rate_limit_enabled', true);
        $rate_limit_attempts = get_option('rate_limit_attempts', 5);
        $rate_limit_period = get_option('rate_limit_period', 3600);
        $math_challenge_enabled = get_option('math_challenge_enabled', true);
        $behavior_analysis_enabled = get_option('behavior_analysis_enabled', true);
        $field_order_check_enabled = get_option('field_order_check_enabled', true);
        
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
                
                <h2><?php _e('Методы защиты', 'course-plugin'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="math_challenge_enabled"><?php _e('Математическая задача', 'course-plugin'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" 
                                   id="math_challenge_enabled" 
                                   name="math_challenge_enabled" 
                                   value="1" 
                                   <?php checked(1, $math_challenge_enabled); ?> />
                            <p class="description">
                                <?php _e('Добавляет простую математическую задачу в форму регистрации. Пользователь должен решить её перед отправкой формы.', 'course-plugin'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="behavior_analysis_enabled"><?php _e('Анализ поведения пользователя', 'course-plugin'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" 
                                   id="behavior_analysis_enabled" 
                                   name="behavior_analysis_enabled" 
                                   value="1" 
                                   <?php checked(1, $behavior_analysis_enabled); ?> />
                            <p class="description">
                                <?php _e('Анализирует поведение пользователя: движения мыши, клики, паттерны ввода, время заполнения формы. Очень эффективно против ботов.', 'course-plugin'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="field_order_check_enabled"><?php _e('Проверка последовательности заполнения полей', 'course-plugin'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" 
                                   id="field_order_check_enabled" 
                                   name="field_order_check_enabled" 
                                   value="1" 
                                   <?php checked(1, $field_order_check_enabled); ?> />
                            <p class="description">
                                <?php _e('Отслеживает порядок заполнения полей формы. Боты часто заполняют поля не по порядку или слишком быстро.', 'course-plugin'); ?>
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
                <h3><?php _e('Методы защиты (все работают без сторонних сервисов):', 'course-plugin'); ?></h3>
                <ul>
                    <li><strong><?php _e('Honeypot поле', 'course-plugin'); ?></strong> - <?php _e('Скрытое поле, которое заполняют только боты. Всегда включено, не требует настройки.', 'course-plugin'); ?></li>
                    <li><strong><?php _e('Проверка времени заполнения', 'course-plugin'); ?></strong> - <?php _e('Форма должна заполняться минимум 3 секунды. Всегда включено, не требует настройки.', 'course-plugin'); ?></li>
                    <li><strong><?php _e('Математическая задача', 'course-plugin'); ?></strong> - <?php _e('Простая математическая задача (например: 5 + 3 = ?). Пользователь должен решить её перед отправкой формы.', 'course-plugin'); ?></li>
                    <li><strong><?php _e('Анализ поведения пользователя', 'course-plugin'); ?></strong> - <?php _e('Отслеживает движения мыши, клики, паттерны ввода, время между действиями. Очень эффективно против ботов.', 'course-plugin'); ?></li>
                    <li><strong><?php _e('Проверка последовательности заполнения полей', 'course-plugin'); ?></strong> - <?php _e('Отслеживает порядок заполнения полей. Боты часто заполняют поля не по порядку.', 'course-plugin'); ?></li>
                    <li><strong><?php _e('Проверка JavaScript окружения', 'course-plugin'); ?></strong> - <?php _e('Проверяет характеристики браузера и устройства. Всегда включено.', 'course-plugin'); ?></li>
                    <li><strong><?php _e('Rate Limiting', 'course-plugin'); ?></strong> - <?php _e('Ограничение попыток регистрации с одного IP адреса. Настраивается.', 'course-plugin'); ?></li>
                </ul>
                <p><strong><?php _e('Преимущества:', 'course-plugin'); ?></strong></p>
                <ul>
                    <li><?php _e('Полная независимость от сторонних сервисов', 'course-plugin'); ?></li>
                    <li><?php _e('Не требует API ключей или регистрации', 'course-plugin'); ?></li>
                    <li><?php _e('Работает полностью на вашем сервере', 'course-plugin'); ?></li>
                    <li><?php _e('Не передает данные пользователей третьим лицам', 'course-plugin'); ?></li>
                    <li><?php _e('Множественные уровни защиты', 'course-plugin'); ?></li>
                </ul>
            </div>
        </div>
        <?php
    }
}

