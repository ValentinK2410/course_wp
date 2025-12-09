<?php
/**
 * Шаблон для шорткода курсов
 */

if (!defined('ABSPATH')) {
    exit;
}

if ($courses->have_posts()) : ?>
    <div class="courses-shortcode-container" data-view="<?php echo esc_attr($atts['view']); ?>">
        <div class="courses-grid">
            <?php while ($courses->have_posts()) : $courses->the_post(); ?>
                <article id="course-<?php the_ID(); ?>" <?php post_class('course-item'); ?>>
                    <div class="course-thumbnail">
                        <?php if (has_post_thumbnail()) : ?>
                            <a href="<?php the_permalink(); ?>">
                                <?php the_post_thumbnail('medium'); ?>
                            </a>
                        <?php else : ?>
                            <a href="<?php the_permalink(); ?>">
                                <div class="course-placeholder">
                                    <span class="dashicons dashicons-book-alt"></span>
                                </div>
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <div class="course-content">
                        <h2 class="course-title">
                            <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                        </h2>
                        
                        <div class="course-excerpt">
                            <?php the_excerpt(); ?>
                        </div>
                        
                        <div class="course-meta">
                            <?php
                            $specializations = get_the_terms(get_the_ID(), 'course_specialization');
                            if ($specializations && !is_wp_error($specializations)) :
                                ?>
                                <span class="course-specialization">
                                    <span class="dashicons dashicons-category"></span>
                                    <?php echo esc_html($specializations[0]->name); ?>
                                </span>
                            <?php endif; ?>
                            
                            <?php
                            $price = get_post_meta(get_the_ID(), '_course_price', true);
                            if ($price) :
                                ?>
                                <span class="course-price">
                                    <span class="dashicons dashicons-money-alt"></span>
                                    <?php echo number_format($price, 0, ',', ' '); ?> ₽
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="course-footer">
                            <a href="<?php the_permalink(); ?>" class="course-read-more">
                                <?php _e('Подробнее', 'course-plugin'); ?>
                            </a>
                        </div>
                    </div>
                </article>
            <?php endwhile; ?>
        </div>
    </div>
    <?php wp_reset_postdata(); ?>
<?php else : ?>
    <p><?php _e('Курсы не найдены.', 'course-plugin'); ?></p>
<?php endif; ?>

