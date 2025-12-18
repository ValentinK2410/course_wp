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
        // Получаем настройки синхронизации из базы данных WordPress
        $this->moodle_url = get_option('moodle_sync_url', '');
        $this->moodle_token = get_option('moodle_sync_token', '');
        
        // Проверяем, включена ли синхронизация пользователей
        $sync_enabled = get_option('moodle_sync_users_enabled', true);
        
        if (!$sync_enabled || !$this->moodle_url || !$this->moodle_token) {
            return; // Если синхронизация отключена или не настроена, не регистрируем хуки
        }
        
        // Создаем объект для работы с Moodle API
        if ($this->moodle_url && $this->moodle_token) {
            $this->api = new Course_Moodle_API($this->moodle_url, $this->moodle_token);
        }
        
        // Регистрируем хук для перехвата пароля при регистрации (до хэширования)
        // Хук 'user_register' срабатывает после успешной регистрации, но пароль уже захэширован
        // Поэтому используем фильтр 'wp_insert_user_data' для перехвата пароля до хэширования
        add_filter('wp_insert_user_data', array($this, 'capture_password_before_hash'), 10, 3);
        
        // Регистрируем хук для синхронизации при регистрации нового пользователя
        // Хук 'user_register' срабатывает после успешной регистрации пользователя в WordPress
        add_action('user_register', array($this, 'sync_user_on_register'), 10, 1);
        
        // Регистрируем хук для синхронизации при обновлении профиля пользователя
        // Хук 'profile_update' срабатывает при обновлении данных пользователя
        add_action('profile_update', array($this, 'sync_user_on_update'), 10, 2);
        
        // Регистрируем хук для перехвата пароля при сбросе
        add_action('after_password_reset', array($this, 'sync_user_password_after_reset'), 10, 2);
        
        // Регистрируем хук для добавления настроек в админку
        add_action('admin_init', array($this, 'register_user_sync_settings'));
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
        error_log('Moodle User Sync: sync_user вызван для пользователя ID: ' . $user_id);
        
        // Если передан пароль, сохраняем его во временное хранилище
        if (!empty($plain_password)) {
            $user = get_userdata($user_id);
            if ($user) {
                $GLOBALS['moodle_user_sync_password'][$user->user_login] = $plain_password;
                error_log('Moodle User Sync: Пароль сохранен для ' . $user->user_login);
            }
        }
        
        // Обновляем настройки API перед синхронизацией
        $this->moodle_url = get_option('moodle_sync_url', '');
        $this->moodle_token = get_option('moodle_sync_token', '');
        $sync_enabled = get_option('moodle_sync_users_enabled', true);
        
        error_log('Moodle User Sync: Настройки - URL: ' . $this->moodle_url . ', Token: ' . (empty($this->moodle_token) ? 'не установлен' : 'установлен') . ', Включено: ' . ($sync_enabled ? 'да' : 'нет'));
        
        // Проверяем настройки перед синхронизацией
        if (!$sync_enabled) {
            error_log('Moodle User Sync: Синхронизация пользователей отключена в настройках');
            return false;
        }
        
        if (empty($this->moodle_url) || empty($this->moodle_token)) {
            error_log('Moodle User Sync: URL или токен не настроены');
            return false;
        }
        
        // Инициализируем API если еще не инициализирован
        if (!$this->api) {
            $this->api = new Course_Moodle_API($this->moodle_url, $this->moodle_token);
            error_log('Moodle User Sync: API объект создан');
        }
        
        // Вызываем стандартный метод синхронизации
        $this->sync_user_on_register($user_id);
        
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
        if (!$update && isset($data['user_pass']) && !empty($data['user_pass'])) {
            // Сохраняем пароль во временное хранилище (в памяти, через статическую переменную)
            // Это безопасно, так как данные не сохраняются в базе данных
            static $passwords = array();
            $passwords[$data['user_login']] = $data['user_pass'];
            
            // Сохраняем пароль в глобальной переменной для доступа из других методов
            $GLOBALS['moodle_user_sync_password'][$data['user_login']] = $data['user_pass'];
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
        
        // Проверяем, что API настроен
        if (!$this->api) {
            if ($this->moodle_url && $this->moodle_token) {
                $this->api = new Course_Moodle_API($this->moodle_url, $this->moodle_token);
                error_log('Moodle User Sync: API объект создан с URL: ' . $this->moodle_url);
            } else {
                error_log('Moodle User Sync: API не настроен (URL: ' . $this->moodle_url . ', Token: ' . (!empty($this->moodle_token) ? 'установлен' : 'не установлен') . ')');
                return;
            }
        }
        
        error_log('Moodle User Sync: Начало синхронизации пользователя ' . $user->user_email . ' (ID: ' . $user_id . ')');
        
        // Проверяем, существует ли уже пользователь в Moodle
        $moodle_user = $this->api->get_user_by_email($user->user_email);
        
        if ($moodle_user) {
            // Если пользователь уже существует в Moodle, обновляем его данные
            // Сохраняем ID пользователя Moodle в метаполе WordPress пользователя
            update_user_meta($user_id, 'moodle_user_id', $moodle_user['id']);
            
            error_log('Moodle User Sync: Пользователь ' . $user->user_email . ' уже существует в Moodle, обновлены данные');
            return;
        }
        
        // Получаем незахэшированный пароль из временного хранилища
        $plain_password = '';
        if (isset($GLOBALS['moodle_user_sync_password'][$user->user_login])) {
            $plain_password = $GLOBALS['moodle_user_sync_password'][$user->user_login];
            // Удаляем пароль из памяти после использования
            unset($GLOBALS['moodle_user_sync_password'][$user->user_login]);
            error_log('Moodle User Sync: Пароль найден для ' . $user->user_email);
        } else {
            // Пытаемся получить пароль из POST данных (если регистрация через форму)
            if (isset($_POST['user_pass']) && !empty($_POST['user_pass'])) {
                $plain_password = $_POST['user_pass'];
                error_log('Moodle User Sync: Пароль получен из POST данных для ' . $user->user_email);
            }
        }
        
        // Если пароль не найден, используем случайный пароль
        // Это может произойти, если пользователь был создан не через стандартную регистрацию
        if (empty($plain_password)) {
            $plain_password = wp_generate_password(12, false);
            error_log('Moodle User Sync: Пароль не найден для ' . $user->user_email . ', используется случайный пароль');
        }
        
        // Подготавливаем данные для создания пользователя в Moodle
        $user_data = array(
            'username' => $user->user_login,  // Логин пользователя
            'password' => $plain_password,    // Незахэшированный пароль
            'firstname' => $user->first_name ? $user->first_name : $user->display_name,  // Имя
            'lastname' => $user->last_name ? $user->last_name : '',  // Фамилия
            'email' => $user->user_email,     // Email
        );
        
        // Пытаемся создать пользователя в Moodle
        error_log('Moodle User Sync: Отправка запроса на создание пользователя в Moodle: ' . print_r($user_data, true));
        $result = $this->api->create_user($user_data);
        
        error_log('Moodle User Sync: Ответ от Moodle API: ' . print_r($result, true));
        
        if ($result && isset($result[0]['id'])) {
            // Если пользователь успешно создан, сохраняем его ID Moodle в метаполе WordPress
            update_user_meta($user_id, 'moodle_user_id', $result[0]['id']);
            error_log('Moodle User Sync: Пользователь ' . $user->user_email . ' успешно создан в Moodle (ID: ' . $result[0]['id'] . ')');
        } else {
            // Если произошла ошибка, записываем её в лог
            error_log('Moodle User Sync: Ошибка при создании пользователя ' . $user->user_email . ' в Moodle');
            if (is_array($result)) {
                if (isset($result[0]['warnings'])) {
                    foreach ($result[0]['warnings'] as $warning) {
                        error_log('Moodle User Sync Warning: ' . print_r($warning, true));
                    }
                }
                if (isset($result[0]['errors'])) {
                    foreach ($result[0]['errors'] as $error) {
                        error_log('Moodle User Sync Error: ' . print_r($error, true));
                    }
                }
                if (isset($result['exception'])) {
                    error_log('Moodle User Sync Exception: ' . $result['message']);
                }
            } else {
                error_log('Moodle User Sync: Неожиданный формат ответа от API: ' . print_r($result, true));
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

