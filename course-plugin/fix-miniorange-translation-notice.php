<?php
/**
 * Plugin Name: Исправление уведомления miniorange-login-openid
 * Description: Подавляет уведомление о ранней загрузке переводов для плагина miniorange-login-openid
 * Version: 1.0.0
 * Author: Кузьменко Валентин (Valentink2410)
 * 
 * Этот плагин исправляет уведомление WordPress 6.7.0+ о ранней загрузке переводов
 * для плагина miniorange-login-openid.
 * 
 * Уведомление: "Function _load_textdomain_just_in_time was called incorrectly"
 * 
 * Это не критическая ошибка, но уведомление может быть раздражающим.
 * Данный плагин подавляет это конкретное уведомление.
 */

// Проверка безопасности
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Подавляем уведомление о ранней загрузке переводов для miniorange-login-openid
 * 
 * Фильтр 'doing_it_wrong_trigger_error' позволяет контролировать,
 * какие уведомления о неправильном использовании функций будут показаны.
 */
add_filter('doing_it_wrong_trigger_error', function($trigger, $function_name, $message) {
    // Проверяем, что это уведомление о загрузке переводов
    if ($function_name === '_load_textdomain_just_in_time') {
        // Проверяем, что сообщение относится к miniorange-login-openid
        if (is_string($message) && strpos($message, 'miniorange-login-openid') !== false) {
            // Подавляем это конкретное уведомление
            return false;
        }
    }
    // Для всех остальных случаев возвращаем исходное значение
    return $trigger;
}, 10, 3);

// Дополнительная защита через фильтр doing_it_wrong_run (для WordPress 6.7.0+)
add_filter('doing_it_wrong_run', function($run, $function_name, $message) {
    if ($function_name === '_load_textdomain_just_in_time') {
        if (is_string($message) && strpos($message, 'miniorange-login-openid') !== false) {
            return false; // Не запускаем уведомление
        }
    }
    return $run;
}, 10, 3);

/**
 * Дополнительная защита: подавляем уведомление через error_handler
 * Это работает на более низком уровне и перехватывает уведомление до его вывода
 */
if (!function_exists('suppress_miniorange_notice')) {
    function suppress_miniorange_notice($errno, $errstr, $errfile, $errline) {
        // Проверяем, что это уведомление о miniorange-login-openid
        if ($errno === E_USER_NOTICE && 
            strpos($errstr, 'miniorange-login-openid') !== false &&
            strpos($errstr, '_load_textdomain_just_in_time') !== false) {
            // Подавляем уведомление
            return true;
        }
        // Для всех остальных уведомлений возвращаем false, чтобы WordPress обработал их стандартным образом
        return false;
    }
    
    // Устанавливаем обработчик ошибок с высоким приоритетом
    set_error_handler('suppress_miniorange_notice', E_USER_NOTICE);
}
