<?php
/**
 * Админ-панель Course Builder
 */

if (!defined('ABSPATH')) {
    exit;
}

class Course_Builder_Admin {
    
    /**
     * Единственный экземпляр класса
     */
    private static $instance = null;
    
    /**
     * Получить экземпляр класса
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
     * Инициализация
     */
    private function init() {
        // Добавляем метабокс для включения builder
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        
        // Сохранение метабокса
        add_action('save_post', array($this, 'save_meta_box'), 10, 2);
        
        // Добавляем кнопку в редактор
        add_action('edit_form_after_title', array($this, 'add_builder_button'));
        
        // Подключаем скрипты и стили
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        
        // Добавляем страницу builder в админ-меню
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // AJAX обработчики
        add_action('wp_ajax_course_builder_get_widgets', array($this, 'ajax_get_widgets'));
        add_action('wp_ajax_course_builder_enable', array($this, 'ajax_enable_builder'));
    }
    
    /**
     * Добавить пункт меню в админке
     */
    public function add_admin_menu() {
        // Добавляем скрытую страницу для редактирования builder
        // Используем add_management_page для добавления в раздел "Инструменты"
        // Но можно также использовать add_menu_page и скрыть через CSS
        $hook = add_menu_page(
            __('Course Builder', 'course-plugin'),
            __('Course Builder', 'course-plugin'),
            'edit_posts',
            'course-builder',
            array($this, 'render_builder_page'),
            'dashicons-admin-customizer',
            100
        );
        
        // Скрываем из меню, но страница остается доступной по прямой ссылке
        // Используем хук для скрытия пункта меню
        add_action('admin_head', function() {
            echo '<style>#toplevel_page_course-builder { display: none !important; }</style>';
        });
    }
    
    /**
     * Рендеринг страницы builder
     */
    public function render_builder_page() {
        // Проверка прав доступа
        if (!current_user_can('edit_posts')) {
            wp_die(__('У вас нет прав для доступа к этой странице', 'course-plugin'));
        }
        
        $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
        
        if (!$post_id) {
            wp_die(__('Не указан ID поста', 'course-plugin'));
        }
        
        $post = get_post($post_id);
        if (!$post) {
            wp_die(__('Пост не найден', 'course-plugin'));
        }
        
        // Проверка прав на редактирование этого поста
        if (!current_user_can('edit_post', $post_id)) {
            wp_die(__('У вас нет прав для редактирования этого поста', 'course-plugin'));
        }
        
        // Включаем builder, если еще не включен
        $builder = Course_Builder::get_instance();
        if (!$builder->is_builder_enabled($post_id)) {
            $builder->enable_builder($post_id);
        }
        
        // Загружаем данные builder
        $builder_data = $builder->get_builder_data($post_id);
        
        ?>
        <div class="wrap course-builder-wrap">
            <div class="course-builder-toolbar">
                <h2><?php echo esc_html($post->post_title); ?> - <?php _e('Course Builder', 'course-plugin'); ?></h2>
                <div class="course-builder-toolbar-actions">
                    <a href="<?php echo get_edit_post_link($post_id); ?>" class="button"><?php _e('Вернуться к редактору', 'course-plugin'); ?></a>
                    <button class="course-builder-save button button-primary"><?php _e('Сохранить', 'course-plugin'); ?></button>
                </div>
            </div>
            
            <div class="course-builder-editor-wrapper">
                <div class="course-builder-editor" id="course-builder-editor">
                    <!-- Здесь будет рендериться структура builder -->
                    <div class="course-builder-empty-state">
                        <p><?php _e('Начните добавлять виджеты из боковой панели', 'course-plugin'); ?></p>
                    </div>
                </div>
                
                <div class="course-builder-sidebar">
                    <div class="course-builder-widgets-panel">
                        <h3><?php _e('Виджеты', 'course-plugin'); ?></h3>
                        <div class="course-builder-widget-list" id="course-builder-widget-list">
                            <!-- Виджеты будут загружены через AJAX -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Загружаем список виджетов
            $.ajax({
                url: courseBuilderAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'course_builder_get_widgets',
                    nonce: courseBuilderAdmin.nonce
                },
                success: function(response) {
                    if (response.success && response.data.widgets) {
                        var html = '';
                        $.each(response.data.widgets, function(type, widget) {
                            html += '<button class="course-builder-add-widget" data-widget-type="' + type + '">';
                            html += '<span class="dashicons ' + widget.icon + '"></span>';
                            html += '<span>' + widget.name + '</span>';
                            html += '</button>';
                        });
                        $('#course-builder-widget-list').html(html);
                    }
                }
            });
            
            // Загружаем данные builder
            CourseBuilderAdmin.loadBuilder();
        });
        </script>
        <?php
    }
    
    /**
     * AJAX включение builder
     */
    public function ajax_enable_builder() {
        // Проверка nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'course_builder_save')) {
            wp_send_json_error(array('message' => __('Ошибка безопасности', 'course-plugin')));
        }
        
        // Проверка прав
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Недостаточно прав', 'course-plugin')));
        }
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        
        if (!$post_id) {
            wp_send_json_error(array('message' => __('Не указан ID поста', 'course-plugin')));
        }
        
        $builder = Course_Builder::get_instance();
        $result = $builder->enable_builder($post_id);
        
        if ($result) {
            wp_send_json_success(array('message' => __('Builder включен', 'course-plugin')));
        } else {
            wp_send_json_error(array('message' => __('Ошибка включения builder', 'course-plugin')));
        }
    }
    
    /**
     * Добавить метабоксы
     */
    public function add_meta_boxes() {
        // Метабокс для страниц
        add_meta_box(
            'course_builder_enable',
            __('Course Builder', 'course-plugin'),
            array($this, 'render_meta_box'),
            array('page', 'course'),
            'side',
            'high'
        );
    }
    
    /**
     * Рендеринг метабокса
     */
    public function render_meta_box($post) {
        wp_nonce_field('course_builder_meta_box', 'course_builder_meta_box_nonce');
        
        $use_builder = Course_Builder::get_instance()->is_builder_enabled($post->ID);
        ?>
        <p>
            <label>
                <input type="checkbox" name="use_course_builder" value="1" <?php checked($use_builder, true); ?>>
                <?php _e('Использовать Course Builder для этой страницы', 'course-plugin'); ?>
            </label>
        </p>
        <p class="description">
            <?php _e('Включите эту опцию, чтобы редактировать страницу с помощью визуального редактора.', 'course-plugin'); ?>
        </p>
        <?php
    }
    
    /**
     * Сохранение метабокса
     */
    public function save_meta_box($post_id, $post) {
        // Проверка nonce
        if (!isset($_POST['course_builder_meta_box_nonce']) || 
            !wp_verify_nonce($_POST['course_builder_meta_box_nonce'], 'course_builder_meta_box')) {
            return;
        }
        
        // Проверка прав
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Проверка автосохранения
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Сохранение флага использования builder
        $builder = Course_Builder::get_instance();
        if (isset($_POST['use_course_builder']) && $_POST['use_course_builder']) {
            $builder->enable_builder($post_id);
        } else {
            $builder->disable_builder($post_id);
        }
    }
    
    /**
     * Добавить кнопку builder в редактор
     */
    public function add_builder_button($post) {
        if (!in_array($post->post_type, array('page', 'course'))) {
            return;
        }
        
        $builder_enabled = Course_Builder::get_instance()->is_builder_enabled($post->ID);
        $builder_url = admin_url('admin.php?page=course-builder&post_id=' . $post->ID);
        ?>
        <div id="course-builder-button-wrapper" style="margin: 20px 0;">
            <?php if ($builder_enabled) : ?>
                <a href="<?php echo esc_url($builder_url); ?>" class="button button-primary button-large" id="course-builder-edit-button">
                    <span class="dashicons dashicons-edit" style="margin-top: 3px;"></span>
                    <?php _e('Редактировать с Course Builder', 'course-plugin'); ?>
                </a>
                <p class="description">
                    <?php _e('Эта страница использует Course Builder. Нажмите кнопку выше для редактирования.', 'course-plugin'); ?>
                </p>
            <?php else : ?>
                <a href="<?php echo esc_url($builder_url); ?>" class="button button-secondary button-large" id="course-builder-enable-button">
                    <span class="dashicons dashicons-admin-customizer" style="margin-top: 3px;"></span>
                    <?php _e('Включить Course Builder', 'course-plugin'); ?>
                </a>
                <p class="description">
                    <?php _e('Используйте Course Builder для визуального редактирования страницы.', 'course-plugin'); ?>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Подключение скриптов и стилей
     */
    public function enqueue_assets($hook) {
        global $post;
        
        $post_id = 0;
        
        // Проверяем, находимся ли мы на странице builder
        if ($hook === 'toplevel_page_course-builder' || (isset($_GET['page']) && $_GET['page'] === 'course-builder')) {
            $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
        } elseif ($post) {
            $post_id = $post->ID;
        }
        
        // Подключаем скрипты и стили на странице builder или на страницах редактирования
        $is_builder_page = ($hook === 'toplevel_page_course-builder' || (isset($_GET['page']) && $_GET['page'] === 'course-builder'));
        $is_edit_page = in_array($hook, array('post.php', 'post-new.php')) && $post && in_array($post->post_type, array('page', 'course'));
        
        if (!$is_builder_page && !$is_edit_page) {
            return;
        }
        
        // Стили
        wp_enqueue_style(
            'course-builder-admin',
            COURSE_PLUGIN_URL . 'assets/css/builder-admin.css',
            array(),
            COURSE_PLUGIN_VERSION
        );
        
        // Скрипты
        wp_enqueue_script(
            'course-builder-admin',
            COURSE_PLUGIN_URL . 'assets/js/builder-admin.js',
            array('jquery', 'jquery-ui-sortable'),
            COURSE_PLUGIN_VERSION,
            true
        );
        
        // Локализация
        wp_localize_script('course-builder-admin', 'courseBuilderAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('course_builder_save'),
            'loadNonce' => wp_create_nonce('course_builder_load'),
            'postId' => $post_id,
            'strings' => array(
                'save' => __('Сохранить', 'course-plugin'),
                'saving' => __('Сохранение...', 'course-plugin'),
                'saved' => __('Сохранено', 'course-plugin'),
                'error' => __('Ошибка', 'course-plugin'),
                'delete' => __('Удалить', 'course-plugin'),
                'duplicate' => __('Дублировать', 'course-plugin'),
                'edit' => __('Редактировать', 'course-plugin'),
            )
        ));
    }
    
    /**
     * AJAX получение списка виджетов
     */
    public function ajax_get_widgets() {
        // Проверка прав
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Недостаточно прав', 'course-plugin')));
        }
        
        $widgets = Course_Builder::get_widgets();
        $widgets_data = array();
        
        foreach ($widgets as $type => $class_name) {
            if (class_exists($class_name)) {
                $widget = new $class_name();
                $widgets_data[$type] = array(
                    'name' => $widget->get_name(),
                    'description' => $widget->get_description(),
                    'icon' => $widget->get_icon(),
                    'type' => $type
                );
            }
        }
        
        wp_send_json_success(array('widgets' => $widgets_data));
    }
}
