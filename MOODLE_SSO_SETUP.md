# Настройка SSO для Moodle

## Проблема
При переходе по ссылке `/auth/sso/login.php?token=...` получаем ошибку 404 Not Found.

## Решение

### Вариант 1: Простой файл (рекомендуется для быстрого старта)

1. Создайте директорию в Moodle:
```bash
mkdir -p /path/to/moodle/auth/sso
```

2. Скопируйте файл `moodle-sso-login.php` в эту директорию:
```bash
cp moodle-sso-login.php /path/to/moodle/auth/sso/login.php
```

3. Установите правильные права доступа:
```bash
chmod 644 /path/to/moodle/auth/sso/login.php
```

4. Настройте WordPress URL и SSO API Key в Moodle:
   - Войдите в админку Moodle
   - Перейдите в: **Site administration → Plugins → Authentication → Manage authentication**
   - Найдите плагин "SSO" (если установлен) или используйте настройки через базу данных

### Вариант 2: Настройка через базу данных Moodle

Если у вас нет плагина SSO, можно добавить настройки напрямую в базу данных:

```sql
-- Добавьте настройки в таблицу config_plugins
INSERT INTO mdl_config_plugins (plugin, name, value) VALUES
('auth_sso', 'wordpress_url', 'https://site.dekan.pro'),
('auth_sso', 'sso_api_key', 'ваш-sso-api-key-из-wordpress');
```

### Вариант 3: Создание плагина аутентификации Moodle (полное решение)

Для полноценной интеграции рекомендуется создать плагин аутентификации для Moodle.

#### Структура плагина:

```
auth/sso/
├── version.php
├── lang/en/auth_sso.php
├── auth.php
└── login.php
```

#### Файл version.php:

```php
<?php
defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2023122200;
$plugin->requires  = 2022041900;
$plugin->component = 'auth_sso';
$plugin->maturity  = MATURITY_STABLE;
```

#### Файл lang/en/auth_sso.php:

```php
<?php
$string['pluginname'] = 'WordPress SSO';
$string['auth_ssodescription'] = 'Single Sign-On authentication via WordPress';
$string['wordpress_url'] = 'WordPress URL';
$string['wordpress_url_desc'] = 'URL of your WordPress site';
$string['sso_api_key'] = 'SSO API Key';
$string['sso_api_key_desc'] = 'SSO API Key from WordPress settings';
$string['sso_token_missing'] = 'SSO token is missing';
$string['sso_not_configured'] = 'SSO is not configured';
$string['sso_verification_failed'] = 'Failed to verify SSO token';
$string['sso_invalid_token'] = 'Invalid or expired SSO token';
$string['sso_user_not_found'] = 'User not found in Moodle';
```

#### Файл auth.php:

```php
<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/authlib.php');

class auth_plugin_sso extends auth_plugin_base {
    
    public function __construct() {
        $this->authtype = 'sso';
        $this->config = get_config('auth_sso');
    }
    
    public function user_login($username, $password) {
        // Этот метод не используется для SSO
        return false;
    }
    
    public function can_change_password() {
        return false;
    }
    
    public function can_reset_password() {
        return false;
    }
    
    public function config_form($config, $err, $user_fields) {
        include 'config.html';
    }
    
    public function process_config($config) {
        set_config('wordpress_url', $config->wordpress_url, 'auth_sso');
        set_config('sso_api_key', $config->sso_api_key, 'auth_sso');
        return true;
    }
}
```

## Быстрое решение (без плагина)

Если вы хотите быстро протестировать SSO без создания плагина:

1. Создайте файл `/path/to/moodle/auth/sso/login.php` с содержимым из `moodle-sso-login.php`

2. Отредактируйте файл и замените строки с настройками:
```php
// Вместо:
$wordpress_url = get_config('auth_sso', 'wordpress_url');
$sso_api_key = get_config('auth_sso', 'sso_api_key');

// Используйте:
$wordpress_url = 'https://site.dekan.pro';
$sso_api_key = 'ваш-sso-api-key-из-wordpress';
```

3. Убедитесь, что файл доступен по URL:
```
https://class.dekan.pro/auth/sso/login.php
```

## Проверка

После настройки:

1. Войдите в WordPress
2. Выполните в консоли браузера: `goToMoodle()`
3. Должен произойти автоматический переход и вход в Moodle

## Troubleshooting

### Ошибка 404 Not Found

**Причина**: Файл не существует или неправильный путь

**Решение**: 
- Убедитесь, что файл создан по пути `/auth/sso/login.php`
- Проверьте права доступа к файлу
- Проверьте конфигурацию веб-сервера (Nginx/Apache)

### Ошибка "SSO is not configured"

**Причина**: Не указаны настройки WordPress URL и SSO API Key

**Решение**: 
- Убедитесь, что в файле указаны правильные значения
- Или установите плагин и настройте через админку Moodle

### Ошибка "User not found in Moodle"

**Причина**: Пользователь не был создан в Moodle через синхронизацию

**Решение**: 
- Убедитесь, что пользователь был создан в Moodle
- Проверьте, что email совпадает в WordPress и Moodle
- Если пользователя нет, создайте его вручную или через синхронизацию

## Безопасность

- Файл должен быть доступен только через веб-сервер
- Убедитесь, что настроен правильный SSO API Key
- Токены действительны только 1 час
- Проверка токена происходит через защищенный API endpoint

