# Редактирование текстов архива курсов в админке WordPress

## Описание

Это руководство покажет вам, как добавить возможность редактировать заголовок и подзаголовок страницы архива курсов прямо из админ-панели WordPress, без необходимости редактировать код шаблона.

## Что мы будем делать

Добавим форму редактирования на страницу списка курсов (`edit.php?post_type=course`), которая позволит изменять:
- Основную часть заголовка (например, "Курсы")
- Дополнительную часть заголовка (например, "для вашего развития")
- Подзаголовок (текст под заголовком)

## Пошаговая инструкция

### Шаг 1: Понимание структуры

В WordPress для хранения настроек используются **опции** (options). Это специальные записи в базе данных, которые хранят различные настройки сайта.

**Технические термины:**
- **Опции (Options)** - способ хранения настроек в базе данных WordPress
- **Nonce** - токен безопасности для защиты от CSRF-атак
- **Хук (Hook)** - точка в коде WordPress, где можно выполнить свой код
- **Метабокс** - блок с дополнительными полями в админ-панели

### Шаг 2: Добавление метода для отображения формы

Откройте файл `course-plugin/includes/class-course-admin.php` и найдите метод `__construct()`. Добавьте следующие хуки:

```php
// Добавляем метабокс для редактирования текстов архива курсов
add_action('admin_notices', array($this, 'add_archive_texts_meta_box'));

// Сохраняем настройки архива
add_action('admin_init', array($this, 'save_archive_texts'));
```

**Объяснение:**
- `admin_notices` - хук, который срабатывает при выводе уведомлений в админке. Мы используем его для вывода нашей формы.
- `admin_init` - хук, который срабатывает при инициализации админ-панели. Используется для обработки сохранения данных.

### Шаг 3: Создание метода для отображения формы

Добавьте новый метод в класс `Course_Admin`:

```php
/**
 * Добавление метабокса для редактирования текстов архива курсов
 * Отображается на странице списка курсов
 */
public function add_archive_texts_meta_box() {
    global $pagenow, $post_type;
    
    // Показываем только на странице списка курсов
    if ('edit.php' !== $pagenow || 'course' !== $post_type) {
        return;
    }
    
    // Получаем сохраненные значения
    $archive_title_main = get_option('course_archive_title_main', __('Курсы', 'course-plugin'));
    $archive_title_sub = get_option('course_archive_title_sub', __('для вашего развития', 'course-plugin'));
    $archive_subtitle = get_option('course_archive_subtitle', __('Выберите курс, который поможет вам достичь новых вершин в карьере', 'course-plugin'));
    
    ?>
    <div class="notice notice-info" style="margin: 20px 0; padding: 15px;">
        <h2 style="margin-top: 0;"><?php _e('Настройки страницы архива курсов', 'course-plugin'); ?></h2>
        <form method="post" action="">
            <?php wp_nonce_field('course_archive_texts', 'course_archive_texts_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="archive_title_main"><?php _e('Заголовок (основная часть)', 'course-plugin'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="archive_title_main" name="archive_title_main" value="<?php echo esc_attr($archive_title_main); ?>" class="regular-text" />
                        <p class="description"><?php _e('Основная часть заголовка (например: "Курсы")', 'course-plugin'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="archive_title_sub"><?php _e('Заголовок (дополнительная часть)', 'course-plugin'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="archive_title_sub" name="archive_title_sub" value="<?php echo esc_attr($archive_title_sub); ?>" class="regular-text" />
                        <p class="description"><?php _e('Дополнительная часть заголовка (например: "для вашего развития")', 'course-plugin'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="archive_subtitle"><?php _e('Подзаголовок', 'course-plugin'); ?></label>
                    </th>
                    <td>
                        <textarea id="archive_subtitle" name="archive_subtitle" rows="3" class="large-text"><?php echo esc_textarea($archive_subtitle); ?></textarea>
                        <p class="description"><?php _e('Текст подзаголовка под основным заголовком', 'course-plugin'); ?></p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="save_archive_texts" class="button button-primary" value="<?php esc_attr_e('Сохранить настройки', 'course-plugin'); ?>" />
            </p>
        </form>
    </div>
    <?php
}
```

**Объяснение кода:**

1. **Проверка страницы:**
   ```php
   if ('edit.php' !== $pagenow || 'course' !== $post_type) {
       return;
   }
   ```
   Это проверяет, что мы находимся на странице списка курсов. Если нет - метод завершается.

2. **Получение сохраненных значений:**
   ```php
   $archive_title_main = get_option('course_archive_title_main', __('Курсы', 'course-plugin'));
   ```
   `get_option()` получает значение из базы данных. Второй параметр - значение по умолчанию, если опция еще не сохранена.

3. **Nonce для безопасности:**
   ```php
   wp_nonce_field('course_archive_texts', 'course_archive_texts_nonce');
   ```
   Создает скрытое поле с токеном безопасности для защиты от CSRF-атак.

4. **Экранирование данных:**
   ```php
   echo esc_attr($archive_title_main);
   ```
   `esc_attr()` экранирует специальные символы для безопасного вывода в HTML-атрибутах.

### Шаг 4: Создание метода для сохранения данных

Добавьте метод для обработки сохранения:

```php
/**
 * Сохранение настроек архива курсов
 */
public function save_archive_texts() {
    // Проверяем, что форма была отправлена
    if (!isset($_POST['save_archive_texts'])) {
        return;
    }
    
    // Проверяем nonce для безопасности
    if (!isset($_POST['course_archive_texts_nonce']) || !wp_verify_nonce($_POST['course_archive_texts_nonce'], 'course_archive_texts')) {
        return;
    }
    
    // Проверяем права доступа
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Сохраняем значения
    if (isset($_POST['archive_title_main'])) {
        update_option('course_archive_title_main', sanitize_text_field($_POST['archive_title_main']));
    }
    
    if (isset($_POST['archive_title_sub'])) {
        update_option('course_archive_title_sub', sanitize_text_field($_POST['archive_title_sub']));
    }
    
    if (isset($_POST['archive_subtitle'])) {
        update_option('course_archive_subtitle', sanitize_textarea_field($_POST['archive_subtitle']));
    }
    
    // Показываем сообщение об успехе
    add_action('admin_notices', function() {
        echo '<div class="notice notice-success is-dismissible"><p>' . __('Настройки архива курсов успешно сохранены!', 'course-plugin') . '</p></div>';
    });
}
```

**Объяснение проверок безопасности:**

1. **Проверка отправки формы:**
   ```php
   if (!isset($_POST['save_archive_texts'])) {
       return;
   }
   ```
   Проверяет, что форма была отправлена (кнопка "Сохранить" была нажата).

2. **Проверка nonce:**
   ```php
   wp_verify_nonce($_POST['course_archive_texts_nonce'], 'course_archive_texts')
   ```
   Проверяет, что запрос пришел с нашей формы, а не от злоумышленника.

3. **Проверка прав:**
   ```php
   if (!current_user_can('manage_options')) {
       return;
   }
   ```
   Проверяет, что пользователь имеет права администратора.

4. **Санитизация данных:**
   ```php
   sanitize_text_field($_POST['archive_title_main'])
   ```
   Очищает данные от вредоносного кода перед сохранением.

### Шаг 5: Обновление шаблона архива

Откройте файл `course-plugin/templates/archive-course.php` и найдите строки с заголовком:

**Было:**
```php
<h1 class="premium-archive-title">
    <span class="title-accent"><?php _e('Курсы', 'course-plugin'); ?></span>
    <span class="title-sub"><?php _e('для вашего развития', 'course-plugin'); ?></span>
</h1>
<p class="premium-archive-subtitle"><?php _e('Выберите курс, который поможет вам достичь новых вершин в карьере', 'course-plugin'); ?></p>
```

**Стало:**
```php
<h1 class="premium-archive-title">
    <span class="title-accent"><?php echo esc_html(get_option('course_archive_title_main', __('Курсы', 'course-plugin'))); ?></span>
    <span class="title-sub"><?php echo esc_html(get_option('course_archive_title_sub', __('для вашего развития', 'course-plugin'))); ?></span>
</h1>
<p class="premium-archive-subtitle"><?php echo esc_html(get_option('course_archive_subtitle', __('Выберите курс, который поможет вам достичь новых вершин в карьере', 'course-plugin'))); ?></p>
```

**Объяснение:**
- `get_option()` - получает сохраненное значение из базы данных
- Второй параметр - значение по умолчанию, если опция не установлена
- `esc_html()` - экранирует HTML-символы для безопасного вывода

## Как использовать

1. Перейдите в админ-панель WordPress
2. Откройте раздел "Курсы" → "Все курсы"
3. Вверху страницы вы увидите блок "Настройки страницы архива курсов"
4. Отредактируйте нужные тексты
5. Нажмите "Сохранить настройки"
6. Изменения сразу отобразятся на странице архива курсов

## Важные моменты безопасности

1. **Всегда проверяйте nonce** - защита от CSRF-атак
2. **Проверяйте права доступа** - только администраторы могут изменять настройки
3. **Санитизируйте данные** - очищайте входящие данные перед сохранением
4. **Экранируйте вывод** - используйте `esc_html()`, `esc_attr()` при выводе данных

## Функции WordPress, которые мы использовали

- `get_option($name, $default)` - получить значение опции из базы данных
- `update_option($name, $value)` - сохранить значение опции в базу данных
- `wp_nonce_field($action, $name)` - создать поле с токеном безопасности
- `wp_verify_nonce($nonce, $action)` - проверить токен безопасности
- `current_user_can($capability)` - проверить права пользователя
- `sanitize_text_field($str)` - очистить текстовое поле
- `sanitize_textarea_field($str)` - очистить текстовую область
- `esc_html($text)` - экранировать HTML
- `esc_attr($text)` - экранировать атрибуты HTML
- `esc_textarea($text)` - экранировать текст для textarea

## Адаптация для других типов постов

Если вы хотите добавить такую же функциональность для другого типа постов (например, "Программы"), просто:

1. Замените `'course'` на ваш тип поста (например, `'program'`)
2. Измените названия опций (например, `'program_archive_title_main'`)
3. Обновите шаблон архива вашего типа поста

### Пример: Добавление для типа поста "Программы"

В файле `class-program-admin.php` добавьте в конструктор:

```php
// Добавляем метабокс для редактирования текстов архива программ
add_action('admin_notices', array($this, 'add_archive_texts_meta_box'));

// Сохраняем настройки архива
add_action('admin_init', array($this, 'save_archive_texts'));
```

Затем добавьте методы `add_archive_texts_meta_box()` и `save_archive_texts()`, заменив:
- `'course'` на `'program'`
- `'course_archive_title_main'` на `'program_archive_title_main'`
- `'course_archive_title_sub'` на `'program_archive_title_sub'`
- `'course_archive_subtitle'` на `'program_archive_subtitle'`
- `'course_archive_texts'` на `'program_archive_texts'`

В шаблоне `archive-program.php` замените статические тексты на:

```php
<h1 class="premium-archive-title">
    <span class="title-accent"><?php echo esc_html(get_option('program_archive_title_main', __('Программы', 'course-plugin'))); ?></span>
    <span class="title-sub"><?php echo esc_html(get_option('program_archive_title_sub', __('обучения и развития', 'course-plugin'))); ?></span>
</h1>
<p class="premium-archive-subtitle"><?php echo esc_html(get_option('program_archive_subtitle', __('Комплексные программы для достижения профессиональных целей', 'course-plugin'))); ?></p>
```

## Заключение

Теперь вы можете редактировать тексты архива курсов прямо из админ-панели WordPress, не редактируя код шаблона. Это делает управление сайтом более удобным и безопасным.

---

**Автор:** valentink2410  
**Дата создания:** 2026  
**Версия:** 1.0
