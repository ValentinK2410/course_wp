<?php
/**
 * Класс для реализации Single Sign-On (SSO)
 * Позволяет пользователям автоматически входить в Moodle и Laravel после входа в WordPress
 * 
 * @copyright Copyright (c) 2024 Кузьменко Валентин (Valentink2410)
 * @author Кузьменко Валентин (Valentink2410)
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
        
        // Добавляем стили для admin bar
        add_action('admin_head', array($this, 'add_admin_bar_styles'));
        add_action('wp_head', array($this, 'add_admin_bar_styles'));
        
        // AJAX endpoint для получения SSO токенов
        add_action('wp_ajax_get_sso_tokens', array($this, 'ajax_get_sso_tokens'));
        add_action('wp_ajax_nopriv_get_sso_tokens', array($this, 'ajax_get_sso_tokens'));
        
        // Endpoint для проверки SSO токена (для Moodle/Laravel)
        add_action('wp_ajax_verify_sso_token', array($this, 'ajax_verify_sso_token'));
        add_action('wp_ajax_nopriv_verify_sso_token', array($this, 'ajax_verify_sso_token'));
        
        // Endpoint для входа из Moodle в WordPress (обратный SSO)
        add_action('wp_ajax_sso_login_from_moodle', array($this, 'ajax_sso_login_from_moodle'));
        add_action('wp_ajax_nopriv_sso_login_from_moodle', array($this, 'ajax_sso_login_from_moodle'));
        
        // Добавляем виджет в меню пользователя
        add_filter('wp_nav_menu_items', array($this, 'add_sso_menu_items'), 10, 2);
        
        // Регистрируем шорткод для кнопок SSO
        add_shortcode('sso_buttons', array($this, 'sso_buttons_shortcode'));
        
        // Добавляем кнопки в WordPress admin bar (верхняя панель)
        add_action('admin_bar_menu', array($this, 'add_admin_bar_items'), 100);
        
        // Генерируем SSO API ключ при первой загрузке, если он не установлен
        if (empty(get_option('sso_api_key', ''))) {
            $this->generate_sso_api_key();
        }
        
        // Генерируем Moodle SSO API ключ при первой загрузке, если он не установлен
        if (empty(get_option('moodle_sso_api_key', ''))) {
            $this->generate_moodle_sso_api_key();
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
     * Генерация Moodle SSO API ключа (для обратного SSO)
     */
    private function generate_moodle_sso_api_key() {
        $api_key = wp_generate_password(64, false);
        update_option('moodle_sso_api_key', $api_key);
        error_log('Course SSO: Moodle SSO API ключ сгенерирован автоматически');
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
     * Добавление кнопок в WordPress admin bar (верхняя панель)
     * 
     * @param WP_Admin_Bar $wp_admin_bar Объект admin bar
     */
    public function add_admin_bar_items($wp_admin_bar) {
        // Показываем только для авторизованных пользователей
        if (!is_user_logged_in()) {
            return;
        }
        
        $moodle_url = get_option('moodle_sync_url', '');
        $laravel_url = get_option('laravel_api_url', '');
        
        // Если не настроены URL, не добавляем кнопки
        if (empty($moodle_url) && empty($laravel_url)) {
            return;
        }
        
        // Добавляем родительский элемент меню
        $wp_admin_bar->add_node(array(
            'id'    => 'course-sso',
            'title' => __('Быстрый переход', 'course-plugin'),
            'meta'  => array(
                'title' => __('Перейти в Moodle или Laravel', 'course-plugin'),
            ),
        ));
        
        // Добавляем кнопку Moodle
        if (!empty($moodle_url)) {
            $wp_admin_bar->add_node(array(
                'parent' => 'course-sso',
                'id'     => 'course-sso-moodle',
                'title'  => __('Перейти в Moodle', 'course-plugin'),
                'href'   => 'javascript:void(0);',
                'meta'   => array(
                    'onclick' => 'goToMoodle(); return false;',
                    'class'  => 'course-sso-moodle',
                    'title'  => __('Перейти в Moodle без ввода пароля', 'course-plugin'),
                ),
            ));
        }
        
        // Добавляем кнопку Laravel
        if (!empty($laravel_url)) {
            $wp_admin_bar->add_node(array(
                'parent' => 'course-sso',
                'id'     => 'course-sso-laravel',
                'title'  => __('Перейти в Laravel', 'course-plugin'),
                'href'   => 'javascript:void(0);',
                'meta'   => array(
                    'onclick' => 'goToLaravel(); return false;',
                    'class'  => 'course-sso-laravel',
                    'title'  => __('Перейти в Laravel без ввода пароля', 'course-plugin'),
                ),
            ));
        }
    }
    
    /**
     * Добавление стилей для кнопок в admin bar
     */
    public function add_admin_bar_styles() {
        // Показываем только для авторизованных пользователей
        if (!is_user_logged_in()) {
            return;
        }
        
        $moodle_url = get_option('moodle_sync_url', '');
        $laravel_url = get_option('laravel_api_url', '');
        
        // Если не настроены URL, не добавляем стили
        if (empty($moodle_url) && empty($laravel_url)) {
            return;
        }
        ?>
        <style type="text/css">
        #wpadminbar #wp-admin-bar-course-sso .ab-item {
            cursor: pointer;
        }
        #wpadminbar #wp-admin-bar-course-sso-moodle .ab-item {
            color: #f98012 !important;
        }
        #wpadminbar #wp-admin-bar-course-sso-moodle:hover .ab-item {
            color: #ff9a3c !important;
        }
        #wpadminbar #wp-admin-bar-course-sso-laravel .ab-item {
            color: #f9322c !important;
        }
        #wpadminbar #wp-admin-bar-course-sso-laravel:hover .ab-item {
            color: #ff4d4d !important;
        }
        </style>
        <?php
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
        // Проверяем API ключ для безопасности (опционально)
        $api_key = isset($_REQUEST['api_key']) ? sanitize_text_field($_REQUEST['api_key']) : '';
        $expected_key = get_option('sso_api_key', '');
        
        // Если API ключ передан, проверяем его
        // Если не передан, но настроен - пропускаем проверку (токен сам по себе защищен)
        if (!empty($api_key)) {
            if (!empty($expected_key) && $api_key !== $expected_key) {
                error_log('Course SSO: Неверный API ключ. Переданный: ' . substr($api_key, 0, 20) . '..., Ожидаемый: ' . substr($expected_key, 0, 20) . '...');
                wp_send_json_error(array('message' => 'Unauthorized'));
            }
        } else {
            // API ключ не передан - это нормально, если он не настроен
            // Если настроен, но не передан - логируем предупреждение, но продолжаем проверку токена
            if (!empty($expected_key)) {
                error_log('Course SSO: API ключ не передан, но настроен в WordPress. Продолжаем проверку токена.');
            }
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
            error_log('Course SSO: Токен недействителен или истек. Токен (первые 20 символов): ' . substr($token, 0, 20) . '..., Сервис: ' . $service);
            wp_send_json_error(array('message' => 'Invalid or expired token'));
        }
    }
    
    /**
     * AJAX обработчик для входа из Moodle в WordPress (обратный SSO)
     * 
     * Использование:
     * https://site.dekan.pro/wp-admin/admin-ajax.php?action=sso_login_from_moodle&token=TOKEN&moodle_api_key=API_KEY
     */
    public function ajax_sso_login_from_moodle() {
        // Проверяем API ключ Moodle для безопасности
        // Используем raw значение из REQUEST, так как sanitize_text_field может изменить ключ
        $moodle_api_key = isset($_REQUEST['moodle_api_key']) ? trim($_REQUEST['moodle_api_key']) : '';
        $expected_key = get_option('moodle_sso_api_key', '');
        
        // Логируем попытку входа для отладки (первые 20 символов для безопасности)
        error_log('Course SSO: Попытка входа из Moodle. Переданный ключ (первые 20 символов): ' . (!empty($moodle_api_key) ? substr($moodle_api_key, 0, 20) . '...' : 'пусто'));
        error_log('Course SSO: Переданный ключ (длина): ' . strlen($moodle_api_key));
        error_log('Course SSO: Ожидаемый ключ (первые 20 символов): ' . (!empty($expected_key) ? substr($expected_key, 0, 20) . '...' : 'пусто'));
        error_log('Course SSO: Ожидаемый ключ (длина): ' . strlen($expected_key));
        
        if (empty($expected_key)) {
            error_log('Course SSO: ОШИБКА - Moodle SSO API ключ не настроен в WordPress!');
            wp_die('Несанкционированный доступ: Moodle SSO API ключ не настроен в WordPress. Перейдите в Настройки → Moodle Sync и установите Moodle SSO API Key.', 'Несанкционированный доступ', array('response' => 401));
        }
        
        // Используем hash_equals для безопасного сравнения строк (защита от timing attacks)
        if (!hash_equals($expected_key, $moodle_api_key)) {
            error_log('Course SSO: ОШИБКА - Неверный Moodle SSO API ключ!');
            error_log('Course SSO: Переданный ключ (первые 30): ' . substr($moodle_api_key, 0, 30));
            error_log('Course SSO: Ожидаемый ключ (первые 30): ' . substr($expected_key, 0, 30));
            wp_die('Несанкционированный доступ: Недействительный ключ API Moodle SSO. Убедитесь, что ключ в файле moodle-sso-to-wordpress.php совпадает с ключом в WordPress (Настройки → Moodle Sync → Moodle SSO API Key).', 'Несанкционированный доступ', array('response' => 401));
        }
        
        // Получаем токен из запроса
        $token = isset($_REQUEST['token']) ? sanitize_text_field($_REQUEST['token']) : '';
        
        if (empty($token)) {
            wp_die('Token required', 'Bad Request', array('response' => 400));
        }
        
        // Декодируем токен от Moodle
        // Формат токена: base64(user_id:email:timestamp:hash)
        $decoded = base64_decode($token);
        if (!$decoded) {
            wp_die('Invalid token format', 'Bad Request', array('response' => 400));
        }
        
        $parts = explode(':', $decoded);
        if (count($parts) !== 4) {
            wp_die('Invalid token format', 'Bad Request', array('response' => 400));
        }
        
        $moodle_user_id = intval($parts[0]);
        $email = sanitize_email($parts[1]);
        $timestamp = intval($parts[2]);
        $token_hash = $parts[3];
        
        // Проверяем срок действия токена (5 минут)
        if (time() - $timestamp > 300) {
            wp_die('Token expired', 'Unauthorized', array('response' => 401));
        }
        
        // Проверяем подпись токена
        $data = $moodle_user_id . '|' . $email . '|' . $timestamp;
        $expected_hash = hash_hmac('sha256', $data, $expected_key);
        
        if (!hash_equals($expected_hash, $token_hash)) {
            wp_die('Invalid token signature', 'Unauthorized', array('response' => 401));
        }
        
        // Ищем пользователя в WordPress по email
        $user = get_user_by('email', $email);
        
        if (!$user) {
            // Пользователь не найден - можно создать нового или показать ошибку
            // Для безопасности лучше показать ошибку, чтобы администратор создал пользователя вручную
            wp_die(
                'Пользователь с email ' . esc_html($email) . ' не найден в WordPress. Обратитесь к администратору.',
                'User Not Found',
                array('response' => 404)
            );
        }
        
        // Автоматически входим пользователя в WordPress
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, true);
        
        // Сохраняем ID пользователя Moodle в метаполе WordPress (если еще не сохранен)
        $existing_moodle_id = get_user_meta($user->ID, 'moodle_user_id', true);
        if (empty($existing_moodle_id)) {
            update_user_meta($user->ID, 'moodle_user_id', $moodle_user_id);
        }
        
        // Проверяем, является ли это первым входом через SSO для синхронизированного пользователя
        $moodle_password_not_changed = get_user_meta($user->ID, 'moodle_password_not_changed', true);
        $sso_first_login_completed = get_user_meta($user->ID, 'sso_first_login_completed', true);
        
        if ($moodle_password_not_changed && empty($sso_first_login_completed)) {
            // Это первый вход через SSO для пользователя, синхронизированного из Moodle
            // Устанавливаем флаг, что первый вход выполнен
            update_user_meta($user->ID, 'sso_first_login_completed', true);
            update_user_meta($user->ID, 'sso_first_login_date', current_time('mysql'));
            
            // Отправляем email с инструкцией по установке пароля WordPress
            $moodle_url = get_option('moodle_sync_url', '');
            $this->send_first_sso_login_email($user, $moodle_url);
            
            error_log('Course SSO: Первый вход через SSO для пользователя ' . $email . '. Email с инструкцией отправлен.');
        }
        
        // Логируем успешный вход
        error_log('Course SSO: Пользователь ' . $email . ' успешно вошел в WordPress из Moodle');
        
        // Перенаправляем на нужную страницу
        $redirect_url = isset($_REQUEST['redirect']) ? esc_url_raw($_REQUEST['redirect']) : admin_url();
        
        // Используем JavaScript для перенаправления, так как заголовки могут быть уже отправлены
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Вход в WordPress...</title>
            <script type="text/javascript">
                window.location.href = <?php echo json_encode($redirect_url); ?>;
            </script>
        </head>
        <body>
            <p>Выполняется вход в WordPress...</p>
            <p>Если перенаправление не произошло автоматически, <a href="<?php echo esc_url($redirect_url); ?>">нажмите здесь</a>.</p>
        </body>
        </html>
        <?php
        exit;
    }
    
    /**
     * Отправка email с инструкцией по установке пароля WordPress после первого входа через SSO
     * 
     * @param WP_User $user Объект пользователя WordPress
     * @param string $moodle_url URL Moodle
     */
    private function send_first_sso_login_email($user, $moodle_url) {
        if (!function_exists('wp_mail')) {
            error_log('Course SSO: Функция wp_mail недоступна для отправки email');
            return false;
        }
        
        $user_email = $user->user_email;
        $user_login = $user->user_login;
        
        // Генерируем ссылку для сброса пароля WordPress
        $reset_key = get_password_reset_key($user);
        $reset_url = network_site_url("wp-login.php?action=rp&key={$reset_key}&login=" . rawurlencode($user_login), 'login');
        
        $subject = 'Добро пожаловать в WordPress! Настройте пароль для прямого входа';
        
        $message = "Здравствуйте, " . $user->display_name . "!\n\n";
        $message .= "Поздравляем! Вы успешно вошли в WordPress через SSO из Moodle.\n\n";
        
        $message .= "═══════════════════════════════════════════════════════════\n";
        $message .= "НАСТРОЙКА ПАРОЛЯ WORDPRESS (ОПЦИОНАЛЬНО)\n";
        $message .= "═══════════════════════════════════════════════════════════\n\n";
        
        $message .= "Вы можете продолжать использовать SSO для входа в WordPress (рекомендуется).\n";
        $message .= "Однако, если вы хотите иметь возможность входить напрямую в WordPress,\n";
        $message .= "вы можете установить свой пароль WordPress.\n\n";
        
        $message .= "Для установки пароля WordPress:\n";
        $message .= "1. Перейдите по этой ссылке:\n";
        $message .= "   " . $reset_url . "\n\n";
        $message .= "2. Введите новый пароль для вашего аккаунта WordPress\n\n";
        $message .= "3. После установки пароля вы сможете входить в WordPress двумя способами:\n";
        $message .= "   - Через SSO из Moodle (как сейчас)\n";
        $message .= "   - Напрямую используя логин и пароль WordPress\n\n";
        
        $message .= "═══════════════════════════════════════════════════════════\n";
        $message .= "СПОСОБЫ ВХОДА В WORDPRESS\n";
        $message .= "═══════════════════════════════════════════════════════════\n\n";
        
        $message .= "СПОСОБ 1: Через SSO из Moodle (РЕКОМЕНДУЕТСЯ)\n";
        $message .= "───────────────────────────────────────────────────────────\n";
        $message .= "1. Войдите в Moodle: " . rtrim($moodle_url, '/') . "/login/index.php\n";
        $message .= "2. Перейдите по ссылке: " . rtrim($moodle_url, '/') . "/moodle-sso-to-wordpress.php\n";
        $message .= "3. Вы автоматически войдете в WordPress\n\n";
        
        $message .= "СПОСОБ 2: Прямой вход в WordPress\n";
        $message .= "───────────────────────────────────────────────────────────\n";
        $message .= "Ссылка для входа: " . home_url('/wp-login.php') . "\n";
        $message .= "Логин: " . $user_login . "\n";
        $message .= "Пароль: (используйте пароль, который вы установите по ссылке выше)\n\n";
        
        $message .= "═══════════════════════════════════════════════════════════\n";
        $message .= "ВАЖНАЯ ИНФОРМАЦИЯ\n";
        $message .= "═══════════════════════════════════════════════════════════\n\n";
        $message .= "• Ваш пароль Moodle остался прежним и не был изменен\n";
        $message .= "• Установка пароля WordPress необязательна - вы можете использовать только SSO\n";
        $message .= "• Ссылка для установки пароля действительна в течение 24 часов\n";
        $message .= "• Если вы не хотите устанавливать пароль WordPress, просто продолжайте использовать SSO\n\n";
        
        $message .= "\nС уважением,\nАдминистрация";
        
        // Используем улучшенный класс для отправки email с поддержкой SMTP
        // Это решает проблемы с доставляемостью в Gmail
        if (class_exists('Course_Email_Sender')) {
            $email_sender = Course_Email_Sender::get_instance();
            
            // Подготавливаем базовые заголовки
            $headers = array();
            
            // Отправляем через улучшенный класс
            $result = $email_sender->send_email($user_email, $subject, $message, $headers);
            
            // Логируем результат
            if ($result['success']) {
                error_log("Course SSO Email: Письмо успешно отправлено на {$user_email} методом: {$result['method']}");
                return true;
            } else {
                error_log("Course SSO Email: ОШИБКА отправки на {$user_email}: {$result['message']} (метод: {$result['method']})");
                return false;
            }
        } else {
            // Fallback на стандартный метод, если класс не загружен
            error_log("Course SSO Email: Класс Course_Email_Sender не найден, используем стандартный метод");
            
            // Получаем настройки для отправки
            $admin_email = get_option('admin_email');
            $site_name = get_bloginfo('name');
            $site_url = home_url();
            
            // Извлекаем домен из email для диагностики
            $email_domain = substr(strrchr($user_email, "@"), 1);
            $is_gmail = (strpos(strtolower($email_domain), 'gmail.com') !== false);
            
            // Отправляем письмо с улучшенными заголовками
            $headers = array();
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            
            $from_name = !empty($site_name) ? $site_name : 'WordPress';
            $from_email = !empty($admin_email) ? $admin_email : 'noreply@' . parse_url($site_url, PHP_URL_HOST);
            $headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
            $headers[] = 'Reply-To: ' . $from_name . ' <' . $from_email . '>';
            $headers[] = 'X-Mailer: WordPress/' . get_bloginfo('version');
            $headers[] = 'MIME-Version: 1.0';
            $headers[] = 'X-Priority: 3';
            
            if ($is_gmail) {
                $headers[] = 'List-Unsubscribe: <' . $site_url . '>, <mailto:' . $from_email . '?subject=unsubscribe>';
            }
            
            // Проверяем, не отключена ли отправка писем (для тестирования)
            $disable_email_sending = get_option('disable_email_sending', false);
            if ($disable_email_sending) {
                error_log("Course SSO Email: Отправка писем отключена в настройках. Письмо не отправлено на {$user_email}");
                return false;
            }
            
            $mail_result = wp_mail($user_email, $subject, $message, $headers);
            
            if ($mail_result) {
                error_log("Course SSO Email: Письмо успешно отправлено на {$user_email}");
                return true;
            } else {
                global $phpmailer;
                $error_message = 'Неизвестная ошибка отправки email';
                if (isset($phpmailer) && is_object($phpmailer) && isset($phpmailer->ErrorInfo)) {
                    $error_message = $phpmailer->ErrorInfo;
                }
                error_log("Course SSO Email: ОШИБКА отправки на {$user_email}: {$error_message}");
                return false;
            }
        }
    }
}

