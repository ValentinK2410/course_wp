# Добавление кнопок SSO в header.mustache темы Academi

## Вариант 1: После навбара (Рекомендуется)

Добавьте код сразу после `{{> theme_academi/navbar }}`:

```mustache
{{> theme_academi/navbar }}

{{! SSO Buttons для перехода в WordPress и Laravel }}
{{#isloggedin}}
<script src="{{config.wwwroot}}/moodle-sso-buttons.php" async></script>
{{/isloggedin}}

{{#themestyleheader}}
    <div class="header-main">
        <!-- остальной код -->
    </div>
{{/themestyleheader}}
```

## Вариант 2: Внутри блока themestyleheader, перед закрывающим тегом

Добавьте перед `{{/themestyleheader}}`:

```mustache
                <ul class="navbar-nav d-none d-md-flex my-1 px-1">
                    <!-- page_heading_menu -->
                    {{{ output.page_heading_menu }}}
                </ul>

                {{! SSO Buttons для перехода в WordPress и Laravel }}
                {{#isloggedin}}
                <div id="moodle-sso-buttons-placeholder"></div>
                <script src="{{config.wwwroot}}/moodle-sso-buttons.php" async></script>
                {{/isloggedin}}

            </nav>
        </div>
    </div>
{{/themestyleheader}}
```

## Вариант 3: В конце файла (если есть закрывающий тег)

Если в конце файла есть еще какой-то код или закрывающий тег, добавьте перед ним:

```mustache
{{/themestyleheader}}

{{! SSO Buttons для перехода в WordPress и Laravel }}
{{#isloggedin}}
<script src="{{config.wwwroot}}/moodle-sso-buttons.php" async></script>
{{/isloggedin}}
```

## Полный пример файла (Вариант 1 - Рекомендуется)

```mustache
{{!
    This file is part of Moodle - http://moodle.org/
    ...
}}

{{> theme_academi/navbar }}

{{! SSO Buttons для перехода в WordPress и Laravel }}
{{#isloggedin}}
<script src="{{config.wwwroot}}/moodle-sso-buttons.php" async></script>
{{/isloggedin}}

{{#themestyleheader}}
    <div class="header-main">
        <div class="container-fluid">
            <nav class="navbar navbar-light bg-faded navbar-expand">
                <a href="{{{ config.wwwroot }}}/?redirect=0" class="navbar-brand {{# logourl }}has-logo{{/ logourl }}
                {{^ logourl }}
                    hidden-sm-down
                {{/ logourl }}
                    ">
                {{#showlogo}}
                    {{# logourl }}
                        <span class="logo">
                            <img src="{{logourl}}" alt="{{sitename}}">
                        </span>
                    {{/ logourl }}
                    {{^ logourl }}
                        <span class="site-name hidden-sm-down">{{{ sitename }}}</span>
                    {{/ logourl }}
                {{/showlogo}}
                {{#showsitename}}
                    <span class="nav-site-name">{{{sitename}}}</span>
                {{/showsitename}}
                </a>

                {{#primarymoremenu}}
                    <div class="primary-navigation">
                        {{> core/moremenu}}
                    </div>
                {{/primarymoremenu}}

                <ul class="navbar-nav d-none d-md-flex my-1 px-1">
                    <!-- page_heading_menu -->
                    {{{ output.page_heading_menu }}}
                </ul>

            </nav>
        </div>
    </div>
{{/themestyleheader}}
```

## После добавления

1. Сохраните файл
2. Очистите кэш Moodle:
   - Администрирование сайта → Разработка → Очистить все кэши
   - Или: `php admin/cli/purge_caches.php`
3. Обновите страницу Moodle
4. Откройте консоль браузера (F12) и проверьте сообщения "Moodle SSO:"

## Проверка

Кнопки должны появиться в шапке Moodle рядом с меню пользователя. Если не появились:
- Проверьте консоль браузера на ошибки
- Убедитесь, что вы авторизованы в Moodle
- Проверьте, что файл `moodle-sso-buttons.php` доступен по URL
