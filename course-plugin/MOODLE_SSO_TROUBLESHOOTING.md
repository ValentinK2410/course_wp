# Устранение проблем с кнопками SSO в Moodle

## Проблема: Скрипт не загружается (нет запроса в Network)

Если в консоли браузера нет запроса к `moodle-sso-buttons.php`, значит скрипт не подключен.

### Проверка 1: Правильно ли добавлен код в header.mustache

Убедитесь, что код добавлен **точно так**:

```mustache
{{> theme_academi/navbar }}

{{! SSO Buttons для перехода в WordPress и Laravel }}
{{#isloggedin}}
<script src="{{config.wwwroot}}/moodle-sso-buttons.php" async></script>
{{/isloggedin}}

{{#themestyleheader}}
```

**Важно:**
- Должно быть `{{#isloggedin}}` (с решеткой в начале)
- Должно быть `{{config.wwwroot}}` (не `{{{config.wwwroot}}}`)
- Должен быть атрибут `async`

### Проверка 2: Очистка кэша Moodle

После изменения `header.mustache` **обязательно** очистите кэш:

```bash
php admin/cli/purge_caches.php
```

Или через интерфейс:
- Администрирование сайта → Разработка → Очистить все кэши

### Проверка 3: Проверка доступности файла

Откройте в браузере (должны быть авторизованы):
```
https://class.russianseminary.org/moodle-sso-buttons.php
```

Должен отобразиться JavaScript код, а не HTML страница.

### Проверка 4: Проверка исходного кода страницы

1. Откройте Moodle в браузере
2. Правой кнопкой → "Просмотр кода страницы" (View Page Source)
3. Найдите в коде: `moodle-sso-buttons.php`
4. Если не найдено - код не добавлен или кэш не очищен

### Проверка 5: Альтернативный способ подключения

Если `{{#isloggedin}}` не работает, попробуйте без условия:

```mustache
{{> theme_academi/navbar }}

{{! SSO Buttons для перехода в WordPress и Laravel }}
<script src="{{config.wwwroot}}/moodle-sso-buttons.php" async></script>

{{#themestyleheader}}
```

Файл сам проверит авторизацию.

### Проверка 6: Прямое подключение в HTML

Временно добавьте прямо в HTML (для тестирования):

```mustache
{{> theme_academi/navbar }}

<script>
console.log('SSO Script: Проверка подключения');
</script>
<script src="{{config.wwwroot}}/moodle-sso-buttons.php" async></script>

{{#themestyleheader}}
```

Если видите "SSO Script: Проверка подключения" в консоли, значит код выполняется.

## Проблема: Скрипт загружается, но кнопки не появляются

### Проверка консоли браузера

Откройте консоль (F12) и ищите сообщения:
- `Moodle SSO: Найден контейнер: ...`
- `Moodle SSO: Кнопки успешно добавлены!`
- `Moodle SSO: Не найден контейнер для вставки кнопок`

### Проверка структуры HTML

1. F12 → Elements
2. Найдите меню пользователя (обычно справа вверху)
3. Посмотрите его структуру и классы
4. Сообщите разработчику для добавления правильных селекторов

## Быстрая проверка

Выполните команды на сервере:

```bash
# 1. Проверьте, что файл существует
ls -la /var/www/www-root/data/www/class.russianseminary.org/moodle-sso-buttons.php

# 2. Проверьте права доступа
chmod 644 /var/www/www-root/data/www/class.russianseminary.org/moodle-sso-buttons.php
chown www-root:www-root /var/www/www-root/data/www/class.russianseminary.org/moodle-sso-buttons.php

# 3. Проверьте доступность через curl (должен быть авторизован в Moodle)
curl -b cookies.txt https://class.russianseminary.org/moodle-sso-buttons.php

# 4. Очистите кэш Moodle
php /var/www/www-root/data/www/class.russianseminary.org/admin/cli/purge_caches.php
```

## Частые ошибки

1. **Кэш не очищен** - самая частая причина
2. **Неправильный синтаксис Mustache** - проверьте скобки `{{}}`
3. **Файл не существует** - проверьте путь к файлу
4. **Пользователь не авторизован** - проверьте условие `{{#isloggedin}}`
5. **Неправильный путь** - используйте `{{config.wwwroot}}`, а не полный URL
