<?php
/**
 * Класс для синхронизации данных из Moodle в WordPress
 * 
 * Этот класс синхронизирует курсы, категории и студентов из Moodle в WordPress
 * Использует Moodle REST API для получения данных и создает/обновляет записи в WordPress
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
            $this->api = new Course_Moodle_API($this->moodle_url, $this->moodle_token);
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
    }
    
    /**
     * Страница настроек в админке WordPress
     * Отображает форму для настройки синхронизации с Moodle
     */
    public function admin_page() {
        // Проверяем права доступа пользователя
        // current_user_can() проверяет, имеет ли текущий пользователь указанные права
        if (!current_user_can('manage_options')) {
            // Если нет прав, прекращаем выполнение
            wp_die(__('У вас нет прав для доступа к этой странице.', 'course-plugin'));
        }
        
        // Обновляем настройки URL и токена при сохранении формы
        if (isset($_POST['submit']) && isset($_POST['option_page']) && $_POST['option_page'] === 'moodle_sync_settings') {
            // Обновляем URL и токен в свойствах класса
            $this->moodle_url = get_option('moodle_sync_url', '');
            $this->moodle_token = get_option('moodle_sync_token', '');
            
            // Пересоздаем объект API с новыми настройками
            if ($this->moodle_url && $this->moodle_token) {
                $this->api = new Course_Moodle_API($this->moodle_url, $this->moodle_token);
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
        
        ?>
        <div class="wrap">
            <h1><?php _e('Настройки синхронизации Moodle', 'course-plugin'); ?></h1>
            
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
            $this->api = new Course_Moodle_API($this->moodle_url, $this->moodle_token);
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
        
        // Синхронизируем студентов
        $this->sync_students();
        
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
        $created = 0;
        $updated = 0;
        $skipped = 0;
        
        // Проходим по каждой категории из Moodle
        foreach ($categories as $category) {
            // Сохраняем категорию в WordPress
            $result = $this->save_category($category);
            if ($result) {
                // Проверяем, была ли категория создана или обновлена
                // Для этого ищем существующую категорию
                $args = array(
                    'taxonomy' => 'course_specialization',
                    'meta_query' => array(
                        array(
                            'key' => 'moodle_category_id',
                            'value' => $category['id'],
                            'compare' => '='
                        )
                    ),
                    'hide_empty' => false
                );
                $existing = get_terms($args);
                
                if (!empty($existing) && !is_wp_error($existing)) {
                    $updated++;
                } else {
                    $created++;
                }
                $count++;
            } else {
                // Если save_category вернул false, возможно категория была пропущена
                $skipped++;
            }
        }
        
        error_log('Moodle Sync: Синхронизировано категорий: ' . $count . ' (создано: ' . $created . ', обновлено: ' . $updated . ', пропущено: ' . $skipped . ')');
        
        return array('count' => $count, 'created' => $created, 'updated' => $updated, 'skipped' => $skipped);
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
                error_log('Moodle API Exception: ' . $courses['message']);
            }
            return false;
        }
        
        $count = 0;
        $created = 0;
        $updated = 0;
        $skipped = 0;
        
        // Проходим по каждому курсу из Moodle
        foreach ($courses as $course) {
            // Пропускаем системные курсы (ID = 1 обычно является системным курсом)
            if (isset($course['id']) && $course['id'] == 1) {
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
     * Синхронизация студентов из Moodle
     * Получает список студентов для каждого курса и сохраняет информацию о них
     */
    private function sync_students() {
        // Получаем список курсов из Moodle
        $courses = $this->api->get_courses();
        
        // Проверяем, успешно ли получены данные
        if (!$courses || !is_array($courses)) {
            error_log('Moodle Sync: Не удалось получить курсы для синхронизации студентов');
            return;
        }
        
        // Проходим по каждому курсу
        foreach ($courses as $course) {
            // Пропускаем системные курсы
            if (isset($course['id']) && $course['id'] == 1) {
                continue;
            }
            
            // Получаем список студентов, записанных на курс
            $students = $this->api->get_enrolled_users($course['id']);
            
            // Проверяем, успешно ли получены данные
            if (!$students || !is_array($students)) {
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
     * Создает или обновляет категорию курса в таксономии "course_specialization"
     * Учитывает настройку обновления существующих категорий
     * 
     * @param array $category Массив с данными категории из Moodle
     * @return bool true если успешно, false в случае ошибки или пропуска
     */
    private function save_category($category) {
        // Проверяем, что категория содержит необходимые данные
        if (!isset($category['id']) || !isset($category['name'])) {
            return false;
        }
        
        // Ищем существующий термин таксономии по ID Moodle
        // Используем метаполе для хранения ID категории из Moodle
        $args = array(
            'taxonomy' => 'course_specialization',
            'meta_query' => array(
                array(
                    'key' => 'moodle_category_id',
                    'value' => $category['id'],
                    'compare' => '='
                )
            ),
            'hide_empty' => false
        );
        
        $existing_terms = get_terms($args);
        
        // Проверяем настройку обновления существующих категорий
        // Если категория существует и обновление отключено, пропускаем её
        $update_categories = get_option('moodle_sync_update_categories', true);
        if (!empty($existing_terms) && !is_wp_error($existing_terms) && !$update_categories) {
            // Категория существует, но обновление отключено - пропускаем
            return false;
        }
        
        // Подготавливаем данные для создания/обновления термина
        $term_data = array(
            'description' => isset($category['description']) ? $category['description'] : '',
            'slug' => sanitize_title($category['name'])
        );
        
        // Если термин уже существует, обновляем его (если обновление разрешено)
        if (!empty($existing_terms) && !is_wp_error($existing_terms)) {
            $term_id = $existing_terms[0]->term_id;
            
            // Обновляем категорию только если настройка разрешает
            if ($update_categories) {
                wp_update_term($term_id, 'course_specialization', array(
                    'name' => $category['name'],
                    'description' => $term_data['description']
                ));
            } else {
                // Если обновление отключено, просто возвращаем успех без изменений
                return true;
            }
        } else {
            // Если термин не существует, создаем новый
            $result = wp_insert_term($category['name'], 'course_specialization', $term_data);
            
            if (!is_wp_error($result)) {
                $term_id = $result['term_id'];
            } else {
                error_log('Moodle Sync: Ошибка при создании категории "' . $category['name'] . '" - ' . $result->get_error_message());
                return false;
            }
        }
        
        // Сохраняем ID категории из Moodle в метаполе термина
        update_term_meta($term_id, 'moodle_category_id', absint($category['id']));
        
        // Сохраняем ID родительской категории, если она есть
        if (isset($category['parent']) && $category['parent'] > 0) {
            // Ищем родительскую категорию по ID Moodle
            $parent_args = array(
                'taxonomy' => 'course_specialization',
                'meta_query' => array(
                    array(
                        'key' => 'moodle_category_id',
                        'value' => $category['parent'],
                        'compare' => '='
                    )
                ),
                'hide_empty' => false
            );
            
            $parent_terms = get_terms($parent_args);
            if (!empty($parent_terms) && !is_wp_error($parent_terms)) {
                wp_update_term($term_id, 'course_specialization', array(
                    'parent' => $parent_terms[0]->term_id
                ));
            }
        }
        
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
            
            // Связываем курс с категорией в WordPress
            $category_args = array(
                'taxonomy' => 'course_specialization',
                'meta_query' => array(
                    array(
                        'key' => 'moodle_category_id',
                        'value' => absint($course['categoryid']),
                        'compare' => '='
                    )
                ),
                'hide_empty' => false
            );
            
            $category_terms = get_terms($category_args);
            if (!empty($category_terms) && !is_wp_error($category_terms)) {
                wp_set_post_terms($post_id, array($category_terms[0]->term_id), 'course_specialization');
            }
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
        
        return $action;
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

