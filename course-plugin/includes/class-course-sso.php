<?php
/**
 * Класс для реализации Single Sign-On (SSO)
 * Позволяет пользователям автоматически входить в Moodle и Laravel после входа в WordPress
 */

// Проверка безопасности: если файл вызывается напрямую, прекращаем выполнение
if (!defined('ABSPATH')) {
    exit;
}

class Course_SSO {
    
    /**
     * Единственный экземпляр класса (Singleton)
     */
    private static $instance = null;
    
    /**
     * Получить экземпляр класса
     * 
     * @return Course_SSO Экземпляр класса
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Конструктор класса
     * Регистрирует хуки WordPress
     */
    private function __construct() {
        // Генерируем SSO токены при входе пользователя в WordPress
        add_action('wp_login', array($this, 'generate_sso_tokens'), 10, 2);
        
        // Добавляем скрипты для автоматического входа в Moodle и Laravel
        add_action('wp_footer', array($this, 'add_sso_scripts'));
        add_action('admin_footer', array($this, 'add_sso_scripts'));
        
        // AJAX endpoint для получения SSO токенов
        add_action('wp_ajax_get_sso_tokens', array($this, 'ajax_get_sso_tokens'));
        add_action('wp_ajax_nopriv_get_sso_tokens', array($this, 'ajax_get_sso_tokens'));
        
        // Endpoint для проверки SSO токена (для Moodle/Laravel)
        add_action('wp_ajax_verify_sso_token', array($this, 'ajax_verify_sso_token'));
        add_action('wp_ajax_nopriv_verify_sso_token', array($this, 'ajax_verify_sso_token'));
        
        // Добавляем виджет в меню пользователя
        add_filter('wp_nav_menu_items', array($this, 'add_sso_menu_items'), 10, 2);
        
        // Регистрируем шорткод для кнопок SSO
        add_shortcode('sso_buttons', array($this, 'sso_buttons_shortcode'));
        
        // Генерируем SSO API ключ при первой загрузке, если он не установлен
        if (empty(get_option('sso_api_key', ''))) {
            $this->generate_sso_api_key();
        }
    }
    
    /**
     * Генерация SSO API ключа
     */
    private function generate_sso_api_key() {
        $api_key = wp_generate_password(64, false);
        update_option('sso_api_key', $api_key);
        error_log('Course SSO: SSO API ключ сгенерирован автоматически');
    }
    
    /**
     * Генерация SSO токенов при входе пользователя
     * 
     * @param string $user_login Логин пользователя
     * @param WP_User $user Объект пользователя
     */
    public function generate_sso_tokens($user_login, $user) {
        // Генерируем токен для Moodle
        $moodle_token = $this->generate_token($user->ID, 'moodle');
        update_user_meta($user->ID, 'sso_moodle_token', $moodle_token);
        update_user_meta($user->ID, 'sso_moodle_token_expires', time() + 3600); // Токен действителен 1 час
        
        // Генерируем токен для Laravel
        $laravel_token = $this->generate_token($user->ID, 'laravel');
        update_user_meta($user->ID, 'sso_laravel_token', $laravel_token);
        update_user_meta($user->ID, 'sso_laravel_token_expires', time() + 3600); // Токен действителен 1 час
        
        error_log('Course SSO: Токены сгенерированы для пользователя ID: ' . $user->ID);
    }
    
    /**
     * Генерация SSO токена
     * 
     * @param int $user_id ID пользователя
     * @param string $service Название сервиса (moodle/laravel)
     * @return string Токен
     */
    private function generate_token($user_id, $service) {
        $user = get_userdata($user_id);
        if (!$user) {
            return '';
        }
        
        // Создаем токен на основе данных пользователя и секретного ключа
        $secret = wp_salt('auth');
        $data = $user_id . '|' . $user->user_email . '|' . $service . '|' . time();
        $token = hash_hmac('sha256', $data, $secret);
        
        return base64_encode($user_id . ':' . $token);
    }
    
    /**
     * Добавление скриптов для SSO входа
     */
    public function add_sso_scripts() {
        // Показываем только для авторизованных пользователей
        if (!is_user_logged_in()) {
            return;
        }
        
        $moodle_url = get_option('moodle_sync_url', '');
        $laravel_url = get_option('laravel_api_url', '');
        
        if (empty($moodle_url) && empty($laravel_url)) {
            return; // Если не настроены URL, не добавляем скрипты
        }
        
        // Получаем URL для AJAX
        $ajax_url = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce('sso_tokens');
        
        ?>
        <script type="text/javascript">
        (function() {
            // Функция для выполнения AJAX запроса (работает с jQuery или без него)
            function ssoAjaxRequest(action, callback) {
                var ajaxUrl = '<?php echo esc_js($ajax_url); ?>';
                var nonce = '<?php echo esc_js($nonce); ?>';
                
                // Используем Fetch API если доступен, иначе XMLHttpRequest
                if (typeof fetch !== 'undefined') {
                    var formData = new FormData();
                    formData.append('action', action);
                    formData.append('nonce', nonce);
                    
                    fetch(ajaxUrl, {
                        method: 'POST',
                        body: formData
                    })
                    .then(function(response) {
                        return response.json();
                    })
                    .then(function(data) {
                        callback(data);
                    })
                    .catch(function(error) {
                        console.error('SSO Error:', error);
                        alert('Ошибка при получении токена');
                    });
                } else if (typeof XMLHttpRequest !== 'undefined') {
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', ajaxUrl, true);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.onreadystatechange = function() {
                        if (xhr.readyState === 4) {
                            if (xhr.status === 200) {
                                try {
                                    var data = JSON.parse(xhr.responseText);
                                    callback(data);
                                } catch (e) {
                                    console.error('SSO Parse Error:', e);
                                    alert('Ошибка при обработке ответа');
                                }
                            } else {
                                alert('Ошибка при получении токена');
                            }
                        }
                    };
                    xhr.send('action=' + encodeURIComponent(action) + '&nonce=' + encodeURIComponent(nonce));
                } else {
                    alert('Браузер не поддерживает необходимые функции для SSO');
                }
            }
            
            // Функция для перехода в Moodle
            window.goToMoodle = function() {
                <?php if (!empty($moodle_url)): ?>
                ssoAjaxRequest('get_sso_tokens', function(response) {
                    if (response.success && response.data && response.data.moodle_token) {
                        var moodleUrl = '<?php echo esc_js(rtrim($moodle_url, '/')); ?>' + '/sso-login.php?token=' + encodeURIComponent(response.data.moodle_token);
                        window.location.href = moodleUrl;
                    } else {
                        alert('Ошибка получения токена для входа в Moodle');
                    }
                });
                <?php else: ?>
                alert('Moodle URL не настроен');
                <?php endif; ?>
            };
            
            // Функция для перехода в Laravel
            window.goToLaravel = function() {
                <?php if (!empty($laravel_url)): ?>
                ssoAjaxRequest('get_sso_tokens', function(response) {
                    if (response.success && response.data && response.data.laravel_token) {
                        var laravelUrl = '<?php echo esc_js(rtrim($laravel_url, '/')); ?>' + '/sso/login?token=' + encodeURIComponent(response.data.laravel_token);
                        window.location.href = laravelUrl;
                    } else {
                        alert('Ошибка получения токена для входа в Laravel');
                    }
                });
                <?php else: ?>
                alert('Laravel URL не настроен');
                <?php endif; ?>
            };
        })();
        </script>
        <?php
    }
    
    /**
     * Добавление пунктов меню для SSO
     */
    public function add_sso_menu_items($items, $args) {
        // Показываем только для авторизованных пользователей
        if (!is_user_logged_in()) {
            return $items;
        }
        
        $moodle_url = get_option('moodle_sync_url', '');
        $laravel_url = get_option('laravel_api_url', '');
        
        if (empty($moodle_url) && empty($laravel_url)) {
            return $items;
        }
        
        $sso_items = '';
        
        if (!empty($moodle_url)) {
            $sso_items .= '<li class="menu-item"><a href="javascript:void(0);" onclick="goToMoodle();" class="sso-moodle-link">Moodle</a></li>';
        }
        
        if (!empty($laravel_url)) {
            $sso_items .= '<li class="menu-item"><a href="javascript:void(0);" onclick="goToLaravel();" class="sso-laravel-link">Система управления</a></li>';
        }
        
        return $items . $sso_items;
    }
    
    /**
     * Шорткод для отображения кнопок SSO
     * Использование: [sso_buttons]
     */
    public function sso_buttons_shortcode($atts) {
        // Показываем только для авторизованных пользователей
        if (!is_user_logged_in()) {
            return '';
        }
        
        $moodle_url = get_option('moodle_sync_url', '');
        $laravel_url = get_option('laravel_api_url', '');
        
        if (empty($moodle_url) && empty($laravel_url)) {
            return '';
        }
        
        ob_start();
        ?>
        <div class="sso-buttons-wrapper" style="margin: 20px 0;">
            <?php if (!empty($moodle_url)): ?>
            <a href="javascript:void(0);" onclick="goToMoodle();" class="sso-button sso-moodle" style="display: inline-block; padding: 10px 20px; margin: 5px; background: #f98012; color: white; text-decoration: none; border-radius: 5px;">
                Перейти в Moodle
            </a>
            <?php endif; ?>
            
            <?php if (!empty($laravel_url)): ?>
            <a href="javascript:void(0);" onclick="goToLaravel();" class="sso-button sso-laravel" style="display: inline-block; padding: 10px 20px; margin: 5px; background: #f9322c; color: white; text-decoration: none; border-radius: 5px;">
                Перейти в Систему управления
            </a>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * AJAX обработчик для получения SSO токенов
     */
    public function ajax_get_sso_tokens() {
        // Проверяем nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sso_tokens')) {
            wp_send_json_error(array('message' => 'Ошибка безопасности'));
        }
        
        // Проверяем, что пользователь авторизован
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Пользователь не авторизован'));
        }
        
        $user_id = get_current_user_id();
        $moodle_token = get_user_meta($user_id, 'sso_moodle_token', true);
        $laravel_token = get_user_meta($user_id, 'sso_laravel_token', true);
        
        // Проверяем срок действия токенов
        $moodle_expires = get_user_meta($user_id, 'sso_moodle_token_expires', true);
        $laravel_expires = get_user_meta($user_id, 'sso_laravel_token_expires', true);
        
        // Если токены истекли, генерируем новые
        if (empty($moodle_token) || empty($moodle_expires) || $moodle_expires < time()) {
            $moodle_token = $this->generate_token($user_id, 'moodle');
            update_user_meta($user_id, 'sso_moodle_token', $moodle_token);
            update_user_meta($user_id, 'sso_moodle_token_expires', time() + 3600);
        }
        
        if (empty($laravel_token) || empty($laravel_expires) || $laravel_expires < time()) {
            $laravel_token = $this->generate_token($user_id, 'laravel');
            update_user_meta($user_id, 'sso_laravel_token', $laravel_token);
            update_user_meta($user_id, 'sso_laravel_token_expires', time() + 3600);
        }
        
        wp_send_json_success(array(
            'moodle_token' => $moodle_token,
            'laravel_token' => $laravel_token
        ));
    }
    
    /**
     * Проверка SSO токена (для использования в Moodle/Laravel)
     * 
     * @param string $token SSO токен
     * @param string $service Название сервиса (moodle/laravel)
     * @return array|false Данные пользователя или false при ошибке
     */
    public static function verify_token($token, $service) {
        if (empty($token)) {
            return false;
        }
        
        // Декодируем токен
        $decoded = base64_decode($token);
        if (!$decoded) {
            return false;
        }
        
        $parts = explode(':', $decoded);
        if (count($parts) !== 2) {
            return false;
        }
        
        $user_id = intval($parts[0]);
        $token_hash = $parts[1];
        
        // Получаем пользователя
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }
        
        // Проверяем срок действия токена
        $expires = get_user_meta($user_id, 'sso_' . $service . '_token_expires', true);
        if (empty($expires) || $expires < time()) {
            return false;
        }
        
        // Проверяем токен
        $stored_token = get_user_meta($user_id, 'sso_' . $service . '_token', true);
        if ($stored_token !== $token) {
            return false;
        }
        
        // Возвращаем данные пользователя
        return array(
            'user_id' => $user_id,
            'email' => $user->user_email,
            'login' => $user->user_login,
            'name' => $user->display_name
        );
    }
    
    /**
     * AJAX обработчик для проверки SSO токена (используется Moodle/Laravel)
     */
    public function ajax_verify_sso_token() {
        // Проверяем API ключ для безопасности
        $api_key = isset($_REQUEST['api_key']) ? sanitize_text_field($_REQUEST['api_key']) : '';
        $expected_key = get_option('sso_api_key', '');
        
        if (empty($expected_key) || $api_key !== $expected_key) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $token = isset($_REQUEST['token']) ? sanitize_text_field($_REQUEST['token']) : '';
        $service = isset($_REQUEST['service']) ? sanitize_text_field($_REQUEST['service']) : '';
        
        if (empty($token) || empty($service)) {
            wp_send_json_error(array('message' => 'Token and service required'));
        }
        
        $user_data = self::verify_token($token, $service);
        
        if ($user_data) {
            wp_send_json_success($user_data);
        } else {
            wp_send_json_error(array('message' => 'Invalid or expired token'));
        }
    }
}

