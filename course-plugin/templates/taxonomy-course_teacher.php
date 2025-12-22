<?php
/**
 * Шаблон для отображения страницы преподавателя
 * 
 * Этот шаблон используется для отображения страницы конкретного преподавателя
 * с его информацией и списком всех его курсов
 */

get_header();

// Получаем данные текущего преподавателя
$teacher = get_queried_object();

// Получаем метаполя преподавателя
$teacher_photo = get_term_meta($teacher->term_id, 'teacher_photo', true);
$teacher_description = get_term_meta($teacher->term_id, 'teacher_description', true);
$teacher_position = get_term_meta($teacher->term_id, 'teacher_position', true);
$teacher_education = get_term_meta($teacher->term_id, 'teacher_education', true);
$teacher_email = get_term_meta($teacher->term_id, 'teacher_email', true);
$teacher_phone = get_term_meta($teacher->term_id, 'teacher_phone', true);
$teacher_website = get_term_meta($teacher->term_id, 'teacher_website', true);
$teacher_facebook = get_term_meta($teacher->term_id, 'teacher_facebook', true);
$teacher_twitter = get_term_meta($teacher->term_id, 'teacher_twitter', true);
$teacher_linkedin = get_term_meta($teacher->term_id, 'teacher_linkedin', true);

// Получаем все курсы этого преподавателя
$courses_args = array(
    'post_type' => 'course',
    'posts_per_page' => -1,
    'post_status' => 'publish',
    'tax_query' => array(
        array(
            'taxonomy' => 'course_teacher',
            'field' => 'term_id',
            'terms' => $teacher->term_id,
        ),
    ),
);

$teacher_courses = new WP_Query($courses_args);
?>

<div class="teacher-page-wrapper">
    <!-- Верхний баннер с фото и основной информацией -->
    <div class="teacher-hero-section">
        <div class="teacher-hero-container">
            <?php if ($teacher_photo) : ?>
                <div class="teacher-photo-large">
                    <img src="<?php echo esc_url($teacher_photo); ?>" alt="<?php echo esc_attr($teacher->name); ?>" />
                </div>
            <?php endif; ?>
            
            <div class="teacher-hero-info">
                <h1 class="teacher-name"><?php echo esc_html($teacher->name); ?></h1>
                
                <?php if ($teacher_position) : ?>
                    <p class="teacher-position"><?php echo esc_html($teacher_position); ?></p>
                <?php endif; ?>
                
                <?php if ($teacher_description) : ?>
                    <div class="teacher-description-short">
                        <?php echo wp_kses_post(wp_trim_words($teacher_description, 30, '...')); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Контакты и социальные сети -->
                <div class="teacher-contacts">
                    <?php if ($teacher_email) : ?>
                        <a href="mailto:<?php echo esc_attr($teacher_email); ?>" class="teacher-contact-item">
                            <span class="dashicons dashicons-email"></span>
                            <?php echo esc_html($teacher_email); ?>
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($teacher_phone) : ?>
                        <a href="tel:<?php echo esc_attr($teacher_phone); ?>" class="teacher-contact-item">
                            <span class="dashicons dashicons-phone"></span>
                            <?php echo esc_html($teacher_phone); ?>
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($teacher_website) : ?>
                        <a href="<?php echo esc_url($teacher_website); ?>" target="_blank" rel="noopener" class="teacher-contact-item">
                            <span class="dashicons dashicons-admin-links"></span>
                            <?php _e('Веб-сайт', 'course-plugin'); ?>
                        </a>
                    <?php endif; ?>
                </div>
                
                <!-- Социальные сети -->
                <?php if ($teacher_facebook || $teacher_twitter || $teacher_linkedin) : ?>
                    <div class="teacher-social-links">
                        <?php if ($teacher_facebook) : ?>
                            <a href="<?php echo esc_url($teacher_facebook); ?>" target="_blank" rel="noopener" class="social-link facebook" title="Facebook">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                                </svg>
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($teacher_twitter) : ?>
                            <a href="<?php echo esc_url($teacher_twitter); ?>" target="_blank" rel="noopener" class="social-link twitter" title="Twitter">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/>
                                </svg>
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($teacher_linkedin) : ?>
                            <a href="<?php echo esc_url($teacher_linkedin); ?>" target="_blank" rel="noopener" class="social-link linkedin" title="LinkedIn">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                                </svg>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Основной контент -->
    <div class="teacher-content-section">
        <div class="teacher-content-container">
            <!-- Полное описание преподавателя -->
            <?php if ($teacher_description) : ?>
                <div class="teacher-description-full">
                    <h2><?php _e('О преподавателе', 'course-plugin'); ?></h2>
                    <div class="teacher-description-text">
                        <?php echo wp_kses_post($teacher_description); ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Образование -->
            <?php if ($teacher_education) : ?>
                <div class="teacher-education">
                    <h2><?php _e('Образование', 'course-plugin'); ?></h2>
                    <div class="teacher-education-text">
                        <?php echo wpautop(esc_html($teacher_education)); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Курсы преподавателя -->
    <?php if ($teacher_courses->have_posts()) : ?>
        <div class="teacher-courses-section">
            <div class="teacher-courses-container">
                <h2 class="teacher-courses-title">
                    <?php 
                    printf(
                        _n(
                            'Курс преподавателя (%d)',
                            'Курсы преподавателя (%d)',
                            $teacher_courses->found_posts,
                            'course-plugin'
                        ),
                        $teacher_courses->found_posts
                    );
                    ?>
                </h2>
                
                <div class="teacher-courses-grid">
                    <?php while ($teacher_courses->have_posts()) : $teacher_courses->the_post(); 
                        $price = get_post_meta(get_the_ID(), '_course_price', true);
                        $old_price = get_post_meta(get_the_ID(), '_course_old_price', true);
                        $rating = get_post_meta(get_the_ID(), '_course_rating', true) ?: 0;
                        $reviews_count = get_post_meta(get_the_ID(), '_course_reviews_count', true) ?: 0;
                        $discount = 0;
                        if ($old_price && $price && $price < $old_price) {
                            $discount = round((($old_price - $price) / $old_price) * 100);
                        }
                    ?>
                        <article class="teacher-course-card">
                            <a href="<?php the_permalink(); ?>" class="course-card-link">
                                <div class="course-card-thumbnail">
                                    <?php if ($discount > 0) : ?>
                                        <span class="course-discount-badge">-<?php echo $discount; ?>%</span>
                                    <?php endif; ?>
                                    
                                    <?php if (has_post_thumbnail()) : ?>
                                        <?php the_post_thumbnail('medium_large'); ?>
                                    <?php else : ?>
                                        <div class="course-placeholder">
                                            <span class="dashicons dashicons-book-alt"></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <h3 class="course-card-title-overlay">
                                        <?php the_title(); ?>
                                    </h3>
                                </div>
                                
                                <div class="course-card-content">
                                    <?php if ($price) : ?>
                                        <div class="course-card-price">
                                            <?php if ($old_price && $price < $old_price) : ?>
                                                <span class="course-old-price"><?php echo number_format($old_price, 2, ',', ' '); ?> Р</span>
                                            <?php endif; ?>
                                            <span class="course-current-price"><?php echo number_format($price, 2, ',', ' '); ?> Р</span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($rating > 0) : ?>
                                        <div class="course-card-rating">
                                            <div class="stars-rating">
                                                <?php
                                                for ($i = 1; $i <= 5; $i++) {
                                                    $star_class = $i <= $rating ? 'star-filled' : 'star-empty';
                                                    echo '<span class="star ' . $star_class . '">★</span>';
                                                }
                                                ?>
                                            </div>
                                            <?php if ($reviews_count > 0) : ?>
                                                <span class="reviews-count">(<?php echo $reviews_count; ?>)</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="course-card-excerpt">
                                        <?php echo wp_trim_words(get_the_excerpt(), 15, '...'); ?>
                                    </div>
                                </div>
                            </a>
                        </article>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
        <?php wp_reset_postdata(); ?>
    <?php else : ?>
        <div class="teacher-no-courses">
            <p><?php _e('У этого преподавателя пока нет курсов.', 'course-plugin'); ?></p>
        </div>
    <?php endif; ?>
</div>

<?php get_footer(); ?>






