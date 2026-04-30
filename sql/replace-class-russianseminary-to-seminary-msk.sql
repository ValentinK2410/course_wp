-- =============================================================================
-- Поиск и замена домена Moodle в базе WordPress (MySQL / MariaDB)
--   class.russianseminary.org  →  class.seminary.msk.ru
--
-- ВАЖНО:
-- 1) Сделайте резервную копию БД перед UPDATE.
-- 2) Длина новой строки отличается от старой — простой REPLACE в wp_options
--    может испортить СЕРИАЛИЗОВАННЫЕ значения (s:NN:"..."). Безопаснее после
--    замены прогнать WP-CLI: wp search-replace 'class.russianseminary.org' 'class.seminary.msk.ru'
--    (он пересчитает длины в сериализации) или использовать плагин Better Search Replace.
-- 3) Замените префикс таблиц wp_ на свой, если отличается.
-- =============================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- 1) ПОИСК (выполните сначала и проверьте количество строк)
-- ---------------------------------------------------------------------------

SELECT 'wp_options' AS tbl, option_id, option_name,
       LENGTH(option_value) AS len
FROM wp_options
WHERE option_value LIKE '%class.russianseminary.org%';

SELECT 'wp_posts' AS tbl, ID, post_type, post_status
FROM wp_posts
WHERE post_content LIKE '%class.russianseminary.org%'
   OR post_excerpt LIKE '%class.russianseminary.org%'
   OR guid LIKE '%class.russianseminary.org%';

SELECT 'wp_postmeta' AS tbl, meta_id, post_id, meta_key
FROM wp_postmeta
WHERE meta_value LIKE '%class.russianseminary.org%';

SELECT 'wp_comments' AS tbl, comment_ID
FROM wp_comments
WHERE comment_content LIKE '%class.russianseminary.org%'
   OR comment_author_url LIKE '%class.russianseminary.org%';

SELECT 'wp_usermeta' AS tbl, umeta_id, user_id, meta_key
FROM wp_usermeta
WHERE meta_value LIKE '%class.russianseminary.org%';

-- При необходимости — другие таблицы с текстом
-- SELECT * FROM wp_termmeta WHERE meta_value LIKE '%class.russianseminary.org%';

-- ---------------------------------------------------------------------------
-- 2) ЗАМЕНА (раскомментируйте после проверки SELECT)
-- ---------------------------------------------------------------------------

/*
START TRANSACTION;

UPDATE wp_options
SET option_value = REPLACE(option_value, 'class.russianseminary.org', 'class.seminary.msk.ru')
WHERE option_value LIKE '%class.russianseminary.org%';

UPDATE wp_posts
SET post_content = REPLACE(post_content, 'class.russianseminary.org', 'class.seminary.msk.ru'),
    post_excerpt = REPLACE(post_excerpt, 'class.russianseminary.org', 'class.seminary.msk.ru'),
    guid = REPLACE(guid, 'class.russianseminary.org', 'class.seminary.msk.ru')
WHERE post_content LIKE '%class.russianseminary.org%'
   OR post_excerpt LIKE '%class.russianseminary.org%'
   OR guid LIKE '%class.russianseminary.org%';

UPDATE wp_postmeta
SET meta_value = REPLACE(meta_value, 'class.russianseminary.org', 'class.seminary.msk.ru')
WHERE meta_value LIKE '%class.russianseminary.org%';

UPDATE wp_comments
SET comment_content = REPLACE(comment_content, 'class.russianseminary.org', 'class.seminary.msk.ru'),
    comment_author_url = REPLACE(comment_author_url, 'class.russianseminary.org', 'class.seminary.msk.ru')
WHERE comment_content LIKE '%class.russianseminary.org%'
   OR comment_author_url LIKE '%class.russianseminary.org%';

UPDATE wp_usermeta
SET meta_value = REPLACE(meta_value, 'class.russianseminary.org', 'class.seminary.msk.ru')
WHERE meta_value LIKE '%class.russianseminary.org%';

-- COMMIT;
-- ROLLBACK;
*/

-- ---------------------------------------------------------------------------
-- 3) Рекомендуемо после SQL-REPLACE по options/postmeta (починка сериализации)
-- ---------------------------------------------------------------------------
-- Из корня WordPress (если установлен WP-CLI):
-- wp search-replace 'class.russianseminary.org' 'class.seminary.msk.ru' --all-tables --precise --dry-run
-- убрать --dry-run после проверки
