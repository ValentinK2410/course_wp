<?php
/**
 * Панель навигации по каталогу на обложке курса/программы (предыдущий → каталог → следующий).
 *
 * @package Course_Plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Соседние записи в порядке даты начала (как в каталоге), с зацикливанием.
 */
class Course_Hero_Catalog_Nav {

    /**
     * @param string $post_type course|program
     * @return int[]
     */
    public static function get_sorted_published_ids($post_type) {
        if (!in_array($post_type, array('course', 'program'), true)) {
            return array();
        }

        $meta_key = ($post_type === 'course') ? '_course_start_date' : '_program_start_date';

        $posts = get_posts(array(
            'post_type'              => $post_type,
            'post_status'            => 'publish',
            'posts_per_page'         => -1,
            'orderby'                => 'title',
            'order'                  => 'ASC',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ));

        if (empty($posts)) {
            return array();
        }

        usort($posts, function ($a, $b) use ($meta_key) {
            $da = (string) get_post_meta($a->ID, $meta_key, true);
            $db = (string) get_post_meta($b->ID, $meta_key, true);
            if ($da === '') {
                $da = '9999-12-31';
            }
            if ($db === '') {
                $db = '9999-12-31';
            }
            $c = strcmp($da, $db);
            if ($c !== 0) {
                return $c;
            }
            return strcmp($a->post_title, $b->post_title);
        });

        $ids = array();
        foreach ($posts as $p) {
            $ids[] = (int) $p->ID;
        }

        return apply_filters('course_hero_catalog_sorted_ids', $ids, $post_type);
    }

    /**
     * @param int    $post_id
     * @param string $post_type course|program
     * @param string $modifier  CSS-модификатор: '' | 'program'
     */
    public static function render($post_id, $post_type, $modifier = '') {
        $post_id = (int) $post_id;
        $ids     = self::get_sorted_published_ids($post_type);
        if (empty($ids) || !in_array($post_id, $ids, true)) {
            return;
        }

        $archive_url = get_post_type_archive_link($post_type);
        if (!is_string($archive_url) || $archive_url === '') {
            return;
        }

        $n = count($ids);
        if ($n < 2) {
            self::render_catalog_only($archive_url, $post_type, $modifier);
            return;
        }

        $idx      = array_search($post_id, $ids, true);
        $prev_id  = $ids[($idx - 1 + $n) % $n];
        $next_id  = $ids[($idx + 1) % $n];
        $prev_url = get_permalink($prev_id);
        $next_url = get_permalink($next_id);
        if (!$prev_url || !$next_url) {
            return;
        }

        $is_program = ($post_type === 'program');
        $aria_prev  = $is_program
            ? esc_attr__('Предыдущая программа', 'course-plugin')
            : esc_attr__('Предыдущий курс', 'course-plugin');
        $aria_next = $is_program
            ? esc_attr__('Следующая программа', 'course-plugin')
            : esc_attr__('Следующий курс', 'course-plugin');
        $aria_cat = $is_program
            ? esc_attr__('Каталог программ', 'course-plugin')
            : esc_attr__('Каталог курсов', 'course-plugin');

        $mod_class = $modifier !== '' ? ' hero-catalog-nav--' . esc_attr($modifier) : '';
        ?>
        <div class="hero-catalog-nav<?php echo $mod_class; ?>" role="navigation" aria-label="<?php echo esc_attr($aria_cat); ?>">
            <a class="hero-catalog-nav__btn hero-catalog-nav__prev" href="<?php echo esc_url($prev_url); ?>" aria-label="<?php echo $aria_prev; ?>">
                <?php echo self::icon_chevron_left(); ?>
            </a>
            <span class="hero-catalog-nav__sep" aria-hidden="true"></span>
            <a class="hero-catalog-nav__btn hero-catalog-nav__catalog" href="<?php echo esc_url($archive_url); ?>" aria-label="<?php echo $aria_cat; ?>">
                <?php echo self::icon_layers(); ?>
            </a>
            <span class="hero-catalog-nav__sep" aria-hidden="true"></span>
            <a class="hero-catalog-nav__btn hero-catalog-nav__next" href="<?php echo esc_url($next_url); ?>" aria-label="<?php echo $aria_next; ?>">
                <?php echo self::icon_chevron_right(); ?>
            </a>
        </div>
        <?php
    }

    /**
     * @param string $archive_url
     * @param string $post_type
     * @param string $modifier
     */
    private static function render_catalog_only($archive_url, $post_type, $modifier) {
        $is_program = ($post_type === 'program');
        $aria_cat   = $is_program
            ? esc_attr__('Каталог программ', 'course-plugin')
            : esc_attr__('Каталог курсов', 'course-plugin');
        $mod_class  = $modifier !== '' ? ' hero-catalog-nav--' . esc_attr($modifier) : '';
        ?>
        <div class="hero-catalog-nav hero-catalog-nav--catalog-only<?php echo $mod_class; ?>" role="navigation">
            <a class="hero-catalog-nav__btn hero-catalog-nav__catalog" href="<?php echo esc_url($archive_url); ?>" aria-label="<?php echo $aria_cat; ?>">
                <?php echo self::icon_layers(); ?>
            </a>
        </div>
        <?php
    }

    /**
     * @return string SVG-разметка (без лишних переносов для echo).
     */
    private static function icon_chevron_left() {
        return '<svg class="hero-catalog-nav__svg" width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M15 6L9 12L15 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    }

    /**
     * @return string
     */
    private static function icon_chevron_right() {
        return '<svg class="hero-catalog-nav__svg" width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M9 6L15 12L9 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    }

    /**
     * Иконка «слои» / каталог.
     *
     * @return string
     */
    private static function icon_layers() {
        return '<svg class="hero-catalog-nav__svg hero-catalog-nav__svg--layers" width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M12 3L4 7L12 11L20 7L12 3Z" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round"/><path d="M4 12L12 16L20 12" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/><path d="M4 17L12 21L20 17" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    }
}
