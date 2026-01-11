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
        
        // Добавляем кнопку в редактор (для классического редактора)
        add_action('edit_form_after_title', array($this, 'add_builder_button'));
        
        // Добавляем кнопку для Gutenberg редактора
        add_action('admin_footer', array($this, 'add_builder_button_gutenberg'));
        
        // Подключаем скрипты и стили
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        
        // Добавляем страницу builder в админ-меню
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // AJAX обработчики
        add_action('wp_ajax_course_builder_get_widgets', array($this, 'ajax_get_widgets'));
        add_action('wp_ajax_course_builder_enable', array($this, 'ajax_enable_builder'));
        add_action('wp_ajax_course_builder_get_widget_settings', array($this, 'ajax_get_widget_settings'));
        add_action('wp_ajax_course_builder_render_widget', array($this, 'ajax_render_widget'));
        add_action('wp_ajax_course_builder_preview_page', array($this, 'ajax_preview_page'));
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
        
        // Убеждаемся, что скрипты загружены перед выводом страницы
        $this->enqueue_assets('toplevel_page_course-builder');
        
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
                    
                    <div class="course-builder-actions-panel" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;">
                        <h3><?php _e('Действия', 'course-plugin'); ?></h3>
                        <button class="button button-secondary" id="course-builder-add-section" style="width: 100%; margin-bottom: 10px;">
                            <span class="dashicons dashicons-plus-alt" style="vertical-align: middle;"></span>
                            <?php _e('Добавить секцию', 'course-plugin'); ?>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Модальное окно для редактирования виджета -->
            <div id="course-builder-widget-modal" style="display: none;">
                <div class="course-builder-modal-overlay"></div>
                <div class="course-builder-modal-content">
                    <div class="course-builder-modal-header">
                        <h2><?php _e('Редактировать виджет', 'course-plugin'); ?></h2>
                        <button class="course-builder-modal-close">&times;</button>
                    </div>
                    <div class="course-builder-modal-body" id="course-builder-widget-settings">
                        <!-- Настройки виджета будут загружены здесь -->
                    </div>
                    <div class="course-builder-modal-footer">
                        <button class="button button-secondary course-builder-modal-cancel"><?php _e('Отмена', 'course-plugin'); ?></button>
                        <button class="button button-primary course-builder-modal-save"><?php _e('Сохранить', 'course-plugin'); ?></button>
                    </div>
                </div>
            </div>
        </div>
        <?php
        // Добавляем inline скрипт в футер через хук, чтобы он выполнялся после загрузки основного скрипта
        // Используем замыкание для передачи post_id
        $footer_script_post_id = $post_id;
        add_action('admin_footer', function() use ($footer_script_post_id) {
            // Проверяем, что мы на странице builder
            if (!isset($_GET['page']) || $_GET['page'] !== 'course-builder') {
                return;
            }
            ?>
            <script type="text/javascript">
            (function($) {
                'use strict';
                
                // Функция инициализации страницы builder
                function initBuilderPage() {
                    // Проверяем доступность переменных
                    if (typeof courseBuilderAdmin === 'undefined') {
                        console.log('Waiting for courseBuilderAdmin to load...');
                        setTimeout(initBuilderPage, 300);
                        return;
                    }
                    
                    console.log('courseBuilderAdmin loaded, initializing...');
                    
                    // Загружаем список виджетов
                    $.ajax({
                        url: courseBuilderAdmin.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'course_builder_get_widgets',
                            nonce: courseBuilderAdmin.nonce
                        },
                        success: function(response) {
                            console.log('Widgets response:', response);
                            if (response.success && response.data && response.data.widgets) {
                                var html = '';
                                var widgetCount = 0;
                                $.each(response.data.widgets, function(type, widget) {
                                    widgetCount++;
                                    html += '<button class="course-builder-add-widget" data-widget-type="' + type + '" style="display: block; width: 100%; margin-bottom: 8px; padding: 10px; text-align: left; background: #fff; border: 1px solid #ddd; border-radius: 3px; cursor: pointer;">';
                                    html += '<span class="dashicons ' + widget.icon + '" style="vertical-align: middle; margin-right: 8px;"></span>';
                                    html += '<span style="vertical-align: middle;">' + widget.name + '</span>';
                                    html += '</button>';
                                });
                                
                                if (widgetCount > 0) {
                                    $('#course-builder-widget-list').html(html);
                                    console.log('Loaded ' + widgetCount + ' widgets');
                                } else {
                                    console.error('No widgets found in response');
                                    $('#course-builder-widget-list').html('<p style="color: #dc3232;">Виджеты не найдены. Проверьте консоль для деталей.</p>');
                                }
                            } else {
                                console.error('Invalid response format:', response);
                                $('#course-builder-widget-list').html('<p style="color: #dc3232;">Ошибка загрузки виджетов: ' + (response.data && response.data.message ? response.data.message : 'Неизвестная ошибка') + '</p>');
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Error loading widgets:', error, xhr);
                            $('#course-builder-widget-list').html('<p style="color: #dc3232;">Ошибка AJAX: ' + error + '</p>');
                        }
                    });
                    
                    // Загружаем данные builder после загрузки скрипта
                    // Проверяем наличие объекта CourseBuilderAdmin
                    function loadBuilderData() {
                        if (typeof CourseBuilderAdmin !== 'undefined' && typeof CourseBuilderAdmin.loadBuilder === 'function') {
                            console.log('Loading builder data...');
                            CourseBuilderAdmin.loadBuilder();
                        } else {
                            console.log('Waiting for CourseBuilderAdmin object...');
                            setTimeout(loadBuilderData, 300);
                        }
                    }
                    
                    loadBuilderData();
                }
                
                // Запускаем инициализацию после полной загрузки DOM
                $(document).ready(function() {
                    // Даем время на загрузку всех скриптов
                    setTimeout(initBuilderPage, 500);
                });
            })(jQuery);
            </script>
            <?php
        }, 999);
        ?>
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
        $builder_url = admin_url('admin.php?page=course-builder&post_id=' . $post->ID);
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
        <p style="margin-top: 15px;">
            <?php if ($use_builder) : ?>
                <a href="<?php echo esc_url($builder_url); ?>" class="button button-primary button-large" style="width: 100%; text-align: center; display: block;">
                    <span class="dashicons dashicons-edit" style="margin-top: 3px; vertical-align: middle;"></span>
                    <?php _e('Редактировать с Course Builder', 'course-plugin'); ?>
                </a>
            <?php else : ?>
                <a href="<?php echo esc_url($builder_url); ?>" class="button button-secondary button-large" style="width: 100%; text-align: center; display: block;">
                    <span class="dashicons dashicons-admin-customizer" style="margin-top: 3px; vertical-align: middle;"></span>
                    <?php _e('Включить Course Builder', 'course-plugin'); ?>
                </a>
            <?php endif; ?>
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
     * Добавить кнопку builder в редактор (классический редактор)
     */
    public function add_builder_button($post) {
        if (!in_array($post->post_type, array('page', 'course'))) {
            return;
        }
        
        $builder_enabled = Course_Builder::get_instance()->is_builder_enabled($post->ID);
        $builder_url = admin_url('admin.php?page=course-builder&post_id=' . $post->ID);
        ?>
        <div id="course-builder-button-wrapper" style="margin: 20px 0; padding: 15px; background: #f0f0f1; border: 1px solid #c3c4c7; border-radius: 4px;">
            <?php if ($builder_enabled) : ?>
                <a href="<?php echo esc_url($builder_url); ?>" class="button button-primary button-large" id="course-builder-edit-button" style="margin-right: 10px;">
                    <span class="dashicons dashicons-edit" style="margin-top: 3px; vertical-align: middle;"></span>
                    <?php _e('Редактировать с Course Builder', 'course-plugin'); ?>
                </a>
                <p class="description" style="margin-top: 10px; margin-bottom: 0;">
                    <?php _e('Эта страница использует Course Builder. Нажмите кнопку выше для редактирования.', 'course-plugin'); ?>
                </p>
            <?php else : ?>
                <a href="<?php echo esc_url($builder_url); ?>" class="button button-secondary button-large" id="course-builder-enable-button" style="margin-right: 10px;">
                    <span class="dashicons dashicons-admin-customizer" style="margin-top: 3px; vertical-align: middle;"></span>
                    <?php _e('Включить Course Builder', 'course-plugin'); ?>
                </a>
                <p class="description" style="margin-top: 10px; margin-bottom: 0;">
                    <?php _e('Используйте Course Builder для визуального редактирования страницы.', 'course-plugin'); ?>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Добавить кнопку builder для Gutenberg редактора
     */
    public function add_builder_button_gutenberg() {
        global $post;
        
        if (!$post || !in_array($post->post_type, array('page', 'course'))) {
            return;
        }
        
        // Проверяем, что мы на странице редактирования
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->base, array('post'))) {
            return;
        }
        
        $builder_enabled = Course_Builder::get_instance()->is_builder_enabled($post->ID);
        $builder_url = admin_url('admin.php?page=course-builder&post_id=' . $post->ID);
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Добавляем кнопку в верхнюю панель Gutenberg
            function addBuilderButton() {
                // Ищем панель инструментов Gutenberg
                var toolbar = $('.edit-post-header-toolbar, .block-editor-writing-flow');
                if (toolbar.length === 0) {
                    // Если панель не найдена, добавляем после заголовка
                    var titleArea = $('.editor-post-title, .wp-block-post-title');
                    if (titleArea.length > 0) {
                        var buttonHtml = '<div id="course-builder-button-wrapper-gutenberg" style="margin: 20px 0; padding: 15px; background: #f0f0f1; border: 1px solid #c3c4c7; border-radius: 4px; clear: both;">';
                        <?php if ($builder_enabled) : ?>
                            buttonHtml += '<a href="<?php echo esc_js($builder_url); ?>" class="components-button is-primary is-large" style="margin-right: 10px;">';
                            buttonHtml += '<span class="dashicons dashicons-edit" style="margin-top: 3px; vertical-align: middle; margin-right: 5px;"></span>';
                            buttonHtml += '<?php echo esc_js(__('Редактировать с Course Builder', 'course-plugin')); ?>';
                            buttonHtml += '</a>';
                            buttonHtml += '<p style="margin-top: 10px; margin-bottom: 0; color: #646970;"><?php echo esc_js(__('Эта страница использует Course Builder. Нажмите кнопку выше для редактирования.', 'course-plugin')); ?></p>';
                        <?php else : ?>
                            buttonHtml += '<a href="<?php echo esc_js($builder_url); ?>" class="components-button is-secondary is-large" style="margin-right: 10px;">';
                            buttonHtml += '<span class="dashicons dashicons-admin-customizer" style="margin-top: 3px; vertical-align: middle; margin-right: 5px;"></span>';
                            buttonHtml += '<?php echo esc_js(__('Включить Course Builder', 'course-plugin')); ?>';
                            buttonHtml += '</a>';
                            buttonHtml += '<p style="margin-top: 10px; margin-bottom: 0; color: #646970;"><?php echo esc_js(__('Используйте Course Builder для визуального редактирования страницы.', 'course-plugin')); ?></p>';
                        <?php endif; ?>
                        buttonHtml += '</div>';
                        
                        titleArea.after(buttonHtml);
                    }
                }
            }
            
            // Пытаемся добавить кнопку сразу
            addBuilderButton();
            
            // Также пытаемся добавить после загрузки Gutenberg
            setTimeout(addBuilderButton, 1000);
            setTimeout(addBuilderButton, 2000);
        });
        </script>
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
        
        // Стили админки
        wp_enqueue_style(
            'course-builder-admin',
            COURSE_PLUGIN_URL . 'assets/css/builder-admin.css',
            array(),
            COURSE_PLUGIN_VERSION
        );
        
        // Фронтенд стили для предпросмотра в редакторе
        wp_enqueue_style(
            'course-builder-frontend',
            COURSE_PLUGIN_URL . 'assets/css/builder-frontend.css',
            array('course-builder-admin'),
            COURSE_PLUGIN_VERSION
        );
        
        // Скрипты - загружаем в футере, но с высоким приоритетом
        wp_enqueue_script(
            'course-builder-admin',
            COURSE_PLUGIN_URL . 'assets/js/builder-admin.js',
            array('jquery', 'jquery-ui-sortable'),
            COURSE_PLUGIN_VERSION,
            false // Загружаем в head, чтобы был доступен раньше
        );
        
        // Убеждаемся, что post_id установлен
        if (!$post_id && isset($_GET['post_id'])) {
            $post_id = intval($_GET['post_id']);
        }
        
        // Проверяем, что post_id валиден
        if (!$post_id) {
            error_log('Course Builder Admin: Warning - post_id is not set when enqueuing scripts');
        }
        
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
        
        // Добавляем отладочную информацию в консоль
        add_action('admin_footer', function() use ($post_id) {
            if (isset($_GET['page']) && $_GET['page'] === 'course-builder') {
                ?>
                <script type="text/javascript">
                console.log('Course Builder Admin initialized');
                console.log('Post ID:', <?php echo intval($post_id); ?>);
                console.log('AJAX URL:', '<?php echo admin_url('admin-ajax.php'); ?>');
                </script>
                <?php
            }
        }, 999);
    }
    
    /**
     * AJAX получение списка виджетов
     */
    public function ajax_get_widgets() {
        // Проверка прав
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Недостаточно прав', 'course-plugin')));
        }
        
        // Убеждаемся, что виджеты зарегистрированы
        // Вызываем хук регистрации виджетов, если он еще не был вызван
        do_action('course_builder_register_widgets');
        
        $widgets = Course_Builder::get_widgets();
        $widgets_data = array();
        
        if (empty($widgets)) {
            // Если виджеты не зарегистрированы, регистрируем их вручную
            Course_Builder_Register::register_widgets();
            $widgets = Course_Builder::get_widgets();
        }
        
        foreach ($widgets as $type => $class_name) {
            if (class_exists($class_name)) {
                try {
                    // Создаем виджет с пустым ID для получения метаданных
                    $widget = new $class_name('temp', array());
                    $widgets_data[$type] = array(
                        'name' => $widget->get_name(),
                        'description' => $widget->get_description(),
                        'icon' => $widget->get_icon(),
                        'type' => $type
                    );
                } catch (Exception $e) {
                    error_log('Course Builder: Ошибка создания виджета ' . $type . ': ' . $e->getMessage());
                } catch (Error $e) {
                    error_log('Course Builder: Фатальная ошибка создания виджета ' . $type . ': ' . $e->getMessage());
                }
            } else {
                error_log('Course Builder: Класс виджета не найден: ' . $class_name);
            }
        }
        
        if (empty($widgets_data)) {
            wp_send_json_error(array('message' => __('Виджеты не найдены', 'course-plugin'), 'debug' => array('widgets_count' => count($widgets))));
        }
        
        wp_send_json_success(array('widgets' => $widgets_data));
    }
    
    /**
     * AJAX получение полей настроек виджета
     */
    public function ajax_get_widget_settings() {
        // Проверка прав
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Недостаточно прав', 'course-plugin')));
        }
        
        $widget_type = isset($_POST['widget_type']) ? sanitize_text_field($_POST['widget_type']) : '';
        
        if (!$widget_type) {
            wp_send_json_error(array('message' => __('Не указан тип виджета', 'course-plugin')));
        }
        
        // Убеждаемся, что виджеты зарегистрированы
        do_action('course_builder_register_widgets');
        
        // Если виджеты не зарегистрированы, регистрируем их вручную
        if (class_exists('Course_Builder_Register')) {
            Course_Builder_Register::register_widgets();
        }
        
        // Получаем класс виджета
        $widget_class = Course_Builder::get_widget_class($widget_type);
        
        if (!$widget_class) {
            error_log('Course Builder: Widget class not found for type: ' . $widget_type);
            wp_send_json_error(array('message' => __('Виджет не найден', 'course-plugin'), 'debug' => array('type' => $widget_type, 'registered_widgets' => array_keys(Course_Builder::get_widgets()))));
        }
        
        if (!class_exists($widget_class)) {
            error_log('Course Builder: Widget class does not exist: ' . $widget_class);
            wp_send_json_error(array('message' => __('Класс виджета не найден', 'course-plugin'), 'debug' => array('class' => $widget_class)));
        }
        
        try {
            $widget = new $widget_class('temp', array());
            $fields = $widget->get_settings_fields();
            
            if (empty($fields)) {
                error_log('Course Builder: No settings fields returned for widget: ' . $widget_type);
            }
            
            wp_send_json_success(array('fields' => $fields));
        } catch (Exception $e) {
            error_log('Course Builder: Exception loading widget settings: ' . $e->getMessage());
            wp_send_json_error(array('message' => __('Ошибка загрузки настроек', 'course-plugin'), 'debug' => array('error' => $e->getMessage())));
        } catch (Error $e) {
            error_log('Course Builder: Fatal error loading widget settings: ' . $e->getMessage());
            wp_send_json_error(array('message' => __('Критическая ошибка загрузки настроек', 'course-plugin'), 'debug' => array('error' => $e->getMessage())));
        }
    }
    
    /**
     * AJAX рендеринг виджета для предпросмотра в редакторе
     */
    public function ajax_render_widget() {
        // Проверка nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'course_builder_save')) {
            wp_send_json_error(array('message' => __('Ошибка безопасности', 'course-plugin')));
        }
        
        // Проверка прав
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Недостаточно прав', 'course-plugin')));
        }
        
        $widget_type = isset($_POST['widget_type']) ? sanitize_text_field($_POST['widget_type']) : '';
        $widget_settings = isset($_POST['widget_settings']) ? $_POST['widget_settings'] : array();
        
        if (empty($widget_type)) {
            wp_send_json_error(array('message' => __('Не указан тип виджета', 'course-plugin')));
        }
        
        // Получаем класс виджета
        $widget_class = Course_Builder::get_widget_class($widget_type);
        
        if (!$widget_class || !class_exists($widget_class)) {
            wp_send_json_error(array('message' => __('Виджет не найден', 'course-plugin')));
        }
        
        try {
            // Создаем экземпляр виджета
            $widget = new $widget_class('preview', $widget_settings);
            
            // Рендерим виджет
            $content = $widget->render($widget_settings);
            
            wp_send_json_success(array('content' => $content));
        } catch (Exception $e) {
            error_log('Course Builder: Ошибка рендеринга виджета: ' . $e->getMessage());
            wp_send_json_error(array('message' => __('Ошибка рендеринга виджета', 'course-plugin'), 'debug' => $e->getMessage()));
        }
    }
    
    /**
     * AJAX предпросмотр полной страницы курса - возвращает полный HTML страницы
     */
    public function ajax_preview_page() {
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
        
        // Устанавливаем глобальные переменные для правильного рендеринга
        global $post, $wp_query;
        $post = get_post($post_id);
        
        if (!$post) {
            wp_send_json_error(array('message' => __('Пост не найден', 'course-plugin')));
        }
        
        setup_postdata($post);
        
        // Устанавливаем query для правильного рендеринга
        $wp_query->is_single = true;
        $wp_query->is_singular = true;
        $wp_query->queried_object = $post;
        $wp_query->queried_object_id = $post_id;
        
        // Начинаем буферизацию
        ob_start();
        
        // Рендерим контент страницы курса (копируем логику из single-course.php)
        echo '<div class="course-builder-page-preview">';
        
        // Получаем данные курса
        $course_code = get_post_meta($post_id, '_course_code', true);
        $course_duration = get_post_meta($post_id, '_course_duration', true);
        $course_price = get_post_meta($post_id, '_course_price', true);
        $course_old_price = get_post_meta($post_id, '_course_old_price', true);
        $course_start_date = get_post_meta($post_id, '_course_start_date', true);
        $course_video_url = get_post_meta($post_id, '_course_video_url', true);
        
        // Получаем преподавателя
        $teachers = get_the_terms($post_id, 'course_teacher');
        $teacher_name = '';
        if ($teachers && !is_wp_error($teachers) && !empty($teachers)) {
            $teacher_name = $teachers[0]->name;
        }
        
        // Вычисляем скидку
        $discount = 0;
        if ($course_old_price && $course_price && $course_price < $course_old_price) {
            $discount = round((($course_old_price - $course_price) / $course_old_price) * 100);
        }
        
        // Рендерим структуру страницы
        echo '<div class="single-course-wrapper">';
        
        // Большой баннер
        echo '<div class="course-hero-banner">';
        echo '<div class="course-hero-content">';
        echo '<h1 class="course-hero-title">' . mb_strtoupper(get_the_title($post_id), 'UTF-8') . '</h1>';
        if ($teacher_name) {
            echo '<p class="course-hero-teacher">' . mb_strtoupper($teacher_name, 'UTF-8') . '</p>';
        }
        if ($course_code) {
            echo '<p class="course-hero-code">' . __('КОД КУРСА:', 'course-plugin') . ' ' . esc_html($course_code) . '</p>';
        }
        echo '</div>';
        if (has_post_thumbnail($post_id)) {
            echo '<div class="course-hero-image">';
            echo get_the_post_thumbnail($post_id, 'full');
            echo '</div>';
        }
        echo '</div>';
        
        echo '<div class="single-course-container">';
        echo '<main class="single-course-main">';
        
        // Код курса и название
        if ($course_code) {
            echo '<div class="course-code-title">';
            echo '<span class="course-code">' . esc_html($course_code) . '</span>';
            echo '<h2>' . get_the_title($post_id) . '</h2>';
            echo '</div>';
        }
        
        // Описание курса
        echo '<div class="course-description-section">';
        echo '<h3>' . __('Описание курса:', 'course-plugin') . '</h3>';
        echo '<div class="course-description">';
        echo apply_filters('the_content', $post->post_content);
        echo '</div>';
        echo '</div>';
        
        // Course Builder контент - здесь будут виджеты
        if (class_exists('Course_Builder_Frontend')) {
            $builder_frontend = Course_Builder_Frontend::get_instance();
            $builder_content = $builder_frontend->render($post_id);
            
            if (!empty($builder_content)) {
                echo '<div class="course-builder-content-wrapper">';
                echo $builder_content;
                echo '</div>';
            }
        }
        
        echo '</main>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        // Fallback если что-то пошло не так
        if (false) {
            // Fallback - рендерим базовую структуру
            echo '<div class="course-builder-page-preview">';
            echo '<div class="single-course-wrapper">';
            echo '<div class="single-course-container">';
            echo '<main class="single-course-main">';
            
            if (class_exists('Course_Builder_Frontend')) {
                $builder_frontend = Course_Builder_Frontend::get_instance();
                $builder_content = $builder_frontend->render($post_id);
                
                if (!empty($builder_content)) {
                    echo '<div class="course-builder-content-wrapper">';
                    echo $builder_content;
                    echo '</div>';
                }
            }
            
            echo '</main>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
        
        wp_reset_postdata();
        
        $content = ob_get_clean();
        
        wp_send_json_success(array('content' => $content));
    }
}
