<?php
/**
 * Класс для синхронизации данных из Moodle в WordPress
 * 
 * Этот класс синхронизирует курсы, категории и студентов из Moodle в WordPress
 * Использует Moodle REST API для получения данных и создает/обновляет записи в WordPress
 * 
 * @copyright Copyright (c) 2024 Кузьменко Валентин (Valentink2410)
 * @author Кузьменко Валентин (Valentink2410)
 */

// Проверка безопасности: если файл вызывается напрямую, прекращаем выполнение
if (!defined('ABSPATH')) {
    exit;
}

class Course_Moodle_Sync {
    
    /**
     * Единственный экземпляр класса (Singleton)
     * Хранит объект класса, если он уже был создан
     */
    private static $instance = null;
    
    /**
     * URL сайта Moodle (например: https://class.dekan.pro)
     * 
     * @var string
     */
    private $moodle_url;
    
    /**
     * Токен для доступа к Moodle REST API
     * 
     * @var string
     */
    private $moodle_token;
    
    /**
     * Объект для работы с Moodle API
     * 
     * @var Course_Moodle_API
     */
    private $api;
    
    /**
     * Получить экземпляр класса
     * Паттерн Singleton: гарантирует создание только одного экземпляра класса
     * 
     * @return Course_Moodle_Sync Экземпляр класса
     */
    public static function get_instance() {
        // Если экземпляр еще не создан, создаем его
        if (null === self::$instance) {
            self::$instance = new self();
        }
        // Возвращаем существующий или только что созданный экземпляр
        return self::$instance;
    }
    
    /**
     * Конструктор класса
     * Приватный, чтобы предотвратить создание экземпляра напрямую
     * Инициализирует настройки и регистрирует хуки WordPress
     */
    private function __construct() {
        // Получаем настройки синхронизации из базы данных WordPress
        // get_option() получает значение опции из таблицы wp_options
        $this->moodle_url = get_option('moodle_sync_url', '');
        $this->moodle_token = get_option('moodle_sync_token', '');
        
        // Создаем объект для работы с Moodle API, если настроены URL и токен
        if ($this->moodle_url && $this->moodle_token) {
            if (class_exists('Course_Moodle_API')) {
                $this->api = new Course_Moodle_API($this->moodle_url, $this->moodle_token);
            } else {
                error_log('Course Plugin Error: Course_Moodle_API class not found');
            }
        }
        
        // Регистрируем хук для добавления меню в админку WordPress
        // Хук 'admin_menu' срабатывает при построении меню администратора
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Регистрируем хук для регистрации настроек плагина
        // Хук 'admin_init' срабатывает при загрузке страниц администратора
        add_action('admin_init', array($this, 'register_settings'));
        
        // Регистрируем обработчик для cron задачи синхронизации
        // Хук 'moodle_sync_cron' будет вызываться по расписанию
        add_action('moodle_sync_cron', array($this, 'sync_data'));
        
        // Регистрируем cron задачу, если она еще не зарегистрирована
        // wp_next_scheduled() проверяет, запланирована ли уже задача
        if (!wp_next_scheduled('moodle_sync_cron')) {
            // wp_schedule_event() создает повторяющуюся задачу
            // time() - текущее время (задача начнется сразу)
            // 'hourly' - интервал выполнения (каждый час)
            // 'moodle_sync_cron' - название хука для выполнения
            wp_schedule_event(time(), 'hourly', 'moodle_sync_cron');
        }
    }
    
    /**
     * Добавление меню в админку WordPress
     * Создает страницу настроек синхронизации в разделе "Настройки"
     */
    public function add_admin_menu() {
        // Добавляем страницу настроек в меню "Настройки" (Settings)
        // add_options_page() создает подстраницу в разделе "Настройки"
        add_options_page(
            'Moodle Sync',                          // Заголовок страницы (в теге <title>)
            'Moodle Sync',                          // Название пункта меню
            'manage_options',                       // Права доступа (только администраторы)
            'moodle-sync',                          // Уникальный идентификатор страницы (slug)
            array($this, 'admin_page')             // Функция для отображения содержимого страницы
        );
    }
    
    /**
     * Регистрация настроек плагина
     * Регистрирует опции, которые будут сохраняться в базе данных WordPress
     */
    public function register_settings() {
        // Регистрируем опцию для хранения URL сайта Moodle
        // register_setting() регистрирует опцию и добавляет валидацию
        register_setting('moodle_sync_settings', 'moodle_sync_url', array(
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw'  // Очистка URL для безопасности
        ));
        
        // Регистрируем опцию для хранения токена Moodle
        register_setting('moodle_sync_settings', 'moodle_sync_token', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field'  // Очистка текста для безопасности
        ));
        
        // Регистрируем опцию для включения/выключения автоматической синхронизации
        register_setting('moodle_sync_settings', 'moodle_sync_enabled', array(
            'type' => 'boolean',
            'default' => false
        ));
        
        // Регистрируем опцию для управления обновлением существующих курсов
        register_setting('moodle_sync_settings', 'moodle_sync_update_courses', array(
            'type' => 'boolean',
            'default' => true  // По умолчанию обновляем существующие курсы
        ));
        
        // Регистрируем опцию для управления обновлением существующих категорий
        register_setting('moodle_sync_settings', 'moodle_sync_update_categories', array(
            'type' => 'boolean',
            'default' => true  // По умолчанию обновляем существующие категории
        ));
        
        // Регистрируем опции для синхронизации с Laravel
        register_setting('moodle_sync_settings', 'laravel_api_url', array(
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw'  // Очистка URL для безопасности
        ));
        
        register_setting('moodle_sync_settings', 'laravel_api_token', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field'  // Очистка текста для безопасности
        ));
        
        // Регистрируем опцию для SSO API ключа
        register_setting('moodle_sync_settings', 'sso_api_key', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field'  // Очистка текста для безопасности
        ));
        
        // Регистрируем опцию для Moodle SSO API ключа (для обратного SSO)
        register_setting('moodle_sync_settings', 'moodle_sso_api_key', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field'  // Очистка текста для безопасности
        ));
        
        // Регистрируем опцию для отключения отправки писем (для тестирования)
        register_setting('moodle_sync_settings', 'disable_email_sending', array(
            'type' => 'boolean',
            'default' => false  // По умолчанию письма отправляются
        ));
    }
    
    /**
     * Страница настроек в админке WordPress
     * Отображает форму для настройки синхронизации с Moodle
     */
    public function admin_page() {
        try {
            // Проверяем права доступа пользователя
            // current_user_can() проверяет, имеет ли текущий пользователь указанные права
            if (!current_user_can('manage_options')) {
                // Если нет прав, прекращаем выполнение
                wp_die(__('У вас нет прав для доступа к этой странице.', 'course-plugin'));
            }
            
            // Проверяем, что необходимые классы загружены
            if (!class_exists('Course_Moodle_API')) {
                wp_die(
                    '<h1>' . __('Ошибка загрузки плагина', 'course-plugin') . '</h1>' .
                    '<p>' . __('Класс Course_Moodle_API не найден. Пожалуйста, убедитесь, что все файлы плагина загружены правильно.', 'course-plugin') . '</p>' .
                    '<p><a href="' . admin_url('plugins.php') . '">' . __('Вернуться к списку плагинов', 'course-plugin') . '</a></p>'
                );
            }
        
        // Обновляем настройки URL и токена при сохранении формы
        if (isset($_POST['submit']) && isset($_POST['option_page']) && $_POST['option_page'] === 'moodle_sync_settings') {
            // Обновляем URL и токен в свойствах класса
            $this->moodle_url = get_option('moodle_sync_url', '');
            $this->moodle_token = get_option('moodle_sync_token', '');
            
            // Пересоздаем объект API с новыми настройками
            if ($this->moodle_url && $this->moodle_token) {
                if (class_exists('Course_Moodle_API')) {
                    $this->api = new Course_Moodle_API($this->moodle_url, $this->moodle_token);
                } else {
                    error_log('Course Plugin Error: Course_Moodle_API class not found');
                }
            }
        }
        
        // Обрабатываем ручную синхронизацию, если была нажата кнопка
        if (isset($_POST['moodle_sync_manual']) && check_admin_referer('moodle_sync_manual', 'moodle_sync_nonce')) {
            // Выполняем синхронизацию (ручная синхронизация работает независимо от настройки автоматической)
            $result = $this->sync_data(true);
            
            // Выводим сообщение о результате
            if ($result['success']) {
                $courses_info = '';
                $categories_info = '';
                
                // Формируем информацию о курсах
                if (isset($result['courses_result'])) {
                    $cr = $result['courses_result'];
                    $courses_info = sprintf(
                        __('Курсов: %d (создано: %d, обновлено: %d, пропущено: %d)', 'course-plugin'),
                        $cr['count'],
                        isset($cr['created']) ? $cr['created'] : 0,
                        isset($cr['updated']) ? $cr['updated'] : 0,
                        isset($cr['skipped']) ? $cr['skipped'] : 0
                    );
                } else {
                    $courses_info = sprintf(__('Курсов: %d', 'course-plugin'), $result['courses']);
                }
                
                // Формируем информацию о категориях
                if (isset($result['categories_result'])) {
                    $catr = $result['categories_result'];
                    $categories_info = sprintf(
                        __('Категорий: %d (создано: %d, обновлено: %d, пропущено: %d)', 'course-plugin'),
                        $catr['count'],
                        isset($catr['created']) ? $catr['created'] : 0,
                        isset($catr['updated']) ? $catr['updated'] : 0,
                        isset($catr['skipped']) ? $catr['skipped'] : 0
                    );
                } else {
                    $categories_info = sprintf(__('Категорий: %d', 'course-plugin'), $result['categories']);
                }
                
                echo '<div class="notice notice-success is-dismissible"><p>' . 
                     __('Синхронизация завершена!', 'course-plugin') . ' ' . 
                     $courses_info . ', ' . $categories_info . 
                     '</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>' . 
                     esc_html($result['message']) . 
                     '</p></div>';
            }
        }
        
        // Получаем текущие значения настроек
        $moodle_url = get_option('moodle_sync_url', '');
        $moodle_token = get_option('moodle_sync_token', '');
        $moodle_enabled = get_option('moodle_sync_enabled', false);
        $moodle_update_courses = get_option('moodle_sync_update_courses', true);
        $moodle_update_categories = get_option('moodle_sync_update_categories', true);
        $moodle_sync_users_enabled = get_option('moodle_sync_users_enabled', true);
        $laravel_api_url = get_option('laravel_api_url', '');
        $laravel_api_token = get_option('laravel_api_token', '');
        $sso_api_key = get_option('sso_api_key', '');
        $moodle_sso_api_key = get_option('moodle_sso_api_key', '');
        $disable_email_sending = get_option('disable_email_sending', false);
        
        ?>
        <div class="wrap">
            <h1><?php _e('Настройки синхронизации Moodle', 'course-plugin'); ?></h1>
            
            <?php
            // Добавляем кнопки для перехода в Moodle и Laravel через SSO
            if (is_user_logged_in() && class_exists('Course_SSO')) {
                $moodle_url = get_option('moodle_sync_url', '');
                $laravel_url = get_option('laravel_api_url', '');
            }
            ?>
            
            <!-- Форма для сохранения настроек -->
            <form method="post" action="options.php">
                <?php 
                // Выводим скрытые поля для безопасности (nonce и т.д.)
                settings_fields('moodle_sync_settings'); 
                ?>
                
                <table class="form-table">
                    <!-- Поле для ввода URL сайта Moodle -->
                    <tr>
                        <th scope="row">
                            <label for="moodle_sync_url"><?php _e('Moodle URL', 'course-plugin'); ?></label>
                        </th>
                        <td>
                            <input type="url" 
                                   id="moodle_sync_url" 
                                   name="moodle_sync_url" 
                                   value="<?php echo esc_attr($moodle_url); ?>" 
                                   class="regular-text" 
                                   placeholder="https://class.dekan.pro" />
                            <p class="description">
                                <?php _e('URL вашего сайта Moodle (например: https://class.dekan.pro)', 'course-plugin'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Поле для ввода токена Moodle -->
                    <tr>
                        <th scope="row">
                            <label for="moodle_sync_token"><?php _e('Moodle Token', 'course-plugin'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="moodle_sync_token" 
                                   name="moodle_sync_token" 
                                   value="<?php echo esc_attr($moodle_token); ?>" 
                                   class="regular-text" />
                            <p class="description">
                                <?php _e('Токен для доступа к Moodle REST API. Получить можно в настройках Moodle: Site administration -> Plugins -> Web services -> Manage tokens', 'course-plugin'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Чекбокс для включения автоматической синхронизации -->
                    <tr>
                        <th scope="row">
                            <label for="moodle_sync_enabled"><?php _e('Включить синхронизацию', 'course-plugin'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" 
                                   id="moodle_sync_enabled" 
                                   name="moodle_sync_enabled" 
                                   value="1" 
                                   <?php checked(1, $moodle_enabled); ?> />
                            <p class="description">
                                <?php _e('Включить автоматическую синхронизацию каждый час', 'course-plugin'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Чекбокс для управления обновлением существующих курсов -->
                    <tr>
                        <th scope="row">
                            <label for="moodle_sync_update_courses"><?php _e('Обновлять существующие курсы', 'course-plugin'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" 
                                   id="moodle_sync_update_courses" 
                                   name="moodle_sync_update_courses" 
                                   value="1" 
                                   <?php checked(1, $moodle_update_courses); ?> />
                            <p class="description">
                                <?php _e('Если включено, существующие курсы будут обновляться данными из Moodle. Если выключено, существующие курсы будут пропускаться.', 'course-plugin'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Чекбокс для управления обновлением существующих категорий -->
                    <tr>
                        <th scope="row">
                            <label for="moodle_sync_update_categories"><?php _e('Обновлять существующие категории', 'course-plugin'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" 
                                   id="moodle_sync_update_categories" 
                                   name="moodle_sync_update_categories" 
                                   value="1" 
                                   <?php checked(1, $moodle_update_categories); ?> />
                            <p class="description">
                                <?php _e('Если включено, существующие категории будут обновляться данными из Moodle. Если выключено, существующие категории будут пропускаться.', 'course-plugin'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Чекбокс для включения синхронизации пользователей -->
                    <tr>
                        <th scope="row">
                            <label for="moodle_sync_users_enabled"><?php _e('Синхронизировать пользователей', 'course-plugin'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" 
                                   id="moodle_sync_users_enabled" 
                                   name="moodle_sync_users_enabled" 
                                   value="1" 
                                   <?php checked(1, $moodle_sync_users_enabled); ?> />
                            <p class="description">
                                <?php _e('Если включено, при регистрации и обновлении профиля в WordPress пользователь будет автоматически создаваться/обновляться в Moodle. Это позволит использовать один логин и пароль на обоих сайтах.', 'course-plugin'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <h2><?php _e('Настройки синхронизации с Laravel', 'course-plugin'); ?></h2>
                <p class="description"><?php _e('После создания пользователя в Moodle, он автоматически создается в Laravel приложении через API.', 'course-plugin'); ?></p>
                
                <table class="form-table">
                    <!-- Поле для ввода URL Laravel API -->
                    <tr>
                        <th scope="row">
                            <label for="laravel_api_url"><?php _e('Laravel API URL', 'course-plugin'); ?></label>
                        </th>
                        <td>
                            <input type="url" 
                                   id="laravel_api_url" 
                                   name="laravel_api_url" 
                                   value="<?php echo esc_attr($laravel_api_url); ?>" 
                                   class="regular-text" 
                                   placeholder="https://m.dekan.pro" />
                            <p class="description">
                                <?php _e('URL вашего Laravel приложения (например: https://m.dekan.pro)', 'course-plugin'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Поле для ввода токена Laravel API -->
                    <tr>
                        <th scope="row">
                            <label for="laravel_api_token"><?php _e('Laravel API Token', 'course-plugin'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="laravel_api_token" 
                                   name="laravel_api_token" 
                                   value="<?php echo esc_attr($laravel_api_token); ?>" 
                                   class="regular-text" />
                            <p class="description">
                                <?php _e('Токен для доступа к Laravel API. Должен совпадать с WORDPRESS_API_TOKEN в .env файле Laravel приложения.', 'course-plugin'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <h2><?php _e('Настройки Single Sign-On (SSO)', 'course-plugin'); ?></h2>
                <p class="description"><?php _e('Позволяет пользователям автоматически входить в Moodle и Laravel после входа в WordPress.', 'course-plugin'); ?></p>
                
                <table class="form-table">
                    <!-- Поле для ввода SSO API ключа -->
                    <tr>
                        <th scope="row">
                            <label for="sso_api_key"><?php _e('SSO API Key', 'course-plugin'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="sso_api_key" 
                                   name="sso_api_key" 
                                   value="<?php echo esc_attr($sso_api_key); ?>" 
                                   class="regular-text" />
                            <p class="description">
                                <?php _e('Секретный ключ для проверки SSO токенов. Должен совпадать с WORDPRESS_SSO_API_KEY в .env файле Laravel приложения. Если пусто, будет сгенерирован автоматически.', 'course-plugin'); ?>
                            </p>
                            <?php if (empty($sso_api_key)): ?>
                            <p class="description" style="color: #d63638;">
                                <strong><?php _e('Внимание:', 'course-plugin'); ?></strong> <?php _e('Ключ не установлен. Рекомендуется установить уникальный ключ для безопасности.', 'course-plugin'); ?>
                            </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    
                    <!-- Поле для ввода Moodle SSO API ключа (для обратного SSO) -->
                    <tr>
                        <th scope="row">
                            <label for="moodle_sso_api_key"><?php _e('Moodle SSO API Key', 'course-plugin'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="moodle_sso_api_key" 
                                   name="moodle_sso_api_key" 
                                   value="<?php echo esc_attr($moodle_sso_api_key); ?>" 
                                   class="regular-text" />
                            <p class="description">
                                <?php _e('Секретный ключ для обратного SSO (вход из Moodle в WordPress). Этот ключ должен быть указан в файле moodle-sso-to-wordpress.php на сервере Moodle. Если пусто, будет сгенерирован автоматически.', 'course-plugin'); ?>
                            </p>
                            <?php if (empty($moodle_sso_api_key)): ?>
                            <p class="description" style="color: #d63638;">
                                <strong><?php _e('Внимание:', 'course-plugin'); ?></strong> <?php _e('Ключ не установлен. Рекомендуется установить уникальный ключ для безопасности.', 'course-plugin'); ?>
                            </p>
                            <?php endif; ?>
                            <?php if (!empty($moodle_sso_api_key)): ?>
                            <p class="description" style="color: #00a32a;">
                                <strong><?php _e('Текущий ключ:', 'course-plugin'); ?></strong> <code style="font-size: 11px; word-break: break-all;"><?php echo esc_html($moodle_sso_api_key); ?></code>
                            </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                
                <h2><?php _e('Настройки тестирования', 'course-plugin'); ?></h2>
                <table class="form-table">
                    <!-- Чекбокс для отключения отправки писем (для тестирования) -->
                    <tr>
                        <th scope="row">
                            <label for="disable_email_sending"><?php _e('Отключить отправку писем', 'course-plugin'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" 
                                   id="disable_email_sending" 
                                   name="disable_email_sending" 
                                   value="1" 
                                   <?php checked(1, $disable_email_sending); ?> />
                            <p class="description">
                                <?php _e('Если включено, все письма пользователям будут отключены. Используйте это для тестирования плагина без отправки реальных писем. Письма будут логироваться, но не будут отправляться.', 'course-plugin'); ?>
                            </p>
                            <?php if ($disable_email_sending): ?>
                            <p class="description" style="color: #d63638;">
                                <strong><?php _e('Внимание:', 'course-plugin'); ?></strong> <?php _e('Отправка писем отключена. Пользователи не будут получать письма с паролями и другой информацией.', 'course-plugin'); ?>
                            </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                
                <?php 
                // Выводим кнопку "Сохранить изменения"
                submit_button(); 
                ?>
            </form>
            
            <hr>
            
            <!-- Раздел для ручной синхронизации -->
            <h2><?php _e('Ручная синхронизация', 'course-plugin'); ?></h2>
            <p><?php _e('Нажмите кнопку ниже для немедленной синхронизации данных из Moodle.', 'course-plugin'); ?></p>
            
            <form method="post" action="">
                <?php 
                // Создаем поле nonce для защиты от CSRF-атак
                // wp_nonce_field() создает скрытое поле с токеном безопасности
                wp_nonce_field('moodle_sync_manual', 'moodle_sync_nonce'); 
                ?>
                <input type="submit" 
                       name="moodle_sync_manual" 
                       class="button button-primary" 
                       value="<?php esc_attr_e('Синхронизировать сейчас', 'course-plugin'); ?>" />
            </form>
        </div>
        <?php
        } catch (Exception $e) {
            // Логируем ошибку
            error_log('Course Plugin Error in admin_page(): ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            
            // Выводим понятное сообщение об ошибке
            wp_die(
                '<h1>' . __('Ошибка загрузки страницы настроек', 'course-plugin') . '</h1>' .
                '<p><strong>' . __('Произошла ошибка:', 'course-plugin') . '</strong> ' . esc_html($e->getMessage()) . '</p>' .
                '<p>' . __('Пожалуйста, проверьте логи WordPress для получения дополнительной информации.', 'course-plugin') . '</p>' .
                '<p><a href="' . admin_url('plugins.php') . '">' . __('Вернуться к списку плагинов', 'course-plugin') . '</a></p>',
                __('Ошибка плагина', 'course-plugin'),
                array('back_link' => true)
            );
        }
    }
    
    /**
     * Основная функция синхронизации данных
     * Выполняет синхронизацию категорий, курсов и студентов из Moodle
     * Вызывается автоматически по расписанию или вручную из админки
     * 
     * @param bool $manual Флаг ручной синхронизации (true = ручная, false = автоматическая)
     * @return array Массив с результатами синхронизации
     */
    public function sync_data($manual = false) {
        // Если это автоматическая синхронизация, проверяем, включена ли она
        // Ручная синхронизация работает независимо от этой настройки
        if (!$manual && !get_option('moodle_sync_enabled')) {
            return array(
                'success' => false,
                'message' => __('Автоматическая синхронизация отключена', 'course-plugin'),
                'courses' => 0,
                'categories' => 0
            );
        }
        
        // Проверяем, настроены ли URL и токен
        if (!$this->moodle_url || !$this->moodle_token) {
            $message = __('URL или токен Moodle не настроены. Пожалуйста, заполните настройки выше.', 'course-plugin');
            error_log('Moodle Sync: URL или токен не настроены');
            return array(
                'success' => false,
                'message' => $message,
                'courses' => 0,
                'categories' => 0
            );
        }
        
        // Создаем объект API, если он еще не создан
        if (!$this->api) {
            if (class_exists('Course_Moodle_API')) {
                $this->api = new Course_Moodle_API($this->moodle_url, $this->moodle_token);
            } else {
                error_log('Course Plugin Error: Course_Moodle_API class not found');
                return array(
                    'success' => false,
                    'message' => __('Ошибка: класс Course_Moodle_API не найден. Проверьте, что все файлы плагина загружены.', 'course-plugin'),
                    'courses' => 0,
                    'categories' => 0
                );
            }
        }
        
        // Счетчики для статистики
        $courses_count = 0;
        $categories_count = 0;
        
        // Синхронизируем категории курсов
        $categories_result = $this->sync_categories();
        if (is_array($categories_result)) {
            $categories_count = $categories_result['count'];
        } else {
            $categories_count = 0;
        }
        
        // Синхронизируем курсы
        $courses_result = $this->sync_courses();
        if (is_array($courses_result)) {
            $courses_count = $courses_result['count'];
        } else {
            $courses_count = 0;
        }
        
        // Синхронизируем студентов (с обработкой ошибок)
        try {
            $this->sync_students();
        } catch (Exception $e) {
            error_log('Moodle Sync: Ошибка при синхронизации студентов: ' . $e->getMessage());
            error_log('Moodle Sync: Stack trace: ' . $e->getTraceAsString());
        } catch (Error $e) {
            error_log('Moodle Sync: Критическая ошибка при синхронизации студентов: ' . $e->getMessage());
            error_log('Moodle Sync: Stack trace: ' . $e->getTraceAsString());
        }
        
        // Возвращаем результат синхронизации
        return array(
            'success' => true,
            'message' => __('Синхронизация успешно завершена', 'course-plugin'),
            'courses' => $courses_count,
            'categories' => $categories_count,
            'courses_result' => $courses_result,
            'categories_result' => $categories_result
        );
    }
    
    /**
     * Синхронизация категорий курсов из Moodle
     * Получает категории из Moodle и создает/обновляет их в WordPress как таксономию
     * 
     * @return array|false Массив с результатами или false в случае ошибки
     */
    private function sync_categories() {
        // Получаем список категорий из Moodle
        $categories = $this->api->get_categories();
        
        // Проверяем, успешно ли получены данные
        if (!$categories || !is_array($categories)) {
            $error_message = 'Moodle Sync: Не удалось получить категории из Moodle';
            error_log($error_message);
            if (is_array($categories) && isset($categories['exception'])) {
                error_log('Moodle API Exception: ' . $categories['message']);
            }
            return false;
        }
        
        $count = 0;
        $skipped = 0;
        
        // Проходим по каждой категории из Moodle
        foreach ($categories as $category) {
            // Сохраняем категорию в WordPress
            // Примечание: категории из Moodle больше не создают термины в таксономии course_specialization
            // В таксономию "Специализации и программы" попадают только программы из WordPress
            $result = $this->save_category($category);
            if ($result) {
                $count++;
            } else {
                // Если save_category вернул false, возможно категория была пропущена
                $skipped++;
            }
        }
        
        error_log('Moodle Sync: Обработано категорий: ' . $count . ' (пропущено: ' . $skipped . ')');
        error_log('Moodle Sync: Примечание - категории из Moodle не создают термины в таксономии "Специализации и программы". Эта таксономия предназначена только для программ.');
        
        return array('count' => $count, 'created' => 0, 'updated' => 0, 'skipped' => $skipped);
    }
    
    /**
     * Синхронизация курсов из Moodle
     * Получает курсы из Moodle и создает/обновляет их в WordPress как записи типа "course"
     * 
     * @return array|false Массив с результатами или false в случае ошибки
     */
    private function sync_courses() {
        // Получаем список курсов из Moodle
        $courses = $this->api->get_courses();
        
        // Проверяем, успешно ли получены данные
        if (!$courses || !is_array($courses)) {
            $error_message = 'Moodle Sync: Не удалось получить курсы из Moodle';
            error_log($error_message);
            if (is_array($courses) && isset($courses['exception'])) {
                error_log('Moodle API Exception: ' . (isset($courses['message']) ? $courses['message'] : 'неизвестная ошибка'));
            }
            
            // Пробуем альтернативный метод - получаем курсы по категориям
            error_log('Moodle Sync: Пробуем получить курсы по категориям');
            $courses = $this->get_courses_by_categories();
            if (!$courses || !is_array($courses) || empty($courses)) {
                return false;
            }
        }
        
        // Проверяем, не является ли ответ ошибкой API
        if (isset($courses['exception'])) {
            $error_message = 'Moodle Sync: API вернул ошибку - ' . (isset($courses['message']) ? $courses['message'] : 'неизвестная ошибка');
            error_log($error_message);
            
            // Пробуем альтернативный метод - получаем курсы по категориям
            error_log('Moodle Sync: Пробуем получить курсы по категориям');
            $courses = $this->get_courses_by_categories();
            if (!$courses || !is_array($courses) || empty($courses)) {
                return false;
            }
        }
        
        // Проверяем, что это массив курсов (не объект ошибки)
        // Если первый элемент массива не является массивом курса, возможно это ошибка
        if (!empty($courses) && !isset($courses[0]) && !isset($courses['courses'])) {
            // Это может быть объект ошибки в другом формате
            error_log('Moodle Sync: Неожиданный формат ответа от API: ' . print_r($courses, true));
            
            // Пробуем альтернативный метод - получаем курсы по категориям
            error_log('Moodle Sync: Пробуем получить курсы по категориям');
            $courses = $this->get_courses_by_categories();
            if (!$courses || !is_array($courses) || empty($courses)) {
                return false;
            }
        }
        
        // Если courses это объект с ключом 'courses', извлекаем массив
        if (isset($courses['courses']) && is_array($courses['courses'])) {
            $courses = $courses['courses'];
        }
        
        $count = 0;
        $created = 0;
        $updated = 0;
        $skipped = 0;
        
        // Проходим по каждому курсу из Moodle
        foreach ($courses as $course) {
            // Пропускаем, если курс не является массивом
            if (!is_array($course)) {
                error_log('Moodle Sync: Пропущен курс (не является массивом): ' . print_r($course, true));
                continue;
            }
            
            // Пропускаем системные курсы (ID = 1 обычно является системным курсом)
            if (!isset($course['id']) || $course['id'] == 1) {
                continue;
            }
            
            // Сохраняем курс в WordPress
            $result = $this->save_course($course);
            if ($result) {
                if ($result === 'created') {
                    $created++;
                    $count++;
                } elseif ($result === 'updated') {
                    $updated++;
                    $count++;
                } elseif ($result === 'skipped') {
                    $skipped++;
                }
            }
        }
        
        error_log('Moodle Sync: Синхронизировано курсов: ' . $count . ' (создано: ' . $created . ', обновлено: ' . $updated . ', пропущено: ' . $skipped . ')');
        
        return array('count' => $count, 'created' => $created, 'updated' => $updated, 'skipped' => $skipped);
    }
    
    /**
     * Альтернативный метод получения курсов по категориям
     * Используется, когда основной метод get_courses() возвращает ошибку
     * 
     * @return array|false Массив курсов или false в случае ошибки
     */
    private function get_courses_by_categories() {
        // Получаем все категории из Moodle
        $categories = $this->api->get_categories(0);
        
        if (!$categories || !is_array($categories) || isset($categories['exception'])) {
            error_log('Moodle Sync: Не удалось получить категории для альтернативного метода получения курсов');
            return false;
        }
        
        $all_courses = array();
        
        // Проходим по каждой категории и получаем курсы
        foreach ($categories as $category) {
            if (!is_array($category) || !isset($category['id'])) {
                continue;
            }
            
            // Получаем курсы для этой категории через новый метод API
            $category_courses = $this->api->get_courses_by_category($category['id']);
            
            if (is_array($category_courses) && !isset($category_courses['exception'])) {
                // Проверяем формат ответа
                if (isset($category_courses['courses']) && is_array($category_courses['courses'])) {
                    $all_courses = array_merge($all_courses, $category_courses['courses']);
                } elseif (isset($category_courses[0])) {
                    $all_courses = array_merge($all_courses, $category_courses);
                }
            }
        }
        
        if (empty($all_courses)) {
            error_log('Moodle Sync: Альтернативный метод не вернул курсы');
            return false;
        }
        
        error_log('Moodle Sync: Альтернативный метод вернул ' . count($all_courses) . ' курсов');
        return $all_courses;
    }
    
    /**
     * Синхронизация студентов из Moodle
     * Получает список студентов для каждого курса и сохраняет информацию о них
     */
    private function sync_students() {
        // Получаем список курсов из Moodle
        $courses = $this->api->get_courses();
        
        // Проверяем, успешно ли получены данные
        if (!$courses || !is_array($courses)) {
            $error_message = 'Moodle Sync: Не удалось получить курсы для синхронизации студентов';
            error_log($error_message);
            if (is_array($courses) && isset($courses['exception'])) {
                error_log('Moodle API Exception: ' . (isset($courses['message']) ? $courses['message'] : 'неизвестная ошибка'));
            }
            
            // Пробуем альтернативный метод - получаем курсы по категориям
            error_log('Moodle Sync: Пробуем получить курсы по категориям для синхронизации студентов');
            $courses = $this->get_courses_by_categories();
            if (!$courses || !is_array($courses) || empty($courses)) {
                error_log('Moodle Sync: Альтернативный метод не вернул курсы для синхронизации студентов');
                return;
            }
        }
        
        // Проверяем, не является ли ответ ошибкой API
        if (isset($courses['exception'])) {
            $error_message = 'Moodle Sync: API вернул ошибку при получении курсов для студентов - ' . (isset($courses['message']) ? $courses['message'] : 'неизвестная ошибка');
            error_log($error_message);
            
            // Пробуем альтернативный метод - получаем курсы по категориям
            error_log('Moodle Sync: Пробуем получить курсы по категориям для синхронизации студентов');
            $courses = $this->get_courses_by_categories();
            if (!$courses || !is_array($courses) || empty($courses)) {
                error_log('Moodle Sync: Альтернативный метод не вернул курсы для синхронизации студентов');
                return;
            }
        }
        
        // Если courses это объект с ключом 'courses', извлекаем массив
        if (isset($courses['courses']) && is_array($courses['courses'])) {
            $courses = $courses['courses'];
        }
        
        // Проходим по каждому курсу
        foreach ($courses as $course) {
            // Пропускаем, если курс не является массивом
            if (!is_array($course)) {
                error_log('Moodle Sync: Пропущен курс (не является массивом) при синхронизации студентов: ' . print_r($course, true));
                continue;
            }
            
            // Пропускаем системные курсы
            if (!isset($course['id']) || $course['id'] == 1) {
                continue;
            }
            
            // Получаем список студентов, записанных на курс
            $students = $this->api->get_enrolled_users($course['id']);
            
            // Проверяем, успешно ли получены данные
            if (!$students || !is_array($students)) {
                error_log('Moodle Sync: Не удалось получить студентов для курса ID: ' . $course['id']);
                continue; // Переходим к следующему курсу
            }
            
            // Проходим по каждому студенту
            foreach ($students as $student) {
                // Сохраняем информацию о студенте
                $this->save_student($student, $course['id']);
            }
        }
    }
    
    /**
     * Сохранение категории в WordPress
     * НЕ создает термины в таксономии "course_specialization" - эта таксономия предназначена только для программ
     * Категории из Moodle сохраняются только в метаполях курсов (moodle_category_id)
     * 
     * @param array $category Массив с данными категории из Moodle
     * @return bool true если успешно, false в случае ошибки или пропуска
     */
    private function save_category($category) {
        // Проверяем, что категория содержит необходимые данные
        if (!isset($category['id']) || !isset($category['name'])) {
            return false;
        }
        
        // НЕ создаем термины в таксономии course_specialization из категорий Moodle
        // В таксономию "Специализации и программы" должны попадать только программы,
        // которые были созданы в WordPress
        // 
        // Информация о категориях из Moodle сохраняется в метаполях курсов (moodle_category_id)
        // через метод save_course()
        
        return true;
    }
    
    /**
     * Сохранение курса в WordPress
     * Создает или обновляет курс как запись типа "course"
     * Учитывает настройку обновления существующих курсов
     * 
     * @param array $course Массив с данными курса из Moodle
     * @return string|false 'created' если создан новый, 'updated' если обновлен существующий, 'skipped' если пропущен, false в случае ошибки
     */
    private function save_course($course) {
        // Проверяем, что курс содержит необходимые данные
        if (!isset($course['id']) || !isset($course['fullname'])) {
            error_log('Moodle Sync: Курс не содержит необходимых данных (ID или название)');
            return false;
        }
        
        // Ищем существующий курс по ID Moodle
        // Используем метаполе для хранения ID курса из Moodle
        $args = array(
            'post_type' => 'course',
            'meta_query' => array(
                array(
                    'key' => 'moodle_course_id',
                    'value' => $course['id'],
                    'compare' => '='
                )
            ),
            'posts_per_page' => 1,
            'post_status' => 'any'
        );
        
        $existing_posts = get_posts($args);
        
        // Проверяем настройку обновления существующих курсов
        // Если курс существует и обновление отключено, пропускаем его
        $update_courses = get_option('moodle_sync_update_courses', true);
        if (!empty($existing_posts) && !$update_courses) {
            // Курс существует, но обновление отключено - пропускаем
            return 'skipped';
        }
        
        // Очищаем HTML теги из описания курса для безопасности
        $summary = isset($course['summary']) ? wp_strip_all_tags($course['summary']) : '';
        
        // Подготавливаем данные для создания/обновления курса
        $post_data = array(
            'post_title' => sanitize_text_field($course['fullname']),  // Название курса
            'post_content' => wp_kses_post($summary),                  // Описание курса (разрешаем безопасные HTML теги)
            'post_excerpt' => isset($course['shortname']) ? sanitize_text_field($course['shortname']) : '',  // Краткое описание
            'post_status' => 'publish',                                 // Статус публикации
            'post_type' => 'course'                                     // Тип записи
        );
        
        $is_new = false;
        
        // Если курс уже существует, обновляем его (если обновление разрешено)
        if (!empty($existing_posts)) {
            $post_id = $existing_posts[0]->ID;
            
            // Обновляем курс только если настройка разрешает
            if ($update_courses) {
                $post_data['ID'] = $post_id;
                $result = wp_update_post($post_data, true);
                
                if (is_wp_error($result)) {
                    error_log('Moodle Sync: Ошибка при обновлении курса ID ' . $post_id . ' - ' . $result->get_error_message());
                    return false;
                }
                
                $action = 'updated';
            } else {
                // Если обновление отключено, просто возвращаем 'skipped'
                return 'skipped';
            }
        } else {
            // Если курс не существует, создаем новый
            $post_id = wp_insert_post($post_data, true);
            
            // Проверяем, успешно ли создан курс
            if (is_wp_error($post_id)) {
                error_log('Moodle Sync: Ошибка при создании курса "' . $course['fullname'] . '" - ' . $post_id->get_error_message());
                return false;
            }
            
            $is_new = true;
            $action = 'created';
        }
        
        // Сохраняем ID курса из Moodle в метаполе
        update_post_meta($post_id, 'moodle_course_id', absint($course['id']));
        
        // Сохраняем дополнительные данные курса в метаполях
        if (isset($course['categoryid'])) {
            update_post_meta($post_id, 'moodle_category_id', absint($course['categoryid']));
            
            // НЕ связываем курс с категорией из Moodle через таксономию course_specialization
            // В таксономию "Специализации и программы" должны попадать только программы,
            // которые были созданы в WordPress, а не категории из Moodle
        }
        
        // Сохраняем дату начала курса, если она указана
        if (isset($course['startdate']) && $course['startdate'] > 0) {
            $start_date = date('Y-m-d', $course['startdate']);
            update_post_meta($post_id, '_course_start_date', $start_date);
        }
        
        // Сохраняем дату окончания курса, если она указана
        if (isset($course['enddate']) && $course['enddate'] > 0) {
            $end_date = date('Y-m-d', $course['enddate']);
            update_post_meta($post_id, '_course_end_date', $end_date);
        }
        
        // Сохраняем URL курса в Moodle
        if (isset($course['id'])) {
            $moodle_course_url = esc_url_raw($this->moodle_url . '/course/view.php?id=' . $course['id']);
            update_post_meta($post_id, 'moodle_course_url', $moodle_course_url);
        }
        
        // Отправляем курс в Laravel приложение
        $this->sync_course_to_laravel($post_id, $course, $action);
        
        return $action;
    }
    
    /**
     * Отправка курса в Laravel приложение через API
     * Вызывается после успешного сохранения курса в WordPress
     * 
     * @param int $wp_course_id ID курса в WordPress
     * @param array $moodle_course Данные курса из Moodle
     * @param string $action Действие ('created' или 'updated')
     */
    private function sync_course_to_laravel($wp_course_id, $moodle_course, $action) {
        // Детальное логирование начала синхронизации
        $log_file = WP_CONTENT_DIR . '/course-registration-debug.log';
        $log_message = '[' . date('Y-m-d H:i:s') . '] ========== НАЧАЛО СИНХРОНИЗАЦИИ КУРСА С LARAVEL ==========' . "\n";
        $log_message .= 'WordPress Course ID: ' . $wp_course_id . "\n";
        $log_message .= 'Action: ' . $action . "\n";
        @file_put_contents($log_file, $log_message, FILE_APPEND);
        
        // Получаем настройки Laravel API
        $laravel_api_url = get_option('laravel_api_url', '');
        $laravel_api_token = get_option('laravel_api_token', '');
        
        // Логируем настройки
        $log_message = '[' . date('Y-m-d H:i:s') . '] Laravel API настройки:' . "\n";
        $log_message .= 'URL: ' . ($laravel_api_url ?: 'НЕ УСТАНОВЛЕН') . "\n";
        $log_message .= 'Token: ' . ($laravel_api_token ? 'УСТАНОВЛЕН (длина: ' . strlen($laravel_api_token) . ')' : 'НЕ УСТАНОВЛЕН') . "\n";
        @file_put_contents($log_file, $log_message, FILE_APPEND);
        
        // Проверяем, настроен ли Laravel API
        if (empty($laravel_api_url) || empty($laravel_api_token)) {
            $error_msg = 'Moodle Sync: Laravel API не настроен, пропускаем синхронизацию курса с Laravel. URL: ' . ($laravel_api_url ?: 'пусто') . ', Token: ' . ($laravel_api_token ? 'установлен' : 'не установлен');
            error_log($error_msg);
            @file_put_contents($log_file, '[' . date('Y-m-d H:i:s') . '] ОШИБКА: ' . $error_msg . "\n", FILE_APPEND);
            return;
        }
        
        // Получаем данные курса из WordPress
        $wp_course = get_post($wp_course_id);
        if (!$wp_course) {
            error_log('Moodle Sync: Курс WordPress с ID ' . $wp_course_id . ' не найден для синхронизации с Laravel');
            return;
        }
        
        // Получаем метаданные курса
        $moodle_course_id = get_post_meta($wp_course_id, 'moodle_course_id', true);
        $moodle_category_id = get_post_meta($wp_course_id, 'moodle_category_id', true);
        $start_date = get_post_meta($wp_course_id, '_course_start_date', true);
        $end_date = get_post_meta($wp_course_id, '_course_end_date', true);
        $duration = get_post_meta($wp_course_id, '_course_duration', true);
        $price = get_post_meta($wp_course_id, '_course_price', true);
        $capacity = get_post_meta($wp_course_id, '_course_capacity', true);
        $enrolled = get_post_meta($wp_course_id, '_course_enrolled', true);
        
        // Получаем категорию курса
        $categories = wp_get_post_terms($wp_course_id, 'course_specialization', array('fields' => 'names'));
        $category_name = !empty($categories) ? $categories[0] : '';
        
        // Подготавливаем данные для отправки в Laravel
        $data = array(
            'wordpress_course_id' => $wp_course_id,
            'moodle_course_id' => $moodle_course_id ?: (isset($moodle_course['id']) ? $moodle_course['id'] : null),
            'name' => $wp_course->post_title,
            'description' => $wp_course->post_content,
            'short_description' => $wp_course->post_excerpt,
            'category_id' => $moodle_category_id ?: (isset($moodle_course['categoryid']) ? $moodle_course['categoryid'] : null),
            'category_name' => $category_name,
            'start_date' => $start_date ?: (isset($moodle_course['startdate']) && $moodle_course['startdate'] > 0 ? date('Y-m-d', $moodle_course['startdate']) : null),
            'end_date' => $end_date ?: (isset($moodle_course['enddate']) && $moodle_course['enddate'] > 0 ? date('Y-m-d', $moodle_course['enddate']) : null),
            'duration' => $duration ?: null,
            'price' => $price ?: null,
            'capacity' => $capacity ?: null,
            'enrolled' => $enrolled ?: 0,
            'status' => $wp_course->post_status,
            'action' => $action, // 'created' или 'updated'
        );
        
        // Формируем URL для API запроса
        $api_url = rtrim($laravel_api_url, '/') . '/api/courses/sync-from-wordpress';
        
        // Логируем данные перед отправкой
        $log_message = '[' . date('Y-m-d H:i:s') . '] Отправка запроса в Laravel:' . "\n";
        $log_message .= 'URL: ' . $api_url . "\n";
        $log_message .= 'Data: ' . json_encode(array_merge($data, array('description' => substr($data['description'], 0, 100) . '...'))) . "\n";
        @file_put_contents($log_file, $log_message, FILE_APPEND);
        
        error_log('Moodle Sync: Отправка курса в Laravel - ID: ' . $wp_course_id . ', название: ' . $wp_course->post_title);
        
        // Выполняем POST запрос к Laravel API
        $response = wp_remote_post($api_url, array(
            'body' => json_encode($data),
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-API-Token' => $laravel_api_token,
            ),
            'timeout' => 30,
        ));
        
        // Проверяем результат запроса
        if (is_wp_error($response)) {
            $error_msg = 'Moodle Sync: Ошибка при синхронизации курса с Laravel - ' . $response->get_error_message() . ' (код: ' . $response->get_error_code() . ')';
            error_log($error_msg);
            @file_put_contents($log_file, '[' . date('Y-m-d H:i:s') . '] ОШИБКА ЗАПРОСА: ' . $error_msg . "\n", FILE_APPEND);
            if (class_exists('Course_Logger')) {
                Course_Logger::error('Ошибка синхронизации курса с Laravel: ID=' . $wp_course_id . ' - ' . $response->get_error_message());
            }
            return;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);
        
        // Логируем ответ
        $log_message = '[' . date('Y-m-d H:i:s') . '] Ответ от Laravel:' . "\n";
        $log_message .= 'Код ответа: ' . $response_code . "\n";
        $log_message .= 'Тело ответа: ' . $response_body . "\n";
        @file_put_contents($log_file, $log_message, FILE_APPEND);
        
        if (($response_code === 201 || $response_code === 200) && isset($response_data['success']) && $response_data['success']) {
            $success_msg = 'Moodle Sync: Курс успешно синхронизирован с Laravel приложением - ID: ' . $wp_course_id;
            error_log($success_msg);
            @file_put_contents($log_file, '[' . date('Y-m-d H:i:s') . '] УСПЕХ: ' . $success_msg . "\n", FILE_APPEND);
            if (class_exists('Course_Logger')) {
                Course_Logger::info('Курс успешно синхронизирован с Laravel: ID=' . $wp_course_id . ', название=' . $wp_course->post_title);
            }
        } else {
            $error_msg = 'Moodle Sync: Ошибка синхронизации курса с Laravel - код ответа: ' . $response_code . ', ответ: ' . $response_body;
            error_log($error_msg);
            @file_put_contents($log_file, '[' . date('Y-m-d H:i:s') . '] ОШИБКА ОТВЕТА: ' . $error_msg . "\n", FILE_APPEND);
            if (class_exists('Course_Logger')) {
                Course_Logger::error('Ошибка синхронизации курса с Laravel: ID=' . $wp_course_id . ' - код: ' . $response_code . ', ответ: ' . $response_body);
            }
        }
    }
    
    /**
     * Сохранение информации о студенте
     * Сохраняет информацию о студенте в метаполях курса
     * 
     * @param array $student Массив с данными студента из Moodle
     * @param int $course_id ID курса в Moodle
     */
    private function save_student($student, $course_id) {
        // Проверяем, что студент содержит необходимые данные
        if (!isset($student['id']) || !isset($student['username'])) {
            return;
        }
        
        // Находим курс в WordPress по ID Moodle
        $args = array(
            'post_type' => 'course',
            'meta_query' => array(
                array(
                    'key' => 'moodle_course_id',
                    'value' => $course_id,
                    'compare' => '='
                )
            ),
            'posts_per_page' => 1,
            'post_status' => 'any'
        );
        
        $courses = get_posts($args);
        
        // Если курс не найден, прекращаем выполнение
        if (empty($courses)) {
            return;
        }
        
        $wp_course_id = $courses[0]->ID;
        
        // Получаем текущий список студентов курса
        $enrolled_students = get_post_meta($wp_course_id, 'moodle_enrolled_students', true);
        
        // Если список студентов еще не создан, создаем пустой массив
        if (!is_array($enrolled_students)) {
            $enrolled_students = array();
        }
        
        // Добавляем или обновляем информацию о студенте
        $enrolled_students[$student['id']] = array(
            'id' => $student['id'],
            'username' => $student['username'],
            'firstname' => isset($student['firstname']) ? $student['firstname'] : '',
            'lastname' => isset($student['lastname']) ? $student['lastname'] : '',
            'email' => isset($student['email']) ? $student['email'] : '',
            'fullname' => isset($student['fullname']) ? $student['fullname'] : ''
        );
        
        // Сохраняем обновленный список студентов
        update_post_meta($wp_course_id, 'moodle_enrolled_students', $enrolled_students);
        
        // Обновляем количество записанных студентов
        update_post_meta($wp_course_id, '_course_enrolled', count($enrolled_students));
    }
}

