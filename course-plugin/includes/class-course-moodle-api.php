<?php
/**
 * Класс для работы с Moodle REST API
 * 
 * Этот класс предоставляет методы для взаимодействия с Moodle через REST API
 * Использует токен для аутентификации и выполняет запросы к различным функциям Moodle
 * 
 * @copyright Copyright (c) 2024 Кузьменко Валентин (Valentink2410)
 * @author Кузьменко Валентин (Valentink2410)
 */

// Проверка безопасности: если файл вызывается напрямую, прекращаем выполнение
if (!defined('ABSPATH')) {
    exit;
}

class Course_Moodle_API {
    
    /**
     * URL сайта Moodle (например: https://class.dekan.pro)
     * 
     * @var string
     */
    private $url;
    
    /**
     * Токен для доступа к Moodle REST API
     * Токен можно получить в настройках Moodle: Site administration -> Plugins -> Web services -> Manage tokens
     * 
     * @var string
     */
    private $token;
    
    /**
     * Конструктор класса
     * Инициализирует URL и токен для работы с Moodle API
     * 
     * @param string $url URL сайта Moodle
     * @param string $token Токен для доступа к REST API
     */
    public function __construct($url, $token) {
        // Удаляем завершающий слэш из URL, если он есть
        // rtrim() удаляет указанные символы с конца строки
        $this->url = rtrim($url, '/');
        $this->token = $token;
    }
    
    /**
     * Выполнение запроса к Moodle REST API
     * Универсальный метод для вызова любых функций Moodle через REST API
     * 
     * @param string $function Название функции Moodle API (например: 'core_course_get_courses')
     * @param array $params Дополнительные параметры для запроса
     * @return array|false Массив данных в формате JSON или false в случае ошибки
     */
    private function call($function, $params = array()) {
        // Формируем URL для запроса к Moodle REST API
        // Стандартный путь к REST API в Moodle: /webservice/rest/server.php
        $url = $this->url . '/webservice/rest/server.php';
        
        // Добавляем обязательные параметры для REST API запроса
        $params['wstoken'] = $this->token;                    // Токен для аутентификации
        $params['wsfunction'] = $function;                    // Название функции API
        $params['moodlewsrestformat'] = 'json';               // Формат ответа (JSON)
        
        // Логируем запрос (без пароля для безопасности)
        $log_params = $params;
        if (isset($log_params['users']) && is_array($log_params['users'])) {
            foreach ($log_params['users'] as &$user) {
                if (isset($user['password'])) {
                    $user['password'] = '***скрыто***';
                }
            }
        }
        error_log('Moodle API Call: URL=' . $url . ', Function=' . $function . ', Params=' . print_r($log_params, true));
        
        // Выполняем POST запрос к Moodle API
        // wp_remote_post() - стандартная функция WordPress для выполнения HTTP POST запросов
        $response = wp_remote_post($url, array(
            'body' => $params,                                // Данные для отправки
            'timeout' => 30                                   // Таймаут запроса (30 секунд)
        ));
        
        // Проверяем, произошла ли ошибка при выполнении запроса
        // is_wp_error() проверяет, является ли результат объектом WP_Error
        if (is_wp_error($response)) {
            // Записываем ошибку в лог WordPress
            // error_log() записывает сообщение в файл debug.log (если включен WP_DEBUG)
            error_log('Moodle API Error: ' . $response->get_error_message() . ' (Code: ' . $response->get_error_code() . ')');
            return false; // Возвращаем false в случае ошибки
        }
        
        // Получаем код ответа HTTP
        $response_code = wp_remote_retrieve_response_code($response);
        error_log('Moodle API Response Code: ' . $response_code);
        
        // Получаем тело ответа (JSON строка)
        // wp_remote_retrieve_body() извлекает тело ответа из объекта ответа
        $body = wp_remote_retrieve_body($response);
        error_log('Moodle API Response Body: ' . substr($body, 0, 500)); // Логируем первые 500 символов
        
        // Декодируем JSON строку в массив PHP
        // json_decode() преобразует JSON в массив (второй параметр true означает массив, а не объект)
        $data = json_decode($body, true);
        
        // Проверяем, вернул ли Moodle исключение (ошибку)
        // Moodle возвращает ошибки в формате: {"exception": "...", "message": "..."}
        if (isset($data['exception'])) {
            // Записываем ошибку в лог с полной информацией
            $error_message = isset($data['message']) ? $data['message'] : 'неизвестная ошибка';
            $error_code = isset($data['errorcode']) ? $data['errorcode'] : (isset($data['exception']) ? $data['exception'] : 'unknown');
            
            error_log('Moodle API Exception: ' . $error_message . ' (Type: ' . $error_code . ')');
            error_log('Moodle API Full Response: ' . print_r($data, true));
            
            if (isset($data['debuginfo'])) {
                error_log('Moodle API Debug Info: ' . $data['debuginfo']);
            }
            
            // Логируем полный ответ для диагностики
            error_log('Moodle API Response Body (full): ' . $body);
            
            // Возвращаем массив с ошибкой, чтобы вызывающий код мог обработать её
            return array('exception' => $data['exception'], 'message' => $error_message, 'errorcode' => $error_code, 'debuginfo' => isset($data['debuginfo']) ? $data['debuginfo'] : '');
        }
        
        // Возвращаем успешно полученные данные
        return $data;
    }
    
    /**
     * Получить категории курсов из Moodle
     * Возвращает список всех категорий курсов или категорий с определенным родителем
     * 
     * @param int $parent ID родительской категории (0 для получения всех категорий верхнего уровня)
     * @return array|false Массив категорий или false в случае ошибки
     */
    public function get_categories($parent = 0) {
        // Вызываем функцию Moodle API 'core_course_get_categories'
        // Эта функция возвращает список категорий курсов
        return $this->call('core_course_get_categories', array(
            'criteria' => array(
                array(
                    'key' => 'parent',        // Ключ для фильтрации по родителю
                    'value' => $parent        // ID родительской категории (0 = верхний уровень)
                )
            )
        ));
    }
    
    /**
     * Получить курсы из Moodle
     * Возвращает список всех курсов или курсов с определенными параметрами
     * 
     * @param array $options Дополнительные параметры для фильтрации курсов
     *                       Например: array('ids' => array(1, 2, 3)) для получения конкретных курсов
     * @return array|false Массив курсов или false в случае ошибки
     */
    public function get_courses($options = array()) {
        // Вызываем функцию Moodle API 'core_course_get_courses'
        // Эта функция возвращает список курсов
        // Если параметры не переданы, передаем пустой массив явно
        $result = $this->call('core_course_get_courses', empty($options) ? array() : $options);
        
        // Если получили ошибку, пробуем альтернативный метод через core_course_get_courses_by_field
        if (is_array($result) && isset($result['exception'])) {
            error_log('Moodle API: core_course_get_courses вернул ошибку, пробуем альтернативный метод через core_course_get_courses_by_field');
            
            // Пробуем получить все курсы через core_course_get_courses_by_field
            // Передаем пустое значение для получения всех курсов
            $alternative_result = $this->call('core_course_get_courses_by_field', array(
                'field' => 'id',
                'value' => ''  // Пустое значение должно вернуть все курсы
            ));
            
            // Если альтернативный метод тоже вернул ошибку, возвращаем исходную ошибку
            if (is_array($alternative_result) && isset($alternative_result['exception'])) {
                error_log('Moodle API: Альтернативный метод тоже вернул ошибку');
                return $result;
            }
            
            // Если альтернативный метод сработал, возвращаем результат
            // Но нужно проверить формат ответа - возможно, это объект с ключом 'courses'
            if (is_array($alternative_result)) {
                if (isset($alternative_result['courses']) && is_array($alternative_result['courses'])) {
                    return $alternative_result['courses'];
                } elseif (isset($alternative_result[0])) {
                    // Если это массив курсов напрямую
                    return $alternative_result;
                }
            }
            
            return $result;
        }
        
        return $result;
    }
    
    /**
     * Получить пользователей, записанных на курс
     * Возвращает список всех пользователей, которые зарегистрированы на указанный курс
     * 
     * @param int $courseid ID курса в Moodle
     * @return array|false Массив пользователей или false в случае ошибки
     */
    public function get_enrolled_users($courseid) {
        // Вызываем функцию Moodle API 'core_enrol_get_enrolled_users'
        // Эта функция возвращает список пользователей, записанных на курс
        return $this->call('core_enrol_get_enrolled_users', array(
            'courseid' => $courseid  // ID курса в Moodle
        ));
    }
    
    /**
     * Получить всех пользователей из Moodle
     * Возвращает список пользователей по заданным критериям
     * 
     * @param array $criteria Критерии для поиска пользователей
     *                        Например: array(array('key' => 'email', 'value' => 'user@example.com'))
     * @return array|false Массив пользователей или false в случае ошибки
     */
    public function get_users($criteria = array()) {
        // Вызываем функцию Moodle API 'core_user_get_users'
        // Эта функция возвращает список пользователей по критериям
        return $this->call('core_user_get_users', array(
            'criteria' => $criteria  // Массив критериев для поиска
        ));
    }
    
    /**
     * Получить информацию о конкретном курсе
     * Возвращает детальную информацию о курсе по его ID
     * 
     * @param int $courseid ID курса в Moodle
     * @return array|false Массив с данными курса или false в случае ошибки
     */
    public function get_course($courseid) {
        // Вызываем функцию Moodle API 'core_course_get_courses_by_field'
        // Эта функция возвращает информацию о курсе по его ID
        return $this->call('core_course_get_courses_by_field', array(
            'field' => 'id',         // Поле для поиска (по ID)
            'value' => $courseid     // Значение (ID курса)
        ));
    }
    
    /**
     * Получить курсы по категории
     * Возвращает список курсов для указанной категории
     * 
     * @param int $category_id ID категории в Moodle
     * @return array|false Массив курсов или false в случае ошибки
     */
    public function get_courses_by_category($category_id) {
        // Вызываем функцию Moodle API 'core_course_get_courses_by_field'
        // Эта функция возвращает курсы по категории
        return $this->call('core_course_get_courses_by_field', array(
            'field' => 'category',   // Поле для поиска (по категории)
            'value' => $category_id   // Значение (ID категории)
        ));
    }
    
    /**
     * Создать пользователя в Moodle
     * Создает нового пользователя в системе Moodle с указанными данными
     * 
     * @param array $user_data Массив с данными пользователя:
     *                        - username (обязательно) - логин пользователя
     *                        - password (обязательно) - пароль пользователя
     *                        - firstname (обязательно) - имя пользователя
     *                        - lastname (обязательно) - фамилия пользователя
     *                        - email (обязательно) - email пользователя
     *                        - city (опционально) - город
     *                        - country (опционально) - код страны (например: 'RU')
     * @return array|false Массив с данными созданного пользователя или false в случае ошибки
     */
    public function create_user($user_data) {
        // Вызываем функцию Moodle API 'core_user_create_users'
        // Эта функция создает нового пользователя в Moodle
        
        // Подготавливаем данные пользователя
        // Moodle требует, чтобы lastname не был пустым и отличался от firstname
        $firstname = isset($user_data['firstname']) ? trim($user_data['firstname']) : '';
        $lastname = !empty($user_data['lastname']) && trim($user_data['lastname']) !== '' ? trim($user_data['lastname']) : '-';
        
        // ВАЖНО: Если firstname и lastname одинаковые ИЛИ lastname начинается с firstname,
        // Moodle вернет ошибку "Invalid parameter value detected"
        // Исправляем это, заменяя lastname на "User"
        if (($firstname === $lastname || strpos($lastname, $firstname) === 0) && $lastname !== '-' && $lastname !== 'User') {
            $lastname = 'User';
            error_log('Moodle API: lastname совпадает или начинается с firstname (' . $firstname . ' -> ' . $lastname . '), заменяем lastname на "User"');
        }
        
        // Если lastname все еще пустой или равен дефису, и firstname не пустой, используем "User"
        if (($lastname === '' || $lastname === '-') && !empty($firstname)) {
            $lastname = 'User';
        }
        
        // Финальная проверка: если после всех проверок они все еще совпадают, меняем lastname
        if ($firstname === $lastname && $lastname !== 'User') {
            $lastname = 'User';
            error_log('Moodle API: Финальная проверка - firstname и lastname все еще совпадают, заменяем lastname на "User"');
        }
        
        // Формируем массив данных для создания пользователя
        $moodle_user_data = array(
            'username' => $user_data['username'],
            'password' => $user_data['password'],
            'firstname' => $firstname,
            'lastname' => $lastname,
            'email' => $user_data['email'],
        );
        
        // Добавляем опциональные поля только если они не пустые
        if (isset($user_data['city']) && !empty($user_data['city'])) {
            $moodle_user_data['city'] = $user_data['city'];
        }
        
        if (isset($user_data['country']) && !empty($user_data['country'])) {
            $moodle_user_data['country'] = $user_data['country'];
        } else {
            $moodle_user_data['country'] = 'RU';  // Страна по умолчанию
        }
        
        // Тип аутентификации
        $moodle_user_data['auth'] = 'manual';
        
        // Логируем данные перед отправкой (без пароля)
        $log_data = $moodle_user_data;
        $log_data['password'] = '***скрыто***';
        error_log('Moodle API: Данные для создания пользователя: ' . print_r($log_data, true));
        
        return $this->call('core_user_create_users', array(
            'users' => array($moodle_user_data)
        ));
    }
    
    /**
     * Обновить данные пользователя в Moodle
     * Обновляет информацию о существующем пользователе в Moodle
     * 
     * @param int $user_id ID пользователя в Moodle
     * @param array $user_data Массив с данными для обновления (те же поля, что и в create_user)
     * @return array|false Массив с результатом обновления или false в случае ошибки
     */
    public function update_user($user_id, $user_data) {
        // Вызываем функцию Moodle API 'core_user_update_users'
        // Эта функция обновляет данные пользователя в Moodle
        $update_data = array(
            'id' => $user_id
        );
        
        // Добавляем только те поля, которые нужно обновить
        if (isset($user_data['firstname'])) {
            $update_data['firstname'] = $user_data['firstname'];
        }
        if (isset($user_data['lastname'])) {
            $update_data['lastname'] = $user_data['lastname'];
        }
        if (isset($user_data['email'])) {
            $update_data['email'] = $user_data['email'];
        }
        if (isset($user_data['password'])) {
            $update_data['password'] = $user_data['password'];
        }
        if (isset($user_data['city'])) {
            $update_data['city'] = $user_data['city'];
        }
        if (isset($user_data['country'])) {
            $update_data['country'] = $user_data['country'];
        }
        
        return $this->call('core_user_update_users', array(
            'users' => array($update_data)
        ));
    }
    
    /**
     * Найти пользователя в Moodle по email
     * Ищет пользователя в Moodle по его email адресу
     * 
     * @param string $email Email адрес пользователя
     * @return array|false Массив с данными пользователя или false, если не найден
     */
    public function get_user_by_email($email) {
        // Вызываем функцию Moodle API 'core_user_get_users_by_field'
        // Эта функция ищет пользователей по указанному полю
        $result = $this->call('core_user_get_users_by_field', array(
            'field' => 'email',  // Поле для поиска (email)
            'values' => array($email)  // Значение для поиска
        ));
        
        // Если пользователь найден, возвращаем первого из результатов
        if (is_array($result) && !empty($result)) {
            return $result[0];
        }
        
        return false;
    }
    
    /**
     * Найти пользователя в Moodle по username
     * Ищет пользователя в Moodle по его логину
     * 
     * @param string $username Логин пользователя
     * @return array|false Массив с данными пользователя или false, если не найден
     */
    public function get_user_by_username($username) {
        // Вызываем функцию Moodle API 'core_user_get_users_by_field'
        // Эта функция ищет пользователей по указанному полю
        $result = $this->call('core_user_get_users_by_field', array(
            'field' => 'username',  // Поле для поиска (username)
            'values' => array($username)  // Значение для поиска
        ));
        
        // Если пользователь найден, возвращаем первого из результатов
        if (is_array($result) && !empty($result)) {
            return $result[0];
        }
        
        return false;
    }
}


