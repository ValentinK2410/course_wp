# Диагностика проблемы синхронизации с Laravel

## Проблема
Пользователь создается в WordPress и Moodle, но НЕ создается в Laravel (m.dekan.pro).

## Шаги диагностики

### 1. Проверка настроек WordPress

Убедитесь, что в настройках WordPress плагина заполнены поля Laravel API:

1. Войдите в админку WordPress: `https://site.dekan.pro/wp-admin`
2. Перейдите в **Настройки → Moodle Sync**
3. Найдите раздел **"Настройки синхронизации с Laravel"**
4. Проверьте, что заполнены:
   - **Laravel API URL**: `https://m.dekan.pro`
   - **Laravel API Token**: должен совпадать с `WORDPRESS_API_TOKEN` в Laravel `.env`
5. Нажмите **"Сохранить изменения"**

### 2. Проверка настроек Laravel

Убедитесь, что в Laravel приложении настроен токен:

1. Откройте файл `.env` в Laravel проекте
2. Проверьте наличие строки:
   ```env
   WORDPRESS_API_TOKEN=your-secret-token-here
   ```
3. **ВАЖНО**: Токен должен совпадать с токеном в WordPress настройках!

### 3. Проверка логов WordPress

Проверьте файл `wp-content/course-registration-debug.log` на наличие записей о синхронизации с Laravel:

```bash
tail -f wp-content/course-registration-debug.log | grep -i laravel
```

Ищите записи:
- `========== НАЧАЛО СИНХРОНИЗАЦИИ С LARAVEL ==========`
- `Laravel API настройки:`
- `ОШИБКА:` или `УСПЕХ:`

### 4. Проверка логов Laravel

Проверьте логи Laravel на наличие запросов от WordPress:

```bash
tail -f storage/logs/laravel.log | grep -i wordpress
```

Ищите записи:
- `Unauthorized API request` - проблема с токеном
- `Validation failed` - проблема с данными
- `User successfully created from WordPress` - успешное создание

### 5. Проверка доступности API endpoint

Проверьте, доступен ли API endpoint:

```bash
curl -X POST https://m.dekan.pro/api/users/sync-from-wordpress \
  -H "Content-Type: application/json" \
  -H "X-API-Token: your-secret-token-here" \
  -d '{
    "name": "Test User",
    "email": "test@example.com",
    "password": "testpassword123",
    "moodle_user_id": 1,
    "phone": ""
  }'
```

Ожидаемый ответ при правильном токене:
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "email": ["The email has already been taken."]
  }
}
```

Если получаете `401 Unauthorized` - проблема с токеном.

### 6. Частые проблемы и решения

#### Проблема: "Laravel API не настроен"
**Решение**: Заполните настройки в WordPress: Настройки → Moodle Sync → раздел "Настройки синхронизации с Laravel"

#### Проблема: "401 Unauthorized"
**Решение**: 
- Проверьте, что токен в WordPress совпадает с `WORDPRESS_API_TOKEN` в Laravel `.env`
- Убедитесь, что токен не содержит пробелов или лишних символов

#### Проблема: "422 Validation failed"
**Решение**: 
- Проверьте, что email уникален (пользователь еще не существует в Laravel)
- Проверьте формат данных в логах

#### Проблема: "500 Internal Server Error"
**Решение**: 
- Проверьте логи Laravel: `storage/logs/laravel.log`
- Убедитесь, что выполнена миграция: `php artisan migrate`
- Проверьте, что роль 'student' существует в базе данных

### 7. Ручная проверка настроек через WordPress

Выполните в консоли WordPress (или через WP-CLI):

```php
// Проверить текущие настройки
echo get_option('laravel_api_url', 'НЕ УСТАНОВЛЕН') . "\n";
echo get_option('laravel_api_token', 'НЕ УСТАНОВЛЕН') . "\n";

// Установить настройки (если нужно)
update_option('laravel_api_url', 'https://m.dekan.pro');
update_option('laravel_api_token', 'your-secret-token-here');
```

### 8. Проверка вызова метода синхронизации

В логах WordPress должна быть запись:
```
[YYYY-MM-DD HH:MM:SS] ========== НАЧАЛО СИНХРОНИЗАЦИИ С LARAVEL ==========
```

Если этой записи нет - метод `sync_user_to_laravel()` не вызывается. Проверьте:
- Создался ли пользователь в Moodle (должен быть `moodle_user_id` в метаполях WordPress)
- Есть ли запись "УСПЕХ! Пользователь успешно создан в Moodle"

## Что прислать для диагностики

1. Содержимое файла `wp-content/course-registration-debug.log` (последние 100 строк)
2. Содержимое файла `storage/logs/laravel.log` из Laravel (последние 50 строк)
3. Значения настроек WordPress:
   ```php
   get_option('laravel_api_url')
   get_option('laravel_api_token')
   ```
4. Значение `WORDPRESS_API_TOKEN` из Laravel `.env` (первые 10 символов)

















