<?php
/**
 * Вспомогательные функции форматирования для фронтенда каталога.
 *
 * @package Course_Plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Локализованный диапазон дат для карточек (месяц — полное название через date_i18n).
 *
 * Если начало и конец в разных календарных годах, выводятся оба года.
 *
 * @param int|false $start_ts Unix timestamp начала.
 * @param int|false $end_ts Unix timestamp окончания или false/null.
 * @param string $middle_sep Разделитель между частями диапазона (например « – » или « — »).
 * @return string
 */
function course_plugin_format_catalog_date_range($start_ts, $end_ts, $middle_sep = ' – ') {
    if (!$start_ts) {
        return '';
    }

    $sd = date('j', $start_ts);
    $sm = date('m', $start_ts);
    $sy = date('Y', $start_ts);

    if (!$end_ts) {
        return sprintf(__('%s %s %s', 'course-plugin'), $sd, date_i18n('F', $start_ts), $sy);
    }

    $ed = date('j', $end_ts);
    $em = date('m', $end_ts);
    $ey = date('Y', $end_ts);

    if ($sm === $em && $sy === $ey) {
        return sprintf(__('%s–%s %s %s', 'course-plugin'), $sd, $ed, date_i18n('F', $start_ts), $sy);
    }

    if ($sy === $ey) {
        /* translators: 1: start day, 2: start month name, 3: separator between range parts, 4: end day, 5: end month name, 6: year */
        return sprintf(__('%1$s %2$s %3$s %4$s %5$s %6$s', 'course-plugin'), $sd, date_i18n('F', $start_ts), $middle_sep, $ed, date_i18n('F', $end_ts), $sy);
    }

    /* translators: 1: start day, 2: start month name, 3: start year, 4: separator, 5: end day, 6: end month name, 7: end year */
    return sprintf(__('%1$s %2$s %3$s %4$s %5$s %6$s %7$s', 'course-plugin'), $sd, date_i18n('F', $start_ts), $sy, $middle_sep, $ed, date_i18n('F', $end_ts), $ey);
}

/**
 * Сколько записей на странице каталога (курсы/программы) по GET-параметрам.
 *
 * Поддержка: show_all=1, per_page=all — все результаты; иначе число 1–200; по умолчанию $default (чётное, удобно для сетки из 3 колонок).
 *
 * @param int    $default Значение по умолчанию (например 20).
 * @param string $context Контекст для фильтра course_plugin_catalog_posts_per_page_default.
 * @return int Положительное число или -1 (без пагинации).
 */
function course_plugin_get_catalog_posts_per_page_from_request($default = 20, $context = 'course') {
    $default = max(1, (int) apply_filters('course_plugin_catalog_posts_per_page_default', (int) $default, $context));

    if (isset($_GET['show_all']) && sanitize_text_field(wp_unslash($_GET['show_all'])) === '1') {
        return -1;
    }

    if (!isset($_GET['per_page'])) {
        return $default;
    }

    $raw = wp_unslash($_GET['per_page']);
    if (is_string($raw) && strtolower($raw) === 'all') {
        return -1;
    }

    $n = (int) $raw;
    if ($n < 1) {
        return $default;
    }

    return min(200, max(1, $n));
}
