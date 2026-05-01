-- =============================================================================
-- Поиск и замена домена Moodle в базе WordPress (MySQL / MariaDB)
--   class.seminary.msk.ru  →  class.mbs.ru
--
-- ВАЖНО:
-- 1) Сделайте резервную копию БД перед UPDATE.
-- 2) Длина строк разная — простой REPLACE в wp_options может сломать
--    СЕРИАЛИЗОВАННЫЕ значения. После SQL лучше выполнить WP-CLI:
--    wp search-replace 'class.seminary.msk.ru' 'class.mbs.ru' --all-tables --precise
--    Сначала с --dry-run. Либо используйте в админке: Инструменты → Замена в БД (плагин Курсы Про).
-- 3) Замените префикс таблиц wp_ на свой, если отличается.
-- =============================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- 1) ПОИСК
-- ---------------------------------------------------------------------------

SELECT 'wp_options' AS tbl, option_id, option_name,
       LENGTH(option_value) AS len
FROM wp_options
WHERE option_value LIKE '%class.seminary.msk.ru%';

SELECT 'wp_posts' AS tbl, ID, post_type, post_status
FROM wp_posts
WHERE post_content LIKE '%class.seminary.msk.ru%'
   OR post_excerpt LIKE '%class.seminary.msk.ru%'
   OR guid LIKE '%class.seminary.msk.ru%';

SELECT 'wp_postmeta' AS tbl, meta_id, post_id, meta_key
FROM wp_postmeta
WHERE meta_value LIKE '%class.seminary.msk.ru%';

SELECT 'wp_comments' AS tbl, comment_ID
FROM wp_comments
WHERE comment_content LIKE '%class.seminary.msk.ru%'
   OR comment_author_url LIKE '%class.seminary.msk.ru%';

SELECT 'wp_usermeta' AS tbl, umeta_id, user_id, meta_key
FROM wp_usermeta
WHERE meta_value LIKE '%class.seminary.msk.ru%';

-- ---------------------------------------------------------------------------
-- 2) ЗАМЕНА (раскомментируйте после проверки SELECT)
-- ---------------------------------------------------------------------------

/*
START TRANSACTION;

UPDATE wp_options
SET option_value = REPLACE(option_value, 'class.seminary.msk.ru', 'class.mbs.ru')
WHERE option_value LIKE '%class.seminary.msk.ru%';

UPDATE wp_posts
SET post_content = REPLACE(post_content, 'class.seminary.msk.ru', 'class.mbs.ru'),
    post_excerpt = REPLACE(post_excerpt, 'class.seminary.msk.ru', 'class.mbs.ru'),
    guid = REPLACE(guid, 'class.seminary.msk.ru', 'class.mbs.ru')
WHERE post_content LIKE '%class.seminary.msk.ru%'
   OR post_excerpt LIKE '%class.seminary.msk.ru%'
   OR guid LIKE '%class.seminary.msk.ru%';

UPDATE wp_postmeta
SET meta_value = REPLACE(meta_value, 'class.seminary.msk.ru', 'class.mbs.ru')
WHERE meta_value LIKE '%class.seminary.msk.ru%';

UPDATE wp_comments
SET comment_content = REPLACE(comment_content, 'class.seminary.msk.ru', 'class.mbs.ru'),
    comment_author_url = REPLACE(comment_author_url, 'class.seminary.msk.ru', 'class.mbs.ru')
WHERE comment_content LIKE '%class.seminary.msk.ru%'
   OR comment_author_url LIKE '%class.seminary.msk.ru%';

UPDATE wp_usermeta
SET meta_value = REPLACE(meta_value, 'class.seminary.msk.ru', 'class.mbs.ru')
WHERE meta_value LIKE '%class.seminary.msk.ru%';

-- COMMIT;
-- ROLLBACK;
*/

-- ---------------------------------------------------------------------------
-- 3) WP-CLI (рекомендуется для сериализации)
-- ---------------------------------------------------------------------------
-- wp search-replace 'class.seminary.msk.ru' 'class.mbs.ru' --all-tables --precise --dry-run
-- убрать --dry-run после проверки
