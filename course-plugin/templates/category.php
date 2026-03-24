<?php
/**
 * Шаблон архива рубрик (записи блога) — сетка карточек из плагина.
 *
 * @package Course_Plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

global $wp_query;

$paged = max(1, (int) get_query_var('paged'));
if ($paged > 1 && (int) $wp_query->found_posts === 0) {
    $term = get_queried_object();
    if ($term && isset($term->term_id)) {
        wp_safe_redirect(get_term_link($term), 302);
        exit;
    }
}

$term = get_queried_object();
$title = $term && !is_wp_error($term) ? single_term_title('', false) : '';
?>

<main id="main" class="site-main course-plugin-category-main">
    <div class="course-plugin-category-archive" data-cpa-version="<?php echo esc_attr(COURSE_PLUGIN_VERSION); ?>">
        <header class="cpa-archive-header">
            <p class="cpa-kicker"><?php esc_html_e('Категория', 'course-plugin'); ?></p>
            <h1 class="cpa-title"><?php echo esc_html($title); ?></h1>
            <?php
            $desc = $term && !is_wp_error($term) ? term_description($term->term_id, 'category') : '';
            if ($desc) :
                ?>
                <div class="cpa-description"><?php echo wp_kses_post($desc); ?></div>
            <?php endif; ?>
        </header>

        <?php if (have_posts()) : ?>
            <div class="cpa-grid" role="list">
                <?php
                while (have_posts()) :
                    the_post();
                    $categories = get_the_category();
                    $primary_cat = $categories && !is_wp_error($categories) ? $categories[0] : null;
                    $cat_label = $primary_cat ? $primary_cat->name : $title;
                    ?>
                    <article <?php post_class('cpa-post-card'); ?> role="listitem">
                        <a class="cpa-card-link" href="<?php the_permalink(); ?>">
                            <div class="cpa-card-media">
                                <?php
                                if (has_post_thumbnail()) {
                                    the_post_thumbnail(
                                        'medium_large',
                                        array(
                                            'class' => 'cpa-card-thumb',
                                            'alt' => the_title_attribute(array('echo' => false)),
                                        )
                                    );
                                } else {
                                    echo '<div class="cpa-card-thumb cpa-card-thumb--placeholder" aria-hidden="true"></div>';
                                }
                                ?>
                                <span class="cpa-card-badge"><?php echo esc_html($cat_label); ?></span>
                            </div>
                            <div class="cpa-card-body">
                                <h2 class="cpa-card-title"><?php the_title(); ?></h2>
                                <div class="cpa-card-meta">
                                    <span class="cpa-card-author"><?php echo esc_html(get_the_author()); ?></span>
                                </div>
                            </div>
                        </a>
                    </article>
                <?php endwhile; ?>
            </div>

            <nav class="cpa-pagination" aria-label="<?php esc_attr_e('Навигация по страницам', 'course-plugin'); ?>">
                <?php
                the_posts_pagination(
                    array(
                        'mid_size' => 1,
                        'prev_text' => '<span aria-hidden="true">←</span> <span class="screen-reader-text">' . esc_html__('Предыдущая страница', 'course-plugin') . '</span>',
                        'next_text' => '<span class="screen-reader-text">' . esc_html__('Следующая страница', 'course-plugin') . '</span> <span aria-hidden="true">→</span>',
                    )
                );
                ?>
            </nav>
        <?php else : ?>
            <p class="cpa-empty"><?php esc_html_e('Записей не найдено.', 'course-plugin'); ?></p>
        <?php endif; ?>
    </div>
</main>

<?php
get_sidebar();
get_footer();
