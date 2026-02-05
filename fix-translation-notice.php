<?php
/**
 * Исправление уведомления о ранней загрузке переводов
 * 
 * Этот код нужно добавить в functions.php активной темы
 * для подавления уведомления от плагина miniorange-login-openid
 * 
 * Проблема: плагин miniorange-login-openid загружает переводы слишком рано
 * Решение: подавляем уведомление через фильтр WordPress
 */

// Подавляем уведомление о ранней загрузке переводов для miniorange-login-openid
add_filter('doing_it_wrong_trigger_error', function($trigger, $function_name, $message) {
    // Проверяем, что это уведомление о загрузке переводов
    if ($function_name === '_load_textdomain_just_in_time') {
        // Проверяем, что сообщение относится к miniorange-login-openid
        if (strpos($message, 'miniorange-login-openid') !== false) {
            return false; // Подавляем уведомление
        }
    }
    return $trigger;
}, 10, 3);

// Альтернативный вариант - более простой, но менее точный
// Раскомментируйте, если первый вариант не работает:
/*
add_action('init', function() {
    // Подавляем уведомления о doing_it_wrong для загрузки переводов
    if (function_exists('_doing_it_wrong')) {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        foreach ($backtrace as $trace) {
            if (isset($trace['function']) && $trace['function'] === '_load_textdomain_just_in_time') {
                if (isset($trace['file']) && strpos($trace['file'], 'miniorange-login-openid') !== false) {
                    // Подавляем это конкретное уведомление
                    return;
                }
            }
        }
    }
}, 1);
*/
