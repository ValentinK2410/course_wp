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
