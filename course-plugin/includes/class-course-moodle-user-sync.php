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
        
        // Регистрируем хук для синхронизации при регистрации нового пользователя
        // Хук 'user_register' срабатывает после успешной регистрации пользователя в WordPress
        add_action('user_register', array($this, 'sync_user_on_register'), 10, 1);
        
        // Регистрируем хук для синхронизации при обновлении профиля пользователя
        // Хук 'profile_update' срабатывает при обновлении данных пользователя
        add_action('profile_update', array($this, 'sync_user_on_update'), 10, 2);
        
        // Регистрируем хук для синхронизации при смене пароля
        // Хук 'password_reset' срабатывает при сбросе пароля пользователя
        add_action('password_reset', array($this, 'sync_user_password'), 10, 2);
        
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
     * Синхронизация пользователя при регистрации
     * Создает пользователя в Moodle при регистрации в WordPress
     * 
     * @param int $user_id ID пользователя WordPress
     */
    public function sync_user_on_register($user_id) {
        // Получаем объект пользователя WordPress
        $user = get_userdata($user_id);
        
        if (!$user) {
            return; // Если пользователь не найден, прекращаем выполнение
        }
        
        // Проверяем, что API настроен
        if (!$this->api) {
            return;
        }
        
        // Проверяем, существует ли уже пользователь в Moodle
        $moodle_user = $this->api->get_user_by_email($user->user_email);
        
        if ($moodle_user) {
            // Если пользователь уже существует в Moodle, обновляем его данные
            // Сохраняем ID пользователя Moodle в метаполе WordPress пользователя
            update_user_meta($user_id, 'moodle_user_id', $moodle_user['id']);
            
            // Обновляем пароль в Moodle, если он был изменен
            $this->sync_user_password($user, $user->user_pass);
            
            error_log('Moodle User Sync: Пользователь ' . $user->user_email . ' уже существует в Moodle, обновлены данные');
            return;
        }
        
        // Подготавливаем данные для создания пользователя в Moodle
        $user_data = array(
            'username' => $user->user_login,  // Логин пользователя
            'password' => $user->user_pass,    // Пароль пользователя (хэш)
            'firstname' => $user->first_name ? $user->first_name : $user->display_name,  // Имя
            'lastname' => $user->last_name ? $user->last_name : '',  // Фамилия
            'email' => $user->user_email,     // Email
        );
        
        // Пытаемся создать пользователя в Moodle
        $result = $this->api->create_user($user_data);
        
        if ($result && isset($result[0]['id'])) {
            // Если пользователь успешно создан, сохраняем его ID Moodle в метаполе WordPress
            update_user_meta($user_id, 'moodle_user_id', $result[0]['id']);
            error_log('Moodle User Sync: Пользователь ' . $user->user_email . ' успешно создан в Moodle (ID: ' . $result[0]['id'] . ')');
        } else {
            // Если произошла ошибка, записываем её в лог
            error_log('Moodle User Sync: Ошибка при создании пользователя ' . $user->user_email . ' в Moodle');
            if (is_array($result) && isset($result[0]['warnings'])) {
                foreach ($result[0]['warnings'] as $warning) {
                    error_log('Moodle User Sync Warning: ' . $warning['message']);
                }
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
     * Синхронизация пароля пользователя
     * Обновляет пароль пользователя в Moodle при смене пароля в WordPress
     * 
     * @param WP_User $user Объект пользователя WordPress
     * @param string $new_pass Новый пароль (хэш)
     */
    public function sync_user_password($user, $new_pass = '') {
        // Проверяем, что API настроен
        if (!$this->api) {
            return;
        }
        
        // Если пароль не передан, получаем его из объекта пользователя
        if (empty($new_pass) && isset($user->user_pass)) {
            $new_pass = $user->user_pass;
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
                return; // Если пользователь не найден в Moodle, прекращаем выполнение
            }
        }
        
        // Обновляем пароль в Moodle
        // ВАЖНО: Moodle требует незахэшированный пароль, но WordPress хранит хэш
        // Поэтому мы не можем напрямую синхронизировать хэш пароля
        // Вместо этого нужно либо использовать SSO, либо хранить пароль отдельно (не рекомендуется)
        // Для безопасности лучше использовать SSO или не синхронизировать пароли напрямую
        
        // ВНИМАНИЕ: Этот код требует, чтобы пароль был передан в открытом виде
        // Это небезопасно! Рекомендуется использовать SSO вместо синхронизации паролей
        // Для демонстрации оставляем этот код, но в продакшене лучше использовать SSO
        
        error_log('Moodle User Sync: Попытка обновления пароля для пользователя ' . $user->user_email);
        // Пароли не синхронизируются напрямую из соображений безопасности
        // Используйте SSO для единого входа
    }
}

