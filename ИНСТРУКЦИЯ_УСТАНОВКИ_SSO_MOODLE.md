# Инструкция по установке SSO для Moodle

## Путь к Moodle
```
/var/www/www-root/data/www/class.dekan.pro/public
```

## Шаг 1: Удалите старый файл (если существует)

Если файл `auth/sso/login.php` существует, удалите его:

```bash
rm /var/www/www-root/data/www/class.dekan.pro/public/auth/sso/login.php
```

Если директория `auth/sso/` пуста, можно удалить её:

```bash
rmdir /var/www/www-root/data/www/class.dekan.pro/public/auth/sso/
```

## Шаг 2: Установите новый SSO скрипт

Скопируйте файл `moodle-sso-external.php` в **корень Moodle**:

```bash
cp moodle-sso-external.php /var/www/www-root/data/www/class.dekan.pro/public/sso-login.php
```

**ВАЖНО**: 
- Файл должен называться `sso-login.php` (не `moodle-sso-external.php`)
- Файл должен быть в корне Moodle, рядом с `config.php`

## Шаг 3: Установите права доступа

```bash
chmod 644 /var/www/www-root/data/www/class.dekan.pro/public/sso-login.php
```

Проверьте владельца файла (должен быть веб-сервер, обычно `www-data` или `nginx`):

```bash
chown www-data:www-data /var/www/www-root/data/www/class.dekan.pro/public/sso-login.php
```

Или если используется nginx:

```bash
chown nginx:nginx /var/www/www-root/data/www/class.dekan.pro/public/sso-login.php
```

## Шаг 4: Проверьте структуру файлов

После установки структура должна быть такой:

```
/var/www/www-root/data/www/class.dekan.pro/public/
├── config.php
├── sso-login.php          ← НОВЫЙ файл (здесь)
├── index.php
├── admin/
├── auth/
│   └── (НЕ должно быть sso/login.php)
└── ...
```

## Шаг 5: Проверьте настройки в Moodle

1. Войдите в админ-панель Moodle: `https://class.dekan.pro/admin`
2. Перейдите: **Администрирование сайта** → **Плагины** → **Аутентификация** → **Управление аутентификацией**
3. Убедитесь, что SSO **НЕ** включен как метод аутентификации
4. Если SSO там есть - отключите его

## Шаг 6: Очистите кеш Moodle

В админ-панели Moodle:
- **Администрирование сайта** → **Разработка** → **Очистить кеш**

Или через командную строку:

```bash
cd /var/www/www-root/data/www/class.dekan.pro/public
php admin/cli/purge_caches.php
```

## Шаг 7: Проверьте работу

1. **Проверьте доступ к админ-панели**:
   - Откройте: `https://class.dekan.pro/admin/user.php`
   - Ошибка "Плагин аутентификации SSO не найден" должна исчезнуть

2. **Проверьте SSO вход**:
   - Войдите в WordPress: `https://site.dekan.pro`
   - В консоли браузера выполните: `goToMoodle()`
   - Должен произойти автоматический вход в Moodle

## Проверка установки

Выполните команду для проверки:

```bash
ls -la /var/www/www-root/data/www/class.dekan.pro/public/sso-login.php
```

Должен быть вывод:
```
-rw-r--r-- 1 www-data www-data [размер] [дата] sso-login.php
```

## Если что-то пошло не так

### Проверьте логи ошибок:

```bash
# Логи PHP
tail -f /var/log/php/error.log

# Логи Moodle
tail -f /var/www/www-root/data/www/class.dekan.pro/public/data/error.log

# Логи веб-сервера
tail -f /var/log/nginx/error.log
# или
tail -f /var/log/apache2/error.log
```

### Проверьте права доступа:

```bash
# Проверьте права на директорию
ls -la /var/www/www-root/data/www/class.dekan.pro/public/ | grep sso-login.php

# Проверьте, что файл читается веб-сервером
sudo -u www-data cat /var/www/www-root/data/www/class.dekan.pro/public/sso-login.php | head -5
```

### Проверьте конфигурацию:

Убедитесь, что в файле `sso-login.php` правильные настройки:

```php
$wordpress_url = 'https://site.dekan.pro';
$sso_api_key = 'bsaQHUiGl4vU59OFcLGBKUohtstpX7JQo4o3S6jlt9qC5tzythZ4b7a1qlAkhPDk';
```

Эти значения должны совпадать с настройками в WordPress.

## Итоговая структура

После правильной установки:

```
/var/www/www-root/data/www/class.dekan.pro/public/
├── config.php                    ← Конфигурация Moodle
├── sso-login.php                 ← SSO скрипт (НОВЫЙ)
├── index.php
├── admin/
│   └── user.php                  ← Должен работать без ошибок
└── ...
```

## Безопасность

⚠️ **ВАЖНО**:
- Файл `sso-login.php` должен быть доступен только для чтения веб-сервером
- Не давайте права на запись (не `chmod 666` или `777`)
- Рекомендуемые права: `644` (владелец: чтение/запись, группа и другие: только чтение)












