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
        
        // Добавляем скрипты и поля в форму регистрации (кастомная форма через шорткод)
        add_action('wp_footer', array($this, 'add_anti_bot_scripts'));
        
        // Добавляем защиту для стандартной формы WordPress (wp-login.php)
        add_action('login_form_register', array($this, 'add_wp_login_scripts'));
        add_action('login_footer', array($this, 'add_wp_login_scripts'));
        
        // Проверка защиты при стандартной регистрации WordPress
        add_filter('registration_errors', array($this, 'validate_wp_registration'), 10, 3);
        
        // AJAX обработчик для получения новой задачи (если пользователь хочет обновить)
        add_action('wp_ajax_get_new_challenge', array($this, 'ajax_get_new_challenge'));
        add_action('wp_ajax_nopriv_get_new_challenge', array($this, 'ajax_get_new_challenge'));
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
     * Получение задач для защиты от ботов
     * Возвращает либо математическую задачу, либо задание с текстом из Евангелия
     * 
     * @return array Массив с данными задачи
     */
    private function get_challenge() {
        // Отрывки из Евангелия от Иоанна с пропущенными словами
        $bible_challenges = array(
            array(
                'text' => 'В начале было Слово, и Слово было у Бога, и Слово было ___',
                'answer' => 'Бог',
                'type' => 'bible'
            ),
            array(
                'text' => 'В Нем была жизнь, и жизнь была свет ___',
                'answer' => 'людей',
                'type' => 'bible'
            ),
            array(
                'text' => 'И Слово стало плотию, и обитало с нами, полное благодати и ___',
                'answer' => 'истины',
                'type' => 'bible'
            ),
            array(
                'text' => 'Иоанн свидетельствует о Нем и, восклицая, говорит: Сей был Тот, о Котором я сказал, что Идущий за мною стал впереди меня, потому что был прежде ___',
                'answer' => 'меня',
                'type' => 'bible'
            ),
            array(
                'text' => 'И от полноты Его все мы приняли и благодать на ___',
                'answer' => 'благодать',
                'type' => 'bible'
            ),
            array(
                'text' => 'Ибо закон дан через Моисея; благодать же и истина произошли через Иисуса ___',
                'answer' => 'Христа',
                'type' => 'bible'
            ),
            array(
                'text' => 'Бога не видел никто никогда; Единородный Сын, сущий в недре Отчем, Он явил ___',
                'answer' => 'Его',
                'type' => 'bible'
            ),
            array(
                'text' => 'Иисус сказал ему в ответ: истинно, истинно говорю тебе, если кто не родится свыше, не может увидеть Царствия ___',
                'answer' => 'Божия',
                'type' => 'bible'
            ),
            array(
                'text' => 'Ибо так возлюбил Бог мир, что отдал Сына Своего Единородного, дабы всякий верующий в Него, не погиб, но имел жизнь ___',
                'answer' => 'вечную',
                'type' => 'bible'
            ),
            array(
                'text' => 'Иисус сказал ей: Я есмь воскресение и жизнь; верующий в Меня, если и умрет, оживет. И всякий, живущий и верующий в Меня, не умрет ___',
                'answer' => 'во век',
                'type' => 'bible'
            ),
        );
        
        // Случайно выбираем тип задачи (50% математика, 50% текст)
        $use_math = (rand(1, 100) <= 50);
        
        if ($use_math) {
            // Математическая задача
            $num1 = rand(1, 10);
            $num2 = rand(1, 10);
            return array(
                'type' => 'math',
                'question' => sprintf(__('Сколько будет %d + %d?', 'course-plugin'), $num1, $num2),
                'answer' => $num1 + $num2,
                'input_type' => 'number'
            );
        } else {
            // Задание с текстом из Евангелия
            $challenge = $bible_challenges[array_rand($bible_challenges)];
            return array(
                'type' => 'bible',
                'question' => sprintf(__('Вставьте пропущенное слово: %s', 'course-plugin'), $challenge['text']),
                'answer' => strtolower(trim($challenge['answer'])),
                'input_type' => 'text'
            );
        }
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
        
        if (!$math_enabled) {
            return; // Если защита отключена, не добавляем скрипты
        }
        
        // Генерируем задачу
        $challenge = $this->get_challenge();
        
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
            // 2. ЗАДАЧА ДЛЯ ЗАЩИТЫ (математика или текст)
            // ============================================
            var challenge = <?php echo json_encode($challenge); ?>;
            
            // Сохраняем правильный ответ в sessionStorage
            sessionStorage.setItem('challenge_answer', challenge.answer.toLowerCase().trim());
            sessionStorage.setItem('challenge_type', challenge.type);
            
            function addChallenge() {
                var form = document.getElementById('course-registration-form');
                if (!form) return;
                
                var challengeField = document.getElementById('anti_bot_challenge');
                if (challengeField) return;
                
                var challengeContainer = document.createElement('p');
                challengeContainer.id = 'challenge_container';
                challengeContainer.style.marginBottom = '15px';
                
                var challengeLabel = document.createElement('label');
                challengeLabel.setAttribute('for', 'anti_bot_challenge');
                challengeLabel.style.display = 'block';
                challengeLabel.style.marginBottom = '5px';
                challengeLabel.style.fontWeight = 'bold';
                challengeLabel.innerHTML = challenge.question + ' <span class="required">*</span>';
                
                var challengeInput = document.createElement('input');
                challengeInput.type = challenge.input_type;
                challengeInput.name = 'anti_bot_challenge';
                challengeInput.id = 'anti_bot_challenge';
                challengeInput.className = 'input';
                challengeInput.required = true;
                challengeInput.setAttribute('autocomplete', 'off');
                challengeInput.style.width = '100%';
                challengeInput.style.padding = '8px';
                challengeInput.style.border = '1px solid #ddd';
                challengeInput.style.borderRadius = '3px';
                
                // Для текстовых заданий добавляем подсказку
                if (challenge.type === 'bible') {
                    challengeInput.placeholder = '<?php echo esc_js(__('Введите пропущенное слово', 'course-plugin')); ?>';
                }
                
                challengeContainer.appendChild(challengeLabel);
                challengeContainer.appendChild(challengeInput);
                
                // Добавляем кнопку "Обновить задачу" для текстовых заданий
                if (challenge.type === 'bible') {
                    var refreshButton = document.createElement('button');
                    refreshButton.type = 'button';
                    refreshButton.className = 'button';
                    refreshButton.style.marginTop = '5px';
                    refreshButton.innerHTML = '<?php echo esc_js(__('Обновить задачу', 'course-plugin')); ?>';
                    refreshButton.onclick = function() {
                        // Загружаем новую задачу через AJAX
                        jQuery.ajax({
                            url: '<?php echo admin_url('admin-ajax.php'); ?>',
                            type: 'POST',
                            data: {
                                action: 'get_new_challenge'
                            },
                                    success: function(response) {
                                        if (response.success && response.data) {
                                            var newChallenge = response.data;
                                            
                                            // Преобразуем ответ в строку перед сохранением (для математических задач answer - число)
                                            var answerStr = String(newChallenge.answer || '');
                                            sessionStorage.setItem('challenge_answer', answerStr.toLowerCase().trim());
                                            sessionStorage.setItem('challenge_type', newChallenge.type || '');
                                            
                                            if (challengeLabel && newChallenge.question) {
                                                challengeLabel.innerHTML = newChallenge.question + ' <span class="required">*</span>';
                                            }
                                            
                                            if (challengeInput) {
                                                challengeInput.value = '';
                                                challengeInput.type = newChallenge.input_type || 'text';
                                                if (newChallenge.type === 'bible') {
                                                    challengeInput.placeholder = '<?php echo esc_js(__('Введите пропущенное слово', 'course-plugin')); ?>';
                                                } else {
                                                    challengeInput.placeholder = '';
                                                }
                                            }
                                        } else {
                                            alert('<?php echo esc_js(__('Не удалось загрузить новую задачу. Попробуйте обновить страницу.', 'course-plugin')); ?>');
                                        }
                                    },
                                    error: function() {
                                        alert('<?php echo esc_js(__('Произошла ошибка при загрузке новой задачи.', 'course-plugin')); ?>');
                                    }
                        });
                    };
                    challengeContainer.appendChild(refreshButton);
                }
                
                // Вставляем перед кнопкой отправки
                var submitButton = form.querySelector('.submit');
                if (submitButton) {
                    form.insertBefore(challengeContainer, submitButton);
                } else {
                    form.appendChild(challengeContainer);
                }
            }
            
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', addChallenge);
            } else {
                addChallenge();
            }
            
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
                    
                    // Проверка задачи (математика или текст)
                    var challengeAnswer = jQuery('#anti_bot_challenge').val();
                    var correctAnswer = sessionStorage.getItem('challenge_answer');
                    var challengeType = sessionStorage.getItem('challenge_type');
                    
                    if (!challengeAnswer || challengeAnswer === '') {
                        $messages.html('<div class="error">Пожалуйста, решите задачу для защиты от ботов.</div>').addClass('error');
                        return false;
                    }
                    
                    // Нормализуем ответ (убираем пробелы, приводим к нижнему регистру)
                    var normalizedAnswer = challengeAnswer.toString().toLowerCase().trim();
                    var normalizedCorrect = correctAnswer.toLowerCase().trim();
                    
                    if (challengeType === 'math') {
                        // Для математических задач сравниваем числа
                        var userAnswer = parseInt(normalizedAnswer);
                        var correctNum = parseInt(normalizedCorrect);
                        if (isNaN(userAnswer) || userAnswer !== correctNum) {
                            $messages.html('<div class="error">Неверный ответ на задачу. Пожалуйста, решите задачу правильно.</div>').addClass('error');
                            return false;
                        }
                    } else {
                        // Для текстовых заданий сравниваем строки (с учетом возможных вариантов написания)
                        // Разрешаем варианты с разными окончаниями или близкие по смыслу
                        var answerVariants = normalizedCorrect.split(' ');
                        var userWords = normalizedAnswer.split(' ');
                        
                        // Проверяем, содержит ли ответ пользователя правильное слово
                        var isCorrect = false;
                        for (var i = 0; i < answerVariants.length; i++) {
                            for (var j = 0; j < userWords.length; j++) {
                                // Сравниваем слова (учитываем возможные окончания)
                                if (userWords[j].indexOf(answerVariants[i]) === 0 || answerVariants[i].indexOf(userWords[j]) === 0) {
                                    isCorrect = true;
                                    break;
                                }
                            }
                            if (isCorrect) break;
                        }
                        
                        // Также проверяем точное совпадение
                        if (!isCorrect && normalizedAnswer === normalizedCorrect) {
                            isCorrect = true;
                        }
                        
                        if (!isCorrect) {
                            $messages.html('<div class="error">Неверный ответ. Пожалуйста, внимательно прочитайте задание и вставьте правильное слово.</div>').addClass('error');
                            return false;
                        }
                    }
                    
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
     * AJAX обработчик для получения новой задачи
     */
    public function ajax_get_new_challenge() {
        $challenge = $this->get_challenge();
        wp_send_json_success($challenge);
    }
    
    /**
     * Добавление защиты для стандартной формы WordPress (wp-login.php)
     */
    public function add_wp_login_scripts() {
        // Проверяем, что мы на странице регистрации
        if (!isset($_GET['action']) || $_GET['action'] !== 'register') {
            return;
        }
        
        $anti_bot_enabled = get_option('anti_bot_enabled', true);
        if (!$anti_bot_enabled) {
            return;
        }
        
        $math_enabled = get_option('math_challenge_enabled', true);
        if (!$math_enabled) {
            return;
        }
        
        // Генерируем задачу
        $challenge = $this->get_challenge();
        
        ?>
        <script type="text/javascript">
        (function() {
            // Ждем загрузки DOM
            function initProtection() {
                var form = document.getElementById('registerform');
                if (!form) {
                    // Если форма еще не загружена, пробуем еще раз через небольшую задержку
                    setTimeout(initProtection, 100);
                    return;
                }
                
                // Добавляем honeypot поле
                if (!document.getElementById('website_url')) {
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
                
                // Добавляем поле с задачей
                if (!document.getElementById('anti_bot_challenge')) {
                    var challenge = <?php echo json_encode($challenge); ?>;
                    
                    // Сохраняем правильный ответ
                    sessionStorage.setItem('challenge_answer', challenge.answer.toLowerCase().trim());
                    sessionStorage.setItem('challenge_type', challenge.type);
                    
                    var challengeContainer = document.createElement('p');
                    challengeContainer.id = 'challenge_container';
                    challengeContainer.style.marginBottom = '15px';
                    
                    var challengeLabel = document.createElement('label');
                    challengeLabel.setAttribute('for', 'anti_bot_challenge');
                    challengeLabel.style.display = 'block';
                    challengeLabel.style.marginBottom = '5px';
                    challengeLabel.style.fontWeight = 'bold';
                    challengeLabel.innerHTML = challenge.question + ' <span style="color: #dc3232;">*</span>';
                    
                    var challengeInput = document.createElement('input');
                    challengeInput.type = challenge.input_type;
                    challengeInput.name = 'anti_bot_challenge';
                    challengeInput.id = 'anti_bot_challenge';
                    challengeInput.className = 'input';
                    challengeInput.required = true;
                    challengeInput.setAttribute('autocomplete', 'off');
                    challengeInput.style.width = '100%';
                    challengeInput.style.padding = '8px';
                    challengeInput.style.border = '1px solid #ddd';
                    challengeInput.style.borderRadius = '3px';
                    challengeInput.style.marginTop = '5px';
                    
                    if (challenge.type === 'bible') {
                        challengeInput.placeholder = '<?php echo esc_js(__('Введите пропущенное слово', 'course-plugin')); ?>';
                    }
                    
                    challengeContainer.appendChild(challengeLabel);
                    challengeContainer.appendChild(challengeInput);
                    
                    // Добавляем кнопку обновления для текстовых заданий
                    if (challenge.type === 'bible') {
                        var refreshButton = document.createElement('button');
                        refreshButton.type = 'button';
                        refreshButton.className = 'button';
                        refreshButton.style.marginTop = '5px';
                        refreshButton.innerHTML = '<?php echo esc_js(__('Обновить задачу', 'course-plugin')); ?>';
                        refreshButton.onclick = function() {
                            // Отключаем кнопку на время загрузки
                            refreshButton.disabled = true;
                            refreshButton.innerHTML = '<?php echo esc_js(__('Загрузка...', 'course-plugin')); ?>';
                            
                            // Используем нативный Fetch API вместо jQuery
                            var formData = new FormData();
                            formData.append('action', 'get_new_challenge');
                            
                            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                                method: 'POST',
                                body: formData
                            })
                            .then(function(response) {
                                return response.json();
                            })
                            .then(function(data) {
                                if (data.success && data.data) {
                                    var newChallenge = data.data;
                                    
                                    // Преобразуем ответ в строку перед сохранением (для математических задач answer - число)
                                    var answerStr = String(newChallenge.answer || '');
                                    sessionStorage.setItem('challenge_answer', answerStr.toLowerCase().trim());
                                    sessionStorage.setItem('challenge_type', newChallenge.type || '');
                                    
                                    if (challengeLabel && newChallenge.question) {
                                        challengeLabel.innerHTML = newChallenge.question + ' <span style="color: #dc3232;">*</span>';
                                    }
                                    
                                    if (challengeInput) {
                                        challengeInput.value = '';
                                        challengeInput.type = newChallenge.input_type || 'text';
                                        if (newChallenge.type === 'bible') {
                                            challengeInput.placeholder = '<?php echo esc_js(__('Введите пропущенное слово', 'course-plugin')); ?>';
                                        } else {
                                            challengeInput.placeholder = '';
                                        }
                                    }
                                } else {
                                    alert('<?php echo esc_js(__('Не удалось загрузить новую задачу. Попробуйте обновить страницу.', 'course-plugin')); ?>');
                                }
                            })
                            .catch(function(error) {
                                console.error('Ошибка при загрузке новой задачи:', error);
                                alert('<?php echo esc_js(__('Произошла ошибка. Попробуйте обновить страницу.', 'course-plugin')); ?>');
                            })
                            .finally(function() {
                                // Включаем кнопку обратно
                                refreshButton.disabled = false;
                                refreshButton.innerHTML = '<?php echo esc_js(__('Обновить задачу', 'course-plugin')); ?>';
                            });
                        };
                        challengeContainer.appendChild(refreshButton);
                    }
                    
                    // Вставляем перед кнопкой отправки
                    var submitButton = form.querySelector('input[type="submit"]');
                    if (submitButton && submitButton.parentNode) {
                        submitButton.parentNode.insertBefore(challengeContainer, submitButton);
                    } else {
                        form.appendChild(challengeContainer);
                    }
                }
                
                // Перехватываем отправку формы
                form.addEventListener('submit', function(e) {
                    // Проверка honeypot
                    var honeypotValue = document.getElementById('website_url');
                    if (honeypotValue && honeypotValue.value !== '') {
                        e.preventDefault();
                        alert('<?php echo esc_js(__('Обнаружена автоматическая регистрация.', 'course-plugin')); ?>');
                        return false;
                    }
                    
                    // Проверка задачи
                    var challengeAnswer = document.getElementById('anti_bot_challenge');
                    if (!challengeAnswer || !challengeAnswer.value) {
                        e.preventDefault();
                        alert('<?php echo esc_js(__('Пожалуйста, решите задачу для защиты от ботов.', 'course-plugin')); ?>');
                        challengeAnswer.focus();
                        return false;
                    }
                    
                    var correctAnswer = sessionStorage.getItem('challenge_answer');
                    var challengeType = sessionStorage.getItem('challenge_type');
                    var normalizedAnswer = challengeAnswer.value.toString().toLowerCase().trim();
                    var normalizedCorrect = correctAnswer ? correctAnswer.toLowerCase().trim() : '';
                    
                    if (!normalizedCorrect) {
                        e.preventDefault();
                        alert('<?php echo esc_js(__('Ошибка проверки. Пожалуйста, обновите страницу и попробуйте снова.', 'course-plugin')); ?>');
                        return false;
                    }
                    
                    if (challengeType === 'math') {
                        var userAnswer = parseInt(normalizedAnswer);
                        var correctNum = parseInt(normalizedCorrect);
                        if (isNaN(userAnswer) || userAnswer !== correctNum) {
                            e.preventDefault();
                            alert('<?php echo esc_js(__('Неверный ответ на задачу. Пожалуйста, решите задачу правильно.', 'course-plugin')); ?>');
                            challengeAnswer.focus();
                            challengeAnswer.select();
                            return false;
                        }
                    } else if (challengeType === 'bible') {
                        var answerVariants = normalizedCorrect.split(' ');
                        var userWords = normalizedAnswer.split(' ');
                        var isCorrect = false;
                        
                        // Проверяем точное совпадение
                        if (normalizedAnswer === normalizedCorrect) {
                            isCorrect = true;
                        } else {
                            // Проверяем частичное совпадение
                            for (var i = 0; i < answerVariants.length; i++) {
                                for (var j = 0; j < userWords.length; j++) {
                                    if (userWords[j].indexOf(answerVariants[i]) === 0 || answerVariants[i].indexOf(userWords[j]) === 0) {
                                        isCorrect = true;
                                        break;
                                    }
                                }
                                if (isCorrect) break;
                            }
                        }
                        
                        if (!isCorrect) {
                            e.preventDefault();
                            alert('<?php echo esc_js(__('Неверный ответ. Пожалуйста, внимательно прочитайте задание и вставьте правильное слово.', 'course-plugin')); ?>');
                            challengeAnswer.focus();
                            challengeAnswer.select();
                            return false;
                        }
                    }
                    
                    // Добавляем тип задачи в форму для серверной проверки
                    var challengeTypeInput = document.createElement('input');
                    challengeTypeInput.type = 'hidden';
                    challengeTypeInput.name = 'challenge_type';
                    challengeTypeInput.value = challengeType;
                    form.appendChild(challengeTypeInput);
                });
            }
            
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initProtection);
            } else {
                initProtection();
            }
        })();
        </script>
        <?php
    }
    
    /**
     * Проверка защиты при стандартной регистрации WordPress
     * 
     * @param WP_Error $errors Объект ошибок WordPress
     * @param string $sanitized_user_login Очищенный логин пользователя
     * @param string $user_email Email пользователя
     * @return WP_Error Объект ошибок с добавленными ошибками защиты
     */
    public function validate_wp_registration($errors, $sanitized_user_login, $user_email) {
        // Проверка защиты от ботов
        $bot_check = self::verify_bot_protection();
        if ($bot_check !== true) {
            $error_message = isset($bot_check['message']) ? $bot_check['message'] : __('Обнаружена автоматическая регистрация.', 'course-plugin');
            $errors->add('anti_bot_failed', $error_message);
        }
        
        return $errors;
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
        
        // Проверка задачи (математика или текст)
        $math_enabled = get_option('math_challenge_enabled', true);
        if ($math_enabled && isset($_POST['anti_bot_challenge'])) {
            $user_answer = isset($_POST['anti_bot_challenge']) ? trim(strtolower($_POST['anti_bot_challenge'])) : '';
            $challenge_type = isset($_POST['challenge_type']) ? sanitize_text_field($_POST['challenge_type']) : '';
            
            if (empty($user_answer)) {
                error_log('Course Anti-Bot: Ответ на задачу не предоставлен');
                return array('message' => __('Пожалуйста, решите задачу для защиты от ботов.', 'course-plugin'));
            }
            
            // Базовая валидация формата ответа
            if ($challenge_type === 'math') {
                // Для математических задач проверяем, что это число
                if (!is_numeric($user_answer)) {
                    error_log('Course Anti-Bot: Неверный формат ответа на математическую задачу');
                    return array('message' => __('Неверный ответ на задачу.', 'course-plugin'));
                }
            } else {
                // Для текстовых заданий проверяем, что это не пустая строка
                if (strlen($user_answer) < 2) {
                    error_log('Course Anti-Bot: Ответ слишком короткий');
                    return array('message' => __('Пожалуйста, введите правильное слово.', 'course-plugin'));
                }
            }
        } else if ($math_enabled && !isset($_POST['anti_bot_challenge'])) {
            // Если защита включена, но ответ не предоставлен
            error_log('Course Anti-Bot: Задача не решена');
            return array('message' => __('Пожалуйста, решите задачу для защиты от ботов.', 'course-plugin'));
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
