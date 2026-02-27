<?php
/**
 * Шлюз записи на курс/программу — требует авторизацию перед переходом в Moodle
 *
 * @copyright Copyright (c) 2024 Кузьменко Валентин (Valentink2410)
 * @author Кузьменко Валентин (Valentink2410)
 */

if (!defined('ABSPATH')) {
    exit;
}

class Course_Enroll_Gate {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_rewrite_rule('^enroll/?$', 'index.php?enroll_gate=1', 'top');
        add_filter('query_vars', array($this, 'add_query_vars'));
        add_action('template_redirect', array($this, 'handle_enroll_gate'), 1);
    }

    public function add_query_vars($vars) {
        $vars[] = 'enroll_gate';
        $vars[] = 'enroll_url';
        return $vars;
    }

    /**
     * Сформировать URL шлюза записи
     */
    public static function get_enroll_url($target_url) {
        if (empty($target_url)) {
            return '';
        }
        return add_query_arg(array(
            'enroll_gate' => '1',
            'enroll_url' => base64_encode($target_url),
        ), home_url('/enroll/'));
    }

    /**
     * Обработка /enroll/
     */
    public function handle_enroll_gate() {
        if ((int) get_query_var('enroll_gate') !== 1) {
            return;
        }

        $target_encoded = get_query_var('enroll_url') ?: (isset($_GET['enroll_url']) ? sanitize_text_field($_GET['enroll_url']) : '');
        if (empty($target_encoded)) {
            wp_die(__('Не указана ссылка для записи.', 'course-plugin'), '', array('response' => 400));
        }

        $target_url = base64_decode($target_encoded, true);
        if (!$target_url || !filter_var($target_url, FILTER_VALIDATE_URL)) {
            wp_die(__('Некорректная ссылка для записи.', 'course-plugin'), '', array('response' => 400));
        }

        $current_enroll_url = add_query_arg(array(
            'enroll_gate' => '1',
            'enroll_url' => $target_encoded,
        ), home_url('/enroll/'));

        // Не авторизован — перенаправляем на вход с возвратом сюда
        if (!is_user_logged_in()) {
            $login_url = wp_login_url($current_enroll_url);
            wp_redirect($login_url);
            exit;
        }

        // Авторизован — выполняем SSO в Moodle и переход на целевой URL
        $moodle_url = rtrim(get_option('moodle_sync_url', ''), '/');
        if (empty($moodle_url)) {
            wp_redirect($target_url);
            exit;
        }

        $moodle_host = parse_url($moodle_url, PHP_URL_HOST);
        $target_host = parse_url($target_url, PHP_URL_HOST);

        if ($moodle_host && $target_host && $moodle_host === $target_host) {
            $sso = Course_SSO::get_instance();
            $user_id = get_current_user_id();
            $moodle_token = get_user_meta($user_id, 'sso_moodle_token', true);
            $moodle_expires = get_user_meta($user_id, 'sso_moodle_token_expires', true);

            if (empty($moodle_token) || (int) $moodle_expires < time()) {
                $user = wp_get_current_user();
            $sso->generate_sso_tokens($user->user_login, $user);
                $moodle_token = get_user_meta($user_id, 'sso_moodle_token', true);
            }

            if (!empty($moodle_token)) {
                $target_path = parse_url($target_url, PHP_URL_PATH);
                $target_query = parse_url($target_url, PHP_URL_QUERY);
                $redirect_path = $target_path . ($target_query ? '?' . $target_query : '');
                $sso_redirect = add_query_arg(array(
                    'token' => $moodle_token,
                    'redirect' => $redirect_path,
                ), $moodle_url . '/sso-login.php');
                wp_redirect($sso_redirect);
                exit;
            }
        }

        wp_redirect($target_url);
        exit;
    }
}
