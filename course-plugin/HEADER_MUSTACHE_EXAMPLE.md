# Пример добавления кнопок SSO в header.mustache

## Где добавить код

Добавьте код в файл `header.mustache` темы Academi. Обычно он находится здесь:
```
/var/www/www-root/data/www/class.russianseminary.org/theme/academi/templates/header.mustache
```

## Где именно в файле

Ищите место, где отображается меню пользователя или навигация. Обычно это:
- Перед закрывающим тегом `</header>`
- Или перед закрывающим тегом `</head>`
- Или в блоке с навигацией

## Вариант 1: В конце header, перед закрывающим тегом

Найдите конец файла `header.mustache` (перед `</header>` или `</head>`) и добавьте:

```mustache
{{! SSO Buttons для перехода в WordPress и Laravel }}
{{#isloggedin}}
<script src="{{config.wwwroot}}/moodle-sso-buttons.php" async></script>
{{/isloggedin}}
```

## Вариант 2: В блоке с навигацией (если есть)

Если в header.mustache есть блок с навигацией или меню пользователя, добавьте туда:

```mustache
<nav class="navbar">
    <!-- существующий код навигации -->
    
    {{! SSO Buttons для перехода в WordPress и Laravel }}
    {{#isloggedin}}
    <div id="moodle-sso-buttons-placeholder"></div>
    <script src="{{config.wwwroot}}/moodle-sso-buttons.php" async></script>
    {{/isloggedin}}
</nav>
```

## Вариант 3: В блоке с меню пользователя

Если есть блок с меню пользователя (usermenu), добавьте перед ним:

```mustache
{{! SSO Buttons для перехода в WordPress и Laravel }}
{{#isloggedin}}
<script src="{{config.wwwroot}}/moodle-sso-buttons.php" async></script>
{{/isloggedin}}

<!-- существующий код меню пользователя -->
<div class="usermenu">
    <!-- ... -->
</div>
```

## Полный пример конца header.mustache

```mustache
<!-- существующий код header -->

{{! SSO Buttons для перехода в WordPress и Laravel }}
{{#isloggedin}}
<script src="{{config.wwwroot}}/moodle-sso-buttons.php" async></script>
{{/isloggedin}}

</header>
```

## Важно

1. Код должен быть внутри блока `{{#isloggedin}}`, чтобы загружался только для авторизованных пользователей
2. Атрибут `async` позволяет скрипту загружаться асинхронно
3. После добавления **обязательно очистите кэш Moodle**:
   - Администрирование сайта → Разработка → Очистить все кэши
   - Или выполните: `php admin/cli/purge_caches.php`

## Проверка

После добавления:
1. Откройте Moodle в браузере (авторизуйтесь)
2. Нажмите F12 → Console
3. Обновите страницу
4. Ищите сообщения "Moodle SSO:"
5. Кнопки должны появиться в шапке рядом с меню пользователя
