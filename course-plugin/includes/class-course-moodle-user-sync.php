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
        
        // Регистрируем хук для синхронизации при регистрации нового пользователя
        // Хук 'user_register' срабатывает после успешной регистрации пользователя в WordPress
        // ВАЖНО: Хук отключен, так как глобальная переменная недоступна в контексте хука
        // Синхронизация происходит через прямой вызов sync_user() из класса Course_Registration
        // add_action('user_register', array($this, 'sync_user_on_register'), 10, 1);
        
        // Регистрируем хук для синхронизации при обновлении профиля пользователя
        // Хук 'profile_update' срабатывает при обновлении данных пользователя
        add_action('profile_update', array($this, 'sync_user_on_update'), 10, 2);
        
        // Регистрируем хук для перехвата пароля при сбросе
        add_action('after_password_reset', array($this, 'sync_user_password_after_reset'), 10, 2);
        
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
        
        // Вызываем стандартный метод синхронизации
        try {
            $this->sync_user_on_register($user_id);
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
     */
    public function sync_user_on_register($user_id) {
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
        
        // Получаем незахэшированный пароль из временного хранилища
        $plain_password = '';
        
        // Логируем состояние глобальной переменной перед поиском пароля
        error_log('Moodle User Sync: Поиск пароля для логина: ' . $user->user_login);
        error_log('Moodle User Sync: Глобальная переменная существует: ' . (isset($GLOBALS['moodle_user_sync_password']) ? 'да' : 'нет'));
        if (isset($GLOBALS['moodle_user_sync_password']) && is_array($GLOBALS['moodle_user_sync_password'])) {
            error_log('Moodle User Sync: Доступные ключи в глобальной переменной: ' . print_r(array_keys($GLOBALS['moodle_user_sync_password']), true));
        }
        
        // Сначала проверяем глобальную переменную (устанавливается в sync_user или capture_password_before_hash)
        if (isset($GLOBALS['moodle_user_sync_password'][$user->user_login])) {
            $plain_password = $GLOBALS['moodle_user_sync_password'][$user->user_login];
            // НЕ удаляем пароль сразу - он может понадобиться для повторных попыток
            error_log('Moodle User Sync: Пароль найден в глобальной переменной для ' . $user->user_email . ' (длина: ' . strlen($plain_password) . ' символов)');
        } 
        // Если не найден, пытаемся получить из POST данных (если регистрация через форму)
        elseif (isset($_POST['user_pass']) && !empty($_POST['user_pass'])) {
            $plain_password = $_POST['user_pass'];
            error_log('Moodle User Sync: Пароль получен из POST данных для ' . $user->user_email);
        }
        // Если все еще не найден, проверяем статическую переменную из capture_password_before_hash
        else {
            // Пытаемся найти пароль в других возможных местах
            error_log('Moodle User Sync: Пароль не найден в стандартных местах для ' . $user->user_email . ', логин: ' . $user->user_login);
            error_log('Moodle User Sync: Доступные ключи в глобальной переменной: ' . (isset($GLOBALS['moodle_user_sync_password']) && is_array($GLOBALS['moodle_user_sync_password']) ? print_r(array_keys($GLOBALS['moodle_user_sync_password']), true) : 'переменная не существует или не массив'));
        }
        
        // Если пароль не найден, используем случайный пароль
        // Это может произойти, если пользователь был создан не через стандартную регистрацию
        // Важно: Moodle требует хотя бы один специальный символ (*, -, или #) И хотя бы одну цифру
        if (empty($plain_password)) {
            // Генерируем пароль с специальными символами и цифрами для Moodle
            $plain_password = wp_generate_password(12, true);  // true = включить специальные символы
            // Убеждаемся, что пароль содержит хотя бы один специальный символ для Moodle (*, -, или #)
            if (!preg_match('/[*\-#]/', $plain_password)) {
                // Если специальных символов нет, добавляем дефис
                $plain_password = substr($plain_password, 0, 11) . '-';
            }
            // Убеждаемся, что пароль содержит хотя бы одну цифру (Moodle требует)
            if (!preg_match('/[0-9]/', $plain_password)) {
                // Если цифр нет, заменяем последний символ на цифру
                $plain_password = substr($plain_password, 0, 11) . '1';
            }
            error_log('Moodle User Sync: ВНИМАНИЕ! Пароль не найден для ' . $user->user_email . ', используется случайный пароль с специальными символами и цифрой. Пользователь не сможет войти в Moodle с тем же паролем!');
        }
        
        // Подготавливаем данные для создания пользователя в Moodle
        // Важно: Moodle требует, чтобы lastname не был пустым, поэтому используем дефис если пусто
        // Пробел может вызывать ошибку "Invalid parameter value detected"
        $firstname = !empty($user->first_name) ? trim($user->first_name) : (!empty($user->display_name) ? trim($user->display_name) : $user->user_login);
        $lastname = !empty($user->last_name) && trim($user->last_name) !== '' ? trim($user->last_name) : '-';  // Дефис вместо пустой строки
        
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
                update_user_meta($user_id, 'moodle_password_used', $plain_password);
                error_log('Moodle User Sync: Пароль сохранен в метаполе пользователя для отправки в письме');
                
                // Удаляем пароль из памяти после успешного создания
                if (isset($GLOBALS['moodle_user_sync_password'][$user->user_login])) {
                    unset($GLOBALS['moodle_user_sync_password'][$user->user_login]);
                }
                error_log('Moodle User Sync: УСПЕХ! Пользователь ' . $user->user_email . ' успешно создан в Moodle (ID: ' . $moodle_user_id . ') с паролем длиной ' . strlen($plain_password) . ' символов');
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
}

