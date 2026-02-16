<?php
/**
 * Шаблон архива преподавателей - страница /teachers/
 *
 * @copyright Copyright (c) 2024 Кузьменко Валентин (Valentink2410)
 * @author Кузьменко Валентин (Valentink2410)
 */

get_header();

$teachers = get_terms(array(
    'taxonomy' => 'course_teacher',
    'hide_empty' => false,
    'orderby' => 'name',
    'order' => 'ASC',
));

// Подсчёт курсов для каждого преподавателя
$teachers_with_courses = array();
if (!is_wp_error($teachers)) {
    foreach ($teachers as $term) {
        $count = $term->count;
        $teachers_with_courses[] = array(
            'term' => $term,
            'courses_count' => $count,
        );
    }
}
?>

<div class="premium-archive-wrapper teachers-archive">
    <!-- Заголовок страницы -->
    <header class="premium-archive-header teachers-archive-header">
        <div class="premium-header-content">
            <h1 class="premium-archive-title">
                <span class="title-accent"><?php _e('Преподаватели', 'course-plugin'); ?></span>
                <span class="title-sub"><?php _e('наши эксперты и менторы', 'course-plugin'); ?></span>
            </h1>
            <p class="premium-archive-subtitle"><?php _e('Познакомьтесь с профессиональной командой специалистов, которые проводят наши курсы и программы', 'course-plugin'); ?></p>

            <!-- Статистика -->
            <div class="premium-stats">
                <div class="stat-item">
                    <span class="stat-number"><?php echo count($teachers_with_courses); ?></span>
                    <span class="stat-label"><?php _e('преподавателей', 'course-plugin'); ?></span>
                </div>
                <div class="stat-divider"></div>
                <div class="stat-item">
                    <span class="stat-number"><?php
                        $total_courses = wp_count_posts('course');
                        echo $total_courses->publish;
                    ?></span>
                    <span class="stat-label"><?php _e('курсов', 'course-plugin'); ?></span>
                </div>
            </div>
        </div>

        <!-- Декоративные элементы -->
        <div class="header-decoration">
            <div class="decoration-circle circle-1"></div>
            <div class="decoration-circle circle-2"></div>
            <div class="decoration-circle circle-3"></div>
        </div>
    </header>

    <div class="premium-archive-container teachers-archive-container">
        <main class="premium-main-content teachers-main-content">
            <?php if (!empty($teachers_with_courses)) : ?>
                <div class="teachers-grid" id="teachers-container">
                    <?php foreach ($teachers_with_courses as $item) :
                        $term = $item['term'];
                        $courses_count = $item['courses_count'];
                        $teacher_photo = get_term_meta($term->term_id, 'teacher_photo', true);
                        $teacher_position = get_term_meta($term->term_id, 'teacher_position', true);
                        $teacher_description = get_term_meta($term->term_id, 'teacher_description', true);
                        $teacher_link = get_term_link($term);
                        if (is_wp_error($teacher_link)) {
                            $teacher_link = '#';
                        }
                    ?>
                        <article class="teacher-card">
                            <a href="<?php echo esc_url($teacher_link); ?>" class="teacher-card-link">
                                <div class="teacher-card-image">
                                    <?php if ($teacher_photo) : ?>
                                        <img src="<?php echo esc_url($teacher_photo); ?>" alt="<?php echo esc_attr($term->name); ?>" loading="lazy" />
                                    <?php else : ?>
                                        <div class="teacher-card-placeholder">
                                            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="2"/>
                                                <path d="M4 20C4 16.6863 7.58172 14 12 14C16.4183 14 20 16.6863 20 20" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                            </svg>
                                        </div>
                                    <?php endif; ?>
                                    <div class="teacher-card-overlay">
                                        <span class="teacher-card-cta">
                                            <?php _e('Подробнее', 'course-plugin'); ?>
                                            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M1 7H13M13 7L8 2M13 7L8 12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </span>
                                    </div>
                                </div>

                                <div class="teacher-card-content">
                                    <h2 class="teacher-card-name"><?php echo esc_html($term->name); ?></h2>
                                    <?php if ($teacher_position) : ?>
                                        <p class="teacher-card-position"><?php echo esc_html($teacher_position); ?></p>
                                    <?php endif; ?>
                                    <?php if ($teacher_description) : ?>
                                        <p class="teacher-card-description"><?php echo esc_html(wp_trim_words($teacher_description, 15, '...')); ?></p>
                                    <?php endif; ?>
                                    <div class="teacher-card-meta">
                                        <span class="teacher-courses-count">
                                            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M12 2H2C1.44772 2 1 2.44772 1 3V11C1 11.5523 1.44772 12 2 12H12C12.5523 12 13 11.5523 13 11V3C13 2.44772 12.5523 2 12 2Z" stroke="currentColor" stroke-width="1.5"/>
                                                <path d="M4 5H10M4 7H10M4 9H7" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
                                            </svg>
                                            <?php printf(
                                                _n('%d курс', '%d курсов', $courses_count, 'course-plugin'),
                                                $courses_count
                                            ); ?>
                                        </span>
                                    </div>
                                </div>
                            </a>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <div class="no-results">
                    <div class="no-results-icon">
                        <svg width="80" height="80" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="40" cy="40" r="35" stroke="#e0e0e0" stroke-width="3"/>
                            <circle cx="40" cy="32" r="8" stroke="#e0e0e0" stroke-width="3"/>
                            <path d="M25 55C25 55 32 45 40 45C48 45 55 55 55 55" stroke="#e0e0e0" stroke-width="3" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <h2 class="no-results-title"><?php _e('Преподаватели не найдены', 'course-plugin'); ?></h2>
                    <p class="no-results-text"><?php _e('Пока нет зарегистрированных преподавателей', 'course-plugin'); ?></p>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php get_footer(); ?>
