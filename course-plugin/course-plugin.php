<?php
/**
 * Plugin Name: Курсы Про
 * Plugin URI: https://github.com/ValentinK2410/course_wp
 * Description: Плагин для управления курсами с возможностью добавления, редактирования и удаления курсов. Включает разделы: специализация и программы, уровень образования, тема, преподаватель.
 * Version: 1.3.2
 * Author: Кузьменко Валентин (Valentink2410)
 * Author URI: https://github.com/ValentinK2410
 * Copyright: Copyright (c) 2024 Кузьменко Валентин (Valentink2410)
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: course-plugin
 * Domain Path: /languages
 */

// Проверка безопасности: если файл вызывается напрямую (не через WordPress), прекращаем выполнение
// ABSPATH - константа WordPress, которая определена только при загрузке через WordPress
if (!defined('ABSPATH')) {
    exit;
}

// Определяем константы плагина для использования в других файлах
// COURSE_PLUGIN_VERSION - версия плагина для версионирования стилей и скриптов
define('COURSE_PLUGIN_VERSION', '1.3.2');

// COURSE_PLUGIN_DIR - абсолютный путь к директории плагина (например: /var/www/wp-content/plugins/course-plugin/)
define('COURSE_PLUGIN_DIR', plugin_dir_path(__FILE__));

// COURSE_PLUGIN_URL - URL путь к директории плагина (например: https://site.com/wp-content/plugins/course-plugin/)
define('COURSE_PLUGIN_URL', plugin_dir_url(__FILE__));

// COURSE_PLUGIN_BASENAME - базовое имя плагина (например: course-plugin/course-plugin.php)
define('COURSE_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Основной класс плагина
 * Использует паттерн Singleton для обеспечения единственного экземпляра класса
 */
class Course_Plugin {
    
    /**
     * Единственный экземпляр класса (статическое свойство)
     * Хранит объект класса, если он уже был создан
     */
    private static $instance = null;
    
    /**
     * Получить экземпляр класса (Singleton)
     * Если экземпляр еще не создан, создает его и возвращает
     * Если уже создан, возвращает существующий экземпляр
     * 
     * @return Course_Plugin Экземпляр класса
     */
    public static function get_instance() {
        // Проверяем, создан ли уже экземпляр класса
        if (null === self::$instance) {
            // Создаем новый экземпляр класса
            self::$instance = new self();
        }
        // Возвращаем существующий или только что созданный экземпляр
        return self::$instance;
    }
    
    /**
     * Конструктор класса
     * Приватный, чтобы предотвратить создание экземпляра напрямую (только через get_instance)
     * Автоматически вызывает метод init() при создании объекта
     */
    private function __construct() {
        $this->init();
    }
    
    /**
     * Инициализация плагина
     * Выполняется при создании экземпляра класса
     * Регистрирует все необходимые хуки WordPress
     */
    private function init() {
        // Подключаем файлы с классами плагина (Custom Post Type, таксономии, админка и т.д.)
        $this->includes();
        
        // Регистрируем функцию активации плагина
        // Вызывается при активации плагина в админ-панели WordPress
        register_activation_hook(__FILE__, array($this, 'activate'));
        
        // Регистрируем функцию деактивации плагина
        // Вызывается при деактивации плагина в админ-панели WordPress
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Регистрируем загрузку компонентов на хук 'init'
        // Хук 'init' срабатывает после загрузки WordPress, но до отправки заголовков
        // Это правильное место для регистрации типов постов и таксономий
        add_action('init', array($this, 'load_components'));
        
        // Создание терминов "Уровень сложности" после регистрации таксономий (приоритет 999)
        add_action('init', array($this, 'maybe_ensure_default_level_terms'), 999);

        // Одноразовая миграция: установить галочку "Не обновлять из Moodle" во всех курсах (приоритет 1000)
        add_action('init', array($this, 'maybe_set_exclude_moodle_default'), 1000);
        
        // Регистрируем загрузку текстового домена для переводов
        // Хук 'plugins_loaded' срабатывает после загрузки всех плагинов
        // Это нужно для правильной загрузки переводов
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Добавляем поддержку Elementor для типов постов курсов и программ
        add_action('elementor/init', array($this, 'add_elementor_support'));
    }
    
    /**
     * Добавление поддержки Elementor для типов постов курсов и программ
     */
    public function add_elementor_support() {
        // Добавляем поддержку для типа поста "course"
        add_filter('elementor_pro/utils/get_public_post_types', function($post_types) {
            if (!in_array('course', $post_types)) {
                $post_types[] = 'course';
            }
            return $post_types;
        });
        
        // Добавляем поддержку для типа поста "program"
        add_filter('elementor_pro/utils/get_public_post_types', function($post_types) {
            if (!in_array('program', $post_types)) {
                $post_types[] = 'program';
            }
            return $post_types;
        });
        
        // Для бесплатной версии Elementor используем другой фильтр
        add_filter('elementor/utils/get_public_post_types', function($post_types) {
            if (!in_array('course', $post_types)) {
                $post_types[] = 'course';
            }
            if (!in_array('program', $post_types)) {
                $post_types[] = 'program';
            }
            return $post_types;
        });
    }
    
    /**
     * Подключение файлов классов плагина
     * Загружает все необходимые классы перед их использованием
     * Проверяет существование файлов перед подключением
     */
    private function includes() {
        // Массив путей к файлам классов относительно директории плагина
        $files = array(
            'includes/class-course-logger.php',        // Класс для логирования (должен быть первым)
            'includes/class-course-post-type.php',      // Класс для регистрации Custom Post Type "Курсы"
            'includes/class-program-post-type.php',     // Класс для регистрации Custom Post Type "Программы"
            'includes/class-course-taxonomies.php',    // Класс для регистрации таксономий (специализация, уровень, тема, преподаватель)
            'includes/class-course-admin.php',          // Класс для административного интерфейса (колонки, фильтры, дублирование)
            'includes/class-program-admin.php',        // Класс для административного интерфейса программ
            'includes/class-course-meta-boxes.php',    // Класс для метабоксов с дополнительными полями курсов
            'includes/class-program-meta-boxes.php',   // Класс для метабоксов с дополнительными полями программ
            'includes/class-course-frontend.php',       // Класс для фронтенда (шаблоны, фильтры, стили)
            'includes/class-program-frontend.php',      // Класс для фронтенда программ
            'includes/class-course-teacher-meta.php',  // Класс для метаполей преподавателей (фото, описание и т.д.)
            'includes/class-course-moodle-api.php',    // Класс для работы с Moodle REST API
            'includes/class-course-moodle-sync.php',   // Класс для синхронизации данных из Moodle в WordPress
            'includes/class-course-transliteration.php', // Класс для транслитерации кириллицы в латиницу
            'includes/class-course-moodle-user-sync.php', // Класс для синхронизации пользователей между WordPress и Moodle
            'includes/class-course-registration.php',   // Класс для формы регистрации пользователей
            'includes/class-course-sso.php',             // Класс для Single Sign-On (SSO)
            'includes/class-course-anti-bot.php',        // Класс для защиты от ботов
            'includes/class-course-anti-bot-admin.php',  // Класс для админ-панели защиты от ботов
            'includes/class-course-email-sender.php',    // Класс для улучшенной отправки email (SMTP поддержка)
            'includes/class-course-email-admin.php',    // Класс для админ-панели настроек email (SMTP)
        );
        
        // Проходим по каждому файлу в массиве
        foreach ($files as $file) {
            // Формируем полный путь к файлу
            $file_path = COURSE_PLUGIN_DIR . $file;
            
            // Проверяем, существует ли файл
            if (file_exists($file_path)) {
                // Подключаем файл (require_once гарантирует, что файл будет подключен только один раз)
                require_once $file_path;
            } else {
                // Если файл не найден и включен режим отладки WordPress, записываем ошибку в лог
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Course Plugin: Файл не найден - ' . $file_path);
                }
            }
        }
    }
    
    /**
     * Загрузка компонентов плагина
     * Инициализирует все классы плагина после их загрузки
     * Вызывается на хуке 'init'
     */
    public function load_components() {
        // Проверяем, что класс Course_Post_Type загружен
        // Если класс не найден, выходим из функции (плагин не будет работать)
        if (!class_exists('Course_Post_Type')) {
            // Если включен режим отладки, записываем ошибку в лог
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Course Plugin: Класс Course_Post_Type не найден');
            }
            return; // Прекращаем выполнение функции
        }
        
        // Инициализируем Custom Post Type "Курсы"
        // Вызываем статический метод get_instance() для создания/получения экземпляра класса
        Course_Post_Type::get_instance();
        
        // Инициализируем Custom Post Type "Программы"
        if (class_exists('Program_Post_Type')) {
            Program_Post_Type::get_instance();
        }
        
        // Инициализируем таксономии (специализация, уровень, тема, преподаватель)
        // Проверяем существование класса перед инициализацией
        if (class_exists('Course_Taxonomies')) {
            Course_Taxonomies::get_instance();
        }
        
        // Инициализируем административный интерфейс
        // Добавляет колонки в список курсов, фильтры, функцию дублирования
        if (class_exists('Course_Admin')) {
            Course_Admin::get_instance();
        }
        
        // Инициализируем административный интерфейс программ
        if (class_exists('Program_Admin')) {
            Program_Admin::get_instance();
        }
        
        // Инициализируем метабоксы
        // Добавляет дополнительные поля при редактировании курса
        if (class_exists('Course_Meta_Boxes')) {
            Course_Meta_Boxes::get_instance();
        }
        
        // Инициализируем метабоксы программ
        if (class_exists('Program_Meta_Boxes')) {
            Program_Meta_Boxes::get_instance();
        }
        
        // Инициализируем фронтенд
        // Загружает шаблоны, стили, скрипты для отображения курсов на сайте
        if (class_exists('Course_Frontend')) {
            Course_Frontend::get_instance();
        }
        
        // Инициализируем фронтенд программ
        if (class_exists('Program_Frontend')) {
            Program_Frontend::get_instance();
        }
        
        // Инициализируем метаполя преподавателей
        // Добавляет поля для фото, описания и контактов преподавателя
        if (class_exists('Course_Teacher_Meta')) {
            Course_Teacher_Meta::get_instance();
        }
        
        // Инициализируем синхронизацию с Moodle
        // Добавляет страницу настроек и автоматическую синхронизацию курсов из Moodle
        if (class_exists('Course_Moodle_Sync')) {
            Course_Moodle_Sync::get_instance();
        }
        
        // Инициализируем транслитерацию кириллицы в латиницу
        // Автоматически переводит кириллические символы в латиницу при создании курсов и категорий
        if (class_exists('Course_Transliteration')) {
            Course_Transliteration::get_instance();
        }
        
        // Инициализируем синхронизацию пользователей с Moodle
        // Автоматически создает и обновляет пользователей в Moodle при регистрации и изменении профиля в WordPress
        if (class_exists('Course_Moodle_User_Sync')) {
            Course_Moodle_User_Sync::get_instance();
        }
        
        // Инициализируем форму регистрации
        // Добавляет шорткод [course_register] для отображения формы регистрации на любой странице
        if (class_exists('Course_Registration')) {
            Course_Registration::get_instance();
        }
        
        // Инициализируем Single Sign-On (SSO)
        // Позволяет пользователям автоматически входить в Moodle и Laravel после входа в WordPress
        if (class_exists('Course_SSO')) {
            Course_SSO::get_instance();
        }
        
        // Инициализируем защиту от ботов
        // Добавляет методы защиты от автоматических регистраций (honeypot, математические задачи, анализ поведения)
        if (class_exists('Course_Anti_Bot')) {
            Course_Anti_Bot::get_instance();
        }
        
        // Инициализируем админ-панель защиты от ботов
        // Добавляет страницу настроек "Настройки → Защита от ботов"
        if (class_exists('Course_Anti_Bot_Admin')) {
            Course_Anti_Bot_Admin::get_instance();
        }
        
        // Инициализируем админ-панель настроек email (SMTP)
        // Добавляет страницу настроек "Настройки → Email (SMTP)"
        if (class_exists('Course_Email_Admin')) {
            Course_Email_Admin::get_instance();
        }
    }
    
    /**
     * Активация плагина
     * Вызывается при активации плагина в админ-панели WordPress
     * Регистрирует типы постов и таксономии, сбрасывает правила перезаписи URL
     */
    public function activate() {
        // Подключаем файлы классов перед активацией
        // Это необходимо, чтобы классы были доступны для регистрации
        $this->includes();
        
        // Регистрируем типы постов и таксономии
        // Вызываем метод load_components() для инициализации всех компонентов
        $this->load_components();
        
        // Отмечаем, что нужно создать термины при следующем init (таксономии регистрируются на init)
        update_option('course_plugin_ensure_level_terms', 1);
        
        // Регистрируем правило для /teachers/ до flush
        if (class_exists('Course_Frontend')) {
            Course_Frontend::get_instance()->add_teachers_rewrite_rule();
        }
        
        // Сбрасываем правила перезаписи URL (rewrite rules)
        // Это необходимо для правильной работы постоянных ссылок (permalink) для курсов
        // После активации плагина URL /courses/ и /teachers/ будут работать корректно
        flush_rewrite_rules();
    }
    
    /**
     * Создаёт термины таксономии "Уровень сложности" (course_level), если их ещё нет.
     * Вызывается на init с приоритетом 999 после регистрации таксономий.
     */
    public function maybe_ensure_default_level_terms() {
        if (!get_option('course_plugin_ensure_level_terms')) {
            return;
        }
        delete_option('course_plugin_ensure_level_terms');
        if (!taxonomy_exists('course_level')) {
            return;
        }
        $default_terms = array(
            'bakalavrskiy'   => __('Бакалаврский', 'course-plugin'),
            'diplomnyy'      => __('Дипломный', 'course-plugin'),
            'magistrskiy'    => __('Магистерский', 'course-plugin'),
            'nachalnyy'      => __('Начальный', 'course-plugin'),
        );
        $existing = get_terms(array('taxonomy' => 'course_level', 'hide_empty' => false, 'fields' => 'names'));
        if (is_wp_error($existing) || !is_array($existing)) {
            $existing = array();
        }
        foreach ($default_terms as $slug => $name) {
            if (!term_exists($slug, 'course_level') && !in_array($name, $existing)) {
                wp_insert_term($name, 'course_level', array('slug' => $slug));
            }
        }
    }

    /**
     * Одноразовая миграция: устанавливает _exclude_from_moodle_sync = '1' во всех курсах,
     * у которых это поле ещё не задано. Чекбокс «Не обновлять из Moodle» будет отмечен по умолчанию.
     */
    public function maybe_set_exclude_moodle_default() {
        if (get_option('course_plugin_exclude_moodle_default_done')) {
            return;
        }
        $courses = get_posts(array(
            'post_type'   => 'course',
            'numberposts' => -1,
            'post_status' => 'any',
            'fields'      => 'ids',
        ));
        foreach ($courses as $post_id) {
            $current = get_post_meta($post_id, '_exclude_from_moodle_sync', true);
            if ($current === '') {
                update_post_meta($post_id, '_exclude_from_moodle_sync', '1');
            }
        }
        update_option('course_plugin_exclude_moodle_default_done', 1);
    }

    /**
     * Деактивация плагина
     * Вызывается при деактивации плагина в админ-панели WordPress
     * Очищает правила перезаписи URL и удаляет cron задачи
     */
    public function deactivate() {
        // Сбрасываем правила перезаписи URL
        // Это необходимо для очистки правил после деактивации плагина
        flush_rewrite_rules();
        
        // Удаляем cron задачу синхронизации с Moodle, если она была зарегистрирована
        // wp_clear_scheduled_hook() удаляет все запланированные события для указанного хука
        $timestamp = wp_next_scheduled('moodle_sync_cron');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'moodle_sync_cron');
        }
    }
    
    /**
     * Загрузка текстового домена для переводов
     * Позволяет переводить плагин на другие языки
     * Вызывается на хуке 'plugins_loaded'
     */
    public function load_textdomain() {
        // Загружаем файлы переводов из папки /languages/
        load_plugin_textdomain(
            'course-plugin',                                    // Текстовый домен плагина (используется в функциях __(), _e() и т.д.)
            false,                                              // Не добавлять путь к языкам WordPress по умолчанию
            dirname(COURSE_PLUGIN_BASENAME) . '/languages'     // Относительный путь к папке с переводами
        );
    }
}

/**
 * Функция инициализации плагина
 * Вызывается в конце файла для запуска плагина
 * 
 * @return Course_Plugin Экземпляр основного класса плагина
 */
function course_plugin_init() {
    // Создаем и возвращаем единственный экземпляр класса Course_Plugin
    return Course_Plugin::get_instance();
}

// Запускаем плагин сразу после загрузки файла
// Это точка входа в плагин
course_plugin_init();
