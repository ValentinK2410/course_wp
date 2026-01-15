# Отладка кнопок SSO в Moodle

Если кнопки не появляются, выполните следующие шаги:

## 1. Проверьте, что файл доступен

Откройте в браузере (должен быть авторизован в Moodle):
```
https://class.russianseminary.org/moodle-sso-buttons.php
```

Должен отобразиться JavaScript код (не HTML страница с ошибкой).

## 2. Проверьте консоль браузера

1. Откройте Moodle в браузере
2. Нажмите F12 (или правой кнопкой → Inspect)
3. Перейдите на вкладку "Console"
4. Обновите страницу
5. Ищите сообщения, начинающиеся с "Moodle SSO:"

Должны быть сообщения:
- `Moodle SSO: Найден контейнер: ...`
- `Moodle SSO: Кнопки успешно добавлены!`

Если видите ошибки, скопируйте их.

## 3. Проверьте, что скрипт загружается

1. В консоли браузера перейдите на вкладку "Network" (Сеть)
2. Обновите страницу
3. Найдите запрос `moodle-sso-buttons.php`
4. Проверьте:
   - Статус должен быть 200 (OK)
   - Content-Type должен быть `application/javascript`

## 4. Проверьте логи Moodle

```bash
tail -f /var/www/www-root/data/www/moodledata/error.log | grep "Moodle SSO"
```

Должны быть записи о запросах токенов.

## 5. Проверьте, что пользователь авторизован

В footer.mustache должно быть:
```mustache
{{#isloggedin}}
<script src="{{config.wwwroot}}/moodle-sso-buttons.php" async></script>
{{/isloggedin}}
```

Убедитесь, что вы авторизованы в Moodle.

## 6. Проверьте кэш Moodle

Очистите кэш:
- Администрирование сайта → Разработка → Очистить все кэши
- Или выполните: `php admin/cli/purge_caches.php`

## 7. Проверьте права доступа к файлу

```bash
ls -la /var/www/www-root/data/www/class.russianseminary.org/moodle-sso-buttons.php
```

Должно быть:
- Права: `644` или `755`
- Владелец: `www-root:www-root` или `www-data:www-data`

## 8. Проверьте настройки в файле

Откройте файл и проверьте:
```php
$wordpress_url = 'https://mbs.russianseminary.org'; // Правильный URL?
$laravel_url = 'https://dekanat.russianseminary.org'; // Правильный URL?
```

## 9. Временное решение - добавить кнопки вручную

Если автоматическая вставка не работает, можно добавить кнопки напрямую в header.mustache:

```mustache
{{#isloggedin}}
<div class="moodle-sso-buttons-container" style="display: inline-flex; gap: 10px; margin-left: 15px;">
    <a href="https://mbs.russianseminary.org/wp-admin/admin-ajax.php?action=sso_login_from_moodle&token=TOKEN" 
       class="sso-button sso-button-wordpress" 
       style="padding: 8px 16px; background: #2271b1; color: white; text-decoration: none; border-radius: 4px;">
        Сайт семинарии
    </a>
    <a href="https://dekanat.russianseminary.org/sso/login?token=TOKEN" 
       class="sso-button sso-button-laravel" 
       style="padding: 8px 16px; background: #f9322c; color: white; text-decoration: none; border-radius: 4px;">
        Деканат
    </a>
</div>
{{/isloggedin}}
```

Но это не будет работать с токенами. Лучше исправить автоматическую вставку.

## 10. Проверьте структуру HTML темы Academi

Откройте страницу Moodle и посмотрите структуру HTML:
1. F12 → Elements (Элементы)
2. Найдите меню пользователя (обычно справа вверху)
3. Посмотрите, какие классы и ID у него есть
4. Сообщите разработчику, чтобы добавить правильные селекторы

## Частые проблемы

### Проблема: Файл возвращает HTML вместо JavaScript
**Решение:** Проверьте, что `require_login()` не перенаправляет. Используйте проверку `isloggedin()` без редиректа.

### Проблема: Кнопки не находятся в нужном месте
**Решение:** Скрипт ищет контейнер автоматически. Если не находит, добавляет в начало body. Проверьте консоль браузера для отладочных сообщений.

### Проблема: Токены не генерируются
**Решение:** Проверьте логи Moodle и WordPress. Убедитесь, что WordPress API доступен и пользователь существует в WordPress.
