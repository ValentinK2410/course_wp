<?php
/**
 * Plugin Name: Курсы Про
 * Plugin URI: https://github.com/ValentinK2410/course_wp
 * Description: Плагин для управления курсами с возможностью добавления, редактирования и удаления курсов. Включает разделы: специализация и программы, уровень образования, тема, преподаватель.
 * Version: 1.0.0
 * Author: valentink2410
 * Author URI: https://github.com/ValentinK2410
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: course-plugin
 * Domain Path: /languages
 */

// Если файл вызывается напрямую, выходим
if (!defined('ABSPATH')) {
    exit;
}

// Определяем константы плагина
define('COURSE_PLUGIN_VERSION', '1.0.0');
define('COURSE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('COURSE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('COURSE_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Основной класс плагина
 */
class Course_Plugin {
    
    /**
     * Единственный экземпляр класса
     */
    private static $instance = null;
    
    /**
     * Получить экземпляр класса (Singleton)
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Конструктор
     */
    private function __construct() {
        $this->init();
    }
    
    /**
     * Инициализация плагина
     */
    private function init() {
        // Подключаем файлы классов
        $this->includes();
        
        // Хуки активации и деактивации
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Инициализация компонентов
        add_action('init', array($this, 'load_components'));
        
        // Загрузка текстового домена
        add_action('plugins_loaded', array($this, 'load_textdomain'));
    }
    
    /**
     * Подключение файлов классов
     */
    private function includes() {
        $files = array(
            'includes/class-course-post-type.php',
            'includes/class-course-taxonomies.php',
            'includes/class-course-admin.php',
            'includes/class-course-meta-boxes.php',
            'includes/class-course-frontend.php',
        );
        
        foreach ($files as $file) {
            $file_path = COURSE_PLUGIN_DIR . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            } else {
                // Логируем ошибку, если файл не найден
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Course Plugin: Файл не найден - ' . $file_path);
                }
            }
        }
    }
    
    /**
     * Загрузка компонентов
     */
    public function load_components() {
        // Проверяем, что классы загружены
        if (!class_exists('Course_Post_Type')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Course Plugin: Класс Course_Post_Type не найден');
            }
            return;
        }
        
        // Инициализируем Custom Post Type
        Course_Post_Type::get_instance();
        
        // Инициализируем таксономии
        if (class_exists('Course_Taxonomies')) {
            Course_Taxonomies::get_instance();
        }
        
        // Инициализируем административный интерфейс
        if (class_exists('Course_Admin')) {
            Course_Admin::get_instance();
        }
        
        // Инициализируем метабоксы
        if (class_exists('Course_Meta_Boxes')) {
            Course_Meta_Boxes::get_instance();
        }
        
        // Инициализируем фронтенд
        if (class_exists('Course_Frontend')) {
            Course_Frontend::get_instance();
        }
    }
    
    /**
     * Активация плагина
     */
    public function activate() {
        // Подключаем файлы классов перед активацией
        $this->includes();
        
        // Регистрируем типы постов и таксономии
        $this->load_components();
        
        // Сбрасываем правила перезаписи
        flush_rewrite_rules();
    }
    
    /**
     * Деактивация плагина
     */
    public function deactivate() {
        // Сбрасываем правила перезаписи
        flush_rewrite_rules();
    }
    
    /**
     * Загрузка текстового домена для переводов
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'course-plugin',
            false,
            dirname(COURSE_PLUGIN_BASENAME) . '/languages'
        );
    }
}

/**
 * Инициализация плагина
 */
function course_plugin_init() {
    return Course_Plugin::get_instance();
}

// Запускаем плагин
course_plugin_init();

