<?php
/**
 * Шаблон для отображения отдельной программы - Премиальный дизайн
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
    $program_tag = get_post_meta(get_the_ID(), '_program_tag', true);
    $program_additional_text = get_post_meta(get_the_ID(), '_program_additional_text', true);
    $program_enroll_url = get_post_meta(get_the_ID(), '_program_enroll_url', true);
    
    // Получаем таксономии
    $teachers = get_the_terms(get_the_ID(), 'course_teacher');
    $specializations = get_the_terms(get_the_ID(), 'course_specialization');
    $levels = get_the_terms(get_the_ID(), 'course_level');
    $topics = get_the_terms(get_the_ID(), 'course_topic');
    
    // Получаем настраиваемые заголовки секций
    $section_description_title = get_post_meta(get_the_ID(), '_program_section_description_title', true) ?: __('Описание программы:', 'course-plugin');
    $section_highlights_title = get_post_meta(get_the_ID(), '_program_section_highlights_title', true) ?: __('Преимущества программы', 'course-plugin');
    $section_courses_title = get_post_meta(get_the_ID(), '_program_section_courses_title', true) ?: __('Курсы в программе:', 'course-plugin');
    $section_teachers_title = get_post_meta(get_the_ID(), '_program_section_teachers_title', true) ?: __('Преподаватели программы', 'course-plugin');
    $sidebar_info_title = get_post_meta(get_the_ID(), '_program_sidebar_info_title', true) ?: __('Информация о программе', 'course-plugin');
    $enroll_button_text = get_post_meta(get_the_ID(), '_program_enroll_button_text', true) ?: __('Записаться на программу', 'course-plugin');
    
    // Получаем данные преподавателей
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
    
    // Форматируем даты
    $formatted_start = '';
    $formatted_end = '';
    if ($program_start_date) {
        $formatted_start = date_i18n('d.m.Y', strtotime($program_start_date));
    }
    if ($program_end_date) {
        $formatted_end = date_i18n('d.m.Y', strtotime($program_end_date));
    }
    
    // Определяем цветовую схему - бордовый с переливом
    $scheme = array(
        'gradient' => 'linear-gradient(135deg, #68202d 0%, #8b2d3a 35%, #a13d4c 65%, #68202d 100%)',
        'accent' => '#68202d',
        'light' => 'rgba(104, 32, 45, 0.1)'
    );
?>

<div class="premium-single-program">
    <!-- Hero Section -->
    <header class="premium-program-hero" style="background: <?php echo $scheme['gradient']; ?>">
        <div class="hero-decoration">
            <div class="hero-circle hero-circle-1"></div>
            <div class="hero-circle hero-circle-2"></div>
            <div class="hero-circle hero-circle-3"></div>
            <!-- Декоративные линии -->
            <svg class="hero-lines" viewBox="0 0 1440 400" preserveAspectRatio="none">
                <path d="M0,100 Q360,150 720,100 T1440,100" stroke="rgba(255,255,255,0.1)" stroke-width="2" fill="none"/>
                <path d="M0,200 Q360,250 720,200 T1440,200" stroke="rgba(255,255,255,0.05)" stroke-width="2" fill="none"/>
                <path d="M0,300 Q360,350 720,300 T1440,300" stroke="rgba(255,255,255,0.03)" stroke-width="2" fill="none"/>
            </svg>
            <svg class="hero-wave" viewBox="0 0 1440 320" preserveAspectRatio="none">
                <path fill="rgba(255,255,255,0.1)" d="M0,160L48,144C96,128,192,96,288,106.7C384,117,480,171,576,181.3C672,192,768,160,864,154.7C960,149,1056,171,1152,165.3C1248,160,1344,128,1392,112L1440,96L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path>
            </svg>
        </div>
        
        <div class="hero-container">
            <div class="hero-content">
                <!-- Breadcrumbs -->
                <nav class="hero-breadcrumbs">
                    <a href="<?php echo home_url(); ?>"><?php _e('Главная', 'course-plugin'); ?></a>
                    <span class="breadcrumb-separator">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M6 4L10 8L6 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </span>
                    <a href="<?php echo get_post_type_archive_link('program'); ?>"><?php _e('Программы', 'course-plugin'); ?></a>
                    <span class="breadcrumb-separator">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M6 4L10 8L6 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </span>
                    <span class="breadcrumb-current"><?php the_title(); ?></span>
                </nav>
                
                <!-- Tags -->
                <div class="hero-tags">
                    <?php if ($program_tag) : ?>
                        <span class="hero-tag hero-tag-featured">
                            <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M7 1L8.5 4.5L12.5 5L9.75 7.5L10.5 11.5L7 9.5L3.5 11.5L4.25 7.5L1.5 5L5.5 4.5L7 1Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>
                            <?php echo esc_html($program_tag); ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($specializations && !is_wp_error($specializations)) : ?>
                        <span class="hero-tag hero-tag-spec">
                            <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><rect x="1" y="1" width="5" height="5" rx="1" stroke="currentColor" stroke-width="1.5"/><rect x="8" y="1" width="5" height="5" rx="1" stroke="currentColor" stroke-width="1.5"/><rect x="1" y="8" width="5" height="5" rx="1" stroke="currentColor" stroke-width="1.5"/><rect x="8" y="8" width="5" height="5" rx="1" stroke="currentColor" stroke-width="1.5"/></svg>
                            <?php echo esc_html($specializations[0]->name); ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($discount > 0) : ?>
                        <span class="hero-tag hero-tag-discount">
                            <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M12 5L7 1L2 5V12H12V5Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><circle cx="7" cy="7" r="2" stroke="currentColor" stroke-width="1.5"/></svg>
                            -<?php echo $discount; ?>%
                        </span>
                    <?php endif; ?>
                </div>
                
                <!-- Title -->
                <h1 class="hero-title"><?php the_title(); ?></h1>
                
                <!-- Subtitle -->
                <?php if ($program_additional_text) : ?>
                    <p class="hero-subtitle"><?php echo esc_html($program_additional_text); ?></p>
                <?php endif; ?>
                
                <!-- Quick Stats -->
                <div class="hero-stats">
                    <?php if ($program_duration) : ?>
                        <div class="hero-stat">
                            <div class="stat-icon">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/><path d="M12 6V12L16 14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                            </div>
                            <div class="stat-content">
                                <span class="stat-value"><?php echo esc_html($program_duration); ?></span>
                                <span class="stat-label"><?php _e('Длительность', 'course-plugin'); ?></span>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if ($program_courses_count) : ?>
                        <div class="hero-stat">
                            <div class="stat-icon">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M4 19.5A2.5 2.5 0 016.5 17H20" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M6.5 2H20V22H6.5A2.5 2.5 0 014 19.5V4.5A2.5 2.5 0 016.5 2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </div>
                            <div class="stat-content">
                                <span class="stat-value"><?php echo esc_html($program_courses_count); ?></span>
                                <span class="stat-label"><?php _e('Курсов', 'course-plugin'); ?></span>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if ($formatted_start) : ?>
                        <div class="hero-stat">
                            <div class="stat-icon">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><rect x="3" y="4" width="18" height="18" rx="2" stroke="currentColor" stroke-width="2"/><path d="M3 10H21M8 2V6M16 2V6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                            </div>
                            <div class="stat-content">
                                <span class="stat-value"><?php echo $formatted_start; ?></span>
                                <span class="stat-label"><?php _e('Старт обучения', 'course-plugin'); ?></span>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if ($program_certificate) : ?>
                        <div class="hero-stat">
                            <div class="stat-icon">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><rect x="3" y="4" width="18" height="14" rx="2" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="20" r="2" stroke="currentColor" stroke-width="2"/><path d="M10 18V20M14 18V20" stroke="currentColor" stroke-width="2"/></svg>
                            </div>
                            <div class="stat-content">
                                <span class="stat-value"><?php _e('Да', 'course-plugin'); ?></span>
                                <span class="stat-label"><?php _e('Сертификат', 'course-plugin'); ?></span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Hero Visual -->
            <div class="hero-visual">
                <div class="hero-visual-card">
                    <?php if (has_post_thumbnail()) : ?>
                        <div class="hero-image">
                            <?php the_post_thumbnail('large'); ?>
                        </div>
                    <?php else : ?>
                        <div class="hero-image-placeholder">
                            <svg width="120" height="120" viewBox="0 0 120 120" fill="none">
                                <circle cx="60" cy="60" r="50" stroke="currentColor" stroke-width="3" stroke-dasharray="8 8"/>
                                <path d="M40 50L60 35L80 50V80H40V50Z" stroke="currentColor" stroke-width="3" stroke-linejoin="round"/>
                                <circle cx="60" cy="60" r="10" stroke="currentColor" stroke-width="3"/>
                            </svg>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Price overlay -->
                    <div class="hero-price-overlay">
                        <?php if ($discount > 0) : ?>
                            <span class="price-discount">-<?php echo $discount; ?>%</span>
                        <?php endif; ?>
                        <div class="price-content">
                            <?php if ($program_old_price && $discount > 0) : ?>
                                <span class="price-old"><?php echo number_format($program_old_price, 0, ',', ' '); ?> ₽</span>
                            <?php endif; ?>
                            <span class="price-current"><?php echo $program_price ? number_format($program_price, 0, ',', ' ') : __('Бесплатно', 'course-plugin'); ?><?php if ($program_price) echo ' ₽'; ?></span>
                        </div>
                        <?php if ($program_enroll_url) : ?>
                            <a href="<?php echo esc_url($program_enroll_url); ?>" class="hero-enroll-btn" target="_blank" rel="noopener">
                                <?php echo esc_html($enroll_button_text); ?>
                                <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M4 10H16M16 10L11 5M16 10L11 15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </a>
                        <?php else : ?>
                            <button class="hero-enroll-btn">
                                <?php echo esc_html($enroll_button_text); ?>
                                <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M4 10H16M16 10L11 5M16 10L11 15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </header>
    
    <!-- Main Content -->
    <div class="premium-program-content">
        <div class="content-container">
            <!-- Main Column -->
            <main class="content-main">
                <!-- Description Section -->
                <section class="content-section section-description">
                    <div class="section-header">
                        <div class="section-icon" style="background: <?php echo $scheme['light']; ?>; color: <?php echo $scheme['accent']; ?>">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M4 6H20M4 10H20M4 14H14M4 18H10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                        </div>
                        <h2 class="section-title"><?php echo esc_html($section_description_title); ?></h2>
                    </div>
                    <div class="section-content program-description">
                        <?php the_content(); ?>
                    </div>
                </section>
                
                <!-- Program Highlights -->
                <?php 
                // Проверяем, нужно ли показывать блок преимуществ
                $show_highlights = get_post_meta(get_the_ID(), '_program_show_highlights', true);
                
                // По умолчанию показываем (если поле не установлено)
                if ($show_highlights !== '0') :
                    
                    // Получаем преимущества из админки или используем значения по умолчанию
                    $highlights = array(
                        1 => array(
                            'title' => get_post_meta(get_the_ID(), '_program_highlight_1_title', true) ?: __('Качественное образование', 'course-plugin'),
                            'text' => get_post_meta(get_the_ID(), '_program_highlight_1_text', true) ?: __('Программа разработана экспертами с многолетним опытом', 'course-plugin'),
                            'gradient' => 'linear-gradient(135deg, #68202d 0%, #8b2d3a 100%)',
                            'icon' => '<svg width="28" height="28" viewBox="0 0 28 28" fill="none"><path d="M14 3L17.5 10L25 11L19.5 16L21 24L14 20L7 24L8.5 16L3 11L10.5 10L14 3Z" stroke="currentColor" stroke-width="2" fill="none"/></svg>'
                        ),
                        2 => array(
                            'title' => get_post_meta(get_the_ID(), '_program_highlight_2_title', true) ?: __('Гибкий график', 'course-plugin'),
                            'text' => get_post_meta(get_the_ID(), '_program_highlight_2_text', true) ?: __('Учитесь в удобное время и в своём темпе', 'course-plugin'),
                            'gradient' => 'linear-gradient(135deg, #8b2d3a 0%, #a13d4c 100%)',
                            'icon' => '<svg width="28" height="28" viewBox="0 0 28 28" fill="none"><circle cx="14" cy="14" r="11" stroke="currentColor" stroke-width="2"/><path d="M14 8V14L18 16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>'
                        ),
                        3 => array(
                            'title' => get_post_meta(get_the_ID(), '_program_highlight_3_title', true) ?: __('Официальный сертификат', 'course-plugin'),
                            'text' => get_post_meta(get_the_ID(), '_program_highlight_3_text', true) ?: __('Получите документ о повышении квалификации', 'course-plugin'),
                            'gradient' => 'linear-gradient(135deg, #a13d4c 0%, #d4576b 100%)',
                            'icon' => '<svg width="28" height="28" viewBox="0 0 28 28" fill="none"><rect x="3" y="5" width="22" height="16" rx="2" stroke="currentColor" stroke-width="2"/><circle cx="14" cy="23" r="2" stroke="currentColor" stroke-width="2"/><path d="M12 21V23M16 21V23" stroke="currentColor" stroke-width="2"/></svg>'
                        ),
                        4 => array(
                            'title' => get_post_meta(get_the_ID(), '_program_highlight_4_title', true) ?: __('Поддержка кураторов', 'course-plugin'),
                            'text' => get_post_meta(get_the_ID(), '_program_highlight_4_text', true) ?: __('Персональная помощь на протяжении всего обучения', 'course-plugin'),
                            'gradient' => 'linear-gradient(135deg, #68202d 0%, #a13d4c 100%)',
                            'icon' => '<svg width="28" height="28" viewBox="0 0 28 28" fill="none"><circle cx="14" cy="9" r="5" stroke="currentColor" stroke-width="2"/><path d="M4 25C4 20.5817 8.47715 17 14 17C19.5228 17 24 20.5817 24 25" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>'
                        ),
                    );
                ?>
                <section class="content-section section-highlights">
                    <div class="section-header">
                        <div class="section-icon" style="background: <?php echo $scheme['light']; ?>; color: <?php echo $scheme['accent']; ?>">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M12 2L15 8L22 9L17 14L18 21L12 18L6 21L7 14L2 9L9 8L12 2Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
                        </div>
                        <h2 class="section-title"><?php echo esc_html($section_highlights_title); ?></h2>
                    </div>
                    <div class="highlights-grid">
                        <?php foreach ($highlights as $highlight) : ?>
                        <div class="highlight-card">
                            <div class="highlight-icon" style="background: <?php echo $highlight['gradient']; ?>">
                                <?php echo $highlight['icon']; ?>
                            </div>
                            <h4 class="highlight-title"><?php echo esc_html($highlight['title']); ?></h4>
                            <p class="highlight-text"><?php echo esc_html($highlight['text']); ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>
                
                <!-- Courses List Section -->
                <?php if (!empty($related_courses_list)) : ?>
                    <section class="content-section section-courses">
                        <div class="section-header">
                            <div class="section-icon" style="background: <?php echo $scheme['light']; ?>; color: <?php echo $scheme['accent']; ?>">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M2 17L12 22L22 17" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M2 12L12 17L22 12" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
                            </div>
                            <h2 class="section-title"><?php echo esc_html($section_courses_title); ?></h2>
                        </div>
                        <div class="courses-list">
                            <?php 
                            $course_number = 1;
                            foreach ($related_courses_list as $course) : 
                                $course_teacher = get_the_terms($course->ID, 'course_teacher');
                                $course_teacher_name = ($course_teacher && !is_wp_error($course_teacher)) ? $course_teacher[0]->name : '';
                                $course_duration = get_post_meta($course->ID, '_course_duration', true);
                            ?>
                                <article class="course-list-item">
                                    <div class="course-number">
                                        <span><?php echo str_pad($course_number, 2, '0', STR_PAD_LEFT); ?></span>
                                    </div>
                                    <div class="course-list-content">
                                        <h4 class="course-list-title">
                                            <a href="<?php echo get_permalink($course->ID); ?>"><?php echo esc_html($course->post_title); ?></a>
                                        </h4>
                                        <?php if ($course->post_excerpt) : ?>
                                            <p class="course-list-excerpt"><?php echo esc_html($course->post_excerpt); ?></p>
                                        <?php endif; ?>
                                        <div class="course-list-meta">
                                            <?php if ($course_teacher_name) : ?>
                                                <span class="meta-item">
                                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="5" r="3" stroke="currentColor" stroke-width="1.5"/><path d="M2 14C2 11.2386 4.68629 9 8 9C11.3137 9 14 11.2386 14 14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                                                    <?php echo esc_html($course_teacher_name); ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($course_duration) : ?>
                                                <span class="meta-item">
                                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.5"/><path d="M8 4V8L10.5 9.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                                                    <?php echo esc_html($course_duration); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <a href="<?php echo get_permalink($course->ID); ?>" class="course-list-link">
                                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M4 10H16M16 10L11 5M16 10L11 15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    </a>
                                </article>
                            <?php 
                            $course_number++;
                            endforeach; 
                            ?>
                        </div>
                    </section>
                <?php endif; ?>
                
                <!-- Teachers Section -->
                <?php if ($teachers && !is_wp_error($teachers) && count($teachers) > 0) : ?>
                    <section class="content-section section-teachers">
                        <div class="section-header">
                            <div class="section-icon" style="background: <?php echo $scheme['light']; ?>; color: <?php echo $scheme['accent']; ?>">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="2"/><path d="M4 20C4 16.6863 7.58172 14 12 14C16.4183 14 20 16.6863 20 20" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                            </div>
                            <h2 class="section-title"><?php echo esc_html($section_teachers_title); ?></h2>
                        </div>
                        <div class="teachers-grid">
                            <?php foreach ($teachers as $t) : 
                                $t_photo = get_term_meta($t->term_id, 'teacher_photo', true);
                                $t_position = get_term_meta($t->term_id, 'teacher_position', true);
                            ?>
                                <a href="<?php echo get_term_link($t); ?>" class="teacher-card">
                                    <div class="teacher-avatar">
                                        <?php if ($t_photo) : ?>
                                            <img src="<?php echo esc_url($t_photo); ?>" alt="<?php echo esc_attr($t->name); ?>">
                                        <?php else : ?>
                                            <div class="avatar-placeholder">
                                                <svg width="40" height="40" viewBox="0 0 40 40" fill="none"><circle cx="20" cy="14" r="8" stroke="currentColor" stroke-width="2"/><path d="M6 36C6 28.268 12.268 22 20 22C27.732 22 34 28.268 34 36" stroke="currentColor" stroke-width="2"/></svg>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="teacher-info">
                                        <h4 class="teacher-name"><?php echo esc_html($t->name); ?></h4>
                                        <?php if ($t_position) : ?>
                                            <p class="teacher-position"><?php echo esc_html($t_position); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="teacher-arrow">
                                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M7 4L13 10L7 16" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>
            </main>
            
            <!-- Sidebar -->
            <aside class="content-sidebar">
                <!-- Program Info Card -->
                <div class="sidebar-card info-card">
                    <div class="card-header" style="background: <?php echo $scheme['gradient']; ?>">
                        <h3><?php echo esc_html($sidebar_info_title); ?></h3>
                    </div>
                    <div class="card-body">
                        <ul class="info-list">
                            <?php if ($program_duration) : ?>
                                <li class="info-item">
                                    <span class="info-icon">
                                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="1.5"/><path d="M10 5V10L13 12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                                    </span>
                                    <span class="info-label"><?php _e('Длительность:', 'course-plugin'); ?></span>
                                    <span class="info-value"><?php echo esc_html($program_duration); ?></span>
                                </li>
                            <?php endif; ?>
                            <?php if ($program_courses_count) : ?>
                                <li class="info-item">
                                    <span class="info-icon">
                                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M3 15.5A2 2 0 015 13.5H17M5 2H17V18H5A2 2 0 013 16V4A2 2 0 015 2Z" stroke="currentColor" stroke-width="1.5"/></svg>
                                    </span>
                                    <span class="info-label"><?php _e('Курсов:', 'course-plugin'); ?></span>
                                    <span class="info-value"><?php echo esc_html($program_courses_count); ?></span>
                                </li>
                            <?php endif; ?>
                            <?php if ($formatted_start) : ?>
                                <li class="info-item">
                                    <span class="info-icon">
                                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><rect x="2" y="3" width="16" height="14" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M2 7H18M6 1V4M14 1V4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                                    </span>
                                    <span class="info-label"><?php _e('Начало:', 'course-plugin'); ?></span>
                                    <span class="info-value"><?php echo $formatted_start; ?></span>
                                </li>
                            <?php endif; ?>
                            <?php if ($formatted_end) : ?>
                                <li class="info-item">
                                    <span class="info-icon">
                                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><rect x="2" y="3" width="16" height="14" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M2 7H18M6 1V4M14 1V4M7 12L9 14L13 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    </span>
                                    <span class="info-label"><?php _e('Окончание:', 'course-plugin'); ?></span>
                                    <span class="info-value"><?php echo $formatted_end; ?></span>
                                </li>
                            <?php endif; ?>
                            <?php if ($program_certificate) : ?>
                                <li class="info-item">
                                    <span class="info-icon">
                                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><rect x="2" y="3" width="16" height="11" rx="2" stroke="currentColor" stroke-width="1.5"/><circle cx="10" cy="16" r="2" stroke="currentColor" stroke-width="1.5"/><path d="M8 14V16M12 14V16" stroke="currentColor" stroke-width="1.5"/></svg>
                                    </span>
                                    <span class="info-label"><?php _e('Сертификат:', 'course-plugin'); ?></span>
                                    <span class="info-value info-value-yes"><?php _e('Да', 'course-plugin'); ?></span>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
                
                <!-- Price Card -->
                <div class="sidebar-card price-card">
                    <?php if ($discount > 0) : ?>
                        <div class="price-badge">-<?php echo $discount; ?>%</div>
                    <?php endif; ?>
                    <div class="price-wrapper">
                        <?php if ($program_old_price && $discount > 0) : ?>
                            <span class="price-old"><?php echo number_format($program_old_price, 0, ',', ' '); ?> ₽</span>
                        <?php endif; ?>
                        <span class="price-current"><?php echo $program_price ? number_format($program_price, 0, ',', ' ') . ' ₽' : __('Бесплатно', 'course-plugin'); ?></span>
                    </div>
                    <?php if ($program_enroll_url) : ?>
                        <a href="<?php echo esc_url($program_enroll_url); ?>" class="enroll-btn" target="_blank" rel="noopener">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="2"/><path d="M10 6V14M6 10H14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                            <?php echo esc_html($enroll_button_text); ?>
                        </a>
                    <?php else : ?>
                        <button class="enroll-btn">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="2"/><path d="M10 6V14M6 10H14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                            <?php echo esc_html($enroll_button_text); ?>
                        </button>
                    <?php endif; ?>
                </div>
                
                <!-- Specialization Card -->
                <?php if ($specializations && !is_wp_error($specializations)) : ?>
                    <div class="sidebar-card spec-card">
                        <h4 class="card-title"><?php _e('Специализация', 'course-plugin'); ?></h4>
                        <div class="spec-list">
                            <?php foreach ($specializations as $spec) : ?>
                                <a href="<?php echo get_term_link($spec); ?>" class="spec-item">
                                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><rect x="2" y="2" width="7" height="7" rx="2" stroke="currentColor" stroke-width="1.5"/><rect x="11" y="2" width="7" height="7" rx="2" stroke="currentColor" stroke-width="1.5"/><rect x="2" y="11" width="7" height="7" rx="2" stroke="currentColor" stroke-width="1.5"/><rect x="11" y="11" width="7" height="7" rx="2" stroke="currentColor" stroke-width="1.5"/></svg>
                                    <?php echo esc_html($spec->name); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Share Card -->
                <div class="sidebar-card share-card">
                    <h4 class="card-title"><?php _e('Поделиться', 'course-plugin'); ?></h4>
                    <div class="share-buttons">
                        <a href="https://t.me/share/url?url=<?php echo urlencode(get_permalink()); ?>&text=<?php echo urlencode(get_the_title()); ?>" target="_blank" rel="noopener" class="share-btn share-telegram">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M18 2L9 11M18 2L12 18L9 11M18 2L2 8L9 11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </a>
                        <a href="https://vk.com/share.php?url=<?php echo urlencode(get_permalink()); ?>" target="_blank" rel="noopener" class="share-btn share-vk">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M10.5 14C5.5 14 2.5 10.5 2.5 5H5C5.2 9 6.8 10.5 8 10.8V5H10.5V8.5C11.7 8.3 13 6.9 13.5 5H16C15.6 7.5 14.2 8.9 13.2 9.5C14.2 10 15.8 11.2 16.5 14H13.8C13.2 12 11.8 10.6 10.5 10.5V14Z" fill="currentColor"/></svg>
                        </a>
                        <button class="share-btn share-copy" onclick="navigator.clipboard.writeText('<?php echo get_permalink(); ?>')">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><rect x="6" y="6" width="11" height="11" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M3 14V4C3 2.89543 3.89543 2 5 2H13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                        </button>
                    </div>
                </div>
            </aside>
        </div>
    </div>
    
    <?php
    // Получаем настраиваемые тексты CTA блока
    $cta_title = get_post_meta(get_the_ID(), '_program_cta_title', true);
    $cta_text = get_post_meta(get_the_ID(), '_program_cta_text', true);
    $cta_button_text = get_post_meta(get_the_ID(), '_program_cta_button_text', true);
    
    // Используем значения по умолчанию, если поля пустые
    if (empty($cta_title)) {
        $cta_title = __('Готовы начать обучение?', 'course-plugin');
    }
    if (empty($cta_text)) {
        $cta_text = __('Присоединяйтесь к программе и откройте новые возможности для профессионального роста!', 'course-plugin');
    }
    if (empty($cta_button_text)) {
        $cta_button_text = __('Записаться сейчас', 'course-plugin');
    }
    ?>
    <!-- CTA Section -->
    <section class="premium-program-cta" style="background: <?php echo $scheme['gradient']; ?>">
        <div class="cta-decoration">
            <svg viewBox="0 0 400 400" fill="none">
                <circle cx="200" cy="200" r="150" stroke="rgba(255,255,255,0.1)" stroke-width="2"/>
                <circle cx="200" cy="200" r="100" stroke="rgba(255,255,255,0.1)" stroke-width="2"/>
                <circle cx="200" cy="200" r="50" stroke="rgba(255,255,255,0.1)" stroke-width="2"/>
            </svg>
        </div>
        <div class="cta-container">
            <div class="cta-content">
                <h2 class="cta-title"><?php echo esc_html($cta_title); ?></h2>
                <p class="cta-text"><?php echo esc_html($cta_text); ?></p>
            </div>
            <?php if ($program_enroll_url) : ?>
                <a href="<?php echo esc_url($program_enroll_url); ?>" class="cta-btn" target="_blank" rel="noopener">
                    <?php echo esc_html($cta_button_text); ?>
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M4 10H16M16 10L11 5M16 10L11 15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </a>
            <?php endif; ?>
        </div>
    </section>
</div>

<?php
endwhile;
get_footer();
?>
