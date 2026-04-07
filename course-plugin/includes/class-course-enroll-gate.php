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
        add_rewrite_rule('^moodle/?$', 'index.php?moodle_sso=1', 'top');
        add_filter('query_vars', array($this, 'add_query_vars'));
        add_action('template_redirect', array($this, 'handle_enroll_gate'), 1);
        add_action('template_redirect', array($this, 'handle_moodle_sso'), 1);
        add_filter('wp_nav_menu_objects', array($this, 'filter_menu_moodle_links'), 10, 2);
    }

    public function add_query_vars($vars) {
        $vars[] = 'enroll_gate';
        $vars[] = 'enroll_url';
        $vars[] = 'enroll_program';
        $vars[] = 'enroll_course';
        $vars[] = 'moodle_sso';
        return $vars;
    }

    /**
     * Заменяет ссылки на Moodle в меню: «Виртуальный класс» → /moodle/ (SSO без /enroll/)
     */
    public function filter_menu_moodle_links($items, $args) {
        $moodle_url = rtrim(get_option('moodle_sync_url', ''), '/');
        if (empty($moodle_url)) {
            return $items;
        }
        $moodle_host = parse_url($moodle_url, PHP_URL_HOST);
        if (!$moodle_host) {
            return $items;
        }
        foreach ($items as $item) {
            if (empty($item->url)) {
                continue;
            }
            $item_host = parse_url($item->url, PHP_URL_HOST);
            if ($item_host && (strtolower($item_host) === strtolower($moodle_host))) {
                $path = parse_url($item->url, PHP_URL_PATH);
                $is_login_or_root = (!$path || $path === '/' || strpos($path, '/login/') !== false);
                if ($is_login_or_root) {
                    $item->url = home_url('/moodle/');
                } else {
                    $item->url = self::get_enroll_url($item->url);
                }
            }
        }
        return $items;
    }

    /**
     * Обработка /moodle/ — SSO в Moodle (главная), без /enroll/
     */
    public function handle_moodle_sso() {
        if ((int) get_query_var('moodle_sso') !== 1) {
            return;
        }
        $moodle_url = rtrim(get_option('moodle_sync_url', ''), '/');
        if (empty($moodle_url)) {
            wp_redirect(home_url('/'));
            exit;
        }
        if (!is_user_logged_in()) {
            wp_redirect(wp_login_url(home_url('/moodle/')));
            exit;
        }
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
            $sso_redirect = $moodle_url . '/sso-login.php?token=' . rawurlencode($moodle_token) . '&redirect=' . rawurlencode('/');
            wp_redirect($sso_redirect);
            exit;
        }
        wp_redirect($moodle_url . '/');
        exit;
    }

    /**
     * Сформировать URL шлюза записи
     *
     * @param string $target_url  Целевой URL в Moodle.
     * @param int    $program_id  ID поста программы (для записи в когорту и мета организатора).
     * @param int    $course_id   ID поста курса (для мета организатора).
     */
    public static function get_enroll_url($target_url, $program_id = 0, $course_id = 0) {
        if (empty($target_url)) {
            return '';
        }
        $args = array(
            'enroll_gate' => '1',
            'enroll_url'  => base64_encode($target_url),
        );
        if ($program_id > 0) {
            $args['enroll_program'] = (int) $program_id;
        }
        if ($course_id > 0) {
            $args['enroll_course'] = (int) $course_id;
        }
        return add_query_arg($args, home_url('/enroll/'));
    }

    /**
     * URL регистрации с сохранением возврата (курс/программа или шлюз записи).
     *
     * @param string $redirect_url Куда вернуть пользователя после регистрации и подтверждения email.
     */
    public static function get_registration_url_with_redirect($redirect_url) {
        $redirect_url = wp_validate_redirect($redirect_url, home_url('/'));
        return add_query_arg('redirect_to', $redirect_url, wp_registration_url());
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

        $program_id = (int) (get_query_var('enroll_program') ?: (isset($_GET['enroll_program']) ? absint($_GET['enroll_program']) : 0));
        $course_id  = (int) (get_query_var('enroll_course') ?: (isset($_GET['enroll_course']) ? absint($_GET['enroll_course']) : 0));
        $return_args = array(
            'enroll_gate' => '1',
            'enroll_url'  => $target_encoded,
        );
        if ($program_id > 0) {
            $return_args['enroll_program'] = $program_id;
        }
        if ($course_id > 0) {
            $return_args['enroll_course'] = $course_id;
        }
        $current_enroll_url = add_query_arg($return_args, home_url('/enroll/'));

        // Не авторизован — перенаправляем на вход с возвратом сюда
        if (!is_user_logged_in()) {
            $login_url = wp_login_url($current_enroll_url);
            // Cookie: если redirect_to потеряется при регистрации, сохраним URL шлюза записи
            if (!headers_sent()) {
                setcookie(
                    'course_pending_enroll',
                    $current_enroll_url,
                    time() + DAY_IN_SECONDS,
                    COOKIEPATH,
                    COOKIE_DOMAIN,
                    is_ssl(),
                    true
                );
            }
            wp_redirect($login_url);
            exit;
        }

        // Запись в когорты Moodle (программа и/или курс), если заданы мета-поля
        $this->maybe_enroll_in_cohort();

        $email_confirmed_flash = isset($_GET['email_confirmed']) && (string) $_GET['email_confirmed'] === '1';

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
                // rawurlencode для токена: base64 содержит + и /, которые могут искажаться в URL
                $sso_base = $moodle_url . '/sso-login.php';
                $profile_mbs = self::should_require_moodle_profile_mbs($program_id, $course_id);
                $sso_redirect = $sso_base . '?token=' . rawurlencode($moodle_token) . '&redirect=' . rawurlencode($redirect_path);
                if ($profile_mbs) {
                    $sso_redirect .= '&profile_mbs=1';
                }
                if ($email_confirmed_flash && apply_filters('course_show_email_confirmed_interstitial', true, $user_id)) {
                    $this->output_email_confirmed_interstitial($sso_redirect);
                    exit;
                }
                wp_redirect($sso_redirect);
                exit;
            }
        }

        wp_redirect($target_url);
        exit;
    }

    /**
     * Краткий экран после подтверждения email (до перехода в Moodle по SSO).
     *
     * @param string $sso_redirect_url Полный URL sso-login.php.
     */
    private function output_email_confirmed_interstitial($sso_redirect_url) {
        header('Content-Type: text/html; charset=UTF-8');
        nocache_headers();
        $url = esc_url($sso_redirect_url);
        $msg = esc_html__(
            'Email подтверждён — мы убедились, что вы настоящий человек. Сейчас откроется виртуальный класс…',
            'course-plugin'
        );
        $link_text = __('Перейти сейчас', 'course-plugin');
        echo '<!DOCTYPE html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<meta http-equiv="refresh" content="2;url=' . esc_attr($url) . '">';
        echo '<title>' . esc_html__('Подтверждение email', 'course-plugin') . '</title>';
        echo '<style>body{font-family:system-ui,-apple-system,sans-serif;max-width:28rem;margin:3rem auto;padding:0 1.25rem;line-height:1.55;color:#1a1a1a;background:#fafafa;}';
        echo '.box{background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:1.25rem 1.35rem;box-shadow:0 2px 8px rgba(0,0,0,.06);}';
        echo '.box p{margin:0 0 1rem;}a{color:#1565c0;}</style></head><body><div class="box"><p>' . $msg . '</p>';
        echo '<p><a href="' . $url . '">' . esc_html($link_text) . '</a></p></div></body></html>';
    }

    /**
     * Запись в когорты Moodle для программы и/или курса (если в мета заданы ID когорт).
     * Вызывается из handle_enroll_gate() при наличии enroll_program / enroll_course в запросе.
     */
    private function maybe_enroll_in_cohort() {
        $program_id = (int) (get_query_var('enroll_program') ?: (isset($_GET['enroll_program']) ? absint($_GET['enroll_program']) : 0));
        $course_id  = (int) (get_query_var('enroll_course') ?: (isset($_GET['enroll_course']) ? absint($_GET['enroll_course']) : 0));

        if ($program_id > 0) {
            $cohort_id = (int) get_post_meta($program_id, '_program_moodle_cohort_id', true);
            if ($cohort_id > 0) {
                $this->enroll_user_in_moodle_cohort($cohort_id, 'program', $program_id);
            } else {
                error_log('Enroll Gate: когорта не привязана к программе ID=' . $program_id);
            }
        }

        if ($course_id > 0) {
            $cohort_id = (int) get_post_meta($course_id, '_course_moodle_cohort_id', true);
            if ($cohort_id > 0) {
                $this->enroll_user_in_moodle_cohort($cohort_id, 'course', $course_id);
            } else {
                error_log('Enroll Gate: когорта не привязана к курсу ID=' . $course_id);
            }
        }
    }

    /**
     * @param int    $cohort_id   ID когорты в Moodle.
     * @param string $entity_type 'program' | 'course'.
     * @param int    $entity_id   ID поста program или course.
     */
    private function enroll_user_in_moodle_cohort($cohort_id, $entity_type, $entity_id) {
        $user_id = get_current_user_id();
        if (!$user_id || $cohort_id <= 0) {
            return;
        }

        if (!class_exists('Course_Moodle_User_Sync')) {
            error_log('Enroll Gate: Course_Moodle_User_Sync недоступен');
            return;
        }

        $sync = Course_Moodle_User_Sync::get_instance();
        $result = $sync->add_user_to_program_cohort($user_id, $cohort_id);

        if ($result) {
            if ($entity_type === 'program') {
                $enrolled = get_user_meta($user_id, 'enrolled_programs', true);
                if (!is_array($enrolled)) {
                    $enrolled = array();
                }
                if (!in_array($entity_id, $enrolled, true)) {
                    $enrolled[] = $entity_id;
                    update_user_meta($user_id, 'enrolled_programs', $enrolled);
                }
                error_log('Enroll Gate: пользователь ' . $user_id . ' записан в когорту ' . $cohort_id . ' (программа ' . $entity_id . ')');
            } else {
                $enrolled = get_user_meta($user_id, 'enrolled_courses', true);
                if (!is_array($enrolled)) {
                    $enrolled = array();
                }
                if (!in_array($entity_id, $enrolled, true)) {
                    $enrolled[] = $entity_id;
                    update_user_meta($user_id, 'enrolled_courses', $enrolled);
                }
                error_log('Enroll Gate: пользователь ' . $user_id . ' записан в когорту ' . $cohort_id . ' (курс ' . $entity_id . ')');
            }
        } else {
            error_log('Enroll Gate: не удалось записать пользователя ' . $user_id . ' в когорту ' . $cohort_id);
        }
    }

    /**
     * Нужно ли проверять заполнение профиля в Moodle (организатор МБС Москва).
     *
     * @param int $program_id ID поста program.
     * @param int $course_id  ID поста course.
     * @return bool
     */
    public static function should_require_moodle_profile_mbs($program_id, $course_id) {
        if ($program_id > 0) {
            $org = get_post_meta($program_id, '_program_organizer', true);
            return ($org === 'mbs_moscow');
        }
        if ($course_id > 0) {
            $org = get_post_meta($course_id, '_course_organizer', true);
            return ($org === 'mbs_moscow');
        }
        return false;
    }
}
