<?php
/**
 * Админка: HTML содержимое модального («окна записи») перед переходом к регистрации.
 *
 * @package Course_Plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Course_Enroll_Modal_Admin {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_preview_assets'));
    }

    /**
     * HTML «тела» модалки по умолчанию (как во фронтенд-шаблоне без кастомного блока).
     *
     * @return string
     */
    public static function get_default_modal_inner_html() {
        ob_start();
        ?>
<h2 id="course-program-reg-modal-title" class="course-program-reg-modal__title"><?php echo esc_html__('ПРОЦЕСС РЕГИСТРАЦИИ НА ПРОГРАММУ', 'course-plugin'); ?></h2>
<p class="course-program-reg-modal__lead"><?php echo esc_html__('Процесс включает в себя два шага', 'course-plugin'); ?></p>

<div class="course-program-reg-modal__step">
    <h3 class="course-program-reg-modal__step-title"><?php echo esc_html__('ШАГ 1. Вход в аккаунт', 'course-plugin'); ?></h3>
    <p class="course-program-reg-modal__text"><?php echo esc_html__('Первый шаг включает в себя создание аккаунта на учебной платформе семинарии или вход в аккаунт, если он у вас уже есть. При создании аккаунта вам нужно будет ввести ФИО и адрес электронной почты. На указанный вами адрес электронной почты вам придёт ссылка, нажав на которую вы сможете перейти к следующему шагу регистрации.', 'course-plugin'); ?></p>
</div>

<div class="course-program-reg-modal__step">
    <h3 class="course-program-reg-modal__step-title"><?php echo esc_html__('ШАГ 2. Получение доступа на платформу и предоставление необходимых данных', 'course-plugin'); ?></h3>
    <p class="course-program-reg-modal__text"><?php echo esc_html__('Когда вы перейдёте по ссылке, которая придёт вам на email, вы попадёте в раздел зачисления на программу, где вам нужно будет ввести необходимые данные (рекомендацию и т. п.). У вас будет время, чтобы всё это сделать до вступительных экзаменов.', 'course-plugin'); ?></p>
</div>
        <?php
        $html = ob_get_clean();
        return is_string($html) ? trim($html) : '';
    }

    /**
     * Скрипт и строки для предпросмотра модалки в админке.
     *
     * @param string $hook Текущий экран.
     */
    public function enqueue_preview_assets($hook) {
        if ($hook !== 'course_page_course-enroll-modal') {
            return;
        }
        wp_enqueue_script('jquery');
        wp_localize_script(
            'jquery',
            'courseEnrollModalPreview',
            array(
                'editorId'      => 'course_enroll_modal_html_wysiwyg',
                'defaultCta'    => __('Приступить к регистрации', 'course-plugin'),
                'defaultBody'   => self::get_default_modal_inner_html(),
                'emptyHint'     => __('Показан стандартный текст (редактор пустой). Сохраните страницу, чтобы на сайте было то же самое.', 'course-plugin'),
            )
        );
        $inline = <<<'JS'
(function($){
    function getEditorHtml(editorId){
        if (typeof window.tinymce !== "undefined") {
            var ed = window.tinymce.get(editorId);
            if (ed && !ed.isHidden()) {
                return ed.getContent() || "";
            }
        }
        var ta = document.getElementById(editorId);
        return ta ? (ta.value || "") : "";
    }
    function isContentEmpty(html){
        var d = document.createElement("div");
        d.innerHTML = html || "";
        return (d.textContent || "").replace(/\u00a0/g, " ").trim() === "";
    }
    function getCtaText(){
        var inp = document.getElementById("course_plugin_enroll_modal_cta_text");
        var v = inp && inp.value ? inp.value.trim() : "";
        return v || (window.courseEnrollModalPreview && courseEnrollModalPreview.defaultCta) || "";
    }
    function openPreview(){
        var cfg = window.courseEnrollModalPreview;
        if (!cfg) return;
        var raw = getEditorHtml(cfg.editorId);
        var body = document.getElementById("course-enroll-modal-preview-body");
        var hint = document.getElementById("course-enroll-modal-preview-hint");
        var wrap = document.getElementById("course-enroll-modal-admin-preview");
        if (!body || !wrap) return;
        if (isContentEmpty(raw)) {
            body.innerHTML = cfg.defaultBody || "";
            if (hint) {
                hint.textContent = cfg.emptyHint || "";
                hint.style.display = "block";
            }
        } else {
            body.innerHTML = '<div class="course-program-reg-modal__body course-program-reg-modal__body--custom">' + raw + "</div>";
            if (hint) {
                hint.textContent = "";
                hint.style.display = "none";
            }
        }
        var cta = document.getElementById("course-enroll-modal-preview-cta");
        if (cta) {
            cta.textContent = getCtaText();
        }
        wrap.hidden = false;
        wrap.setAttribute("aria-hidden", "false");
        document.body.classList.add("course-enroll-modal-admin-preview-open");
    }
    function closePreview(){
        var wrap = document.getElementById("course-enroll-modal-admin-preview");
        if (!wrap) return;
        wrap.hidden = true;
        wrap.setAttribute("aria-hidden", "true");
        document.body.classList.remove("course-enroll-modal-admin-preview-open");
    }
    $(function(){
        $(document).on("click", "#course-enroll-modal-open-preview", function(e){ e.preventDefault(); openPreview(); });
        $(document).on("click", "#course-enroll-modal-admin-preview [data-course-enroll-preview-close]", function(e){
            e.preventDefault();
            closePreview();
        });
        $(document).on("keydown", function(e){
            if (e.key !== "Escape") return;
            var wrap = document.getElementById("course-enroll-modal-admin-preview");
            if (wrap && !wrap.hidden) closePreview();
        });
    });
})(jQuery);
JS;
        wp_add_inline_script('jquery', $inline, 'after');
    }

    public function sanitize_modal_html($value) {
        if (!is_string($value)) {
            return '';
        }
        return wp_kses_post(wp_unslash($value));
    }

    public function register_menu() {
        add_submenu_page(
            'edit.php?post_type=course',
            __('Модальное окно записи', 'course-plugin'),
            __('Модальное окно записи', 'course-plugin'),
            'manage_options',
            'course-enroll-modal',
            array($this, 'render_page')
        );
    }

    public function register_settings() {
        register_setting(
            'course_enroll_modal_settings',
            'course_plugin_enroll_modal_html',
            array(
                'type' => 'string',
                'sanitize_callback' => array($this, 'sanitize_modal_html'),
                'default' => '',
            )
        );
        register_setting(
            'course_enroll_modal_settings',
            'course_plugin_enroll_modal_cta_text',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => '',
            )
        );
    }

    /**
     * Текст на кнопке-переходе под текстом модалки (к шлюзу / регистрации).
     *
     * @return string
     */
    public static function get_cta_label() {
        $v = get_option('course_plugin_enroll_modal_cta_text', '');
        $v = is_string($v) ? trim($v) : '';
        if ($v === '') {
            return __('Приступить к регистрации', 'course-plugin');
        }
        return $v;
    }

    /**
     * Кастомный HTML тела модалки или пустая строка = встроенный шаблон по умолчанию.
     *
     * @return string
     */
    public static function get_custom_html() {
        $h = get_option('course_plugin_enroll_modal_html', '');
        return is_string($h) ? trim($h) : '';
    }

    public static function uses_custom_html() {
        return self::get_custom_html() !== '';
    }

    public function render_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $modal_html = get_option('course_plugin_enroll_modal_html', '');
        if (!is_string($modal_html)) {
            $modal_html = '';
        }

        $editor_id = 'course_enroll_modal_html_wysiwyg';
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Модальное окно записи', 'course-plugin'); ?></h1>
            <p class="description">
                <?php esc_html_e('Текст показывается гостям на страницах программ при нажатии «Записаться» (перед переходом по ссылке записи). Редактор как у записей: вкладки «Визуально» и «Текст», вложения и скрипты отключены.', 'course-plugin'); ?>
            </p>

            <form method="post" action="options.php" class="course-enroll-modal-settings-form">
                <?php settings_fields('course_enroll_modal_settings'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="<?php echo esc_attr($editor_id); ?>"><?php esc_html_e('Содержимое окна', 'course-plugin'); ?></label>
                        </th>
                        <td>
                            <?php
                            wp_editor(
                                $modal_html,
                                $editor_id,
                                array(
                                    'textarea_name' => 'course_plugin_enroll_modal_html',
                                    'media_buttons'  => false,
                                    'teeny'            => false,
                                    'textarea_rows'    => 14,
                                    'editor_height'    => 320,
                                    'quicktags'        => true,
                                    'drag_drop_upload' => false,
                                    'tinymce'          => array(
                                        'resize'             => true,
                                        'wp_autoresize_on' => false,
                                    ),
                                )
                            );
                            ?>
                            <p class="description"><?php esc_html_e('Оставьте пустым (удалите всё и сохраните), чтобы использовать стандартный текст с двумя шагами.', 'course-plugin'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="course_plugin_enroll_modal_cta_text"><?php esc_html_e('Текст кнопки внизу окна', 'course-plugin'); ?></label>
                        </th>
                        <td>
                            <input type="text" class="regular-text" name="course_plugin_enroll_modal_cta_text" id="course_plugin_enroll_modal_cta_text" value="<?php echo esc_attr(get_option('course_plugin_enroll_modal_cta_text', '')); ?>" placeholder="<?php echo esc_attr__('Приступить к регистрации', 'course-plugin'); ?>" />
                            <p class="description"><?php esc_html_e('Пустое поле — подставится стандартная подпись «Приступить к регистрации».', 'course-plugin'); ?></p>
                        </td>
                    </tr>
                </table>
                <p class="submit course-enroll-modal-submit-row">
                    <?php submit_button(__('Сохранить', 'course-plugin'), 'primary', 'submit', false); ?>
                    <button type="button" class="button button-secondary button-large" id="course-enroll-modal-open-preview"><?php esc_html_e('Предпросмотр', 'course-plugin'); ?></button>
                </p>
            </form>

            <hr />
            <h2><?php esc_html_e('Пример разметки (можно скопировать во вкладке «Текст»)', 'course-plugin'); ?></h2>
            <?php
            $example_html = '<h2 id="course-program-reg-modal-title" class="course-program-reg-modal__title">' . esc_html__('ПРОЦЕСС РЕГИСТРАЦИИ НА ПРОГРАММУ', 'course-plugin') . "</h2>\n"
                . '<p class="course-program-reg-modal__lead">' . esc_html__('Процесс включает в себя два шага', 'course-plugin') . "</p>\n\n"
                . '<div class="course-program-reg-modal__step">' . "\n"
                . '  <h3 class="course-program-reg-modal__step-title">' . esc_html__('ШАГ 1. Вход в аккаунт', 'course-plugin') . "</h3>\n"
                . '  <p class="course-program-reg-modal__text">…</p>' . "\n"
                . "</div>\n\n"
                . '<div class="course-program-reg-modal__step">' . "\n"
                . '  <h3 class="course-program-reg-modal__step-title">' . esc_html__('ШАГ 2. Получение доступа на платформу и предоставление необходимых данных', 'course-plugin') . "</h3>\n"
                . '  <p class="course-program-reg-modal__text">…</p>' . "\n"
                . '</div>';
            ?>
            <p><textarea readonly class="large-text code" rows="14" onclick="this.select();" style="font-family:monospace;"><?php echo esc_textarea($example_html); ?></textarea></p>

            <style id="course-enroll-modal-admin-preview-css">
                #course-enroll-modal-admin-preview[hidden]{display:none!important;}
                #course-enroll-modal-admin-preview:not([hidden]){
                    display:flex!important;position:fixed;inset:0;z-index:100050;align-items:center;justify-content:center;
                    padding:16px;box-sizing:border-box;
                }
                #course-enroll-modal-admin-preview .course-program-reg-modal__overlay{
                    position:absolute;inset:0;background:rgba(15,15,20,.72);backdrop-filter:blur(4px);cursor:pointer;
                }
                #course-enroll-modal-admin-preview .course-program-reg-modal__panel{
                    position:relative;z-index:1;max-width:560px;width:100%;max-height:90vh;overflow:auto;
                    background:#fff;color:#1a1a1a;border-radius:12px;padding:28px 24px 24px;box-shadow:0 24px 64px rgba(0,0,0,.35);
                    margin:0;
                }
                #course-enroll-modal-admin-preview .course-program-reg-modal__close{
                    position:absolute;top:12px;right:14px;border:0;background:transparent;font-size:28px;line-height:1;
                    cursor:pointer;color:#666;padding:4px 8px;
                }
                #course-enroll-modal-admin-preview .course-program-reg-modal__close:hover{color:#111;}
                #course-enroll-modal-admin-preview .course-program-reg-modal__title{font-size:1.15rem;font-weight:700;margin:0 0 8px;letter-spacing:.02em;line-height:1.3;}
                #course-enroll-modal-admin-preview .course-program-reg-modal__lead{font-size:.95rem;color:#444;margin:0 0 20px;font-weight:600;}
                #course-enroll-modal-admin-preview .course-program-reg-modal__step{margin-bottom:18px;}
                #course-enroll-modal-admin-preview .course-program-reg-modal__step-title{font-size:.9rem;font-weight:700;margin:0 0 8px;color:#68202d;}
                #course-enroll-modal-admin-preview .course-program-reg-modal__text{font-size:.88rem;line-height:1.55;margin:0;color:#333;}
                #course-enroll-modal-admin-preview .course-program-reg-modal__body--custom{font-size:.88rem;line-height:1.55;color:#333;}
                #course-enroll-modal-admin-preview .course-program-reg-modal__body--custom .course-program-reg-modal__title:first-child{margin-top:0;}
                #course-enroll-modal-admin-preview .course-program-reg-modal__body--custom h1,
                #course-enroll-modal-admin-preview .course-program-reg-modal__body--custom h2,
                #course-enroll-modal-admin-preview .course-program-reg-modal__body--custom h3{font-size:1.05rem;font-weight:700;margin:1em 0 .5em;color:#68202d;}
                #course-enroll-modal-admin-preview .course-program-reg-modal__body--custom h1:first-child,
                #course-enroll-modal-admin-preview .course-program-reg-modal__body--custom h2:first-child,
                #course-enroll-modal-admin-preview .course-program-reg-modal__body--custom h3:first-child{margin-top:0;}
                #course-enroll-modal-admin-preview .course-program-reg-modal__body--custom p{margin:0 0 .85em;}
                #course-enroll-modal-admin-preview .course-program-reg-modal__body--custom ul,
                #course-enroll-modal-admin-preview .course-program-reg-modal__body--custom ol{margin:.5em 0 1em 1.25em;}
                #course-enroll-modal-admin-preview .course-program-reg-modal__actions{margin:24px 0 0;text-align:center;}
                #course-enroll-modal-admin-preview .course-program-reg-modal__cta{
                    display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:14px 28px;
                    background:linear-gradient(135deg,#68202d,#a13d4c);color:#fff!important;text-decoration:none;border-radius:8px;
                    font-weight:600;font-size:.95rem;cursor:default;pointer-events:none;opacity:1;
                }
                #course-enroll-modal-preview-hint{
                    display:none;margin:0 0 14px;padding:8px 12px;background:#f0f6fc;border-left:4px solid #2271b1;font-size:13px;line-height:1.45;color:#1d2327;
                }
                body.course-enroll-modal-admin-preview-open{overflow:hidden!important;}
                .course-enroll-modal-submit-row .button{margin-right:8px;}
            </style>
            <div id="course-enroll-modal-admin-preview" hidden aria-hidden="true">
                <div class="course-program-reg-modal__overlay" data-course-enroll-preview-close tabindex="-1" role="presentation"></div>
                <div class="course-program-reg-modal__panel" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr__('Предпросмотр окна записи', 'course-plugin'); ?>">
                    <button type="button" class="course-program-reg-modal__close" data-course-enroll-preview-close aria-label="<?php echo esc_attr__('Закрыть предпросмотр', 'course-plugin'); ?>">&times;</button>
                    <p id="course-enroll-modal-preview-hint"></p>
                    <div id="course-enroll-modal-preview-body"></div>
                    <p class="course-program-reg-modal__actions">
                        <span class="course-program-reg-modal__cta" id="course-enroll-modal-preview-cta"><?php echo esc_html__('Приступить к регистрации', 'course-plugin'); ?></span>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }
}
