# Альтернативные способы установки SSO кнопок в Moodle

## Проблема
Если скрипт не загружается через `<script src="...">`, используйте один из альтернативных способов.

## Способ 1: Inline скрипт (Рекомендуется)

### Шаг 1: Скопируйте файл на сервер
```bash
cd /var/www/www-root/data/www/class.russianseminary.org
wget https://raw.githubusercontent.com/ValentinK2410/course_wp/master/course-plugin/moodle-sso-buttons-inline.php -O moodle-sso-buttons-inline.php
```

### Шаг 2: Добавьте в header.mustache

Откройте файл:
```bash
nano /var/www/www-root/data/www/class.russianseminary.org/theme/academi/templates/header.mustache
```

Найдите строку `{{> theme_academi/navbar }}` и добавьте ПОСЛЕ неё:

```mustache
{{> theme_academi/navbar }}

{{! SSO Buttons - Inline версия }}
{{#isloggedin}}
<script>
(function() {
    // Получаем токены через AJAX
    var xhr = new XMLHttpRequest();
    xhr.open('GET', '{{config.wwwroot}}/moodle-sso-buttons-inline.php', false); // синхронный запрос
    xhr.send();
    
    if (xhr.status === 200) {
        eval(xhr.responseText);
    }
})();
</script>
{{/isloggedin}}
```

### Шаг 3: Очистите кеш
```bash
php admin/cli/purge_caches.php
```

## Способ 2: Прямое встраивание PHP кода

Если у вас есть доступ к PHP в шаблонах Moodle, добавьте:

```mustache
{{#isloggedin}}
<?php
require_once(__DIR__ . '/../../moodle-sso-buttons-inline.php');
?>
{{/isloggedin}}
```

## Способ 3: Через footer.mustache

Добавьте в конец файла `footer.mustache`:

```mustache
{{#isloggedin}}
<script src="{{config.wwwroot}}/moodle-sso-buttons.php" async></script>
{{/isloggedin}}
```

## Способ 4: Проверка загрузки скрипта

Если скрипт не загружается, проверьте:

1. **Права доступа к файлу:**
```bash
chmod 644 /var/www/www-root/data/www/class.russianseminary.org/moodle-sso-buttons.php
```

2. **Проверьте прямой доступ:**
Откройте в браузере: `https://class.russianseminary.org/moodle-sso-buttons.php`
Должен появиться JavaScript код или `// Пользователь не авторизован`

3. **Проверьте консоль браузера (F12):**
- Network → найдите запрос к `moodle-sso-buttons.php`
- Console → проверьте ошибки

4. **Проверьте логи Moodle:**
```bash
tail -50 /var/www/www-root/data/www/moodledata/error.log | grep "Moodle SSO"
```

## Способ 5: Использование кастомного блока Moodle

Создайте кастомный HTML блок в Moodle:
1. Администрирование → Плагины → Блоки → HTML
2. Добавьте блок на главную страницу
3. В содержимое блока вставьте:
```html
<script src="https://class.russianseminary.org/moodle-sso-buttons.php" async></script>
```

## Рекомендация

Используйте **Способ 1 (Inline скрипт)** - он самый надежный и не зависит от загрузки внешнего файла.
