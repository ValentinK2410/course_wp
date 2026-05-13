<?php
/**
 * Настройки страницы одиночного мероприятия Unicamp (post type tp_event):
 * текст «О мероприятии», скрытие регистрации/стоимости/мест и комментариев.
 *
 * @package Course_Plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Tweaks для tp_event на фронте.
 */
class Course_Tp_Event_Tweaks {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'), 99);
        add_filter('comments_open', array($this, 'comments_open_tp_event'), 50, 2);
        add_filter('comments_array', array($this, 'empty_comments_tp_event'), 50, 2);
        add_action('template_redirect', array($this, 'maybe_start_full_page_buffer'), -99998);
    }

    /**
     * @return bool
     */
    private function is_frontend_tp_event_single() {
        return !is_admin() && !wp_doing_ajax() && function_exists('is_singular') && is_singular('tp_event');
    }

    public function enqueue_styles() {
        if (!$this->is_frontend_tp_event_single()) {
            return;
        }

        wp_enqueue_style(
            'course-tp-event-tweaks',
            COURSE_PLUGIN_URL . 'assets/css/tp-event-tweaks.css',
            array(),
            COURSE_PLUGIN_VERSION
        );
    }

    /**
     * @param bool       $open
     * @param int|string $post_id
     * @return bool
     */
    public function comments_open_tp_event($open, $post_id) {
        if ($post_id && get_post_type((int) $post_id) === 'tp_event') {
            return false;
        }
        return $open;
    }

    /**
     * @param array      $comments
     * @param int|string $post_id
     * @return array
     */
    public function empty_comments_tp_event($comments, $post_id) {
        if ($post_id && get_post_type((int) $post_id) === 'tp_event') {
            return array();
        }
        return $comments;
    }

    /**
     * Подмена заголовка «О курсе» через буфер: в шаблоне темы текст может быть без gettext.
     */
    public function maybe_start_full_page_buffer() {
        if (!$this->is_frontend_tp_event_single()) {
            return;
        }
        if (function_exists('wp_is_json_request') && wp_is_json_request()) {
            return;
        }
        ob_start(array(__CLASS__, 'filter_full_html'));
    }

    /**
     * @param string $html
     * @return string
     */
    public static function filter_full_html($html) {
        if (!is_string($html) || $html === '') {
            return $html;
        }

        $heading_safe = wp_specialchars(__('О мероприятии', 'course-plugin'), ENT_NOQUOTES, 'UTF-8');

        $out = preg_replace_callback(
            '/(<h3\s+[^>]*class="[^"]*entry-event-heading[^"]*"[^>]*>)(\s*)О\s*курсе(\s*)(<\/h3>)/ui',
            static function ($m) use ($heading_safe) {
                return $m[1] . $m[2] . $heading_safe . $m[3] . $m[4];
            },
            $html,
            1
        );

        return is_string($out) ? $out : $html;
    }
}
