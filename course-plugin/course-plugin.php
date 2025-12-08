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
        require_once COURSE_PLUGIN_DIR . 'includes/class-course-post-type.php';
        require_once COURSE_PLUGIN_DIR . 'includes/class-course-taxonomies.php';
        require_once COURSE_PLUGIN_DIR . 'includes/class-course-admin.php';
        require_once COURSE_PLUGIN_DIR . 'includes/class-course-meta-boxes.php';
    }
    
    /**
     * Загрузка компонентов
     */
    public function load_components() {
        // Инициализируем Custom Post Type
        Course_Post_Type::get_instance();
        
        // Инициализируем таксономии
        Course_Taxonomies::get_instance();
        
        // Инициализируем административный интерфейс
        Course_Admin::get_instance();
        
        // Инициализируем метабоксы
        Course_Meta_Boxes::get_instance();
    }
    
    /**
     * Активация плагина
     */
    public function activate() {
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

