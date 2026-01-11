<?php
/**
 * Класс для транслитерации кириллицы в латиницу
 * 
 * Автоматически переводит кириллические символы в латиницу при создании:
 * - Курсов (для slug'ов в URL)
 * - Категорий и таксономий (специализация, уровень, тема, преподаватель)
 * 
 * Это улучшает SEO и делает URL более читаемыми
 * 
 * @copyright Copyright (c) 2024 Кузьменко Валентин (Valentink2410)
 * @author Кузьменко Валентин (Valentink2410)
 */

// Проверка безопасности: если файл вызывается напрямую, прекращаем выполнение
if (!defined('ABSPATH')) {
    exit;
}

class Course_Transliteration {
    
    /**
     * Единственный экземпляр класса (Singleton)
     */
    private static $instance = null;
    
    /**
     * Таблица соответствия кириллических символов латинским
     * Используется для транслитерации русских букв
     */
    private $transliteration_table = array(
        // Русские буквы -> латинские
        'А' => 'A', 'а' => 'a',
        'Б' => 'B', 'б' => 'b',
        'В' => 'V', 'в' => 'v',
        'Г' => 'G', 'г' => 'g',
        'Д' => 'D', 'д' => 'd',
        'Е' => 'E', 'е' => 'e',
        'Ё' => 'Yo', 'ё' => 'yo',
        'Ж' => 'Zh', 'ж' => 'zh',
        'З' => 'Z', 'з' => 'z',
        'И' => 'I', 'и' => 'i',
        'Й' => 'Y', 'й' => 'y',
        'К' => 'K', 'к' => 'k',
        'Л' => 'L', 'л' => 'l',
        'М' => 'M', 'м' => 'm',
        'Н' => 'N', 'н' => 'n',
        'О' => 'O', 'о' => 'o',
        'П' => 'P', 'п' => 'p',
        'Р' => 'R', 'р' => 'r',
        'С' => 'S', 'с' => 's',
        'Т' => 'T', 'т' => 't',
        'У' => 'U', 'у' => 'u',
        'Ф' => 'F', 'ф' => 'f',
        'Х' => 'Kh', 'х' => 'kh',
        'Ц' => 'Ts', 'ц' => 'ts',
        'Ч' => 'Ch', 'ч' => 'ch',
        'Ш' => 'Sh', 'ш' => 'sh',
        'Щ' => 'Shch', 'щ' => 'shch',
        'Ъ' => '', 'ъ' => '',  // Твердый знак не транслитерируется
        'Ы' => 'Y', 'ы' => 'y',
        'Ь' => '', 'ь' => '',  // Мягкий знак не транслитерируется
        'Э' => 'E', 'э' => 'e',
        'Ю' => 'Yu', 'ю' => 'yu',
        'Я' => 'Ya', 'я' => 'ya',
    );
    
    /**
     * Получить экземпляр класса
     * 
     * @return Course_Transliteration Экземпляр класса
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Конструктор класса
     * Регистрирует фильтры для автоматической транслитерации
     */
    private function __construct() {
        // Фильтр для транслитерации заголовков в slug'и
        // Применяется ко всем типам постов и таксономиям
        // Приоритет 10 означает, что наша функция выполнится до стандартной обработки WordPress
        add_filter('sanitize_title', array($this, 'transliterate_title'), 10, 3);
        
        // Фильтр для обработки данных поста перед сохранением
        // Позволяет автоматически создавать slug из кириллического заголовка
        add_filter('wp_insert_post_data', array($this, 'transliterate_post_slug'), 10, 2);
        
        // Фильтр для обработки данных термина перед сохранением
        // Автоматически транслитерирует название категории/таксономии в slug
        add_filter('wp_insert_term_data', array($this, 'transliterate_term_data'), 10, 3);
    }
    
    /**
     * Транслитерация заголовка в slug
     * Вызывается при создании slug'а из заголовка (для постов и терминов)
     * 
     * @param string $title Заголовок, который нужно транслитерировать
     * @param string $raw_title Оригинальный заголовок (не обработанный)
     * @param string $context Контекст использования (обычно 'save')
     * @return string Транслитерированный заголовок
     */
    public function transliterate_title($title, $raw_title = '', $context = 'save') {
        // Если заголовок пустой, возвращаем его как есть
        if (empty($raw_title)) {
            $raw_title = $title;
        }
        
        // Если заголовок уже содержит только латинские символы, цифры и дефисы, возвращаем как есть
        if (preg_match('/^[a-z0-9\-]+$/i', $raw_title)) {
            return $title;
        }
        
        // Транслитерируем кириллицу в латиницу
        $transliterated = $this->transliterate($raw_title);
        
        // Если транслитерация дала результат, используем его
        if (!empty($transliterated) && $transliterated !== $raw_title) {
            // Применяем стандартную функцию WordPress sanitize_title для финальной обработки
            // Она преобразует в нижний регистр, заменяет пробелы на дефисы и удаляет спецсимволы
            return sanitize_title($transliterated);
        }
        
        // Если транслитерация не изменила строку, возвращаем оригинальный результат
        return $title;
    }
    
    /**
     * Транслитерация slug'а поста при сохранении
     * Автоматически создает slug из кириллического заголовка курса
     * 
     * @param array $data Массив данных поста перед сохранением
     * @param array $postarr Массив с данными поста из формы
     * @return array Измененный массив данных поста
     */
    public function transliterate_post_slug($data, $postarr) {
        // Применяем транслитерацию только для типа поста 'course'
        if (isset($data['post_type']) && $data['post_type'] === 'course') {
            // Если slug не задан вручную, создаем его из заголовка
            if (empty($data['post_name']) && !empty($data['post_title'])) {
                // Транслитерируем заголовок и создаем slug
                $transliterated = $this->transliterate($data['post_title']);
                $data['post_name'] = sanitize_title($transliterated);
            }
            // Если slug задан вручную, но содержит кириллицу, транслитерируем его
            elseif (!empty($data['post_name']) && preg_match('/[а-яё]/iu', $data['post_name'])) {
                $transliterated = $this->transliterate($data['post_name']);
                $data['post_name'] = sanitize_title($transliterated);
            }
        }
        
        return $data;
    }
    
    /**
     * Транслитерация данных термина при сохранении
     * Автоматически создает slug из кириллического названия категории/таксономии
     * 
     * @param array $data Массив данных термина (name, slug, term_group)
     * @param string $taxonomy Название таксономии
     * @param array $args Дополнительные аргументы
     * @return array Измененный массив данных термина
     */
    public function transliterate_term_data($data, $taxonomy, $args) {
        // Применяем транслитерацию только для таксономий плагина курсов
        $course_taxonomies = array(
            'course_specialization',
            'course_level',
            'course_topic',
            'course_teacher'
        );
        
        if (!in_array($taxonomy, $course_taxonomies)) {
            return $data;
        }
        
        // Если slug не задан вручную или пустой, создаем его из названия
        if (empty($data['slug']) && !empty($data['name'])) {
            // Проверяем, содержит ли название кириллицу
            if (preg_match('/[а-яё]/iu', $data['name'])) {
                // Транслитерируем название
                $transliterated = $this->transliterate($data['name']);
                // Применяем sanitize_title для создания правильного slug'а
                $data['slug'] = sanitize_title($transliterated);
            }
        }
        // Если slug задан вручную, но содержит кириллицу, транслитерируем его
        elseif (!empty($data['slug']) && preg_match('/[а-яё]/iu', $data['slug'])) {
            $transliterated = $this->transliterate($data['slug']);
            $data['slug'] = sanitize_title($transliterated);
        }
        
        return $data;
    }
    
    /**
     * Основная функция транслитерации кириллицы в латиницу
     * Преобразует русские буквы в соответствующие латинские символы
     * 
     * @param string $text Текст для транслитерации
     * @return string Транслитерированный текст
     */
    private function transliterate($text) {
        // Если текст пустой, возвращаем его как есть
        if (empty($text)) {
            return $text;
        }
        
        // Заменяем кириллические символы на латинские согласно таблице соответствия
        $transliterated = strtr($text, $this->transliteration_table);
        
        // Заменяем множественные пробелы на один
        $transliterated = preg_replace('/\s+/', ' ', $transliterated);
        
        // Удаляем пробелы в начале и конце строки
        $transliterated = trim($transliterated);
        
        return $transliterated;
    }
}

