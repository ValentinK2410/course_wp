<?php
/**
 * Заголовок страницы записей («Ресурсы» /blog/): замена текста архива статей.
 *
 * @package Course_Plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Заголовок H1 и title для страницы списка записей (page_for_posts).
 */
class Course_Blog_Resources_Title {

    private static $instance = null;

    /** @var string Прежний заголовок на сайте (для замены в HTML/виджетах). */
    const OLD_HEADING = 'Новости и блоги';

    /**
     * @return self
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_filter('document_title_parts', array($this, 'document_title_parts'), 20);
        add_filter('the_title', array($this, 'filter_the_title'), 10, 2);
        add_action('template_redirect', array($this, 'maybe_start_output_buffer'), -99997);
    }

    /**
     * Страница со списком записей (не главная сайта и не одиночная запись).
     *
     * @return bool
     */
    private function is_blog_posts_listing() {
        return !is_admin()
            && !wp_doing_ajax()
            && function_exists('is_home')
            && is_home()
            && !is_front_page();
    }

    /**
     * @param array<string, string> $parts
     * @return array<string, string>
     */
    public function document_title_parts($parts) {
        if (!$this->is_blog_posts_listing() || !is_array($parts)) {
            return $parts;
        }
        $parts['title'] = __('Все ресурсы богословской семинарии', 'course-plugin');
        return $parts;
    }

    /**
     * Заголовок страницы «Записи» в меню и там, где выводится название страницы записей.
     *
     * @param string       $title
     * @param int|mixed    $post_id
     * @return string
     */
    public function filter_the_title($title, $post_id = null) {
        if (is_admin()) {
            return $title;
        }
        $posts_page = (int) get_option('page_for_posts');
        if (!$posts_page || (int) $post_id !== $posts_page) {
            return $title;
        }
        return __('Все ресурсы богословской семинарии', 'course-plugin');
    }

    /**
     * Elementor и темы могут не использовать the_title для hero — замена в разметке (ограниченно).
     */
    public function maybe_start_output_buffer() {
        if (!$this->is_blog_posts_listing()) {
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
        $new = __('Все ресурсы богословской семинарии', 'course-plugin');
        $old = self::OLD_HEADING;
        if ($old === '' || strpos($html, $old) === false) {
            return $html;
        }
        $out = preg_replace('/' . preg_quote($old, '/') . '/u', $new, $html, 8);
        return is_string($out) ? $out : $html;
    }
}
