# Инструкция по установке кнопок SSO в Moodle

Файл `moodle-sso-buttons.php` автоматически вставляет кнопки для перехода в WordPress и Laravel в шапку Moodle.

## Способ 1: Автоматическая вставка через JavaScript (Рекомендуется)

Файл автоматически найдет нужное место в шапке Moodle и вставит кнопки. Для этого нужно добавить его в шапку темы Moodle.

### Вариант A: Через редактирование header.mustache (для тем Boost, Classic и др.)

1. Найдите файл `header.mustache` в вашей теме Moodle:
   ```
   /var/www/www-root/data/www/class.russianseminary.org/theme/boost/templates/header.mustache
   ```
   Или в кастомной теме:
   ```
   /var/www/www-root/data/www/class.russianseminary.org/theme/ваша_тема/templates/header.mustache
   ```

2. Откройте файл `header.mustache` и найдите место перед закрывающим тегом `</header>` или перед `</head>`

3. Добавьте перед закрывающим тегом `</head>`:
   ```mustache
   {{#isloggedin}}
   {{> core/requirejs}}
   <script>
   require(['core/config'], function() {
       require(['core/ajax'], function(ajax) {
           // Загружаем файл с кнопками SSO
           var script = document.createElement('script');
           script.src = '{{config.wwwroot}}/moodle-sso-buttons.php';
           script.async = true;
           document.head.appendChild(script);
       });
   });
   </script>
   {{/isloggedin}}
   ```

   Или более простой вариант - добавьте перед `</body>`:
   ```mustache
   {{#isloggedin}}
   <script src="{{config.wwwroot}}/moodle-sso-buttons.php" async></script>
   {{/isloggedin}}
   ```

### Вариант B: Через редактирование footer.mustache (Проще)

1. Найдите файл `footer.mustache` в вашей теме:
   ```
   /var/www/www-root/data/www/class.russianseminary.org/theme/boost/templates/footer.mustache
   ```

2. Добавьте перед закрывающим тегом `</body>`:
   ```mustache
   {{#isloggedin}}
   <script src="{{config.wwwroot}}/moodle-sso-buttons.php" async></script>
   {{/isloggedin}}
   ```

### Вариант C: Через локальный плагин Moodle (Самый правильный способ)

1. Создайте директорию для локального плагина:
   ```bash
   mkdir -p /var/www/www-root/data/www/class.russianseminary.org/local/sso_buttons
   ```

2. Создайте файл `version.php`:
   ```php
   <?php
   defined('MOODLE_INTERNAL') || die();
   $plugin->component = 'local_sso_buttons';
   $plugin->version = 2024011400;
   $plugin->requires = 2022041900;
   ```

3. Создайте файл `lib.php`:
   ```php
   <?php
   defined('MOODLE_INTERNAL') || die();
   
   function local_sso_buttons_before_footer_html() {
       global $USER;
       if (isloggedin() && !isguestuser()) {
           $wwwroot = new moodle_url('/moodle-sso-buttons.php');
           return '<script src="' . $wwwroot->out() . '" async></script>';
       }
       return '';
   }
   ```

4. В файле `footer.mustache` добавьте:
   ```mustache
   {{{ output.sso_buttons }}}
   ```

5. В файле `lib.php` темы добавьте:
   ```php
   public function sso_buttons() {
       return local_sso_buttons_before_footer_html();
   }
   ```

## Способ 2: Прямое включение в header.mustache

Если вы хотите встроить код напрямую, добавьте в `header.mustache`:

```mustache
{{#isloggedin}}
<div id="moodle-sso-buttons-placeholder"></div>
<script>
(function() {
    var script = document.createElement('script');
    script.src = '{{config.wwwroot}}/moodle-sso-buttons.php';
    script.async = true;
    document.head.appendChild(script);
})();
</script>
{{/isloggedin}}
```

## Способ 3: Через настройки темы (Если поддерживается)

Некоторые темы Moodle позволяют добавлять кастомный HTML/JavaScript через настройки темы:
1. Перейдите в **Администрирование сайта → Внешний вид → Темы → Настройки темы**
2. Найдите поле "Дополнительный HTML" или "Custom HTML"
3. Добавьте:
   ```html
   <script src="https://class.russianseminary.org/moodle-sso-buttons.php" async></script>
   ```

## После установки

1. Очистите кэш Moodle:
   - Перейдите в **Администрирование сайта → Разработка → Очистить все кэши**
   - Или выполните команду: `php admin/cli/purge_caches.php`

2. Проверьте работу:
   - Войдите в Moodle как авторизованный пользователь
   - Кнопки "Сайт семинарии" и "Деканат" должны появиться в шапке рядом с меню пользователя

## Настройка файла moodle-sso-buttons.php

Откройте файл `/var/www/www-root/data/www/class.russianseminary.org/moodle-sso-buttons.php` и проверьте настройки:

```php
$wordpress_url = 'https://mbs.russianseminary.org'; // URL вашего WordPress сайта
$laravel_url = 'https://dekanat.russianseminary.org'; // URL вашего Laravel приложения
$sso_api_key = ''; // SSO API Key из WordPress (опционально)
```

## Устранение неполадок

Если кнопки не отображаются:

1. Проверьте логи Moodle:
   ```bash
   tail -f /var/www/www-root/data/www/moodledata/error.log | grep "Moodle SSO"
   ```

2. Проверьте консоль браузера (F12) на наличие ошибок JavaScript

3. Убедитесь, что файл доступен:
   ```bash
   curl https://class.russianseminary.org/moodle-sso-buttons.php
   ```

4. Проверьте права доступа:
   ```bash
   ls -la /var/www/www-root/data/www/class.russianseminary.org/moodle-sso-buttons.php
   ```
