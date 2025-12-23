# Тестирование SSO

## Быстрая проверка работоспособности

### Шаг 1: Проверка WordPress

1. Войдите в WordPress: `https://site.dekan.pro/wp-login.php`
2. Откройте консоль браузера (F12)
3. Выполните команду:
```javascript
jQuery.ajax({
    url: '/wp-admin/admin-ajax.php',
    type: 'POST',
    data: {
        action: 'get_sso_tokens',
        nonce: jQuery('input[name="_wpnonce"]').val() || 'test'
    },
    success: function(response) {
        console.log('SSO токены:', response);
    }
});
```

**Ожидаемый результат**: `{success: true, data: {moodle_token: "...", laravel_token: "..."}}`

### Шаг 2: Проверка Laravel конфигурации

В Laravel приложении выполните:

```bash
php artisan tinker
```

Затем в tinker:
```php
config('services.wordpress.url');
config('services.wordpress.sso_api_key');
```

**Ожидаемый результат**: 
- URL должен быть `https://site.dekan.pro`
- SSO API Key должен совпадать с ключом из WordPress

### Шаг 3: Проверка WordPress API endpoint

Откройте в браузере (замените `YOUR_API_KEY` и `YOUR_TOKEN`):

```
https://site.dekan.pro/wp-admin/admin-ajax.php?action=verify_sso_token&token=YOUR_TOKEN&service=laravel&api_key=YOUR_API_KEY
```

**Ожидаемый результат**: JSON ответ с данными пользователя или ошибкой

### Шаг 4: Полный тест SSO

1. Войдите в WordPress
2. Откройте консоль браузера (F12)
3. Выполните:
```javascript
goToLaravel();
```

**Ожидаемый результат**: Автоматический переход в Laravel и вход в систему

## Возможные проблемы и решения

### Проблема: "Ошибка получения токена"

**Причина**: Пользователь не авторизован в WordPress

**Решение**: Убедитесь, что вы вошли в WordPress

### Проблема: "SSO не настроен" в Laravel

**Причина**: Не указаны настройки в `.env`

**Решение**: 
1. Проверьте файл `.env` в Laravel
2. Убедитесь, что указаны:
   ```
   WORDPRESS_URL=https://site.dekan.pro
   WORDPRESS_SSO_API_KEY=ваш-ключ
   ```
3. Выполните: `php artisan config:clear`

### Проблема: "Unauthorized"

**Причина**: SSO API Key не совпадает

**Решение**:
1. Проверьте SSO API Key в WordPress: Настройки → Moodle Sync → SSO API Key
2. Убедитесь, что тот же ключ указан в Laravel `.env` как `WORDPRESS_SSO_API_KEY`
3. Выполните: `php artisan config:clear`

### Проблема: "Пользователь не найден"

**Причина**: Пользователь не был создан в Laravel через синхронизацию

**Решение**: 
1. Убедитесь, что пользователь был создан в Laravel
2. Проверьте, что email совпадает в WordPress и Laravel
3. Если пользователя нет, создайте его вручную или через синхронизацию

### Проблема: CSRF Token Mismatch

**Причина**: Маршрут не исключен из CSRF проверки

**Решение**: Убедитесь, что в `bootstrap/app.php` маршрут `/sso/login` исключен из CSRF

## Логи для отладки

### WordPress логи:
- `wp-content/debug.log` - общие логи WordPress
- `wp-content/course-registration-debug.log` - логи регистрации и синхронизации

### Laravel логи:
- `storage/logs/laravel.log` - все логи Laravel

### Проверка логов SSO:

В WordPress ищите строки с `Course SSO:`
В Laravel ищите строки с `SSO:`

