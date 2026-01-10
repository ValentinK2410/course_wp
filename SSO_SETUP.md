# Настройка Single Sign-On (SSO)

## Что это такое?

SSO позволяет пользователям автоматически входить в Moodle и Laravel после входа в WordPress без необходимости вводить пароль повторно.

## Как это работает:

1. Пользователь входит в WordPress
2. WordPress генерирует временные SSO токены (действительны 1 час)
3. Пользователь может перейти в Moodle или Laravel по специальным ссылкам
4. Moodle/Laravel проверяют токен через WordPress API
5. Пользователь автоматически входит в систему

## Настройка WordPress:

1. Войдите в админку WordPress: `https://site.dekan.pro/wp-admin`
2. Перейдите в **Настройки → Moodle Sync**
3. Найдите раздел **"Настройки Single Sign-On (SSO)"**
4. Проверьте поле **"SSO API Key"** - ключ будет сгенерирован автоматически при первой загрузке
5. Скопируйте этот ключ - он понадобится для настройки Laravel

## Настройка Laravel:

Добавьте в файл `.env` Laravel приложения:

```env
WORDPRESS_URL=https://site.dekan.pro
WORDPRESS_SSO_API_KEY=ваш-sso-api-key-из-wordpress
```

**ВАЖНО**: `WORDPRESS_SSO_API_KEY` должен совпадать с ключом в WordPress настройках!

## Использование SSO:

### Вариант 1: Через JavaScript функции

После входа в WordPress, на любой странице можно использовать:

```javascript
// Переход в Moodle
goToMoodle();

// Переход в Laravel
goToLaravel();
```

### Вариант 2: Через прямые ссылки

Можно создать кнопки или ссылки на странице:

```html
<a href="javascript:void(0);" onclick="goToMoodle();">Войти в Moodle</a>
<a href="javascript:void(0);" onclick="goToLaravel();">Войти в Laravel</a>
```

### Вариант 3: Через меню WordPress

SSO ссылки автоматически добавляются в меню WordPress (если настроено).

## Настройка Moodle:

Для Moodle нужно создать плагин аутентификации или использовать внешний сервис. 

**Простой вариант** - создать файл `auth/sso/login.php` в Moodle:

```php
<?php
require_once(__DIR__ . '/../../config.php');

$token = required_param('token', PARAM_RAW);

// Проверяем токен через WordPress API
$wordpress_url = 'https://site.dekan.pro';
$sso_api_key = 'ваш-sso-api-key';

$response = file_get_contents($wordpress_url . '/wp-admin/admin-ajax.php?' . http_build_query([
    'action' => 'verify_sso_token',
    'token' => $token,
    'service' => 'moodle',
    'api_key' => $sso_api_key,
]));

$data = json_decode($response, true);

if (isset($data['success']) && $data['success']) {
    $user_data = $data['data'];
    
    // Находим пользователя в Moodle по email
    $user = $DB->get_record('user', ['email' => $user_data['email']]);
    
    if ($user) {
        // Автоматически входим пользователя
        complete_user_login($user);
        redirect('/');
    }
}

// Если не удалось войти, перенаправляем на страницу входа
redirect('/login/index.php');
```

## Безопасность:

- SSO токены действительны только 1 час
- Токены привязаны к конкретному пользователю
- Требуется SSO API ключ для проверки токенов
- Токены нельзя использовать повторно после истечения срока

## Проверка работы:

1. Войдите в WordPress
2. Откройте консоль браузера (F12)
3. Выполните: `goToMoodle()` или `goToLaravel()`
4. Должен произойти автоматический вход в соответствующую систему

## Troubleshooting:

### Проблема: "Ошибка получения токена"
- Проверьте, что пользователь авторизован в WordPress
- Проверьте логи WordPress: `wp-content/debug.log`

### Проблема: "Unauthorized" в Laravel
- Проверьте, что `WORDPRESS_SSO_API_KEY` в Laravel совпадает с ключом в WordPress
- Проверьте, что `WORDPRESS_URL` указан правильно

### Проблема: Токен не работает
- Проверьте срок действия токена (1 час)
- Попробуйте войти в WordPress заново для генерации новых токенов

















