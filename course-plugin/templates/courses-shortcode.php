<?php
/**
 * Шаблон для шорткода курсов и программ — премиальный дизайн
 *
 * @copyright Copyright (c) 2024 Кузьменко Валентин (Valentink2410)
 * @author Кузьменко Валентин (Valentink2410)
 */

if (!defined('ABSPATH')) {
    exit;
}

$shortcode_context = isset($shortcode_context) ? $shortcode_context : array(
    'post_type' => 'course',
    'more_text' => __('Все курсы', 'course-plugin'),
    'more_url' => get_post_type_archive_link('course'),
    'button_style' => 'default',
    'theme_class' => '',
);
$ctx = wp_parse_args($shortcode_context, array(
    'post_type' => 'course',
    'more_text' => __('Все курсы', 'course-plugin'),
    'more_url' => home_url('/courses/'),
    'button_style' => 'default',
    'theme_class' => '',
));
$wrapper_classes = array('courses-shortcode-premium', 'csp-style-' . esc_attr($ctx['button_style']));
if (!empty($ctx['theme_class'])) {
    $wrapper_classes[] = esc_attr($ctx['theme_class']);
}

if ($courses->have_posts()) : ?>
    <div class="<?php echo implode(' ', $wrapper_classes); ?>">
        <div class="csp-grid">
            <?php while ($courses->have_posts()) : $courses->the_post();
                $is_program = ($ctx['post_type'] === 'program');
                $price = get_post_meta(get_the_ID(), $is_program ? '_program_price' : '_course_price', true);
                $old_price = get_post_meta(get_the_ID(), $is_program ? '_program_old_price' : '_course_old_price', true);
                $start_date = get_post_meta(get_the_ID(), $is_program ? '_program_start_date' : '_course_start_date', true);
                $duration = get_post_meta(get_the_ID(), $is_program ? '_program_duration' : '_course_duration', true);
                $rating = get_post_meta(get_the_ID(), '_course_rating', true) ?: 0;
                $reviews_count = get_post_meta(get_the_ID(), '_course_reviews_count', true) ?: 0;

                $discount = 0;
                if ($old_price && $price && $price < $old_price) {
                    $discount = round((($old_price - $price) / $old_price) * 100);
                }

                $specializations = get_the_terms(get_the_ID(), 'course_specialization');
                $levels = get_the_terms(get_the_ID(), 'course_level');
                $teachers = get_the_terms(get_the_ID(), 'course_teacher');
            ?>
                <article class="csp-card">
                    <a href="<?php the_permalink(); ?>" class="csp-card-link">
                        <div class="csp-card-image">
                            <?php if ($discount > 0) : ?>
                                <span class="csp-badge-discount">-<?php echo $discount; ?>%</span>
                            <?php endif; ?>

                            <?php if ($specializations && !is_wp_error($specializations)) : ?>
                                <span class="csp-badge-spec"><?php echo esc_html($specializations[0]->name); ?></span>
                            <?php endif; ?>

                            <?php if (has_post_thumbnail()) : ?>
                                <?php the_post_thumbnail('medium_large'); ?>
                            <?php else : ?>
                                <div class="csp-placeholder">
                                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="csp-card-body">
                            <div class="csp-card-tags">
                                <?php if ($levels && !is_wp_error($levels)) : ?>
                                    <span class="csp-tag csp-tag-level"><?php echo esc_html($levels[0]->name); ?></span>
                                <?php endif; ?>
                                <?php if ($start_date) :
                                    $formatted = date_i18n('d M Y', strtotime($start_date));
                                ?>
                                    <span class="csp-tag csp-tag-date">
                                        <svg width="12" height="12" viewBox="0 0 16 16" fill="none"><rect x="2" y="3" width="12" height="11" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M5 1v4M11 1v4M2 7h12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                                        <?php echo $formatted; ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <h3 class="csp-card-title"><?php the_title(); ?></h3>

                            <p class="csp-card-excerpt"><?php echo wp_trim_words(get_the_excerpt(), 14, '...'); ?></p>

                            <?php if ($teachers && !is_wp_error($teachers)) : ?>
                                <div class="csp-card-teacher">
                                    <?php
                                    $t_photo = get_term_meta($teachers[0]->term_id, 'teacher_photo', true);
                                    ?>
                                    <?php if ($t_photo) : ?>
                                        <img src="<?php echo esc_url($t_photo); ?>" alt="" class="csp-teacher-avatar" />
                                    <?php else : ?>
                                        <span class="csp-teacher-avatar csp-teacher-avatar-placeholder">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="1.5"/><path d="M4 20c0-3.314 3.582-6 8-6s8 2.686 8 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                                        </span>
                                    <?php endif; ?>
                                    <span class="csp-teacher-name"><?php echo esc_html($teachers[0]->name); ?></span>
                                </div>
                            <?php endif; ?>

                            <div class="csp-card-footer">
                                <div class="csp-card-price">
                                    <?php if ($price) : ?>
                                        <?php if ($old_price && $price < $old_price) : ?>
                                            <span class="csp-price-old"><?php echo number_format($old_price, 0, ',', ' '); ?> ₽</span>
                                        <?php endif; ?>
                                        <span class="csp-price-current"><?php echo number_format($price, 0, ',', ' '); ?> ₽</span>
                                    <?php else : ?>
                                        <span class="csp-price-free"><?php _e('Бесплатно', 'course-plugin'); ?></span>
                                    <?php endif; ?>
                                </div>

                                <?php if ($rating > 0) : ?>
                                    <div class="csp-card-rating">
                                        <span class="csp-star">★</span>
                                        <span class="csp-rating-value"><?php echo number_format($rating, 1); ?></span>
                                        <?php if ($reviews_count > 0) : ?>
                                            <span class="csp-rating-count">(<?php echo $reviews_count; ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($duration) : ?>
                                    <span class="csp-duration">
                                        <svg width="12" height="12" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6.5" stroke="currentColor" stroke-width="1.5"/><path d="M8 4.5V8l2.5 2.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                                        <?php echo esc_html($duration); ?> ч.
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="csp-card-hover-action">
                            <span class="csp-btn"><?php _e('Подробнее', 'course-plugin'); ?> →</span>
                        </div>
                    </a>
                </article>
            <?php endwhile; ?>
        </div>

        <div class="csp-more">
            <a href="<?php echo esc_url($ctx['more_url']); ?>" class="csp-more-link">
                <?php echo esc_html($ctx['more_text']); ?>
            </a>
        </div>
    </div>
    <?php wp_reset_postdata(); ?>
<?php else : ?>
    <div class="csp-empty">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <p><?php echo ($ctx['post_type'] === 'program') ? __('Программы не найдены.', 'course-plugin') : __('Курсы не найдены.', 'course-plugin'); ?></p>
    </div>
<?php endif; ?>
