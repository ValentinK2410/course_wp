<?php
/**
 * Шаблон для отображения отдельной программы
 * 
 * @copyright Copyright (c) 2024 Кузьменко Валентин (Valentink2410)
 * @author Кузьменко Валентин (Valentink2410)
 */

get_header();

// Получаем данные текущей программы
while (have_posts()) : the_post();
    
    // Получаем метаполя программы
    $program_price = get_post_meta(get_the_ID(), '_program_price', true);
    $program_old_price = get_post_meta(get_the_ID(), '_program_old_price', true);
    $program_duration = get_post_meta(get_the_ID(), '_program_duration', true);
    $program_courses_count = get_post_meta(get_the_ID(), '_program_courses_count', true);
    $program_start_date = get_post_meta(get_the_ID(), '_program_start_date', true);
    $program_end_date = get_post_meta(get_the_ID(), '_program_end_date', true);
    $program_certificate = get_post_meta(get_the_ID(), '_program_certificate', true);
    $program_related_courses = get_post_meta(get_the_ID(), '_program_related_courses', true);
    
    // Получаем таксономии
    $teachers = get_the_terms(get_the_ID(), 'course_teacher');
    $specializations = get_the_terms(get_the_ID(), 'course_specialization');
    $levels = get_the_terms(get_the_ID(), 'course_level');
    $topics = get_the_terms(get_the_ID(), 'course_topic');
    
    // Получаем данные преподавателя (первый из списка)
    $teacher_name = '';
    $teacher_photo = '';
    $teacher_position = '';
    $teacher = null;
    if ($teachers && !is_wp_error($teachers) && !empty($teachers)) {
        $teacher = $teachers[0];
        $teacher_name = $teacher->name;
        $teacher_photo = get_term_meta($teacher->term_id, 'teacher_photo', true);
        $teacher_position = get_term_meta($teacher->term_id, 'teacher_position', true);
    }
    
    // Вычисляем скидку
    $discount = 0;
    if ($program_old_price && $program_price && $program_price < $program_old_price) {
        $discount = round((($program_old_price - $program_price) / $program_old_price) * 100);
    }
    
    // Получаем связанные курсы
    $related_courses_list = array();
    if (!empty($program_related_courses) && is_array($program_related_courses)) {
        $related_courses_list = get_posts(array(
            'post_type' => 'course',
            'post__in' => $program_related_courses,
            'posts_per_page' => -1,
            'orderby' => 'post__in'
        ));
    }
?>

<div class="single-course-wrapper single-program-wrapper">
    <!-- Большой баннер с названием программы -->
    <div class="course-hero-banner program-hero-banner">
        <div class="course-hero-content program-hero-content">
            <h1 class="course-hero-title program-hero-title"><?php echo mb_strtoupper(get_the_title(), 'UTF-8'); ?></h1>
            <?php if ($teacher_name) : ?>
                <p class="course-hero-teacher program-hero-teacher"><?php echo mb_strtoupper($teacher_name, 'UTF-8'); ?></p>
            <?php endif; ?>
        </div>
        <?php if (has_post_thumbnail()) : ?>
            <div class="course-hero-image program-hero-image">
                <?php the_post_thumbnail('full'); ?>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="single-course-container single-program-container">
        <!-- Основной контент слева -->
        <main class="single-course-main single-program-main">
            <!-- Название программы -->
            <div class="course-code-title program-title-section">
                <h2><?php the_title(); ?></h2>
            </div>
            
            <!-- Описание программы -->
            <div class="course-description-section program-description-section">
                <h3><?php _e('Описание программы:', 'course-plugin'); ?></h3>
                <div class="course-description program-description">
                    <?php the_content(); ?>
                </div>
            </div>
            
            <!-- Информация о программе -->
            <div class="program-info-section">
                <?php if ($program_duration) : ?>
                    <div class="program-info-item">
                        <strong><?php _e('Длительность:', 'course-plugin'); ?></strong>
                        <span><?php echo esc_html($program_duration); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if ($program_courses_count) : ?>
                    <div class="program-info-item">
                        <strong><?php _e('Количество курсов:', 'course-plugin'); ?></strong>
                        <span><?php echo esc_html($program_courses_count); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if ($program_start_date) : ?>
                    <div class="program-info-item">
                        <strong><?php _e('Дата начала:', 'course-plugin'); ?></strong>
                        <span><?php echo date_i18n('d.m.Y', strtotime($program_start_date)); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if ($program_end_date) : ?>
                    <div class="program-info-item">
                        <strong><?php _e('Дата окончания:', 'course-plugin'); ?></strong>
                        <span><?php echo date_i18n('d.m.Y', strtotime($program_end_date)); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if ($program_certificate) : ?>
                    <div class="program-info-item program-certificate-item">
                        <strong><?php _e('Сертификат:', 'course-plugin'); ?></strong>
                        <span><?php echo esc_html($program_certificate); ?></span>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Связанные курсы -->
            <?php if (!empty($related_courses_list)) : ?>
                <div class="program-related-courses-section">
                    <h3><?php _e('Курсы в программе:', 'course-plugin'); ?></h3>
                    <div class="program-related-courses-list">
                        <?php foreach ($related_courses_list as $course) : ?>
                            <div class="program-related-course-item">
                                <a href="<?php echo get_permalink($course->ID); ?>" class="program-related-course-link">
                                    <?php if (has_post_thumbnail($course->ID)) : ?>
                                        <div class="program-related-course-thumbnail">
                                            <?php echo get_the_post_thumbnail($course->ID, 'thumbnail'); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="program-related-course-info">
                                        <h4><?php echo esc_html($course->post_title); ?></h4>
                                        <?php if ($course->post_excerpt) : ?>
                                            <p><?php echo esc_html($course->post_excerpt); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Таксономии -->
            <?php if ($specializations || $levels || $topics) : ?>
                <div class="program-taxonomies-section">
                    <?php if ($specializations && !is_wp_error($specializations)) : ?>
                        <div class="program-taxonomy">
                            <strong><?php _e('Специализация:', 'course-plugin'); ?></strong>
                            <?php
                            $spec_names = array();
                            foreach ($specializations as $spec) {
                                $spec_names[] = '<a href="' . get_term_link($spec) . '">' . esc_html($spec->name) . '</a>';
                            }
                            echo implode(', ', $spec_names);
                            ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($levels && !is_wp_error($levels)) : ?>
                        <div class="program-taxonomy">
                            <strong><?php _e('Уровень:', 'course-plugin'); ?></strong>
                            <?php
                            $level_names = array();
                            foreach ($levels as $level) {
                                $level_names[] = '<a href="' . get_term_link($level) . '">' . esc_html($level->name) . '</a>';
                            }
                            echo implode(', ', $level_names);
                            ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($topics && !is_wp_error($topics)) : ?>
                        <div class="program-taxonomy">
                            <strong><?php _e('Тема:', 'course-plugin'); ?></strong>
                            <?php
                            $topic_names = array();
                            foreach ($topics as $topic) {
                                $topic_names[] = '<a href="' . get_term_link($topic) . '">' . esc_html($topic->name) . '</a>';
                            }
                            echo implode(', ', $topic_names);
                            ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
        
        <!-- Боковая панель справа -->
        <aside class="single-course-sidebar single-program-sidebar">
            <!-- Блок с ценой и кнопкой записи -->
            <div class="course-price-box program-price-box">
                <?php if ($discount > 0) : ?>
                    <div class="course-discount-badge program-discount-badge">-<?php echo $discount; ?>%</div>
                <?php endif; ?>
                
                <div class="course-price-info program-price-info">
                    <?php if ($program_old_price && $program_price < $program_old_price) : ?>
                        <div class="course-old-price program-old-price">
                            <?php echo number_format($program_old_price, 2, ',', ' '); ?> Р
                        </div>
                    <?php endif; ?>
                    <div class="course-current-price program-current-price">
                        <?php echo $program_price ? number_format($program_price, 2, ',', ' ') : '0,00'; ?> Р
                    </div>
                </div>
                
                <button class="course-enroll-btn program-enroll-btn">
                    <?php _e('Записаться на программу', 'course-plugin'); ?>
                </button>
            </div>
            
            <!-- Информация о программе -->
            <div class="course-info-box program-info-box">
                <h4><?php _e('Информация о программе', 'course-plugin'); ?></h4>
                
                <?php if ($program_duration) : ?>
                    <div class="info-item">
                        <span class="info-label"><?php _e('Длительность:', 'course-plugin'); ?></span>
                        <span class="info-value"><?php echo esc_html($program_duration); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if ($program_courses_count) : ?>
                    <div class="info-item">
                        <span class="info-label"><?php _e('Курсов:', 'course-plugin'); ?></span>
                        <span class="info-value"><?php echo esc_html($program_courses_count); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if ($program_start_date) : ?>
                    <div class="info-item">
                        <span class="info-label"><?php _e('Начало:', 'course-plugin'); ?></span>
                        <span class="info-value"><?php echo date_i18n('d.m.Y', strtotime($program_start_date)); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if ($program_certificate) : ?>
                    <div class="info-item">
                        <span class="info-label"><?php _e('Сертификат:', 'course-plugin'); ?></span>
                        <span class="info-value"><?php _e('Да', 'course-plugin'); ?></span>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Преподаватель -->
            <?php if ($teacher) : ?>
                <div class="course-teacher-box program-teacher-box">
                    <h4><?php _e('Преподаватель', 'course-plugin'); ?></h4>
                    <div class="teacher-info">
                        <?php if ($teacher_photo) : ?>
                            <div class="teacher-photo">
                                <img src="<?php echo esc_url($teacher_photo); ?>" alt="<?php echo esc_attr($teacher_name); ?>" />
                            </div>
                        <?php endif; ?>
                        <div class="teacher-details">
                            <h5><a href="<?php echo get_term_link($teacher); ?>"><?php echo esc_html($teacher_name); ?></a></h5>
                            <?php if ($teacher_position) : ?>
                                <p class="teacher-position"><?php echo esc_html($teacher_position); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </aside>
    </div>
</div>

<?php endwhile; ?>

<?php get_footer(); ?>
