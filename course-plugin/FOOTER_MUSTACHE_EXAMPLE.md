# Пример добавления кнопок SSO в footer.mustache

## Где добавить код

Добавьте следующий код в **самом конце файла** `footer.mustache`, после последнего блока `{{#js}}`:

```mustache
{{#js}}
require(['theme_boost/footer-popover'], function(FooterPopover) {
    FooterPopover.init();
});
{{/js}}

{{! SSO Buttons для перехода в WordPress и Laravel }}
{{#isloggedin}}
<script src="{{config.wwwroot}}/moodle-sso-buttons.php" async></script>
{{/isloggedin}}
```

## Полный пример конца файла footer.mustache

```mustache
{{#js}}
require(['theme_boost/footer-popover'], function(FooterPopover) {
    FooterPopover.init();
});
{{/js}}

{{! SSO Buttons для перехода в WordPress и Laravel }}
{{#isloggedin}}
<script src="{{config.wwwroot}}/moodle-sso-buttons.php" async></script>
{{/isloggedin}}
```

## Важно

1. Код должен быть добавлен **после всех блоков** `{{#js}}`
2. Условие `{{#isloggedin}}` гарантирует, что скрипт загрузится только для авторизованных пользователей
3. Атрибут `async` позволяет скрипту загружаться асинхронно, не блокируя загрузку страницы
4. После добавления кода **обязательно очистите кэш Moodle**:
   - Администрирование сайта → Разработка → Очистить все кэши
   - Или выполните: `php admin/cli/purge_caches.php`

## Альтернативный вариант (если нужна большая совместимость)

Если по какой-то причине первый вариант не работает, можно добавить код в блок `{{# output.standard_end_of_body_html }}`:

```mustache
<div class="footer-section p-3 border-bottom">
    <div class="logininfo">
        {{{ output.login_info }}}
    </div>
    <div class="tool_usertours-resettourcontainer">
    </div>
    {{{ output.standard_footer_html }}}
    {{! SSO Buttons для перехода в WordPress и Laravel }}
    {{#isloggedin}}
    <script src="{{config.wwwroot}}/moodle-sso-buttons.php" async></script>
    {{/isloggedin}}
    {{{ output.standard_end_of_body_html }}}
</div>
```

Но **рекомендуется первый вариант** - в самом конце файла.
