<?php
/**
 * Шаблон для отображения отдельного курса
 * 
 * Этот шаблон используется для отображения страницы конкретного курса
 * Структура соответствует дизайну с большим баннером, описанием, целями и боковой панелью
 */

get_header();

// Получаем данные текущего курса
while (have_posts()) : the_post();
    
    // Получаем метаполя курса
    $course_code = get_post_meta(get_the_ID(), '_course_code', true);
    $course_duration = get_post_meta(get_the_ID(), '_course_duration', true);
    $course_price = get_post_meta(get_the_ID(), '_course_price', true);
    $course_old_price = get_post_meta(get_the_ID(), '_course_old_price', true);
    $course_start_date = get_post_meta(get_the_ID(), '_course_start_date', true);
    $course_end_date = get_post_meta(get_the_ID(), '_course_end_date', true);
    $course_capacity = get_post_meta(get_the_ID(), '_course_capacity', true);
    $course_enrolled = get_post_meta(get_the_ID(), '_course_enrolled', true);
    $course_rating = get_post_meta(get_the_ID(), '_course_rating', true) ?: 0;
    $course_reviews_count = get_post_meta(get_the_ID(), '_course_reviews_count', true) ?: 0;
    
    // Дополнительные поля для расширенной информации
    $course_weeks = get_post_meta(get_the_ID(), '_course_weeks', true);
    $course_credits = get_post_meta(get_the_ID(), '_course_credits', true);
    $course_hours_per_week = get_post_meta(get_the_ID(), '_course_hours_per_week', true);
    $course_language = get_post_meta(get_the_ID(), '_course_language', true) ?: 'Русский';
    $course_certificate = get_post_meta(get_the_ID(), '_course_certificate', true);
    $course_video_url = get_post_meta(get_the_ID(), '_course_video_url', true);
    
    // Цели курса
    $course_cognitive_goals = get_post_meta(get_the_ID(), '_course_cognitive_goals', true);
    $course_emotional_goals = get_post_meta(get_the_ID(), '_course_emotional_goals', true);
    $course_psychomotor_goals = get_post_meta(get_the_ID(), '_course_psychomotor_goals', true);
    $course_content = get_post_meta(get_the_ID(), '_course_content', true);
    
    // Получаем таксономии
    $teachers = get_the_terms(get_the_ID(), 'course_teacher');
    $specializations = get_the_terms(get_the_ID(), 'course_specialization');
    $levels = get_the_terms(get_the_ID(), 'course_level');
    $topics = get_the_terms(get_the_ID(), 'course_topic');
    
    // Получаем имя преподавателя (первый из списка)
    $teacher_name = '';
    if ($teachers && !is_wp_error($teachers) && !empty($teachers)) {
        $teacher_name = $teachers[0]->name;
    }
    
    // Вычисляем скидку
    $discount = 0;
    if ($course_old_price && $course_price && $course_price < $course_old_price) {
        $discount = round((($course_old_price - $course_price) / $course_old_price) * 100);
    }
?>

<div class="single-course-wrapper">
    <!-- Большой синий баннер с названием курса -->
    <div class="course-hero-banner">
        <div class="course-hero-content">
            <h1 class="course-hero-title"><?php echo mb_strtoupper(get_the_title(), 'UTF-8'); ?></h1>
            <?php if ($teacher_name) : ?>
                <p class="course-hero-teacher"><?php echo mb_strtoupper($teacher_name, 'UTF-8'); ?></p>
            <?php endif; ?>
            <?php if ($course_code) : ?>
                <p class="course-hero-code"><?php _e('КОД КУРСА:', 'course-plugin'); ?> <?php echo esc_html($course_code); ?></p>
            <?php endif; ?>
            <?php if ($course_video_url) : ?>
                <div class="course-hero-video-overlay">
                    <button class="course-play-button" data-video-url="<?php echo esc_url($course_video_url); ?>">
                        <svg width="60" height="60" viewBox="0 0 60 60" fill="none">
                            <circle cx="30" cy="30" r="30" fill="white" fill-opacity="0.9"/>
                            <path d="M24 18L42 30L24 42V18Z" fill="#0073aa"/>
                        </svg>
                    </button>
                </div>
            <?php endif; ?>
        </div>
        <?php if (has_post_thumbnail()) : ?>
            <div class="course-hero-image">
                <?php the_post_thumbnail('full'); ?>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="single-course-container">
        <!-- Основной контент слева -->
        <main class="single-course-main">
            <!-- Код курса и название -->
            <?php if ($course_code) : ?>
                <div class="course-code-title">
                    <span class="course-code"><?php echo esc_html($course_code); ?></span>
                    <h2><?php the_title(); ?></h2>
                </div>
            <?php endif; ?>
            
            <!-- Описание курса -->
            <div class="course-description-section">
                <h3><?php _e('Описание курса:', 'course-plugin'); ?></h3>
                <div class="course-description">
                    <?php the_content(); ?>
                </div>
            </div>
            
            <!-- Цели и задачи курса -->
            <?php if ($course_cognitive_goals || $course_emotional_goals || $course_psychomotor_goals) : ?>
                <div class="course-goals-section">
                    <h3><?php _e('Цели и задачи курса:', 'course-plugin'); ?></h3>
                    <p class="goals-intro"><?php _e('Изучив этот курс, студенты смогут:', 'course-plugin'); ?></p>
                    
                    <?php if ($course_cognitive_goals) : ?>
                        <div class="goal-category">
                            <h4><?php _e('Когнитивные цели (знать):', 'course-plugin'); ?></h4>
                            <div class="goal-content">
                                <?php echo wpautop($course_cognitive_goals); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($course_emotional_goals) : ?>
                        <div class="goal-category">
                            <h4><?php _e('Эмоциональные цели (уметь):', 'course-plugin'); ?></h4>
                            <div class="goal-content">
                                <?php echo wpautop($course_emotional_goals); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($course_psychomotor_goals) : ?>
                        <div class="goal-category">
                            <h4><?php _e('Психомоторные цели (уметь):', 'course-plugin'); ?></h4>
                            <div class="goal-content">
                                <?php echo wpautop($course_psychomotor_goals); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- Содержание курса -->
            <?php if ($course_content) : ?>
                <div class="course-content-section">
                    <h3><?php _e('Содержание курса', 'course-plugin'); ?></h3>
                    <div class="course-content-outline">
                        <?php echo wpautop($course_content); ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Встроенное видео -->
            <?php if ($course_video_url) : ?>
                <div class="course-video-section">
                    <div class="course-video-wrapper">
                        <?php
                        // Определяем тип видео (YouTube, Vimeo или прямой URL)
                        if (strpos($course_video_url, 'youtube.com') !== false || strpos($course_video_url, 'youtu.be') !== false) {
                            // Извлекаем ID видео из YouTube URL
                            preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $course_video_url, $matches);
                            $youtube_id = isset($matches[1]) ? $matches[1] : '';
                            if ($youtube_id) {
                                ?>
                                <iframe 
                                    width="100%" 
                                    height="500" 
                                    src="https://www.youtube.com/embed/<?php echo esc_attr($youtube_id); ?>" 
                                    frameborder="0" 
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                    allowfullscreen>
                                </iframe>
                                <div class="video-youtube-link">
                                    <a href="<?php echo esc_url($course_video_url); ?>" target="_blank" rel="noopener">
                                        <?php _e('Посмотреть на YouTube', 'course-plugin'); ?>
                                    </a>
                                </div>
                                <?php
                            }
                        } elseif (strpos($course_video_url, 'vimeo.com') !== false) {
                            // Извлекаем ID видео из Vimeo URL
                            preg_match('/vimeo.com\/(\d+)/', $course_video_url, $matches);
                            $vimeo_id = isset($matches[1]) ? $matches[1] : '';
                            if ($vimeo_id) {
                                ?>
                                <iframe 
                                    src="https://player.vimeo.com/video/<?php echo esc_attr($vimeo_id); ?>" 
                                    width="100%" 
                                    height="500" 
                                    frameborder="0" 
                                    allow="autoplay; fullscreen; picture-in-picture" 
                                    allowfullscreen>
                                </iframe>
                                <?php
                            }
                        } else {
                            // Прямой URL видео
                            ?>
                            <video width="100%" height="500" controls>
                                <source src="<?php echo esc_url($course_video_url); ?>" type="video/mp4">
                                <?php _e('Ваш браузер не поддерживает видео.', 'course-plugin'); ?>
                            </video>
                            <?php
                        }
                        ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Другие курсы по теме -->
            <?php
            // Получаем другие курсы из той же специализации или темы
            $related_args = array(
                'post_type' => 'course',
                'posts_per_page' => 3,
                'post__not_in' => array(get_the_ID()),
                'orderby' => 'rand',
            );
            
            // Добавляем фильтр по специализации, если она есть
            if ($specializations && !is_wp_error($specializations) && !empty($specializations)) {
                $related_args['tax_query'] = array(
                    array(
                        'taxonomy' => 'course_specialization',
                        'field' => 'term_id',
                        'terms' => array($specializations[0]->term_id),
                    ),
                );
            }
            
            $related_courses = new WP_Query($related_args);
            
            if ($related_courses->have_posts()) :
            ?>
                <div class="related-courses-section">
                    <h3><?php _e('Другие курсы по теме', 'course-plugin'); ?></h3>
                    <div class="related-courses-grid">
                        <?php while ($related_courses->have_posts()) : $related_courses->the_post(); ?>
                            <article class="related-course-item">
                                <a href="<?php the_permalink(); ?>">
                                    <?php if (has_post_thumbnail()) : ?>
                                        <div class="related-course-thumbnail">
                                            <?php the_post_thumbnail('medium'); ?>
                                        </div>
                                    <?php endif; ?>
                                    <h4><?php the_title(); ?></h4>
                                </a>
                            </article>
                        <?php endwhile; ?>
                    </div>
                </div>
                <?php wp_reset_postdata(); ?>
            <?php endif; ?>
        </main>
        
        <!-- Боковая панель справа -->
        <aside class="single-course-sidebar">
            <!-- Кнопки действий -->
            <div class="course-action-buttons">
                <a href="#" class="course-action-btn course-btn-seminary-new">
                    <?php _e('Курс на семинарском уровне (если вы не студент SEMINARY)', 'course-plugin'); ?>
                </a>
                <a href="#" class="course-action-btn course-btn-seminary-student">
                    <?php _e('Курс на семинарском уровне (если вы уже студент SEMINARY)', 'course-plugin'); ?>
                </a>
                <a href="#" class="course-action-btn course-btn-buy">
                    <?php _e('Лайт курс', 'course-plugin'); ?>
                </a>
            </div>
            
            <!-- Краткий обзор курса -->
            <div class="course-overview-box">
                <h4><?php _e('Краткий обзор курса', 'course-plugin'); ?></h4>
                <ul class="course-overview-list">
                    <?php if ($course_weeks) : ?>
                        <li>
                            <span class="overview-label"><?php _e('Недель:', 'course-plugin'); ?></span>
                            <span class="overview-value"><?php echo esc_html($course_weeks); ?></span>
                        </li>
                    <?php endif; ?>
                    <?php if ($course_credits) : ?>
                        <li>
                            <span class="overview-label"><?php _e('Кредитов:', 'course-plugin'); ?></span>
                            <span class="overview-value"><?php echo esc_html($course_credits); ?></span>
                        </li>
                    <?php endif; ?>
                    <?php if ($course_hours_per_week) : ?>
                        <li>
                            <span class="overview-label"><?php _e('Часов работы / неделя:', 'course-plugin'); ?></span>
                            <span class="overview-value"><?php echo esc_html($course_hours_per_week); ?></span>
                        </li>
                    <?php endif; ?>
                    <li>
                        <span class="overview-label"><?php _e('Язык:', 'course-plugin'); ?></span>
                        <span class="overview-value"><?php echo esc_html($course_language); ?></span>
                    </li>
                    <?php if ($course_certificate) : ?>
                        <li>
                            <span class="overview-label"><?php _e('Сертификат:', 'course-plugin'); ?></span>
                            <span class="overview-value"><?php _e('Да', 'course-plugin'); ?></span>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
            
            <!-- Информация о преподавателе -->
            <?php if ($teacher_name) : ?>
                <div class="course-instructor-box">
                    <h4><?php _e('Преподаватель', 'course-plugin'); ?></h4>
                    <div class="instructor-info">
                        <?php
                        // Пытаемся получить фото преподавателя из метаполя или таксономии
                        $instructor_photo = '';
                        if ($teachers && !is_wp_error($teachers) && !empty($teachers)) {
                            $instructor_photo = get_term_meta($teachers[0]->term_id, 'instructor_photo', true);
                        }
                        ?>
                        <?php if ($instructor_photo) : ?>
                            <div class="instructor-photo">
                                <img src="<?php echo esc_url($instructor_photo); ?>" alt="<?php echo esc_attr($teacher_name); ?>">
                            </div>
                        <?php endif; ?>
                        <div class="instructor-name"><?php echo esc_html($teacher_name); ?></div>
                        <?php if ($specializations && !is_wp_error($specializations)) : ?>
                            <div class="instructor-specializations">
                                <?php foreach ($specializations as $spec) : ?>
                                    <span class="instructor-spec-item">
                                        <span class="dashicons dashicons-book"></span>
                                        <?php echo esc_html($spec->name); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Цена курса -->
            <?php if ($course_price) : ?>
                <div class="course-price-box">
                    <?php if ($discount > 0) : ?>
                        <div class="course-price-old"><?php echo number_format($course_old_price, 2, ',', ' '); ?> Р</div>
                        <div class="course-price-discount">-<?php echo $discount; ?>%</div>
                    <?php endif; ?>
                    <div class="course-price-current"><?php echo number_format($course_price, 2, ',', ' '); ?> Р</div>
                </div>
            <?php endif; ?>
            
            <!-- Рейтинг курса -->
            <?php if ($course_rating > 0) : ?>
                <div class="course-rating-box">
                    <div class="course-rating-stars">
                        <?php
                        for ($i = 1; $i <= 5; $i++) {
                            $star_class = $i <= $course_rating ? 'star-filled' : 'star-empty';
                            echo '<span class="star ' . $star_class . '">★</span>';
                        }
                        ?>
                    </div>
                    <?php if ($course_reviews_count > 0) : ?>
                        <div class="course-reviews-count">(<?php echo $course_reviews_count; ?> <?php _e('отзывов', 'course-plugin'); ?>)</div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </aside>
    </div>
</div>

<!-- Футер с призывом к действию -->
<div class="course-footer-cta">
    <a href="#"><?php _e('> Запишитесь на курс и наслаждайтесь!', 'course-plugin'); ?></a>
</div>

<?php
endwhile;
get_footer();
?>

