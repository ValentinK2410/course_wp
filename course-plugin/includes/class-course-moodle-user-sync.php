<?php
/**
 * Класс для синхронизации пользователей между WordPress и Moodle
 * 
 * Этот класс синхронизирует данные пользователей при регистрации и обновлении профиля
 * Позволяет пользователям использовать один логин и пароль на обоих сайтах
 */

// Проверка безопасности: если файл вызывается напрямую, прекращаем выполнение
if (!defined('ABSPATH')) {
    exit;
}

class Course_Moodle_User_Sync {
    
    /**
     * Единственный экземпляр класса (Singleton)
     */
    private static $instance = null;
    
    /**
     * URL сайта Moodle
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
     * @return Course_Moodle_User_Sync Экземпляр класса
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Конструктор класса
     * Приватный, чтобы предотвратить создание экземпляра напрямую
     * Инициализирует настройки и регистрирует хуки WordPress
     */
    private function __construct() {
        // ВСЕГДА регистрируем хуки, даже если настройки не заполнены
        // Проверка настроек будет происходить внутри методов синхронизации
        
        // Регистрируем хук для перехвата пароля при регистрации (до хэширования)
        // Хук 'user_register' срабатывает после успешной регистрации, но пароль уже захэширован
        // Поэтому используем фильтр 'wp_insert_user_data' для перехвата пароля до хэширования
        add_filter('wp_insert_user_data', array($this, 'capture_password_before_hash'), 10, 3);
        
        // Хук 'user_register' отключен, так как синхронизация происходит через прямой вызов sync_user() с паролем
        // Это гарантирует, что пароль всегда доступен при синхронизации
        // Проблема: глобальная переменная не доступна в контексте хука user_register
        // Решение: использовать только прямой вызов sync_user($user_id, $plain_password) из Course_Registration
        // add_action('user_register', array($this, 'sync_user_on_register'), 10, 1); // Отключен
        
        // Регистрируем хук для синхронизации при обновлении профиля пользователя
        // Хук 'profile_update' срабатывает при обновлении данных пользователя
        add_action('profile_update', array($this, 'sync_user_on_update'), 10, 2);
        
        // Регистрируем хук для перехвата пароля при сбросе
        add_action('after_password_reset', array($this, 'sync_user_password_after_reset'), 10, 2);
        
        // Регистрируем хук для создания пользователя в Moodle при установке пароля
        // Это срабатывает когда пользователь подтверждает email и устанавливает пароль
        add_action('wp_set_password', array($this, 'sync_user_on_password_set'), 10, 2);
        
        // Регистрируем хук для добавления настроек в админку
        add_action('admin_init', array($this, 'register_user_sync_settings'));
        
        // Логируем только в режиме отладки и только один раз
        if (defined('WP_DEBUG') && WP_DEBUG && !get_option('moodle_user_sync_logged_init')) {
            error_log('Moodle User Sync: Класс инициализирован, хуки зарегистрированы');
            update_option('moodle_user_sync_logged_init', true);
        }
    }
    
    /**
     * Регистрация настроек синхронизации пользователей
     * Добавляет опцию для включения/выключения синхронизации пользователей
     */
    public function register_user_sync_settings() {
        // Регистрируем опцию для включения/выключения синхронизации пользователей
        register_setting('moodle_sync_settings', 'moodle_sync_users_enabled', array(
            'type' => 'boolean',
            'default' => true  // По умолчанию синхронизация включена
        ));
        
        // Регистрируем настройки для синхронизации с Laravel
        register_setting('moodle_sync_settings', 'laravel_api_url', array(
            'type' => 'string',
            'default' => ''
        ));
        register_setting('moodle_sync_settings', 'laravel_api_token', array(
            'type' => 'string',
            'default' => ''
        ));
    }
    
    /**
     * Публичный метод для синхронизации пользователя
     * Можно вызывать напрямую после создания пользователя
     * 
     * @param int $user_id ID пользователя WordPress
     * @param string $plain_password Незахэшированный пароль (опционально)
     * @return bool true если синхронизация успешна, false в случае ошибки
     */
    public function sync_user($user_id, $plain_password = '') {
        // КРИТИЧЕСКОЕ ЛОГИРОВАНИЕ в файл
        $log_file = WP_CONTENT_DIR . '/course-registration-debug.log';
        $log_message = '[' . date('Y-m-d H:i:s') . '] ========== sync_user() ВЫЗВАН ==========' . "\n";
        $log_message .= 'User ID: ' . $user_id . "\n";
        $log_message .= 'Password provided: ' . (!empty($plain_password) ? 'YES (length: ' . strlen($plain_password) . ')' : 'NO') . "\n";
        @file_put_contents($log_file, $log_message, FILE_APPEND);
        
        // Используем альтернативное логирование
        if (class_exists('Course_Logger')) {
            Course_Logger::info('========== НАЧАЛО СИНХРОНИЗАЦИИ ==========');
            Course_Logger::info('sync_user вызван для пользователя ID: ' . $user_id);
        }
        
        error_log('Moodle User Sync: ========== НАЧАЛО СИНХРОНИЗАЦИИ ==========');
        error_log('Moodle User Sync: sync_user вызван для пользователя ID: ' . $user_id);
        
        // Получаем данные пользователя
        $user = get_userdata($user_id);
        if (!$user) {
            if (class_exists('Course_Logger')) {
                Course_Logger::error('ОШИБКА - не удалось получить данные пользователя ID: ' . $user_id);
            }
            error_log('Moodle User Sync: ОШИБКА - не удалось получить данные пользователя ID: ' . $user_id);
            return false;
        }
        
        // Если передан пароль, сохраняем его во временное хранилище
        if (!empty($plain_password)) {
            $GLOBALS['moodle_user_sync_password'][$user->user_login] = $plain_password;
            if (class_exists('Course_Logger')) {
                Course_Logger::info('Пароль сохранен в глобальную переменную для логина: ' . $user->user_login . ' (длина пароля: ' . strlen($plain_password) . ' символов)');
            }
            error_log('Moodle User Sync: Пароль сохранен в глобальную переменную для логина: ' . $user->user_login . ' (длина пароля: ' . strlen($plain_password) . ' символов)');
        } else {
            if (class_exists('Course_Logger')) {
                Course_Logger::warning('Пароль не передан в sync_user! Проверяю глобальную переменную...');
            }
            error_log('Moodle User Sync: ВНИМАНИЕ - пароль не передан в sync_user! Проверяю глобальную переменную...');
            // Проверяем, может пароль уже сохранен в глобальной переменной
            if (isset($GLOBALS['moodle_user_sync_password'][$user->user_login])) {
                if (class_exists('Course_Logger')) {
                    Course_Logger::info('Пароль найден в глобальной переменной для логина: ' . $user->user_login);
                }
                error_log('Moodle User Sync: Пароль найден в глобальной переменной для логина: ' . $user->user_login);
            } else {
                if (class_exists('Course_Logger')) {
                    Course_Logger::error('Пароль не найден ни в параметрах, ни в глобальной переменной!');
                }
                error_log('Moodle User Sync: Пароль не найден ни в параметрах, ни в глобальной переменной!');
                error_log('Moodle User Sync: Доступные ключи в глобальной переменной: ' . (isset($GLOBALS['moodle_user_sync_password']) && is_array($GLOBALS['moodle_user_sync_password']) ? print_r(array_keys($GLOBALS['moodle_user_sync_password']), true) : 'переменная не существует или не массив'));
            }
        }
        
        // Обновляем настройки API перед синхронизацией
        $this->moodle_url = get_option('moodle_sync_url', '');
        $this->moodle_token = get_option('moodle_sync_token', '');
        $sync_enabled = get_option('moodle_sync_users_enabled', true);
        
        if (class_exists('Course_Logger')) {
            Course_Logger::info('Настройки - URL: ' . $this->moodle_url . ', Token: ' . (empty($this->moodle_token) ? 'не установлен' : 'установлен') . ', Включено: ' . ($sync_enabled ? 'да' : 'нет'));
        }
        error_log('Moodle User Sync: Настройки - URL: ' . $this->moodle_url . ', Token: ' . (empty($this->moodle_token) ? 'не установлен' : 'установлен') . ', Включено: ' . ($sync_enabled ? 'да' : 'нет'));
        
        // Проверяем настройки перед синхронизацией
        if (!$sync_enabled) {
            if (class_exists('Course_Logger')) {
                Course_Logger::warning('Синхронизация пользователей отключена в настройках');
            }
            error_log('Moodle User Sync: Синхронизация пользователей отключена в настройках');
            return false;
        }
        
        if (empty($this->moodle_url) || empty($this->moodle_token)) {
            if (class_exists('Course_Logger')) {
                Course_Logger::error('URL или токен не настроены');
            }
            error_log('Moodle User Sync: URL или токен не настроены');
            return false;
        }
        
        // Инициализируем API если еще не инициализирован или пересоздаем с новыми настройками
        // Всегда пересоздаем API объект, чтобы использовать актуальные настройки
        $this->api = new Course_Moodle_API($this->moodle_url, $this->moodle_token);
        error_log('Moodle User Sync: API объект создан/обновлен с URL: ' . $this->moodle_url);
        
        // Вызываем стандартный метод синхронизации с передачей пароля
        try {
            // Передаем пароль в sync_user_on_register, если он был передан в sync_user
            if (!empty($plain_password)) {
                $this->sync_user_on_register($user_id, $plain_password);
            } else {
                // Если пароль не передан, пытаемся найти его в глобальной переменной
                if (isset($GLOBALS['moodle_user_sync_password'][$user->user_login])) {
                    $plain_password = $GLOBALS['moodle_user_sync_password'][$user->user_login];
                    $this->sync_user_on_register($user_id, $plain_password);
                } else {
                    error_log('Moodle User Sync: КРИТИЧЕСКАЯ ОШИБКА - пароль не передан и не найден в глобальной переменной!');
                    return false;
                }
            }
            error_log('Moodle User Sync: Метод sync_user_on_register завершен для пользователя ID: ' . $user_id);
        } catch (Exception $e) {
            error_log('Moodle User Sync: Исключение при синхронизации: ' . $e->getMessage());
            return false;
        }
        
        return true;
    }
    
    /**
     * Перехват пароля до хэширования при регистрации
     * Сохраняет незахэшированный пароль во временное хранилище для последующей синхронизации с Moodle
     * 
     * @param array $data Данные пользователя перед сохранением
     * @param bool $update Флаг обновления (true = обновление, false = создание)
     * @param int $user_id ID пользователя (если обновление)
     * @return array Данные пользователя
     */
    public function capture_password_before_hash($data, $update, $user_id) {
        // Сохраняем незахэшированный пароль только при создании нового пользователя
        if (!$update && isset($data['user_pass']) && !empty($data['user_pass']) && isset($data['user_login'])) {
            // Сохраняем пароль во временное хранилище (в памяти, через статическую переменную)
            // Это безопасно, так как данные не сохраняются в базе данных
            static $passwords = array();
            $passwords[$data['user_login']] = $data['user_pass'];
            
            // Сохраняем пароль в глобальной переменной для доступа из других методов
            // ВАЖНО: Используем глобальную переменную, чтобы пароль был доступен в хуке user_register
            $GLOBALS['moodle_user_sync_password'][$data['user_login']] = $data['user_pass'];
            
            // Логируем для отладки
            error_log('Moodle User Sync: Пароль перехвачен через фильтр wp_insert_user_data для логина: ' . $data['user_login'] . ' (длина: ' . strlen($data['user_pass']) . ' символов)');
            if (class_exists('Course_Logger')) {
                Course_Logger::info('Пароль перехвачен через фильтр wp_insert_user_data: логин=' . $data['user_login'] . ', длина=' . strlen($data['user_pass']));
            }
        }
        
        return $data;
    }
    
    /**
     * Синхронизация пользователя при регистрации
     * Создает пользователя в Moodle при регистрации в WordPress
     * 
     * @param int $user_id ID пользователя WordPress
     * @param string $plain_password Незахэшированный пароль (опционально, если не передан, будет искаться в глобальной переменной)
     */
    public function sync_user_on_register($user_id, $plain_password = '') {
        // Получаем объект пользователя WordPress
        $user = get_userdata($user_id);
        
        if (!$user) {
            error_log('Moodle User Sync: Пользователь с ID ' . $user_id . ' не найден');
            return; // Если пользователь не найден, прекращаем выполнение
        }
        
        // Проверяем, включена ли синхронизация пользователей
        $sync_enabled = get_option('moodle_sync_users_enabled', true);
        if (!$sync_enabled) {
            error_log('Moodle User Sync: Синхронизация пользователей отключена');
            return;
        }
        
        // Обновляем настройки API на случай, если они изменились
        $this->moodle_url = get_option('moodle_sync_url', '');
        $this->moodle_token = get_option('moodle_sync_token', '');
        
        // Нормализуем URL (убираем слеш в конце)
        $this->moodle_url = rtrim($this->moodle_url, '/');
        
        error_log('Moodle User Sync: Настройки - URL: ' . $this->moodle_url . ', Token установлен: ' . (!empty($this->moodle_token) ? 'да' : 'нет'));
        
        // Проверяем, что API настроен (должен быть установлен в sync_user, но проверяем на всякий случай)
        if (!$this->api) {
            if ($this->moodle_url && $this->moodle_token) {
                $this->api = new Course_Moodle_API($this->moodle_url, $this->moodle_token);
                error_log('Moodle User Sync: API объект создан в sync_user_on_register с URL: ' . $this->moodle_url);
            } else {
                error_log('Moodle User Sync: API не настроен (URL: ' . $this->moodle_url . ', Token: ' . (!empty($this->moodle_token) ? 'установлен' : 'не установлен') . ')');
                return;
            }
        }
        
        // Дополнительная проверка: убеждаемся, что API объект имеет правильные настройки
        if (empty($this->moodle_url) || empty($this->moodle_token)) {
            error_log('Moodle User Sync: КРИТИЧЕСКАЯ ОШИБКА - URL или токен пусты в sync_user_on_register');
            return;
        }
        
        error_log('Moodle User Sync: Начало синхронизации пользователя ' . $user->user_email . ' (ID: ' . $user_id . ', логин: ' . $user->user_login . ')');
        
        // Проверяем, существует ли уже пользователь в Moodle
        error_log('Moodle User Sync: Проверка существования пользователя в Moodle по email: ' . $user->user_email);
        $moodle_user = $this->api->get_user_by_email($user->user_email);
        
        if ($moodle_user) {
            // Если пользователь уже существует в Moodle, обновляем его данные
            // Сохраняем ID пользователя Moodle в метаполе WordPress пользователя
            update_user_meta($user_id, 'moodle_user_id', $moodle_user['id']);
            
            error_log('Moodle User Sync: Пользователь ' . $user->user_email . ' уже существует в Moodle (ID: ' . $moodle_user['id'] . ', username: ' . (isset($moodle_user['username']) ? $moodle_user['username'] : 'неизвестно') . '), данные обновлены. Новый пользователь не создается.');
            return;
        }
        
        error_log('Moodle User Sync: Пользователь ' . $user->user_email . ' НЕ найден в Moodle, продолжаем создание нового пользователя');
        
        // Получаем незахэшированный пароль
        // Сначала проверяем, передан ли пароль как параметр
        if (empty($plain_password)) {
            // Если пароль не передан, пытаемся найти его в глобальной переменной
            error_log('Moodle User Sync: Пароль не передан как параметр, ищем в глобальной переменной для логина: ' . $user->user_login);
            error_log('Moodle User Sync: Глобальная переменная существует: ' . (isset($GLOBALS['moodle_user_sync_password']) ? 'да' : 'нет'));
            if (isset($GLOBALS['moodle_user_sync_password']) && is_array($GLOBALS['moodle_user_sync_password'])) {
                error_log('Moodle User Sync: Доступные ключи в глобальной переменной: ' . print_r(array_keys($GLOBALS['moodle_user_sync_password']), true));
            }
            
            // Проверяем глобальную переменную (устанавливается в sync_user или capture_password_before_hash)
            if (isset($GLOBALS['moodle_user_sync_password'][$user->user_login])) {
                $plain_password = $GLOBALS['moodle_user_sync_password'][$user->user_login];
                error_log('Moodle User Sync: Пароль найден в глобальной переменной для ' . $user->user_email . ' (длина: ' . strlen($plain_password) . ' символов)');
                error_log('Moodle User Sync: Пароль (первые 3 символа): ' . substr($plain_password, 0, 3) . '***');
            } 
            // Если не найден, пытаемся получить из POST данных (если регистрация через форму)
            elseif (isset($_POST['user_pass']) && !empty($_POST['user_pass'])) {
                $plain_password = $_POST['user_pass'];
                error_log('Moodle User Sync: Пароль получен из POST данных для ' . $user->user_email . ' (длина: ' . strlen($plain_password) . ' символов)');
            }
            // Если все еще не найден, это критическая ошибка
            else {
                error_log('Moodle User Sync: КРИТИЧЕСКАЯ ОШИБКА! Пароль не найден для ' . $user->user_email . '. Пользователь НЕ будет создан в Moodle!');
                if (class_exists('Course_Logger')) {
                    Course_Logger::error('Пароль не найден для пользователя ID: ' . $user_id . ', логин: ' . $user->user_login . '. Пользователь НЕ будет создан в Moodle!');
                }
                return; // Прекращаем создание пользователя в Moodle, если пароль не найден
            }
        } else {
            error_log('Moodle User Sync: Пароль передан как параметр (длина: ' . strlen($plain_password) . ' символов, первые 3 символа: ' . substr($plain_password, 0, 3) . '***)');
        }
        
        // Если пароль не найден, это критическая ошибка
        // Пользователь должен быть создан с паролем из формы, а не со случайным
        if (empty($plain_password)) {
            error_log('Moodle User Sync: КРИТИЧЕСКАЯ ОШИБКА! Пароль не найден для ' . $user->user_email . '. Пользователь НЕ будет создан в Moodle!');
            if (class_exists('Course_Logger')) {
                Course_Logger::error('Пароль не найден для пользователя ID: ' . $user_id . ', логин: ' . $user->user_login);
            }
            return; // Прекращаем создание пользователя в Moodle, если пароль не найден
        }
        
        // Пароль уже должен быть модифицирован в Course_Registration для соответствия требованиям Moodle
        // Но на всякий случай проверяем и модифицируем здесь тоже
        // Moodle требует: хотя бы один специальный символ (*, -, или #) И хотя бы одну цифру
        $password_needs_modification = false;
        $modified_password = $plain_password;
        
        // Проверяем наличие специальных символов
        if (!preg_match('/[*\-#]/', $modified_password)) {
            $password_needs_modification = true;
            // Добавляем дефис в конец пароля
            $modified_password = $modified_password . '-';
            error_log('Moodle User Sync: Пароль не содержит специальных символов, добавлен дефис');
        }
        
        // Проверяем наличие цифр
        if (!preg_match('/[0-9]/', $modified_password)) {
            $password_needs_modification = true;
            // Добавляем цифру в конец пароля
            $modified_password = $modified_password . '1';
            error_log('Moodle User Sync: Пароль не содержит цифр, добавлена цифра 1');
        }
        
        // Если пароль был модифицирован, используем модифицированную версию
        if ($password_needs_modification) {
            error_log('Moodle User Sync: Пароль был модифицирован для соответствия требованиям Moodle');
            error_log('Moodle User Sync: Оригинальный пароль (длина: ' . strlen($plain_password) . '): ' . substr($plain_password, 0, 3) . '***');
            error_log('Moodle User Sync: Модифицированный пароль (длина: ' . strlen($modified_password) . '): ' . substr($modified_password, 0, 3) . '***');
            $plain_password = $modified_password;
        } else {
            error_log('Moodle User Sync: Пароль соответствует требованиям Moodle, используется без изменений (длина: ' . strlen($plain_password) . ')');
        }
        
        // Логируем финальный пароль перед отправкой в Moodle
        error_log('Moodle User Sync: Финальный пароль для отправки в Moodle (длина: ' . strlen($plain_password) . ', первые 3 символа: ' . substr($plain_password, 0, 3) . '***)');
        
        // Подготавливаем данные для создания пользователя в Moodle
        // Важно: Moodle требует, чтобы lastname не был пустым и содержал хотя бы один буквенный символ
        // Используем имя пользователя или "User" если фамилия пустая
        $firstname = !empty($user->first_name) ? trim($user->first_name) : (!empty($user->display_name) ? trim($user->display_name) : $user->user_login);
        // Если фамилия пустая, используем имя пользователя или "User" вместо дефиса или пробела
        if (!empty($user->last_name) && trim($user->last_name) !== '') {
            $lastname = trim($user->last_name);
        } elseif (!empty($user->first_name)) {
            // Если есть имя, используем его как фамилию
            $lastname = trim($user->first_name);
        } elseif (!empty($user->display_name)) {
            // Если есть отображаемое имя, используем его
            $lastname = trim($user->display_name);
        } else {
            // В крайнем случае используем "User"
            $lastname = 'User';
        }
        
        $user_data = array(
            'username' => $user->user_login,  // Логин пользователя
            'password' => $plain_password,    // Незахэшированный пароль
            'firstname' => $firstname,        // Имя (обязательно)
            'lastname' => $lastname,          // Фамилия (обязательно, минимум пробел)
            'email' => $user->user_email,     // Email (обязательно)
        );
        
        error_log('Moodle User Sync: Подготовленные данные пользователя: username=' . $user_data['username'] . ', firstname=' . $user_data['firstname'] . ', lastname="' . $user_data['lastname'] . '", email=' . $user_data['email']);
        
        // Пытаемся создать пользователя в Moodle
        error_log('Moodle User Sync: Отправка запроса на создание пользователя в Moodle');
        error_log('Moodle User Sync: Данные пользователя (без пароля): username=' . $user_data['username'] . ', email=' . $user_data['email'] . ', firstname=' . $user_data['firstname'] . ', lastname=' . $user_data['lastname']);
        
        if (empty($user_data['password'])) {
            error_log('Moodle User Sync: КРИТИЧЕСКАЯ ОШИБКА - пароль пустой!');
        }
        
        $result = $this->api->create_user($user_data);
        
        error_log('Moodle User Sync: Ответ от Moodle API получен. Тип: ' . gettype($result));
        if (is_array($result)) {
            error_log('Moodle User Sync: Размер массива ответа: ' . count($result));
            error_log('Moodle User Sync: Содержимое ответа: ' . print_r($result, true));
        } else {
            error_log('Moodle User Sync: Ответ не является массивом: ' . print_r($result, true));
        }
        
        if ($result === false) {
            error_log('Moodle User Sync: КРИТИЧЕСКАЯ ОШИБКА - API вернул false (возможно, ошибка сети или неправильный URL)');
            return;
        }
        
        // Проверяем, есть ли исключение в ответе (ошибка от Moodle)
        if (isset($result['exception'])) {
            error_log('Moodle User Sync: ОШИБКА от Moodle API - ' . (isset($result['message']) ? $result['message'] : 'неизвестная ошибка') . ' (Код: ' . (isset($result['errorcode']) ? $result['errorcode'] : 'неизвестно') . ')');
            // Продолжаем обработку для логирования всех деталей ошибки
        }
        
        // Проверяем результат создания пользователя
        // Moodle API может вернуть массив с данными пользователя или объект с ошибкой
        if ($result && is_array($result) && !empty($result)) {
            // Проверяем, есть ли ID пользователя в результате
            if (isset($result[0]['id'])) {
                // Если пользователь успешно создан, сохраняем его ID Moodle в метаполе WordPress
                $moodle_user_id = $result[0]['id'];
                update_user_meta($user_id, 'moodle_user_id', $moodle_user_id);
                
                // Сохраняем пароль, который был использован при создании пользователя в Moodle
                // Это нужно для отправки пользователю в письме
                // ВАЖНО: Сохраняем именно тот пароль, который был отправлен в Moodle
                update_user_meta($user_id, 'moodle_password_used', $plain_password);
                error_log('Moodle User Sync: Пароль сохранен в метаполе пользователя для отправки в письме (длина: ' . strlen($plain_password) . ' символов)');
                error_log('Moodle User Sync: Пароль (первые 3 символа): ' . substr($plain_password, 0, 3) . '***');
                
                if (class_exists('Course_Logger')) {
                    Course_Logger::info('Пароль сохранен в метаполе для пользователя ID: ' . $user_id . ' (длина: ' . strlen($plain_password) . ')');
                }
                
                // Удаляем пароль из памяти после успешного создания
                if (isset($GLOBALS['moodle_user_sync_password'][$user->user_login])) {
                    unset($GLOBALS['moodle_user_sync_password'][$user->user_login]);
                }
                error_log('Moodle User Sync: УСПЕХ! Пользователь ' . $user->user_email . ' успешно создан в Moodle (ID: ' . $moodle_user_id . ') с паролем длиной ' . strlen($plain_password) . ' символов');
                
                // Создаем пользователя в Laravel приложении через API
                $this->sync_user_to_laravel($user_id, $moodle_user_id, $plain_password);
            } else {
                // Пользователь не создан, но результат есть - проверяем ошибки
                error_log('Moodle User Sync: ОШИБКА при создании пользователя ' . $user->user_email . ' в Moodle - ID не найден в результате');
                error_log('Moodle User Sync: Структура результата: ' . print_r($result, true));
            }
        } else {
            // Если произошла ошибка, записываем её в лог
            error_log('Moodle User Sync: ОШИБКА при создании пользователя ' . $user->user_email . ' в Moodle');
            
            if (is_array($result) && !empty($result)) {
                if (isset($result[0]['warnings']) && is_array($result[0]['warnings'])) {
                    foreach ($result[0]['warnings'] as $warning) {
                        error_log('Moodle User Sync Warning: ' . print_r($warning, true));
                    }
                }
                if (isset($result[0]['errors']) && is_array($result[0]['errors'])) {
                    foreach ($result[0]['errors'] as $error) {
                        error_log('Moodle User Sync Error: ' . print_r($error, true));
                    }
                }
                if (isset($result[0]['id'])) {
                    error_log('Moodle User Sync: Пользователь создан, но структура ответа неожиданная. ID: ' . $result[0]['id']);
                    update_user_meta($user_id, 'moodle_user_id', $result[0]['id']);
                }
            }
            
            if (isset($result['exception'])) {
                error_log('Moodle User Sync Exception: ' . (isset($result['message']) ? $result['message'] : 'неизвестная ошибка'));
            }
            
            if (!is_array($result) || empty($result)) {
                error_log('Moodle User Sync: Неожиданный формат ответа от API. Результат: ' . print_r($result, true));
            }
        }
    }
    
    /**
     * Синхронизация пользователя при обновлении профиля
     * Обновляет данные пользователя в Moodle при изменении профиля в WordPress
     * 
     * @param int $user_id ID пользователя WordPress
     * @param WP_User $old_user_data Старые данные пользователя (до обновления)
     */
    public function sync_user_on_update($user_id, $old_user_data) {
        // Получаем объект пользователя WordPress
        $user = get_userdata($user_id);
        
        if (!$user) {
            return; // Если пользователь не найден, прекращаем выполнение
        }
        
        // Проверяем, что API настроен
        if (!$this->api) {
            return;
        }
        
        // Получаем ID пользователя в Moodle из метаполя WordPress
        $moodle_user_id = get_user_meta($user_id, 'moodle_user_id', true);
        
        if (!$moodle_user_id) {
            // Если ID Moodle не найден, пытаемся найти пользователя по email
            $moodle_user = $this->api->get_user_by_email($user->user_email);
            
            if ($moodle_user) {
                $moodle_user_id = $moodle_user['id'];
                update_user_meta($user_id, 'moodle_user_id', $moodle_user_id);
            } else {
                // Если пользователь не найден в Moodle, создаем его
                $this->sync_user_on_register($user_id);
                return;
            }
        }
        
        // Подготавливаем данные для обновления пользователя в Moodle
        $user_data = array();
        
        // Проверяем, изменились ли данные, которые нужно синхронизировать
        if ($user->first_name !== $old_user_data->first_name || 
            $user->last_name !== $old_user_data->last_name) {
            $user_data['firstname'] = $user->first_name ? $user->first_name : $user->display_name;
            $user_data['lastname'] = $user->last_name ? $user->last_name : '';
        }
        
        if ($user->user_email !== $old_user_data->user_email) {
            $user_data['email'] = $user->user_email;
        }
        
        // Обновляем данные в Moodle только если есть изменения
        if (!empty($user_data)) {
            $result = $this->api->update_user($moodle_user_id, $user_data);
            
            if ($result !== false) {
                error_log('Moodle User Sync: Данные пользователя ' . $user->user_email . ' обновлены в Moodle');
            } else {
                error_log('Moodle User Sync: Ошибка при обновлении данных пользователя ' . $user->user_email . ' в Moodle');
            }
        }
    }
    
    /**
     * Синхронизация пользователя при установке пароля
     * Создает пользователя в Moodle когда пользователь подтверждает email и устанавливает пароль
     * 
     * @param string $password Новый пароль (незахэшированный)
     * @param int $user_id ID пользователя WordPress
     */
    public function sync_user_on_password_set($password, $user_id) {
        // Логируем вызов хука
        $log_file = WP_CONTENT_DIR . '/course-registration-debug.log';
        $log_message = '[' . date('Y-m-d H:i:s') . '] ========== sync_user_on_password_set() ВЫЗВАН ==========' . "\n";
        $log_message .= 'User ID: ' . $user_id . "\n";
        $log_message .= 'Password length: ' . strlen($password) . "\n";
        @file_put_contents($log_file, $log_message, FILE_APPEND);
        
        error_log('Moodle User Sync: sync_user_on_password_set вызван для пользователя ID: ' . $user_id);
        
        if (class_exists('Course_Logger')) {
            Course_Logger::info('sync_user_on_password_set вызван для пользователя ID: ' . $user_id);
        }
        
        // Получаем данные пользователя
        $user = get_userdata($user_id);
        if (!$user) {
            error_log('Moodle User Sync: Пользователь с ID ' . $user_id . ' не найден');
            return;
        }
        
        // Проверяем, включена ли синхронизация пользователей
        $sync_enabled = get_option('moodle_sync_users_enabled', true);
        if (!$sync_enabled) {
            error_log('Moodle User Sync: Синхронизация пользователей отключена');
            return;
        }
        
        // Проверяем, создан ли уже пользователь в Moodle
        $moodle_user_id = get_user_meta($user_id, 'moodle_user_id', true);
        if ($moodle_user_id) {
            error_log('Moodle User Sync: Пользователь ID ' . $user_id . ' уже синхронизирован с Moodle (ID: ' . $moodle_user_id . '), обновляем пароль');
            if (class_exists('Course_Logger')) {
                Course_Logger::info('Пользователь уже синхронизирован с Moodle, обновляем пароль');
            }
            // Пользователь уже существует, обновляем только пароль
            $this->update_moodle_password($user_id, $password);
            return;
        }
        
        // Пользователь еще не создан в Moodle
        // Проверяем, есть ли сохраненный пароль из регистрации (модифицированный для Moodle)
        $pending_password = get_user_meta($user_id, 'pending_moodle_password', true);
        
        if (!empty($pending_password)) {
            // Используем сохраненный пароль (уже модифицированный для Moodle)
            error_log('Moodle User Sync: Используется сохраненный пароль из регистрации (длина: ' . strlen($pending_password) . ')');
            if (class_exists('Course_Logger')) {
                Course_Logger::info('Используется сохраненный пароль из регистрации для создания пользователя в Moodle');
            }
            $password_for_moodle = $pending_password;
            // Удаляем сохраненный пароль после использования
            delete_user_meta($user_id, 'pending_moodle_password');
        } else {
            // Используем текущий пароль, но модифицируем его для Moodle
            error_log('Moodle User Sync: Сохраненный пароль не найден, используем текущий пароль и модифицируем для Moodle');
            $password_for_moodle = $password;
            
            // Модифицируем пароль для соответствия требованиям Moodle
            $needs_modification = false;
            if (!preg_match('/[*\-#]/', $password_for_moodle)) {
                $password_for_moodle = $password_for_moodle . '-';
                $needs_modification = true;
            }
            if (!preg_match('/[0-9]/', $password_for_moodle)) {
                $password_for_moodle = $password_for_moodle . '1';
                $needs_modification = true;
            }
            
            // ВАЖНО: Обновляем пароль в WordPress на модифицированный, чтобы он совпадал с Moodle
            // Это нужно для того, чтобы пользователь мог войти с тем же паролем
            // Но только если пароль был модифицирован, чтобы избежать бесконечного цикла
            if ($needs_modification && $password_for_moodle !== $password) {
                // Временно отключаем хук, чтобы избежать бесконечного цикла
                remove_action('wp_set_password', array($this, 'sync_user_on_password_set'), 10);
                wp_set_password($password_for_moodle, $user_id);
                add_action('wp_set_password', array($this, 'sync_user_on_password_set'), 10, 2);
                error_log('Moodle User Sync: Пароль в WordPress обновлен на модифицированный для совпадения с Moodle');
            }
        }
        
        error_log('Moodle User Sync: Пользователь ID ' . $user_id . ' еще не создан в Moodle, создаем с паролем (длина: ' . strlen($password_for_moodle) . ')');
        
        // Сохраняем пароль во временное хранилище для sync_user_on_register
        $GLOBALS['moodle_user_sync_password'][$user->user_login] = $password_for_moodle;
        
        // Создаем пользователя в Moodle
        $this->sync_user_on_register($user_id, $password_for_moodle);
        
        // Удаляем пароль из памяти после синхронизации
        if (isset($GLOBALS['moodle_user_sync_password'][$user->user_login])) {
            unset($GLOBALS['moodle_user_sync_password'][$user->user_login]);
        }
        
        // Отправляем письмо пользователю с паролем для всех трех платформ
        // Это единственное письмо, которое отправляется пользователю
        // Оно содержит данные для входа на WordPress, Moodle и Laravel
        $this->send_password_email($user_id, $password_for_moodle);
    }
    
    /**
     * Публичный метод для синхронизации пароля пользователя с Moodle
     * Можно вызывать из других классов для обновления пароля в Moodle
     * 
     * @param int $user_id ID пользователя WordPress
     * @param string $new_password Новый пароль (незахэшированный)
     * @return bool true если успешно, false в случае ошибки
     */
    public function sync_user_password_to_moodle($user_id, $new_password) {
        return $this->update_moodle_password($user_id, $new_password);
    }
    
    /**
     * Обновление пароля пользователя в Moodle
     * 
     * @param int $user_id ID пользователя WordPress
     * @param string $new_password Новый пароль (незахэшированный)
     */
    private function update_moodle_password($user_id, $new_password) {
        // Обновляем настройки API на случай, если они изменились
        $this->moodle_url = get_option('moodle_sync_url', '');
        $this->moodle_token = get_option('moodle_sync_token', '');
        
        // Нормализуем URL (убираем слеш в конце)
        $this->moodle_url = rtrim($this->moodle_url, '/');
        
        // Проверяем, что API настроен
        if (!$this->api) {
            if ($this->moodle_url && $this->moodle_token) {
                $this->api = new Course_Moodle_API($this->moodle_url, $this->moodle_token);
            } else {
                error_log('Moodle User Sync: API не настроен для обновления пароля');
                return;
            }
        }
        
        // Получаем ID пользователя в Moodle
        $moodle_user_id = get_user_meta($user_id, 'moodle_user_id', true);
        if (!$moodle_user_id) {
            error_log('Moodle User Sync: ID пользователя Moodle не найден для обновления пароля');
            return;
        }
        
        // Обновляем пароль в Moodle
        $result = $this->api->update_user($moodle_user_id, array(
            'password' => $new_password
        ));
        
        if ($result !== false) {
            // Помечаем, что пароль синхронизирован
            update_user_meta($user_id, 'moodle_password_synced', true);
            delete_user_meta($user_id, 'moodle_password_needs_sync');
            error_log('Moodle User Sync: Пароль пользователя ID ' . $user_id . ' обновлен в Moodle');
            return true;
        } else {
            error_log('Moodle User Sync: Ошибка при обновлении пароля пользователя ID ' . $user_id . ' в Moodle');
            return false;
        }
    }
    
    /**
     * Синхронизация пароля пользователя после сброса
     * Обновляет пароль пользователя в Moodle при сбросе пароля в WordPress
     * 
     * @param WP_User $user Объект пользователя WordPress
     * @param string $new_pass Новый пароль (незахэшированный)
     */
    public function sync_user_password_after_reset($user, $new_pass) {
        // Проверяем, что API настроен
        if (!$this->api) {
            return;
        }
        
        if (empty($new_pass)) {
            return; // Если пароль пустой, прекращаем выполнение
        }
        
        // Получаем ID пользователя в Moodle
        $moodle_user_id = get_user_meta($user->ID, 'moodle_user_id', true);
        
        if (!$moodle_user_id) {
            // Если ID Moodle не найден, пытаемся найти пользователя по email
            $moodle_user = $this->api->get_user_by_email($user->user_email);
            
            if ($moodle_user) {
                $moodle_user_id = $moodle_user['id'];
                update_user_meta($user->ID, 'moodle_user_id', $moodle_user_id);
            } else {
                // Если пользователь не найден в Moodle, создаем его
                $this->sync_user_on_register($user->ID);
                return;
            }
        }
        
        // Обновляем пароль в Moodle
        $result = $this->api->update_user($moodle_user_id, array(
            'password' => $new_pass
        ));
        
        if ($result !== false) {
            error_log('Moodle User Sync: Пароль пользователя ' . $user->user_email . ' обновлен в Moodle');
        } else {
            error_log('Moodle User Sync: Ошибка при обновлении пароля пользователя ' . $user->user_email . ' в Moodle');
        }
    }
    
    /**
     * Синхронизация пользователя с Laravel приложением
     * Создает пользователя в Laravel через API после успешного создания в Moodle
     * 
     * @param int $user_id ID пользователя WordPress
     * @param int $moodle_user_id ID пользователя в Moodle
     * @param string $password Пароль пользователя (незахэшированный)
     */
    private function sync_user_to_laravel($user_id, $moodle_user_id, $password) {
        // КРИТИЧЕСКОЕ ЛОГИРОВАНИЕ - начало синхронизации с Laravel
        $log_file = WP_CONTENT_DIR . '/course-registration-debug.log';
        $log_message = '[' . date('Y-m-d H:i:s') . '] ========== НАЧАЛО СИНХРОНИЗАЦИИ С LARAVEL ==========' . "\n";
        $log_message .= 'User ID: ' . $user_id . "\n";
        $log_message .= 'Moodle User ID: ' . $moodle_user_id . "\n";
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
            $error_msg = 'Moodle User Sync: Laravel API не настроен, пропускаем синхронизацию с Laravel. URL: ' . ($laravel_api_url ?: 'пусто') . ', Token: ' . ($laravel_api_token ? 'установлен' : 'не установлен');
            error_log($error_msg);
            @file_put_contents($log_file, '[' . date('Y-m-d H:i:s') . '] ОШИБКА: ' . $error_msg . "\n", FILE_APPEND);
            if (class_exists('Course_Logger')) {
                Course_Logger::warning('Laravel API не настроен для синхронизации пользователя ID: ' . $user_id);
            }
            return;
        }
        
        // Получаем данные пользователя WordPress
        $user = get_userdata($user_id);
        if (!$user) {
            error_log('Moodle User Sync: Пользователь WordPress с ID ' . $user_id . ' не найден для синхронизации с Laravel');
            return;
        }
        
        // Подготавливаем данные для отправки в Laravel
        $name = trim($user->first_name . ' ' . $user->last_name);
        if (empty($name)) {
            $name = $user->display_name ? $user->display_name : $user->user_login;
        }
        
        // ВАЖНО: Используем тот же пароль, что был отправлен в Moodle
        // Пароль уже модифицирован для Moodle, используем его и для Laravel
        // Это гарантирует, что пользователь сможет войти с одним паролем во все системы
        $data = array(
            'name' => $name,
            'email' => $user->user_email,
            'password' => $password, // Пароль уже модифицирован для Moodle
            'moodle_user_id' => $moodle_user_id,
            'phone' => get_user_meta($user_id, 'phone', true) ?: '',
        );
        
        // Логируем пароль для отладки (первые 3 символа)
        error_log('Moodle User Sync: Отправка пароля в Laravel (длина: ' . strlen($password) . ', первые 3 символа: ' . substr($password, 0, 3) . '***)');
        
        // Формируем URL для API запроса
        $api_url = rtrim($laravel_api_url, '/') . '/api/users/sync-from-wordpress';
        
        // Логируем данные перед отправкой
        $log_message = '[' . date('Y-m-d H:i:s') . '] Отправка запроса в Laravel:' . "\n";
        $log_message .= 'URL: ' . $api_url . "\n";
        $log_message .= 'Data: ' . json_encode(array_merge($data, array('password' => '***скрыто***'))) . "\n";
        @file_put_contents($log_file, $log_message, FILE_APPEND);
        
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
            $error_msg = 'Moodle User Sync: Ошибка при синхронизации с Laravel - ' . $response->get_error_message() . ' (код: ' . $response->get_error_code() . ')';
            error_log($error_msg);
            @file_put_contents($log_file, '[' . date('Y-m-d H:i:s') . '] ОШИБКА ЗАПРОСА: ' . $error_msg . "\n", FILE_APPEND);
            if (class_exists('Course_Logger')) {
                Course_Logger::error('Ошибка синхронизации с Laravel для пользователя ID: ' . $user_id . ' - ' . $response->get_error_message());
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
        
        if ($response_code === 201 && isset($response_data['success']) && $response_data['success']) {
            $success_msg = 'Moodle User Sync: Пользователь успешно синхронизирован с Laravel приложением';
            error_log($success_msg);
            @file_put_contents($log_file, '[' . date('Y-m-d H:i:s') . '] УСПЕХ: ' . $success_msg . "\n", FILE_APPEND);
            if (class_exists('Course_Logger')) {
                Course_Logger::info('Пользователь успешно синхронизирован с Laravel: ID=' . $user_id . ', email=' . $user->user_email);
            }
        } else {
            $error_msg = 'Moodle User Sync: Ошибка синхронизации с Laravel - код ответа: ' . $response_code . ', ответ: ' . $response_body;
            error_log($error_msg);
            @file_put_contents($log_file, '[' . date('Y-m-d H:i:s') . '] ОШИБКА ОТВЕТА: ' . $error_msg . "\n", FILE_APPEND);
            if (class_exists('Course_Logger')) {
                Course_Logger::error('Ошибка синхронизации с Laravel для пользователя ID: ' . $user_id . ' - код: ' . $response_code . ', ответ: ' . $response_body);
            }
        }
    }
    
    /**
     * Отправка письма пользователю с паролем
     * Вызывается после создания пользователя в Moodle
     * 
     * @param int $user_id ID пользователя WordPress
     * @param string $password Пароль пользователя
     */
    private function send_password_email($user_id, $password) {
        $log_file = WP_CONTENT_DIR . '/course-registration-debug.log';
        $log_message = '[' . date('Y-m-d H:i:s') . '] ========== ОТПРАВКА ПИСЬМА С ПАРОЛЕМ ==========' . "\n";
        $log_message .= 'User ID: ' . $user_id . "\n";
        @file_put_contents($log_file, $log_message, FILE_APPEND);
        
        $user = get_userdata($user_id);
        if (!$user) {
            error_log('Moodle User Sync: Пользователь с ID ' . $user_id . ' не найден для отправки письма');
            return;
        }
        
        // Получаем настройки сайта
        $blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
        $admin_email = get_option('admin_email');
        
        // Формируем тему письма
        $subject = sprintf(__('[%s] Ваши данные для входа', 'course-plugin'), $blogname);
        
        // Формируем текст письма
        $message = sprintf(__('Здравствуйте, %s!', 'course-plugin'), $user->display_name) . "\r\n\r\n";
        $message .= __('Ваш аккаунт успешно создан на всех платформах.', 'course-plugin') . "\r\n\r\n";
        $message .= __('Ваши данные для входа:', 'course-plugin') . "\r\n";
        $message .= sprintf(__('Логин: %s', 'course-plugin'), $user->user_login) . "\r\n";
        $message .= sprintf(__('Email: %s', 'course-plugin'), $user->user_email) . "\r\n";
        $message .= sprintf(__('Пароль: %s', 'course-plugin'), $password) . "\r\n\r\n";
        $message .= __('Вы можете использовать эти данные для входа на:', 'course-plugin') . "\r\n";
        $message .= sprintf(__('- WordPress: %s', 'course-plugin'), wp_login_url()) . "\r\n";
        $moodle_url = get_option('moodle_sync_url', '');
        if ($moodle_url) {
            $message .= sprintf(__('- Moodle: %s', 'course-plugin'), rtrim($moodle_url, '/') . '/login/index.php') . "\r\n";
        }
        $laravel_url = get_option('laravel_api_url', '');
        if ($laravel_url) {
            $message .= sprintf(__('- Система управления: %s', 'course-plugin'), rtrim($laravel_url, '/') . '/login') . "\r\n";
        }
        $message .= "\r\n";
        $message .= __('С уважением,', 'course-plugin') . "\r\n";
        $message .= sprintf(__('Команда %s', 'course-plugin'), $blogname) . "\r\n";
        
        // Заголовки письма
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $blogname . ' <' . $admin_email . '>'
        );
        
        // Отправляем письмо
        $mail_result = wp_mail($user->user_email, $subject, $message, $headers);
        
        // Логируем результат
        if ($mail_result) {
            $success_msg = 'Moodle User Sync: Письмо с паролем успешно отправлено пользователю ' . $user->user_email;
            error_log($success_msg);
            @file_put_contents($log_file, '[' . date('Y-m-d H:i:s') . '] УСПЕХ: ' . $success_msg . "\n", FILE_APPEND);
        } else {
            $error_msg = 'Moodle User Sync: ОШИБКА отправки письма с паролем пользователю ' . $user->user_email;
            error_log($error_msg);
            @file_put_contents($log_file, '[' . date('Y-m-d H:i:s') . '] ОШИБКА: ' . $error_msg . "\n", FILE_APPEND);
            
            // Проверяем ошибки PHPMailer
            global $phpmailer;
            if (isset($phpmailer) && isset($phpmailer->ErrorInfo)) {
                $phpmailer_error = 'PHPMailer ошибка: ' . $phpmailer->ErrorInfo;
                error_log('Moodle User Sync: ' . $phpmailer_error);
                @file_put_contents($log_file, '[' . date('Y-m-d H:i:s') . '] ' . $phpmailer_error . "\n", FILE_APPEND);
            }
        }
    }
}

