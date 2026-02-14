<?php
/**
 * Шаблон для отображения отдельного курса - Премиальный дизайн
 * 
 * @copyright Copyright (c) 2024 Кузьменко Валентин (Valentink2410)
 * @author Кузьменко Валентин (Valentink2410)
 */

// Подключаем шапку сайта
get_header();

// Цикл WordPress для получения данных текущего курса
while (have_posts()) : the_post();
    
    // ============================================
    // ПОЛУЧЕНИЕ МЕТАПОЛЕЙ КУРСА
    // ============================================
    // Основные метаполя курса из базы данных
    $course_code = get_post_meta(get_the_ID(), '_course_code', true);
    $course_duration = get_post_meta(get_the_ID(), '_course_duration', true);
    $course_price = get_post_meta(get_the_ID(), '_course_price', true);
    $course_old_price = get_post_meta(get_the_ID(), '_course_old_price', true);
    $course_start_date = get_post_meta(get_the_ID(), '_course_start_date', true);
    $course_end_date = get_post_meta(get_the_ID(), '_course_end_date', true);
    $course_capacity = get_post_meta(get_the_ID(), '_course_capacity', true);
    $course_enrolled = get_post_meta(get_the_ID(), '_course_enrolled', true);
    $course_rating = get_post_meta(get_the_ID(), '_course_rating', true) ?: 0; // Рейтинг курса (по умолчанию 0)
    $course_reviews_count = get_post_meta(get_the_ID(), '_course_reviews_count', true) ?: 0; // Количество отзывов (по умолчанию 0)
    $course_location = get_post_meta(get_the_ID(), '_course_location', true); // Место проведения курса
    
    // ============================================
    // ДОПОЛНИТЕЛЬНЫЕ ПОЛЯ КУРСА
    // ============================================
    // Поля для отображения в сайдбаре и других секциях
    $course_weeks = get_post_meta(get_the_ID(), '_course_weeks', true);
    $course_credits = get_post_meta(get_the_ID(), '_course_credits', true);
    $course_hours_per_week = get_post_meta(get_the_ID(), '_course_hours_per_week', true);
    $course_language = get_post_meta(get_the_ID(), '_course_language', true) ?: 'Русский';
    $course_certificate = get_post_meta(get_the_ID(), '_course_certificate', true);
    $course_video_url = get_post_meta(get_the_ID(), '_course_video_url', true);
    $course_accommodation = get_post_meta(get_the_ID(), '_course_accommodation', true); // Проживание и питание
    $course_lifetime_access = get_post_meta(get_the_ID(), '_course_lifetime_access', true); // Пожизненный доступ
    
    // Цели курса
    $course_cognitive_goals = get_post_meta(get_the_ID(), '_course_cognitive_goals', true);
    $course_emotional_goals = get_post_meta(get_the_ID(), '_course_emotional_goals', true);
    $course_psychomotor_goals = get_post_meta(get_the_ID(), '_course_psychomotor_goals', true);
    $course_content = get_post_meta(get_the_ID(), '_course_content', true); // Содержание курса (программа обучения)
    
    // ============================================
    // ПОЛУЧЕНИЕ ТАКСОНОМИЙ
    // ============================================
    // Получаем связанные термины таксономий для курса
    $teachers = get_the_terms(get_the_ID(), 'course_teacher');
    $specializations = get_the_terms(get_the_ID(), 'course_specialization');
    $levels = get_the_terms(get_the_ID(), 'course_level');
    $topics = get_the_terms(get_the_ID(), 'course_topic'); // Темы курса
    
    // ============================================
    // НАСТРАИВАЕМЫЕ ЗАГОЛОВКИ СЕКЦИЙ
    // ============================================
    // Получаем кастомные заголовки секций страницы (если не заданы - используются значения по умолчанию)
    $section_description_title = get_post_meta(get_the_ID(), '_course_section_description_title', true) ?: __('Описание курса:', 'course-plugin');
    $section_goals_title = get_post_meta(get_the_ID(), '_course_section_goals_title', true) ?: __('Цели и задачи курса:', 'course-plugin');
    $section_goals_intro = get_post_meta(get_the_ID(), '_course_section_goals_intro', true) ?: __('Изучив этот курс, студенты смогут:', 'course-plugin');
    $section_content_title = get_post_meta(get_the_ID(), '_course_section_content_title', true) ?: __('Содержание курса', 'course-plugin');
    $section_video_title = get_post_meta(get_the_ID(), '_course_section_video_title', true) ?: __('Видео о курсе', 'course-plugin');
    $section_related_title = get_post_meta(get_the_ID(), '_course_section_related_title', true) ?: __('Другие курсы по теме', 'course-plugin');
    $sidebar_overview_title = get_post_meta(get_the_ID(), '_course_sidebar_overview_title', true) ?: __('Краткий обзор курса', 'course-plugin');
    
    // Названия целей
    $goal_cognitive_title = get_post_meta(get_the_ID(), '_course_goal_cognitive_title', true) ?: __('Когнитивные цели', 'course-plugin');
    $goal_cognitive_subtitle = get_post_meta(get_the_ID(), '_course_goal_cognitive_subtitle', true) ?: __('Знать', 'course-plugin');
    $goal_emotional_title = get_post_meta(get_the_ID(), '_course_goal_emotional_title', true) ?: __('Эмоциональные цели', 'course-plugin');
    $goal_emotional_subtitle = get_post_meta(get_the_ID(), '_course_goal_emotional_subtitle', true) ?: __('Чувствовать', 'course-plugin');
    $goal_psychomotor_title = get_post_meta(get_the_ID(), '_course_goal_psychomotor_title', true) ?: __('Психомоторные цели', 'course-plugin');
    $goal_psychomotor_subtitle = get_post_meta(get_the_ID(), '_course_goal_psychomotor_subtitle', true) ?: __('Уметь', 'course-plugin');
    
    // Тексты кнопок
    $btn_enroll_text = get_post_meta(get_the_ID(), '_course_btn_enroll_text', true) ?: __('Записаться на курс', 'course-plugin');
    $btn_student_text = get_post_meta(get_the_ID(), '_course_btn_student_text', true) ?: __('Для студентов семинарии', 'course-plugin');
    $btn_lite_text = get_post_meta(get_the_ID(), '_course_btn_lite_text', true) ?: __('Лайт курс', 'course-plugin');
    
    // ============================================
    // НАСТРОЙКИ ВИДИМОСТИ СЕКЦИЙ
    // ============================================
    // Управление отображением секций на странице курса
    // По умолчанию все секции включены (если мета-поле не установлено или равно '1')
    $show_description = get_post_meta(get_the_ID(), '_course_show_description', true) !== '0';
    $show_goals = get_post_meta(get_the_ID(), '_course_show_goals', true) !== '0';
    $show_content = get_post_meta(get_the_ID(), '_course_show_content', true) !== '0';
    $show_video = get_post_meta(get_the_ID(), '_course_show_video', true) !== '0';
    $show_related = get_post_meta(get_the_ID(), '_course_show_related', true) !== '0';
    $show_sidebar = get_post_meta(get_the_ID(), '_course_show_sidebar', true) !== '0';
    $show_cta = get_post_meta(get_the_ID(), '_course_show_cta', true) !== '0';
    $show_price = get_post_meta(get_the_ID(), '_course_show_price', true) !== '0';
    $show_teacher = get_post_meta(get_the_ID(), '_course_show_teacher', true) !== '0';
    
    // Настройки видимости полей в hero
    $show_hero_code = get_post_meta(get_the_ID(), '_course_show_hero_code', true) !== '0';
    $show_hero_level = get_post_meta(get_the_ID(), '_course_show_hero_level', true) !== '0';
    $show_hero_dates = get_post_meta(get_the_ID(), '_course_show_hero_dates', true) !== '0';
    $show_hero_duration = get_post_meta(get_the_ID(), '_course_show_hero_duration', true) !== '0';
    $show_hero_language = get_post_meta(get_the_ID(), '_course_show_hero_language', true) !== '0';
    $show_hero_certificate = get_post_meta(get_the_ID(), '_course_show_hero_certificate', true) !== '0';
    $show_hero_location = get_post_meta(get_the_ID(), '_course_show_hero_location', true) !== '0';
    
    // Настройки видимости полей в сайдбаре (по умолчанию все видимы, если мета-поле не установлено)
    $field_language_meta = get_post_meta(get_the_ID(), '_course_show_field_language', true);
    $show_field_language = ($field_language_meta === '' || $field_language_meta === '1');
    $field_weeks_meta = get_post_meta(get_the_ID(), '_course_show_field_weeks', true);
    $show_field_weeks = ($field_weeks_meta === '' || $field_weeks_meta === '1');
    $field_credits_meta = get_post_meta(get_the_ID(), '_course_show_field_credits', true);
    $show_field_credits = ($field_credits_meta === '' || $field_credits_meta === '1');
    $field_hours_meta = get_post_meta(get_the_ID(), '_course_show_field_hours', true);
    $show_field_hours = ($field_hours_meta === '' || $field_hours_meta === '1');
    $field_certificate_meta = get_post_meta(get_the_ID(), '_course_show_field_certificate', true);
    $show_field_certificate = ($field_certificate_meta === '' || $field_certificate_meta === '1');
    
    // ============================================
    // ДОПОЛНИТЕЛЬНЫЕ БЛОКИ КОНТЕНТА
    // ============================================
    // Блоки, которые можно добавить в основной контент страницы
    $extra_blocks = get_post_meta(get_the_ID(), '_course_extra_blocks', true);
    if (!is_array($extra_blocks)) {
        $extra_blocks = array();
    }
    
    // ============================================
    // КАСТОМНЫЕ БЛОКИ САЙДБАРА
    // ============================================
    // Блоки, которые можно добавить в сайдбар страницы курса
    $sidebar_blocks = get_post_meta(get_the_ID(), '_course_sidebar_blocks', true);
    if (!is_array($sidebar_blocks)) {
        $sidebar_blocks = array(); // Инициализируем пустым массивом, если данных нет
    }
    
    // ============================================
    // ДАННЫЕ ПРЕПОДАВАТЕЛЯ
    // ============================================
    // Получаем информацию о преподавателе курса из таксономии
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
    
    // ============================================
    // ССЫЛКИ ДЛЯ КНОПОК КУРСА
    // ============================================
    // URL для различных вариантов записи на курс
    $course_seminary_new_url = get_post_meta(get_the_ID(), '_course_seminary_new_url', true); // Для новых студентов
    $course_seminary_student_url = get_post_meta(get_the_ID(), '_course_seminary_student_url', true); // Для студентов семинарии
    $course_lite_course_url = get_post_meta(get_the_ID(), '_course_lite_course_url', true); // Лайт версия курса
    
    // ============================================
    // ВЫЧИСЛЕНИЕ СКИДКИ
    // ============================================
    // Рассчитываем процент скидки, если есть старая и новая цена
    $discount = 0;
    if ($course_old_price && $course_price && $course_price < $course_old_price) {
        $discount = round((($course_old_price - $course_price) / $course_old_price) * 100);
    }
    
    // ============================================
    // ФОРМАТИРОВАНИЕ ДАТ
    // ============================================
    $formatted_dates = '';
    $days_until_start = null;
    if ($course_start_date) {
        $start_timestamp = strtotime($course_start_date);
        $current_timestamp = current_time('timestamp');
        $days_diff = floor(($start_timestamp - $current_timestamp) / (60 * 60 * 24));
        $days_until_start = $days_diff;
        
        if ($course_end_date) {
            $end_timestamp = strtotime($course_end_date);
            $start_day = date('j', $start_timestamp);
            $end_day = date('j', $end_timestamp);
            if (date('m', $start_timestamp) === date('m', $end_timestamp)) {
                $month = date_i18n('F', $start_timestamp);
                $year = date('Y', $start_timestamp);
                $formatted_dates = sprintf(__('%s–%s %s %s', 'course-plugin'), $start_day, $end_day, $month, $year);
            } else {
                $start_month = date_i18n('F', $start_timestamp);
                $end_month = date_i18n('F', $end_timestamp);
                $year = date('Y', $start_timestamp);
                $formatted_dates = sprintf(__('%s %s — %s %s %s', 'course-plugin'), $start_day, $start_month, $end_day, $end_month, $year);
            }
        } else {
            $day = date('j', $start_timestamp);
            $month = date_i18n('F', $start_timestamp);
            $year = date('Y', $start_timestamp);
            $formatted_dates = sprintf(__('%s %s %s', 'course-plugin'), $day, $month, $year);
        }
    }
    
    // ============================================
    // ФОРМАТИРОВАНИЕ МЕСТА ПРОВЕДЕНИЯ
    // ============================================
    // Получаем локализованное название места проведения курса
    $location_names = array(
        'online' => __('Онлайн-курсы', 'course-plugin'),
        'zoom' => __('Зум', 'course-plugin'),
        'moscow' => __('Москва (центральный кампус)', 'course-plugin'),
        'prokhladny' => __('Прохладный', 'course-plugin'),
        'nizhny-novgorod' => __('Нижний Новгород', 'course-plugin'),
        'chelyabinsk' => __('Челябинск', 'course-plugin'),
        'norilsk' => __('Норильск', 'course-plugin'),
        'izhevsk' => __('Ижевск', 'course-plugin'),
        'yug' => __('Юг', 'course-plugin'),
        'novokuznetsk' => __('Новокузнецк', 'course-plugin'),
    );
    $location_name = '';
    if ($course_location && isset($location_names[$course_location])) {
        $location_name = $location_names[$course_location];
    } elseif ($course_location) {
        $location_name = $course_location; // Если значение не найдено в массиве, используем как есть
    }
    
    // ============================================
    // ЦВЕТОВАЯ СХЕМА
    // ============================================
    // Определяем цветовую схему страницы - бордовый с градиентным переливом
    $scheme = array(
        'gradient' => 'linear-gradient(135deg, #68202d 0%, #8b2d3a 35%, #a13d4c 65%, #68202d 100%)',
        'accent' => '#68202d',
        'light' => 'rgba(104, 32, 45, 0.1)'
    );
?>

<!-- ============================================
     ОСНОВНАЯ ОБЕРТКА СТРАНИЦЫ КУРСА
     ============================================ -->
<div class="premium-single-course">
    <!-- ============================================
         HERO СЕКЦИЯ (ШАПКА СТРАНИЦЫ)
         ============================================
         Верхняя часть страницы с заголовком, тегами и основной информацией
         -->
    <header class="premium-course-hero" style="background: <?php echo $scheme['gradient']; ?>">
        <div class="hero-decoration">
            <div class="hero-circle hero-circle-1"></div>
            <div class="hero-circle hero-circle-2"></div>
            <div class="hero-circle hero-circle-3"></div>
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
                    <a href="<?php echo get_post_type_archive_link('course'); ?>"><?php _e('Курсы', 'course-plugin'); ?></a>
                    <span class="breadcrumb-separator">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M6 4L10 8L6 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </span>
                    <span class="breadcrumb-current"><?php the_title(); ?></span>
                </nav>
                
                <!-- Tags -->
                <div class="hero-tags">
                    <?php if ($show_hero_code && $course_code) : ?>
                        <span class="hero-tag hero-tag-code">
                            <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M4.5 4L1.5 7L4.5 10M9.5 4L12.5 7L9.5 10M8 2L6 12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            <?php echo esc_html($course_code); ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($show_hero_level && $levels && !is_wp_error($levels)) : ?>
                        <span class="hero-tag hero-tag-level">
                            <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><rect x="1" y="8" width="3" height="4" rx="0.5" stroke="currentColor" stroke-width="1.5"/><rect x="5.5" y="5" width="3" height="7" rx="0.5" stroke="currentColor" stroke-width="1.5"/><rect x="10" y="2" width="3" height="10" rx="0.5" stroke="currentColor" stroke-width="1.5"/></svg>
                            <?php echo esc_html($levels[0]->name); ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($show_hero_dates && $formatted_dates) : ?>
                        <span class="hero-tag hero-tag-date">
                            <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><rect x="1" y="2" width="12" height="11" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M1 5H13M4 1V3M10 1V3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                            <?php echo esc_html($formatted_dates); ?>
                        </span>
                        <?php if ($days_until_start !== null) : ?>
                            <span class="hero-tag hero-tag-countdown <?php echo $days_until_start < 0 ? 'countdown-past' : ($days_until_start <= 7 ? 'countdown-soon' : 'countdown-future'); ?>">
                                <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><circle cx="7" cy="7" r="6" stroke="currentColor" stroke-width="1.5"/><path d="M7 4V7L9 8.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                                <?php if ($days_until_start < 0) : ?>
                                    <?php printf(__('Начался %d дн. назад', 'course-plugin'), abs($days_until_start)); ?>
                                <?php elseif ($days_until_start == 0) : ?>
                                    <?php _e('Начинается сегодня', 'course-plugin'); ?>
                                <?php else : ?>
                                    <?php printf(_n('Начинается через %d день', 'Начинается через %d дней', $days_until_start, 'course-plugin'), $days_until_start); ?>
                                <?php endif; ?>
                            </span>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if ($show_hero_location && $location_name) : ?>
                        <span class="hero-tag hero-tag-location">
                            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M7 1.5C4.92893 1.5 3.25 3.17893 3.25 5.25C3.25 8.1875 7 12.5 7 12.5C7 12.5 10.75 8.1875 10.75 5.25C10.75 3.17893 9.07107 1.5 7 1.5Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                <circle cx="7" cy="5.25" r="1.5" stroke="currentColor" stroke-width="1.5"/>
                            </svg>
                            <?php echo esc_html($location_name); ?>
                        </span>
                    <?php endif; ?>
                </div>
                
                <!-- Title -->
                <h1 class="hero-title"><?php the_title(); ?></h1>
                
                <!-- Subtitle / Teacher -->
                <?php if ($teacher_name) : ?>
                    <div class="hero-instructor">
                        <?php if ($teacher_photo) : ?>
                            <img src="<?php echo esc_url($teacher_photo); ?>" alt="<?php echo esc_attr($teacher_name); ?>" class="instructor-avatar">
                        <?php else : ?>
                            <div class="instructor-avatar-placeholder">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="2"/><path d="M4 20C4 16.6863 7.58172 14 12 14C16.4183 14 20 16.6863 20 20" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                            </div>
                        <?php endif; ?>
                        <div class="instructor-info">
                            <span class="instructor-label"><?php _e('Преподаватель', 'course-plugin'); ?></span>
                            <a href="<?php echo $teacher ? esc_url(get_term_link($teacher->term_id, 'course_teacher')) : '#'; ?>" class="instructor-name"><?php echo esc_html($teacher_name); ?></a>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Quick Stats -->
                <div class="hero-stats">
                    <?php if ($show_hero_duration && $course_duration) : ?>
                        <div class="hero-stat">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="2"/><path d="M10 5V10L13 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                            <span><?php echo esc_html($course_duration); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($show_hero_language && $course_language) : ?>
                        <div class="hero-stat">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="2"/><path d="M2 10H18M10 2C12.5 4.5 14 7 14 10C14 13 12.5 15.5 10 18M10 2C7.5 4.5 6 7 6 10C6 13 7.5 15.5 10 18" stroke="currentColor" stroke-width="2"/></svg>
                            <span><?php echo esc_html($course_language); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($show_hero_certificate && $course_certificate) : ?>
                        <div class="hero-stat">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><rect x="2" y="3" width="16" height="12" rx="2" stroke="currentColor" stroke-width="2"/><circle cx="10" cy="17" r="2" stroke="currentColor" stroke-width="2"/><path d="M8 15V17M12 15V17" stroke="currentColor" stroke-width="2"/></svg>
                            <span><?php _e('Сертификат', 'course-plugin'); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Hero Image/Video -->
            <div class="hero-media">
                <?php if ($course_video_url) : ?>
                    <div class="hero-video-wrapper">
                        <button class="hero-play-btn" data-video-url="<?php echo esc_url($course_video_url); ?>">
                            <span class="play-icon">
                                <svg width="32" height="32" viewBox="0 0 32 32" fill="none"><path d="M12 8L24 16L12 24V8Z" fill="currentColor"/></svg>
                            </span>
                            <span class="play-text"><?php _e('Смотреть видео', 'course-plugin'); ?></span>
                        </button>
                        <?php if (has_post_thumbnail()) : ?>
                            <?php the_post_thumbnail('large', array('class' => 'hero-video-poster')); ?>
                        <?php else : ?>
                            <div class="hero-video-poster-placeholder">
                                <svg width="80" height="80" viewBox="0 0 80 80" fill="none"><rect x="10" y="15" width="60" height="40" rx="4" stroke="currentColor" stroke-width="3"/><path d="M35 30L50 40L35 50V30Z" fill="currentColor"/><rect x="25" y="60" width="30" height="4" rx="2" fill="currentColor"/></svg>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php elseif (has_post_thumbnail()) : ?>
                    <div class="hero-image">
                        <?php the_post_thumbnail('large'); ?>
                    </div>
                <?php else : ?>
                    <div class="hero-image-placeholder">
                        <svg width="120" height="120" viewBox="0 0 120 120" fill="none">
                            <rect x="20" y="30" width="80" height="50" rx="4" stroke="currentColor" stroke-width="3"/>
                            <path d="M50 45L75 60L50 75V45Z" fill="currentColor"/>
                            <rect x="40" y="85" width="40" height="6" rx="3" fill="currentColor"/>
                        </svg>
                    </div>
                <?php endif; ?>
                <?php
                $hero_enroll_url = $course_seminary_new_url ?: $course_seminary_student_url ?: $course_lite_course_url;
                if ($hero_enroll_url) :
                ?>
                    <a href="<?php echo esc_url($hero_enroll_url); ?>" target="_blank" rel="noopener" class="hero-enroll-btn action-btn action-btn-secondary">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="2"/><path d="M10 6V14M6 10H14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                        <?php echo esc_html($btn_enroll_text); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>
    
    <!-- ============================================
         ОСНОВНОЙ КОНТЕНТ СТРАНИЦЫ
         ============================================
         Секции с описанием, целями, содержанием и другой информацией о курсе
         -->
    <div class="premium-course-content">
        <div class="content-container">
            <!-- ============================================
                 ОСНОВНАЯ КОЛОНКА КОНТЕНТА
                 ============================================
                 Левая часть страницы с основным контентом
                 -->
            <main class="content-main">
                <!-- ============================================
                     СЕКЦИЯ "ОПИСАНИЕ КУРСА"
                     ============================================
                     Основное описание курса из контента поста
                     -->
                <?php if ($show_description) : ?>
                <section class="content-section section-description">
                    <div class="section-header">
                        <div class="section-icon" style="background: <?php echo $scheme['light']; ?>; color: <?php echo $scheme['accent']; ?>">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M4 6H20M4 10H20M4 14H14M4 18H10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                        </div>
                        <h2 class="section-title"><?php echo esc_html($section_description_title); ?></h2>
                    </div>
                    <div class="section-content course-description">
                        <?php the_content(); ?>
                    </div>
                </section>
                <?php endif; ?>
                
                <!-- ============================================
                     СЕКЦИЯ "ЦЕЛИ И ЗАДАЧИ КУРСА"
                     ============================================
                     Отображение когнитивных, эмоциональных и психомоторных целей курса
                     -->
                <?php if ($show_goals && ($course_cognitive_goals || $course_emotional_goals || $course_psychomotor_goals)) : ?>
                    <section class="content-section section-goals">
                        <div class="section-header">
                            <div class="section-icon" style="background: <?php echo $scheme['light']; ?>; color: <?php echo $scheme['accent']; ?>">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="6" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="2" fill="currentColor"/></svg>
                            </div>
                            <h2 class="section-title"><?php echo esc_html($section_goals_title); ?></h2>
                        </div>
                        <p class="goals-intro"><?php echo esc_html($section_goals_intro); ?></p>
                        
                        <div class="goals-grid">
                            <?php if ($course_cognitive_goals) : ?>
                                <div class="goal-card">
                                    <div class="goal-icon" style="background: linear-gradient(135deg, #68202d 0%, #8b2d3a 100%)">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z" fill="currentColor"/><circle cx="12" cy="10" r="3" fill="currentColor"/><path d="M12 14c-3 0-6 1.5-6 3v1h12v-1c0-1.5-3-3-6-3z" fill="currentColor"/></svg>
                                    </div>
                                    <h4 class="goal-title"><?php echo esc_html($goal_cognitive_title); ?></h4>
                                    <span class="goal-subtitle"><?php echo esc_html($goal_cognitive_subtitle); ?></span>
                                    <div class="goal-content">
                                        <?php echo wpautop($course_cognitive_goals); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($course_emotional_goals) : ?>
                                <div class="goal-card">
                                    <div class="goal-icon" style="background: linear-gradient(135deg, #8b2d3a 0%, #a13d4c 100%)">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z" fill="currentColor"/></svg>
                                    </div>
                                    <h4 class="goal-title"><?php echo esc_html($goal_emotional_title); ?></h4>
                                    <span class="goal-subtitle"><?php echo esc_html($goal_emotional_subtitle); ?></span>
                                    <div class="goal-content">
                                        <?php echo wpautop($course_emotional_goals); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($course_psychomotor_goals) : ?>
                                <div class="goal-card">
                                    <div class="goal-icon" style="background: linear-gradient(135deg, #a13d4c 0%, #d4576b 100%)">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M13.5 5.5C14.59 5.5 15.5 4.59 15.5 3.5C15.5 2.41 14.59 1.5 13.5 1.5C12.41 1.5 11.5 2.41 11.5 3.5C11.5 4.59 12.41 5.5 13.5 5.5ZM9.89 19.38L10.89 15L13 17V23H15V15.5L12.89 13.5L13.5 10.5C14.79 12 16.79 13 19 13V11C17.09 11 15.5 10 14.69 8.58L13.69 7C13.29 6.38 12.61 6 11.89 6C11.54 6 11.19 6.08 10.89 6.25L6 8.83V13H8V10.17L9.45 9.38L8 17L2.62 16L2.16 18L9.89 19.38Z" fill="currentColor"/></svg>
                                    </div>
                                    <h4 class="goal-title"><?php echo esc_html($goal_psychomotor_title); ?></h4>
                                    <span class="goal-subtitle"><?php echo esc_html($goal_psychomotor_subtitle); ?></span>
                                    <div class="goal-content">
                                        <?php echo wpautop($course_psychomotor_goals); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>
                <?php endif; ?>
                
                <!-- ============================================
                     СЕКЦИЯ "СОДЕРЖАНИЕ КУРСА"
                     ============================================
                     Программа обучения, темы и модули курса
                     -->
                <?php if ($show_content && $course_content) : ?>
                    <section class="content-section section-curriculum">
                        <div class="section-header">
                            <div class="section-icon" style="background: <?php echo $scheme['light']; ?>; color: <?php echo $scheme['accent']; ?>">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M4 6H2V20C2 21.1 2.9 22 4 22H18V20H4V6ZM20 2H8C6.9 2 6 2.9 6 4V16C6 17.1 6.9 18 8 18H20C21.1 18 22 17.1 22 16V4C22 2.9 21.1 2 20 2ZM20 16H8V4H20V16ZM10 9H18V11H10V9ZM10 12H14V14H10V12ZM10 6H18V8H10V6Z" fill="currentColor"/></svg>
                            </div>
                            <h2 class="section-title"><?php echo esc_html($section_content_title); ?></h2>
                        </div>
                        <div class="section-content curriculum-content">
                            <?php echo wpautop($course_content); ?>
                        </div>
                    </section>
                <?php endif; ?>
                
                <!-- ============================================
                     СЕКЦИЯ "ВИДЕО О КУРСЕ"
                     ============================================
                     Встроенное видео с YouTube, Vimeo или прямой ссылкой
                     -->
                <?php if ($show_video && $course_video_url) : ?>
                    <section class="content-section section-video">
                        <div class="section-header">
                            <div class="section-icon" style="background: <?php echo $scheme['light']; ?>; color: <?php echo $scheme['accent']; ?>">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M21 3H3C1.89 3 1 3.89 1 5V17C1 18.1 1.89 19 3 19H8V21H16V19H21C22.1 19 23 18.1 23 17V5C23 3.89 22.1 3 21 3ZM21 17H3V5H21V17ZM16 11L9 15V7L16 11Z" fill="currentColor"/></svg>
                            </div>
                            <h2 class="section-title"><?php echo esc_html($section_video_title); ?></h2>
                        </div>
                        <div class="video-wrapper">
                            <?php
                            if (strpos($course_video_url, 'youtube.com') !== false || strpos($course_video_url, 'youtu.be') !== false) {
                                preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $course_video_url, $matches);
                                $youtube_id = isset($matches[1]) ? $matches[1] : '';
                                if ($youtube_id) {
                                    ?>
                                    <iframe src="https://www.youtube.com/embed/<?php echo esc_attr($youtube_id); ?>" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                                    <?php
                                }
                            } elseif (strpos($course_video_url, 'vimeo.com') !== false) {
                                preg_match('/vimeo.com\/(\d+)/', $course_video_url, $matches);
                                $vimeo_id = isset($matches[1]) ? $matches[1] : '';
                                if ($vimeo_id) {
                                    ?>
                                    <iframe src="https://player.vimeo.com/video/<?php echo esc_attr($vimeo_id); ?>" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>
                                    <?php
                                }
                            } else {
                                ?>
                                <video controls>
                                    <source src="<?php echo esc_url($course_video_url); ?>" type="video/mp4">
                                </video>
                                <?php
                            }
                            ?>
                        </div>
                    </section>
                <?php endif; ?>
                
                <!-- ============================================
                     ДОПОЛНИТЕЛЬНЫЕ БЛОКИ КОНТЕНТА
                     ============================================
                     Кастомные блоки, добавленные через админку
                     -->
                <?php if (!empty($extra_blocks)) : ?>
                    <?php foreach ($extra_blocks as $block) : ?>
                        <?php if (!empty($block['title']) || !empty($block['content'])) : ?>
                        <section class="content-section section-extra">
                            <?php if (!empty($block['title'])) : ?>
                            <div class="section-header">
                                <div class="section-icon" style="background: <?php echo $scheme['light']; ?>; color: <?php echo $scheme['accent']; ?>">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M2 17L12 22L22 17" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M2 12L12 17L22 12" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
                                </div>
                                <h2 class="section-title"><?php echo esc_html($block['title']); ?></h2>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($block['content'])) : ?>
                            <div class="section-content">
                                <?php echo wp_kses_post($block['content']); ?>
                            </div>
                            <?php endif; ?>
                        </section>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <!-- ============================================
                     СЕКЦИЯ "ДРУГИЕ КУРСЫ ПО ТЕМЕ"
                     ============================================
                     Похожие курсы из той же специализации
                     -->
                <?php if ($show_related) :
                $related_args = array(
                    'post_type' => 'course',
                    'posts_per_page' => 3,
                    'post__not_in' => array(get_the_ID()),
                    'orderby' => 'rand',
                );
                
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
                    <section class="content-section section-related">
                        <div class="section-header">
                            <div class="section-icon" style="background: <?php echo $scheme['light']; ?>; color: <?php echo $scheme['accent']; ?>">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M2 17L12 22L22 17" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M2 12L12 17L22 12" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
                            </div>
                            <h2 class="section-title"><?php echo esc_html($section_related_title); ?></h2>
                        </div>
                        <div class="related-courses-grid">
                            <?php 
                            $related_schemes = array(
                                'linear-gradient(135deg, #68202d 0%, #8b2d3a 100%)',
                                'linear-gradient(135deg, #8b2d3a 0%, #a13d4c 100%)',
                                'linear-gradient(135deg, #a13d4c 0%, #d4576b 100%)',
                            );
                            $i = 0;
                            while ($related_courses->have_posts()) : $related_courses->the_post(); 
                                $rel_teacher = get_the_terms(get_the_ID(), 'course_teacher');
                                $rel_teacher_name = ($rel_teacher && !is_wp_error($rel_teacher)) ? $rel_teacher[0]->name : '';
                            ?>
                                <article class="related-course-card">
                                    <a href="<?php the_permalink(); ?>" class="related-card-link">
                                        <div class="related-card-header" style="background: <?php echo $related_schemes[$i % 3]; ?>">
                                            <span class="related-card-badge"><?php _e('Курс', 'course-plugin'); ?></span>
                                            <div class="related-card-icon">
                                                <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
                                                    <circle cx="20" cy="20" r="16" stroke="rgba(255,255,255,0.3)" stroke-width="2"/>
                                                    <path d="M16 14L26 20L16 26V14Z" fill="rgba(255,255,255,0.8)"/>
                                                </svg>
                                            </div>
                                        </div>
                                        <div class="related-card-content">
                                            <h4 class="related-card-title"><?php the_title(); ?></h4>
                                            <?php if ($rel_teacher_name) : ?>
                                                <p class="related-card-teacher"><?php echo esc_html($rel_teacher_name); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </a>
                                </article>
                            <?php 
                            $i++;
                            endwhile; 
                            ?>
                        </div>
                    </section>
                    <?php wp_reset_postdata(); ?>
                <?php endif; ?>
                <?php endif; ?>
            </main>
            
            <!-- ============================================
                 САЙДБАР (БОКОВАЯ ПАНЕЛЬ)
                 ============================================
                 Правая колонка с краткой информацией, кнопками и дополнительными блоками
                 -->
            <aside class="content-sidebar">
                <!-- ============================================
                     КАРТОЧКА "КРАТКИЙ ОБЗОР КУРСА"
                     ============================================
                     Основная информация о курсе: язык, недели, кредиты, часы, сертификат
                     -->
                <?php if ($show_sidebar) : ?>
                <div class="sidebar-card overview-card">
                    <div class="card-header" style="background: <?php echo $scheme['gradient']; ?>">
                        <h3><?php echo esc_html($sidebar_overview_title); ?></h3>
                    </div>
                    <div class="card-body">
                        <ul class="overview-list">
                            <?php if ($show_field_language && $course_language) : ?>
                                <li class="overview-item">
                                    <span class="overview-icon">
                                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="1.5"/><path d="M2 10H18M10 2C12 4 13 7 13 10C13 13 12 16 10 18M10 2C8 4 7 7 7 10C7 13 8 16 10 18" stroke="currentColor" stroke-width="1.5"/></svg>
                                    </span>
                                    <span class="overview-label"><?php _e('Язык:', 'course-plugin'); ?></span>
                                    <span class="overview-value"><?php echo esc_html($course_language); ?></span>
                                </li>
                            <?php endif; ?>
                            <?php if ($show_field_weeks && $course_weeks) : ?>
                                <li class="overview-item">
                                    <span class="overview-icon">
                                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><rect x="2" y="3" width="16" height="14" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M2 7H18M6 1V4M14 1V4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                                    </span>
                                    <span class="overview-label"><?php _e('Недель:', 'course-plugin'); ?></span>
                                    <span class="overview-value"><?php echo esc_html($course_weeks); ?></span>
                                </li>
                            <?php endif; ?>
                            <?php if ($show_field_credits && $course_credits) : ?>
                                <li class="overview-item">
                                    <span class="overview-icon">
                                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M10 2L12.5 7H17.5L13.5 11L15 17L10 14L5 17L6.5 11L2.5 7H7.5L10 2Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>
                                    </span>
                                    <span class="overview-label"><?php _e('Кредитов:', 'course-plugin'); ?></span>
                                    <span class="overview-value"><?php echo esc_html($course_credits); ?></span>
                                </li>
                            <?php endif; ?>
                            <?php if ($show_field_hours && $course_hours_per_week) : ?>
                                <li class="overview-item">
                                    <span class="overview-icon">
                                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="1.5"/><path d="M10 5V10L13 12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                                    </span>
                                    <span class="overview-label"><?php _e('Часов / неделя:', 'course-plugin'); ?></span>
                                    <span class="overview-value"><?php echo esc_html($course_hours_per_week); ?></span>
                                </li>
                            <?php endif; ?>
                            <?php if ($show_field_certificate && $course_certificate) : ?>
                                <li class="overview-item">
                                    <span class="overview-icon">
                                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><rect x="2" y="3" width="16" height="11" rx="2" stroke="currentColor" stroke-width="1.5"/><circle cx="10" cy="16" r="2" stroke="currentColor" stroke-width="1.5"/><path d="M8 14V16M12 14V16" stroke="currentColor" stroke-width="1.5"/></svg>
                                    </span>
                                    <span class="overview-label"><?php _e('Сертификат:', 'course-plugin'); ?></span>
                                    <span class="overview-value overview-value-yes"><?php _e('Да', 'course-plugin'); ?></span>
                                </li>
                            <?php endif; ?>
                            <?php if ($course_accommodation) : ?>
                                <li class="overview-item">
                                    <span class="overview-icon">
                                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M3 11L10 4L17 11V18H12V14H8V18H3V11Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    </span>
                                    <span class="overview-label"><?php _e('Проживание и питание:', 'course-plugin'); ?></span>
                                    <span class="overview-value">
                                        <?php
                                        $accommodation_names = array(
                                            'included' => __('Включено', 'course-plugin'),
                                            'not_included' => __('Не включено', 'course-plugin'),
                                            'online' => __('Онлайн', 'course-plugin'),
                                        );
                                        echo esc_html(isset($accommodation_names[$course_accommodation]) ? $accommodation_names[$course_accommodation] : $course_accommodation);
                                        ?>
                                    </span>
                                </li>
                            <?php endif; ?>
                            <?php if ($course_lifetime_access === '1') : ?>
                                <li class="overview-item">
                                    <span class="overview-icon">
                                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M10 2L11.5 7.5H17L13 11L14.5 16.5L10 13.5L5.5 16.5L7 11L3 7.5H8.5L10 2Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>
                                    </span>
                                    <span class="overview-label"><?php _e('Пожизненный доступ к материалам курса:', 'course-plugin'); ?></span>
                                    <span class="overview-value overview-value-yes"><?php _e('Да', 'course-plugin'); ?></span>
                                </li>
                            <?php endif; ?>
                        </ul>
                        <?php 
                        // Если список пустой, показываем сообщение
                        $has_visible_fields = ($show_field_language && $course_language) || 
                                             ($show_field_weeks && $course_weeks) || 
                                             ($show_field_credits && $course_credits) || 
                                             ($show_field_hours && $course_hours_per_week) || 
                                             ($show_field_certificate && $course_certificate) ||
                                             $course_accommodation ||
                                             ($course_lifetime_access === '1');
                        if (!$has_visible_fields) : 
                        ?>
                            <p class="description" style="padding: 15px; text-align: center; color: #666;">
                                <?php _e('Нет данных для отображения. Заполните поля курса или включите их отображение в настройках страницы.', 'course-plugin'); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- ============================================
                     БЛОК КНОПОК ДЕЙСТВИЙ
                     ============================================
                     Кнопки для записи на курс (разные варианты)
                     -->
                <?php if ($course_seminary_new_url || $course_seminary_student_url || $course_lite_course_url) : ?>
                    <div class="sidebar-card action-card">
                        <div class="action-buttons">
                            <?php if ($course_seminary_new_url) : ?>
                                <a href="<?php echo esc_url($course_seminary_new_url); ?>" target="_blank" rel="noopener" class="action-btn action-btn-primary">
                                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="2"/><path d="M10 6V14M6 10H14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                                    <?php echo esc_html($btn_enroll_text); ?>
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($course_seminary_student_url) : ?>
                                <a href="<?php echo esc_url($course_seminary_student_url); ?>" target="_blank" rel="noopener" class="action-btn action-btn-secondary">
                                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="6" r="4" stroke="currentColor" stroke-width="2"/><path d="M3 18C3 14.134 6.134 11 10 11C13.866 11 17 14.134 17 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                                    <?php echo esc_html($btn_student_text); ?>
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($course_lite_course_url) : ?>
                                <a href="<?php echo esc_url($course_lite_course_url); ?>" target="_blank" rel="noopener" class="action-btn action-btn-outline">
                                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M3 10L10 3L17 10M5 8V16H15V8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    <?php echo esc_html($btn_lite_text); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- ============================================
                     КАРТОЧКА С ЦЕНОЙ
                     ============================================
                     Отображение стоимости курса и скидки (если есть)
                     -->
                <?php if ($show_price && $course_price) : ?>
                    <div class="sidebar-card price-card">
                        <?php if ($discount > 0) : ?>
                            <div class="price-discount-badge">-<?php echo $discount; ?>%</div>
                        <?php endif; ?>
                        <div class="price-content">
                            <?php if ($discount > 0) : ?>
                                <span class="price-old"><?php echo number_format($course_old_price, 0, ',', ' '); ?> ₽</span>
                            <?php endif; ?>
                            <span class="price-current"><?php echo number_format($course_price, 0, ',', ' '); ?> ₽</span>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- ============================================
                     КАРТОЧКА ПРЕПОДАВАТЕЛЯ
                     ============================================
                     Информация о преподавателе курса с аватаром и ссылкой на профиль
                     -->
                <?php if ($show_teacher && $teacher_name) : ?>
                    <div class="sidebar-card instructor-card">
                        <h4 class="card-title"><?php _e('Преподаватель', 'course-plugin'); ?></h4>
                        <a href="<?php echo $teacher ? esc_url(get_term_link($teacher->term_id, 'course_teacher')) : '#'; ?>" class="instructor-link">
                            <div class="instructor-avatar-wrapper">
                                <?php if ($teacher_photo) : ?>
                                    <img src="<?php echo esc_url($teacher_photo); ?>" alt="<?php echo esc_attr($teacher_name); ?>">
                                <?php else : ?>
                                    <div class="avatar-placeholder">
                                        <svg width="40" height="40" viewBox="0 0 40 40" fill="none"><circle cx="20" cy="14" r="8" stroke="currentColor" stroke-width="2"/><path d="M6 36C6 28.268 12.268 22 20 22C27.732 22 34 28.268 34 36" stroke="currentColor" stroke-width="2"/></svg>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="instructor-details">
                                <span class="instructor-name"><?php echo esc_html($teacher_name); ?></span>
                                <?php if ($teacher_position) : ?>
                                    <span class="instructor-position"><?php echo esc_html($teacher_position); ?></span>
                                <?php endif; ?>
                            </div>
                            <svg class="instructor-arrow" width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M7 4L13 10L7 16" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </a>
                    </div>
                <?php endif; ?>
                
                <!-- ============================================
                     КАСТОМНЫЕ БЛОКИ САЙДБАРА
                     ============================================
                     Дополнительные блоки, созданные через админку
                     Поддерживаются типы: card (карточка), simple (простой), info (информационный)
                     -->
                <?php if (!empty($sidebar_blocks)) : ?>
                    <?php foreach ($sidebar_blocks as $block) : ?>
                        <?php 
                        $block_type = isset($block['type']) ? $block['type'] : 'card';
                        $block_title = isset($block['title']) ? $block['title'] : '';
                        $block_content = isset($block['content']) ? $block['content'] : '';
                        $block_icon = isset($block['icon']) ? $block['icon'] : '';
                        
                        if (empty($block_title) && empty($block_content)) {
                            continue; // Пропускаем пустые блоки
                        }
                        ?>
                        
                        <div class="sidebar-card custom-sidebar-block sidebar-block-<?php echo esc_attr($block_type); ?>">
                            <?php if ($block_type === 'card' || $block_type === 'info') : ?>
                                <?php if ($block_title) : ?>
                                    <div class="card-header" style="<?php echo $block_type === 'info' ? 'background: ' . $scheme['light'] . ';' : 'background: ' . $scheme['gradient'] . ';'; ?>">
                                        <?php if ($block_type === 'info' && $block_icon) : ?>
                                            <div class="block-icon">
                                                <?php if (strpos($block_icon, '<svg') !== false) : ?>
                                                    <?php echo $block_icon; ?>
                                                <?php else : ?>
                                                    <span class="<?php echo esc_attr($block_icon); ?>"></span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        <h3><?php echo esc_html($block_title); ?></h3>
                                    </div>
                                <?php endif; ?>
                                <div class="card-body">
                                    <?php echo wp_kses_post($block_content); ?>
                                </div>
                            <?php else : ?>
                                <!-- Простой блок без рамки -->
                                <?php if ($block_title) : ?>
                                    <h4 class="block-title"><?php echo esc_html($block_title); ?></h4>
                                <?php endif; ?>
                                <div class="block-content">
                                    <?php echo wp_kses_post($block_content); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </aside>
        </div>
    </div>
    
    <?php
    // ============================================
    // CTA БЛОК (ПРИЗЫВ К ДЕЙСТВИЮ)
    // ============================================
    // Получаем настраиваемые тексты CTA блока внизу страницы
    $cta_title = get_post_meta(get_the_ID(), '_course_cta_title', true);
    $cta_text = get_post_meta(get_the_ID(), '_course_cta_text', true);
    $cta_button_text = get_post_meta(get_the_ID(), '_course_cta_button_text', true);
    
    // Используем значения по умолчанию, если поля пустые
    if (empty($cta_title)) {
        $cta_title = __('Готовы начать обучение?', 'course-plugin');
    }
    if (empty($cta_text)) {
        $cta_text = __('Запишитесь на курс и начните свой путь к новым знаниям!', 'course-plugin');
    }
    if (empty($cta_button_text)) {
        $cta_button_text = __('Записаться на курс', 'course-plugin');
    }
    ?>
    <!-- ============================================
         CTA СЕКЦИЯ (ПРИЗЫВ К ДЕЙСТВИЮ)
         ============================================
         Финальный блок внизу страницы с призывом записаться на курс
         -->
    <?php if ($show_cta) : ?>
    <section class="premium-course-cta" style="background: <?php echo $scheme['gradient']; ?>">
        <div class="cta-container">
            <div class="cta-content">
                <h2 class="cta-title"><?php echo esc_html($cta_title); ?></h2>
                <p class="cta-text"><?php echo esc_html($cta_text); ?></p>
            </div>
            <?php if ($course_seminary_new_url) : ?>
                <a href="<?php echo esc_url($course_seminary_new_url); ?>" target="_blank" rel="noopener" class="cta-btn">
                    <?php echo esc_html($cta_button_text); ?>
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M4 10H16M16 10L11 5M16 10L11 15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </a>
            <?php endif; ?>
        </div>
        <div class="cta-decoration">
            <svg viewBox="0 0 200 200" fill="none"><circle cx="100" cy="100" r="80" stroke="rgba(255,255,255,0.1)" stroke-width="2"/><circle cx="100" cy="100" r="60" stroke="rgba(255,255,255,0.1)" stroke-width="2"/><circle cx="100" cy="100" r="40" stroke="rgba(255,255,255,0.1)" stroke-width="2"/></svg>
        </div>
    </section>
    <?php endif; ?>
</div>

<?php
// Завершаем цикл WordPress
endwhile;

// Подключаем подвал сайта
get_footer();
?>
