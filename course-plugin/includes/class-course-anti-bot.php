<?php
/**
 * Класс для защиты регистрации от ботов
 * Использует оригинальные методы защиты без сторонних сервисов
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
        
        // AJAX обработчик для проверки защиты
        add_action('wp_ajax_verify_anti_bot', array($this, 'ajax_verify_anti_bot'));
        add_action('wp_ajax_nopriv_verify_anti_bot', array($this, 'ajax_verify_anti_bot'));
    }
    
    /**
     * Регистрация настроек в админке
     */
    public function register_settings() {
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
        
        register_setting('course_anti_bot_settings', 'math_challenge_enabled', array(
            'type' => 'boolean',
            'default' => true
        ));
        
        register_setting('course_anti_bot_settings', 'behavior_analysis_enabled', array(
            'type' => 'boolean',
            'default' => true
        ));
        
        register_setting('course_anti_bot_settings', 'field_order_check_enabled', array(
            'type' => 'boolean',
            'default' => true
        ));
    }
    
    /**
     * Добавление скриптов и полей защиты в форму регистрации
     */
    public function add_anti_bot_scripts() {
        $anti_bot_enabled = get_option('anti_bot_enabled', true);
        if (!$anti_bot_enabled) {
            return;
        }
        
        $math_enabled = get_option('math_challenge_enabled', true);
        $behavior_enabled = get_option('behavior_analysis_enabled', true);
        $field_order_enabled = get_option('field_order_check_enabled', true);
        
        ?>
        <script type="text/javascript">
        (function() {
            // ============================================
            // 1. HONEYPOT ПОЛЕ (всегда активно)
            // ============================================
            function addHoneypotField() {
                var form = document.getElementById('course-registration-form');
                if (!form) return;
                
                if (document.getElementById('website_url')) return;
                
                var honeypot = document.createElement('input');
                honeypot.type = 'text';
                honeypot.name = 'website_url';
                honeypot.id = 'website_url';
                honeypot.style.cssText = 'display:none !important; visibility:hidden !important; position:absolute !important; left:-9999px !important; opacity:0 !important; height:0 !important; width:0 !important;';
                honeypot.tabIndex = -1;
                honeypot.setAttribute('autocomplete', 'off');
                honeypot.setAttribute('aria-hidden', 'true');
                form.appendChild(honeypot);
            }
            
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', addHoneypotField);
            } else {
                addHoneypotField();
            }
            
            // ============================================
            // 2. МАТЕМАТИЧЕСКАЯ ЗАДАЧА
            // ============================================
            <?php if ($math_enabled): ?>
            var mathChallenge = {
                num1: Math.floor(Math.random() * 10) + 1,
                num2: Math.floor(Math.random() * 10) + 1,
                operator: '+',
                answer: 0
            };
            mathChallenge.answer = mathChallenge.num1 + mathChallenge.num2;
            
            // Сохраняем ответ в sessionStorage
            sessionStorage.setItem('math_answer', mathChallenge.answer);
            
            function addMathChallenge() {
                var form = document.getElementById('course-registration-form');
                if (!form) return;
                
                var mathField = document.getElementById('math_challenge');
                if (mathField) return;
                
                var mathContainer = document.createElement('p');
                mathContainer.id = 'math_challenge_container';
                
                var mathLabel = document.createElement('label');
                mathLabel.setAttribute('for', 'math_challenge');
                mathLabel.innerHTML = '<?php echo esc_js(__('Сколько будет', 'course-plugin')); ?> ' + 
                    mathChallenge.num1 + ' + ' + mathChallenge.num2 + '? <span class="required">*</span>';
                
                var mathInput = document.createElement('input');
                mathInput.type = 'number';
                mathInput.name = 'math_challenge';
                mathInput.id = 'math_challenge';
                mathInput.className = 'input';
                mathInput.required = true;
                mathInput.setAttribute('autocomplete', 'off');
                
                mathContainer.appendChild(mathLabel);
                mathContainer.appendChild(mathInput);
                
                // Вставляем перед кнопкой отправки
                var submitButton = form.querySelector('.submit');
                if (submitButton) {
                    form.insertBefore(mathContainer, submitButton);
                } else {
                    form.appendChild(mathContainer);
                }
            }
            
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', addMathChallenge);
            } else {
                addMathChallenge();
            }
            <?php endif; ?>
            
            // ============================================
            // 3. АНАЛИЗ ПОВЕДЕНИЯ ПОЛЬЗОВАТЕЛЯ
            // ============================================
            <?php if ($behavior_enabled): ?>
            var behaviorData = {
                mouseMovements: 0,
                mouseClicks: 0,
                keyStrokes: 0,
                focusChanges: 0,
                formStartTime: Date.now(),
                lastActivity: Date.now(),
                fieldFocusOrder: [],
                typingPatterns: []
            };
            
            // Отслеживание движений мыши
            document.addEventListener('mousemove', function() {
                behaviorData.mouseMovements++;
                behaviorData.lastActivity = Date.now();
            });
            
            // Отслеживание кликов
            document.addEventListener('click', function() {
                behaviorData.mouseClicks++;
                behaviorData.lastActivity = Date.now();
            });
            
            // Отслеживание нажатий клавиш
            document.addEventListener('keydown', function() {
                behaviorData.keyStrokes++;
                behaviorData.lastActivity = Date.now();
            });
            
            // Отслеживание фокуса на полях формы
            var form = document.getElementById('course-registration-form');
            if (form) {
                var inputs = form.querySelectorAll('input, textarea, select');
                inputs.forEach(function(input, index) {
                    input.addEventListener('focus', function() {
                        behaviorData.focusChanges++;
                        behaviorData.fieldFocusOrder.push(index);
                        behaviorData.lastActivity = Date.now();
                    });
                    
                    // Отслеживание паттернов ввода
                    input.addEventListener('input', function(e) {
                        var now = Date.now();
                        var timeSinceLastKey = now - (behaviorData.typingPatterns[behaviorData.typingPatterns.length - 1] || now);
                        behaviorData.typingPatterns.push(timeSinceLastKey);
                        behaviorData.lastActivity = now;
                        
                        // Ограничиваем размер массива
                        if (behaviorData.typingPatterns.length > 50) {
                            behaviorData.typingPatterns.shift();
                        }
                    });
                });
            }
            <?php endif; ?>
            
            // ============================================
            // 4. ПРОВЕРКА ВРЕМЕНИ ЗАПОЛНЕНИЯ
            // ============================================
            var formStartTime = Date.now();
            var minFormTime = 3000; // Минимум 3 секунды
            
            if (form) {
                var firstInput = form.querySelector('input[type="text"], input[type="email"]');
                if (firstInput) {
                    firstInput.addEventListener('focus', function() {
                        formStartTime = Date.now();
                    }, {once: true});
                }
            }
            
            // ============================================
            // 5. ПРОВЕРКА ПОСЛЕДОВАТЕЛЬНОСТИ ЗАПОЛНЕНИЯ ПОЛЕЙ
            // ============================================
            <?php if ($field_order_enabled): ?>
            var expectedFieldOrder = [0, 1, 2, 3, 4, 5]; // Ожидаемый порядок полей
            var actualFieldOrder = [];
            
            if (form) {
                var inputs = form.querySelectorAll('input[type="text"], input[type="email"], input[type="password"]');
                inputs.forEach(function(input, index) {
                    input.addEventListener('focus', function() {
                        actualFieldOrder.push(index);
                    });
                });
            }
            <?php endif; ?>
            
            // ============================================
            // 6. ПРОВЕРКА JAVASCRIPT ОКРУЖЕНИЯ
            // ============================================
            var jsEnvironment = {
                hasJQuery: typeof jQuery !== 'undefined',
                hasConsole: typeof console !== 'undefined',
                screenWidth: window.screen ? window.screen.width : 0,
                screenHeight: window.screen ? window.screen.height : 0,
                userAgent: navigator.userAgent || '',
                language: navigator.language || '',
                plugins: navigator.plugins ? navigator.plugins.length : 0,
                cookiesEnabled: navigator.cookieEnabled || false
            };
            
            // ============================================
            // ОБРАБОТКА ОТПРАВКИ ФОРМЫ
            // ============================================
            if (form && typeof jQuery !== 'undefined') {
                jQuery(form).on('submit', function(e) {
                    e.preventDefault();
                    
                    var $form = jQuery(this);
                    var $messages = jQuery('#course-registration-messages');
                    var $submit = jQuery('#wp-submit');
                    
                    // Очищаем предыдущие сообщения
                    $messages.html('').removeClass('error success');
                    
                    // Проверка honeypot
                    var honeypotValue = document.getElementById('website_url');
                    if (honeypotValue && honeypotValue.value !== '') {
                        $messages.html('<div class="error">Обнаружена автоматическая регистрация.</div>').addClass('error');
                        return false;
                    }
                    
                    // Проверка времени заполнения
                    var formTime = Date.now() - formStartTime;
                    if (formTime < minFormTime) {
                        $messages.html('<div class="error">Пожалуйста, заполните форму внимательно. Минимальное время заполнения: 3 секунды.</div>').addClass('error');
                        return false;
                    }
                    
                    // Проверка математической задачи
                    <?php if ($math_enabled): ?>
                    var mathAnswer = parseInt(jQuery('#math_challenge').val());
                    var correctAnswer = parseInt(sessionStorage.getItem('math_answer'));
                    if (isNaN(mathAnswer) || mathAnswer !== correctAnswer) {
                        $messages.html('<div class="error">Неверный ответ на математическую задачу. Пожалуйста, решите задачу правильно.</div>').addClass('error');
                        return false;
                    }
                    <?php endif; ?>
                    
                    // Проверка поведения пользователя
                    <?php if ($behavior_enabled): ?>
                    var behaviorScore = 0;
                    
                    // Проверка активности мыши (должно быть хотя бы несколько движений)
                    if (behaviorData.mouseMovements < 5) {
                        behaviorScore -= 10;
                    }
                    
                    // Проверка кликов (должно быть хотя бы несколько кликов)
                    if (behaviorData.mouseClicks < 2) {
                        behaviorScore -= 10;
                    }
                    
                    // Проверка нажатий клавиш (должно быть достаточно для заполнения формы)
                    if (behaviorData.keyStrokes < 10) {
                        behaviorScore -= 10;
                    }
                    
                    // Проверка фокуса на полях (должно быть несколько переходов между полями)
                    if (behaviorData.focusChanges < 3) {
                        behaviorScore -= 10;
                    }
                    
                    // Проверка паттернов ввода (время между нажатиями клавиш должно быть разным)
                    if (behaviorData.typingPatterns.length > 5) {
                        var avgTime = behaviorData.typingPatterns.reduce(function(a, b) { return a + b; }, 0) / behaviorData.typingPatterns.length;
                        var variance = behaviorData.typingPatterns.reduce(function(sum, time) {
                            return sum + Math.pow(time - avgTime, 2);
                        }, 0) / behaviorData.typingPatterns.length;
                        
                        // Если дисперсия слишком мала, значит ввод слишком равномерный (бот)
                        if (variance < 100) {
                            behaviorScore -= 20;
                        }
                    }
                    
                    // Проверка времени бездействия (если форма заполнена слишком быстро без пауз)
                    var timeSinceLastActivity = Date.now() - behaviorData.lastActivity;
                    if (timeSinceLastActivity < 500 && formTime < 5000) {
                        behaviorScore -= 15;
                    }
                    
                    if (behaviorScore < -20) {
                        $messages.html('<div class="error">Обнаружено подозрительное поведение. Пожалуйста, заполните форму как обычный пользователь.</div>').addClass('error');
                        return false;
                    }
                    <?php endif; ?>
                    
                    // Проверка последовательности заполнения полей
                    <?php if ($field_order_enabled): ?>
                    // Боты часто заполняют поля не по порядку или слишком быстро
                    if (actualFieldOrder.length > 0) {
                        var orderScore = 0;
                        for (var i = 0; i < actualFieldOrder.length - 1; i++) {
                            // Проверяем, что поля заполняются последовательно
                            if (Math.abs(actualFieldOrder[i + 1] - actualFieldOrder[i]) > 2) {
                                orderScore -= 5;
                            }
                        }
                        
                        // Если слишком много скачков между полями
                        if (orderScore < -10 && formTime < 5000) {
                            $messages.html('<div class="error">Пожалуйста, заполняйте поля формы последовательно.</div>').addClass('error');
                            return false;
                        }
                    }
                    <?php endif; ?>
                    
                    // Проверка JavaScript окружения
                    // Боты часто имеют подозрительные характеристики
                    if (!jsEnvironment.cookiesEnabled) {
                        $messages.html('<div class="error">В вашем браузере отключены cookies. Пожалуйста, включите их для регистрации.</div>').addClass('error');
                        return false;
                    }
                    
                    // Проверка размера экрана (боты часто имеют нестандартные размеры)
                    if (jsEnvironment.screenWidth > 0 && (jsEnvironment.screenWidth < 200 || jsEnvironment.screenWidth > 10000)) {
                        $messages.html('<div class="error">Обнаружено подозрительное устройство.</div>').addClass('error');
                        return false;
                    }
                    
                    // Отключаем кнопку отправки
                    $submit.prop('disabled', true).val('<?php echo esc_js(__('Проверка безопасности...', 'course-plugin')); ?>');
                    
                    // Подготавливаем данные для отправки
                    var formData = $form.serialize();
                    
                    // Добавляем данные о поведении
                    <?php if ($behavior_enabled): ?>
                    formData += '&behavior_data=' + encodeURIComponent(JSON.stringify({
                        mouseMovements: behaviorData.mouseMovements,
                        mouseClicks: behaviorData.mouseClicks,
                        keyStrokes: behaviorData.keyStrokes,
                        focusChanges: behaviorData.focusChanges,
                        formTime: formTime,
                        fieldOrder: actualFieldOrder,
                        typingPatterns: behaviorData.typingPatterns.slice(-10) // Последние 10 паттернов
                    }));
                    <?php endif; ?>
                    
                    // Добавляем данные об окружении
                    formData += '&js_environment=' + encodeURIComponent(JSON.stringify({
                        screenWidth: jsEnvironment.screenWidth,
                        screenHeight: jsEnvironment.screenHeight,
                        userAgent: jsEnvironment.userAgent,
                        language: jsEnvironment.language,
                        plugins: jsEnvironment.plugins
                    }));
                    
                    // Отправляем форму через AJAX
                    jQuery.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: formData,
                        success: function(response) {
                            if (response.success) {
                                $messages.html('<div class="success">' + response.data.message + '</div>').addClass('success');
                                $form[0].reset();
                                
                                if (response.data.redirect) {
                                    setTimeout(function() {
                                        window.location.href = response.data.redirect;
                                    }, 2000);
                                }
                            } else {
                                $messages.html('<div class="error">' + response.data.message + '</div>').addClass('error');
                                $submit.prop('disabled', false).val('<?php echo esc_js(__('Зарегистрироваться', 'course-plugin')); ?>');
                            }
                        },
                        error: function() {
                            $messages.html('<div class="error">Произошла ошибка. Попробуйте позже.</div>').addClass('error');
                            $submit.prop('disabled', false).val('<?php echo esc_js(__('Зарегистрироваться', 'course-plugin')); ?>');
                        }
                    });
                });
            }
        })();
        </script>
        <?php
    }
    
    /**
     * AJAX обработчик для проверки защиты (если нужен отдельный endpoint)
     */
    public function ajax_verify_anti_bot() {
        // Этот метод можно использовать для дополнительных проверок
        wp_send_json_success(array('message' => 'OK'));
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
        
        // Проверка математической задачи
        $math_enabled = get_option('math_challenge_enabled', true);
        if ($math_enabled) {
            // Ответ проверяется на клиенте, но можно добавить дополнительную проверку на сервере
            // Для этого нужно сохранять правильный ответ в сессии или передавать его через скрытое поле
        }
        
        // Проверка данных о поведении
        $behavior_enabled = get_option('behavior_analysis_enabled', true);
        if ($behavior_enabled && isset($_POST['behavior_data'])) {
            $behavior_data = json_decode(stripslashes($_POST['behavior_data']), true);
            
            if ($behavior_data) {
                // Проверка минимальной активности
                if (isset($behavior_data['mouseMovements']) && $behavior_data['mouseMovements'] < 5) {
                    error_log('Course Anti-Bot: Слишком мало движений мыши: ' . $behavior_data['mouseMovements']);
                    return array('message' => __('Обнаружено подозрительное поведение.', 'course-plugin'));
                }
                
                if (isset($behavior_data['keyStrokes']) && $behavior_data['keyStrokes'] < 10) {
                    error_log('Course Anti-Bot: Слишком мало нажатий клавиш: ' . $behavior_data['keyStrokes']);
                    return array('message' => __('Обнаружено подозрительное поведение.', 'course-plugin'));
                }
                
                if (isset($behavior_data['focusChanges']) && $behavior_data['focusChanges'] < 3) {
                    error_log('Course Anti-Bot: Слишком мало переходов между полями: ' . $behavior_data['focusChanges']);
                    return array('message' => __('Пожалуйста, заполняйте все поля формы.', 'course-plugin'));
                }
                
                // Проверка времени заполнения
                if (isset($behavior_data['formTime']) && $behavior_data['formTime'] < 3000) {
                    error_log('Course Anti-Bot: Форма заполнена слишком быстро: ' . $behavior_data['formTime'] . ' мс');
                    return array('message' => __('Форма заполнена слишком быстро. Пожалуйста, заполните форму внимательно.', 'course-plugin'));
                }
                
                // Проверка паттернов ввода
                if (isset($behavior_data['typingPatterns']) && is_array($behavior_data['typingPatterns']) && count($behavior_data['typingPatterns']) > 5) {
                    $patterns = $behavior_data['typingPatterns'];
                    $avgTime = array_sum($patterns) / count($patterns);
                    $variance = 0;
                    foreach ($patterns as $time) {
                        $variance += pow($time - $avgTime, 2);
                    }
                    $variance = $variance / count($patterns);
                    
                    // Если дисперсия слишком мала, значит ввод слишком равномерный (бот)
                    if ($variance < 100) {
                        error_log('Course Anti-Bot: Подозрительные паттерны ввода. Дисперсия: ' . $variance);
                        return array('message' => __('Обнаружено подозрительное поведение при вводе данных.', 'course-plugin'));
                    }
                }
            }
        }
        
        // Проверка JavaScript окружения
        if (isset($_POST['js_environment'])) {
            $env = json_decode(stripslashes($_POST['js_environment']), true);
            
            if ($env) {
                // Проверка размера экрана
                if (isset($env['screenWidth']) && ($env['screenWidth'] < 200 || $env['screenWidth'] > 10000)) {
                    error_log('Course Anti-Bot: Подозрительный размер экрана: ' . $env['screenWidth']);
                    return array('message' => __('Обнаружено подозрительное устройство.', 'course-plugin'));
                }
                
                // Проверка User-Agent (боты часто имеют пустой или подозрительный UA)
                if (isset($env['userAgent']) && (empty($env['userAgent']) || strlen($env['userAgent']) < 10)) {
                    error_log('Course Anti-Bot: Подозрительный User-Agent: ' . $env['userAgent']);
                    return array('message' => __('Обнаружен подозрительный браузер.', 'course-plugin'));
                }
            }
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
        
        return true; // Все проверки пройдены
    }
}
