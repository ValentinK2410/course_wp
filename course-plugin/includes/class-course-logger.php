<?php
/**
 * Класс для логирования событий плагина
 * Записывает логи в файл плагина, даже если WP_DEBUG не включен
 */

if (!defined('ABSPATH')) {
    exit;
}

class Course_Logger {
    
    /**
     * Путь к файлу логов
     */
    private static $log_file = null;
    
    /**
     * Получить путь к файлу логов
     */
    private static function get_log_file() {
        if (self::$log_file === null) {
            $upload_dir = wp_upload_dir();
            $log_dir = $upload_dir['basedir'] . '/course-plugin-logs';
            
            // Создаем директорию для логов, если её нет
            if (!file_exists($log_dir)) {
                wp_mkdir_p($log_dir);
            }
            
            self::$log_file = $log_dir . '/sync-' . date('Y-m-d') . '.log';
        }
        
        return self::$log_file;
    }
    
    /**
     * Записать сообщение в лог
     * 
     * @param string $message Сообщение для записи
     * @param string $level Уровень логирования (INFO, ERROR, WARNING)
     */
    public static function log($message, $level = 'INFO') {
        $log_file = self::get_log_file();
        $timestamp = date('Y-m-d H:i:s');
        $log_message = "[{$timestamp}] [{$level}] {$message}\n";
        
        // Записываем в файл
        @file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX);
        
        // Также записываем в стандартный лог WordPress, если включен WP_DEBUG
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Course Plugin [{$level}]: {$message}");
        }
    }
    
    /**
     * Записать информационное сообщение
     */
    public static function info($message) {
        self::log($message, 'INFO');
    }
    
    /**
     * Записать сообщение об ошибке
     */
    public static function error($message) {
        self::log($message, 'ERROR');
    }
    
    /**
     * Записать предупреждение
     */
    public static function warning($message) {
        self::log($message, 'WARNING');
    }
    
    /**
     * Получить последние N строк лога
     */
    public static function get_last_lines($lines = 50) {
        $log_file = self::get_log_file();
        
        if (!file_exists($log_file)) {
            return array();
        }
        
        $content = file_get_contents($log_file);
        $all_lines = explode("\n", $content);
        $all_lines = array_filter($all_lines); // Удаляем пустые строки
        
        return array_slice($all_lines, -$lines);
    }
}



















