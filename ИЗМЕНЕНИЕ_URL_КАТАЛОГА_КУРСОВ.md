# Изменение URL каталога курсов на /courses/

## Что было изменено

Изменен slug архива курсов с `/course/` на `/courses/` в файле:
- `course-plugin/includes/class-course-post-type.php`

**Было:**
```php
'rewrite' => array('slug' => 'course'),
```

**Стало:**
```php
'rewrite' => array('slug' => 'courses'),
```

## Что нужно сделать после изменений

### 1. Сбросить правила перезаписи URL (rewrite rules)

**Вариант 1: Через админ-панель WordPress (рекомендуется)**
1. Войдите в админ-панель WordPress
2. Перейдите в **Настройки → Постоянные ссылки**
3. Нажмите кнопку **Сохранить изменения** (не нужно ничего менять, просто сохраните)
4. Готово! Правила перезаписи URL обновлены

**Вариант 2: Через код (временный файл)**
Создайте файл `reset-rewrite-rules.php` в корне WordPress:

```php
<?php
require_once('wp-load.php');
flush_rewrite_rules();
echo "Rewrite rules сброшены! Теперь каталог курсов доступен по адресу /courses/";
```

Откройте файл в браузере: `https://mbs.russianseminary.org/reset-rewrite-rules.php`
После этого удалите файл.

**Вариант 3: Через WP-CLI (если доступен)**
```bash
wp rewrite flush
```

### 2. Проверить работу

После сброса rewrite rules:

1. **Откройте каталог курсов:**
   - Старый URL `/course/` больше не будет работать
   - Новый URL `/courses/` должен работать ✅

2. **Проверьте ссылки в шаблоне:**
   - Все ссылки используют функцию `get_post_type_archive_link('course')`
   - Эта функция автоматически использует новый slug `courses`
   - Никаких дополнительных изменений не требуется

3. **Проверьте меню навигации:**
   - Если в меню есть ссылка на каталог курсов, она должна автоматически обновиться
   - Если ссылка была добавлена вручную, обновите её на `/courses/`

## Технические детали

### Что изменилось

1. **Slug архива:** `course` → `courses`
2. **URL архива:** `/course/` → `/courses/`
3. **REST API:** остался `courses` (без изменений)

### Что НЕ изменилось

1. **Тип поста:** остался `course` (внутренний идентификатор)
2. **URL отдельных курсов:** остались без изменений (например, `/course/nazvanie-kursa/`)
3. **Все функции WordPress:** продолжают работать с типом поста `course`

### Поддержка обоих вариантов URL

Код уже поддерживает оба варианта URL (`/course/` и `/courses/`) для обратной совместимости, но после сброса rewrite rules основной URL будет `/courses/`.

## Проверка после изменений

### 1. Проверка URL архива

```bash
# Должен вернуть: https://mbs.russianseminary.org/courses/
curl -I https://mbs.russianseminary.org/courses/
```

### 2. Проверка через WordPress

Создайте временный файл `test-courses-url.php` в корне WordPress:

```php
<?php
require_once('wp-load.php');

echo '<h1>Проверка URL каталога курсов</h1>';
echo '<p>URL архива: <a href="' . get_post_type_archive_link('course') . '">' . get_post_type_archive_link('course') . '</a></p>';
echo '<p>Ожидаемый URL: https://mbs.russianseminary.org/courses/</p>';

if (get_post_type_archive_link('course') === 'https://mbs.russianseminary.org/courses/') {
    echo '<p style="color: green;">✅ URL правильный!</p>';
} else {
    echo '<p style="color: red;">❌ URL неправильный. Нужно сбросить rewrite rules.</p>';
}
```

Откройте файл в браузере для проверки.

## Важные замечания

1. **Старые ссылки:** После изменения старые ссылки `/course/` перестанут работать. Если у вас есть внешние ссылки на `/course/`, их нужно обновить.

2. **Кэш:** Если используется кэширование (WP Super Cache, W3 Total Cache и т.д.), очистите кэш после изменений.

3. **CDN:** Если используется CDN (Cloudflare и т.д.), очистите кэш CDN.

4. **Поисковые системы:** Google и другие поисковые системы со временем обновят индексацию на новый URL.

## Откат изменений

Если нужно вернуть обратно на `/course/`:

1. Измените в `class-course-post-type.php`:
   ```php
   'rewrite' => array('slug' => 'course'),
   ```

2. Сбросьте rewrite rules (см. выше)

3. Готово!

## Контакты

Если возникли проблемы после изменений, обратитесь к разработчику.
