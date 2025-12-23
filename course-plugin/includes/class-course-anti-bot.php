<?php
/**
 * Класс для защиты регистрации от ботов
 * Реализует несколько уровней защиты: reCAPTCHA, Honeypot, Rate Limiting
 */

// Проверка безопасности: если файл вызывается напрямую, прекращаем выполнение
if (!defined('ABSPATH')) {
    exit;
}

class Course_Anti_Bot {
    
    /**
     * Единственный экземпляр класса (Singleton)
     */
    private static $instance = null;
    
    /**
     * Получить экземпляр класса
     * 
     * @return Course_Anti_Bot Экземпляр класса
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
        // Регистрируем настройки
        add_action('admin_init', array($this, 'register_settings'));
        
        // Добавляем скрипты и поля в форму регистрации
        add_action('wp_footer', array($this, 'add_anti_bot_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // AJAX обработчик для проверки reCAPTCHA
        add_action('wp_ajax_verify_recaptcha', array($this, 'ajax_verify_recaptcha'));
        add_action('wp_ajax_nopriv_verify_recaptcha', array($this, 'ajax_verify_recaptcha'));
    }
    
    /**
     * Регистрация настроек в админке
     */
    public function register_settings() {
        register_setting('course_anti_bot_settings', 'recaptcha_site_key', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field'
        ));
        
        register_setting('course_anti_bot_settings', 'recaptcha_secret_key', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field'
        ));
        
        register_setting('course_anti_bot_settings', 'anti_bot_enabled', array(
            'type' => 'boolean',
            'default' => true
        ));
        
        register_setting('course_anti_bot_settings', 'rate_limit_enabled', array(
            'type' => 'boolean',
            'default' => true
        ));
        
        register_setting('course_anti_bot_settings', 'rate_limit_attempts', array(
            'type' => 'integer',
            'default' => 5
        ));
        
        register_setting('course_anti_bot_settings', 'rate_limit_period', array(
            'type' => 'integer',
            'default' => 3600 // 1 час
        ));
    }
    
    /**
     * Подключение скриптов
     */
    public function enqueue_scripts() {
        $recaptcha_site_key = get_option('recaptcha_site_key', '');
        $anti_bot_enabled = get_option('anti_bot_enabled', true);
        
        if ($anti_bot_enabled && !empty($recaptcha_site_key)) {
            // Подключаем reCAPTCHA v3
            wp_enqueue_script(
                'google-recaptcha',
                'https://www.google.com/recaptcha/api.js?render=' . esc_attr($recaptcha_site_key),
                array(),
                null,
                true
            );
        }
    }
    
    /**
     * Добавление скриптов и honeypot поля в форму регистрации
     */
    public function add_anti_bot_scripts() {
        // Показываем только на страницах с формой регистрации
        if (!is_page() && !is_singular()) {
            return;
        }
        
        $anti_bot_enabled = get_option('anti_bot_enabled', true);
        if (!$anti_bot_enabled) {
            return;
        }
        
        $recaptcha_site_key = get_option('recaptcha_site_key', '');
        $rate_limit_enabled = get_option('rate_limit_enabled', true);
        
        ?>
        <script type="text/javascript">
        (function() {
            // Honeypot поле - скрытое поле, которое боты заполнят, а люди нет
            var honeypotAdded = false;
            
            function addHoneypotField() {
                if (honeypotAdded) return;
                
                var form = document.getElementById('course-registration-form');
                if (!form) return;
                
                // Создаем скрытое поле
                var honeypot = document.createElement('input');
                honeypot.type = 'text';
                honeypot.name = 'website_url';
                honeypot.id = 'website_url';
                honeypot.style.display = 'none';
                honeypot.style.visibility = 'hidden';
                honeypot.style.position = 'absolute';
                honeypot.style.left = '-9999px';
                honeypot.tabIndex = -1;
                honeypot.setAttribute('autocomplete', 'off');
                
                // Добавляем поле в форму
                form.appendChild(honeypot);
                honeypotAdded = true;
            }
            
            // Добавляем honeypot при загрузке страницы
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', addHoneypotField);
            } else {
                addHoneypotField();
            }
            
            // Проверка времени заполнения формы (боты заполняют слишком быстро)
            var formStartTime = Date.now();
            var minFormTime = 2000; // Минимум 2 секунды
            
            // Отслеживаем начало заполнения формы
            var form = document.getElementById('course-registration-form');
            if (form) {
                form.addEventListener('focusin', function() {
                    formStartTime = Date.now();
                }, true);
            }
            
            // Перехватываем отправку формы для проверки
            if (form) {
                form.addEventListener('submit', function(e) {
                    // Проверка honeypot
                    var honeypotValue = document.getElementById('website_url');
                    if (honeypotValue && honeypotValue.value !== '') {
                        e.preventDefault();
                        alert('Обнаружена автоматическая регистрация. Если вы не бот, обратитесь к администратору.');
                        return false;
                    }
                    
                    // Проверка времени заполнения
                    var formTime = Date.now() - formStartTime;
                    if (formTime < minFormTime) {
                        e.preventDefault();
                        alert('Форма заполнена слишком быстро. Пожалуйста, заполните форму внимательно.');
                        return false;
                    }
                    
                    // Проверка reCAPTCHA (если включена)
                    <?php if (!empty($recaptcha_site_key)): ?>
                    var recaptchaToken = null;
                    var recaptchaSiteKey = '<?php echo esc_js($recaptcha_site_key); ?>';
                    
                    e.preventDefault();
                    
                    // Получаем токен reCAPTCHA
                    if (typeof grecaptcha !== 'undefined') {
                        grecaptcha.ready(function() {
                            grecaptcha.execute(recaptchaSiteKey, {action: 'register'}).then(function(token) {
                                recaptchaToken = token;
                                
                                // Проверяем токен через AJAX
                                var xhr = new XMLHttpRequest();
                                xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>', true);
                                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                                xhr.onreadystatechange = function() {
                                    if (xhr.readyState === 4) {
                                        if (xhr.status === 200) {
                                            try {
                                                var response = JSON.parse(xhr.responseText);
                                                if (response.success) {
                                                    // reCAPTCHA прошла проверку, отправляем форму
                                                    var formData = new FormData(form);
                                                    formData.append('recaptcha_token', recaptchaToken);
                                                    
                                                    // Вызываем оригинальный обработчик формы
                                                    if (typeof jQuery !== 'undefined' && jQuery.fn.ajaxSubmit) {
                                                        jQuery(form).ajaxSubmit({
                                                            url: '<?php echo admin_url('admin-ajax.php'); ?>',
                                                            type: 'POST',
                                                            success: function(response) {
                                                                // Обработка ответа
                                                                if (response.success) {
                                                                    alert(response.data.message);
                                                                    if (response.data.redirect) {
                                                                        window.location.href = response.data.redirect;
                                                                    }
                                                                } else {
                                                                    alert(response.data.message);
                                                                }
                                                            }
                                                        });
                                                    } else {
                                                        // Fallback: обычная отправка формы
                                                        form.submit();
                                                    }
                                                } else {
                                                    alert('Ошибка проверки безопасности. Попробуйте еще раз.');
                                                }
                                            } catch (e) {
                                                console.error('Ошибка обработки ответа reCAPTCHA:', e);
                                                alert('Ошибка проверки безопасности. Попробуйте еще раз.');
                                            }
                                        } else {
                                            alert('Ошибка проверки безопасности. Попробуйте еще раз.');
                                        }
                                    }
                                };
                                xhr.send('action=verify_recaptcha&token=' + encodeURIComponent(token) + '&nonce=<?php echo wp_create_nonce("verify_recaptcha"); ?>');
                            });
                        });
                    } else {
                        console.error('reCAPTCHA не загружена');
                        alert('Ошибка загрузки системы безопасности. Обновите страницу.');
                    }
                    <?php endif; ?>
                });
            }
        })();
        </script>
        <?php
    }
    
    /**
     * AJAX обработчик для проверки reCAPTCHA
     */
    public function ajax_verify_recaptcha() {
        // Проверяем nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'verify_recaptcha')) {
            wp_send_json_error(array('message' => 'Ошибка безопасности'));
        }
        
        $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : '';
        if (empty($token)) {
            wp_send_json_error(array('message' => 'Токен не предоставлен'));
        }
        
        $secret_key = get_option('recaptcha_secret_key', '');
        if (empty($secret_key)) {
            wp_send_json_error(array('message' => 'reCAPTCHA не настроена'));
        }
        
        // Проверяем токен через Google API
        $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', array(
            'body' => array(
                'secret' => $secret_key,
                'response' => $token,
                'remoteip' => $_SERVER['REMOTE_ADDR']
            ),
            'timeout' => 10
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => 'Ошибка при проверке reCAPTCHA'));
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['success']) && $body['success'] === true) {
            // Проверяем score (для reCAPTCHA v3, должно быть >= 0.5)
            $score = isset($body['score']) ? floatval($body['score']) : 1.0;
            if ($score >= 0.5) {
                wp_send_json_success(array('message' => 'Проверка пройдена', 'score' => $score));
            } else {
                wp_send_json_error(array('message' => 'Низкий рейтинг безопасности'));
            }
        } else {
            $error_codes = isset($body['error-codes']) ? implode(', ', $body['error-codes']) : 'неизвестная ошибка';
            wp_send_json_error(array('message' => 'Проверка не пройдена: ' . $error_codes));
        }
    }
    
    /**
     * Проверка защиты от ботов перед регистрацией
     * Вызывается из Course_Registration::process_registration()
     * 
     * @return array|true Массив с ошибкой или true если проверка пройдена
     */
    public static function verify_bot_protection() {
        $anti_bot_enabled = get_option('anti_bot_enabled', true);
        if (!$anti_bot_enabled) {
            return true; // Защита отключена
        }
        
        // Проверка honeypot поля
        if (isset($_POST['website_url']) && !empty($_POST['website_url'])) {
            error_log('Course Anti-Bot: Обнаружен бот (honeypot заполнен)');
            return array('message' => __('Обнаружена автоматическая регистрация.', 'course-plugin'));
        }
        
        // Проверка rate limiting
        $rate_limit_enabled = get_option('rate_limit_enabled', true);
        if ($rate_limit_enabled) {
            $ip = $_SERVER['REMOTE_ADDR'];
            $attempts = get_transient('registration_attempts_' . $ip);
            $max_attempts = get_option('rate_limit_attempts', 5);
            $period = get_option('rate_limit_period', 3600);
            
            if ($attempts !== false && intval($attempts) >= $max_attempts) {
                error_log('Course Anti-Bot: Превышен лимит попыток регистрации для IP: ' . $ip);
                return array('message' => sprintf(__('Превышен лимит попыток регистрации. Попробуйте через %d минут.', 'course-plugin'), round($period / 60)));
            }
            
            // Увеличиваем счетчик попыток
            if ($attempts === false) {
                set_transient('registration_attempts_' . $ip, 1, $period);
            } else {
                set_transient('registration_attempts_' . $ip, intval($attempts) + 1, $period);
            }
        }
        
        // Проверка reCAPTCHA (если токен предоставлен)
        $recaptcha_token = isset($_POST['recaptcha_token']) ? sanitize_text_field($_POST['recaptcha_token']) : '';
        $recaptcha_secret = get_option('recaptcha_secret_key', '');
        
        if (!empty($recaptcha_token) && !empty($recaptcha_secret)) {
            $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', array(
                'body' => array(
                    'secret' => $recaptcha_secret,
                    'response' => $recaptcha_token,
                    'remoteip' => $_SERVER['REMOTE_ADDR']
                ),
                'timeout' => 10
            ));
            
            if (!is_wp_error($response)) {
                $body = json_decode(wp_remote_retrieve_body($response), true);
                if (isset($body['success']) && $body['success'] === true) {
                    $score = isset($body['score']) ? floatval($body['score']) : 1.0;
                    if ($score < 0.5) {
                        error_log('Course Anti-Bot: Низкий рейтинг reCAPTCHA: ' . $score);
                        return array('message' => __('Проверка безопасности не пройдена. Попробуйте еще раз.', 'course-plugin'));
                    }
                } else {
                    error_log('Course Anti-Bot: reCAPTCHA проверка не пройдена');
                    return array('message' => __('Проверка безопасности не пройдена. Попробуйте еще раз.', 'course-plugin'));
                }
            }
        }
        
        return true; // Все проверки пройдены
    }
}

