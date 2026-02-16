<?php
/**
 * Шаблон архива курсов - Премиальный дизайн
 * 
 * @copyright Copyright (c) 2024 Кузьменко Валентин (Valentink2410)
 * @author Кузьменко Валентин (Valentink2410)
 */

get_header(); 

global $wp_query;

// Редирект на страницу 1, если результатов 0 и открыта страница > 1
$paged_check = get_query_var('paged') ? get_query_var('paged') : 1;
if ($paged_check > 1 && $wp_query->found_posts == 0) {
    $base = get_post_type_archive_link('course');
    $args = $_GET;
    unset($args['paged']);
    if (!empty($args)) {
        $redirect_url = add_query_arg($args, $base);
    } else {
        $redirect_url = $base;
    }
    wp_safe_redirect(esc_url_raw($redirect_url), 302);
    exit;
}

// Проверяем, что это действительно архив курсов
$is_course_archive = false;

if (is_post_type_archive('course')) {
    $is_course_archive = true;
} elseif (isset($_SERVER['REQUEST_URI'])) {
    $request_uri = $_SERVER['REQUEST_URI'];
    $path = parse_url($request_uri, PHP_URL_PATH);
    if (preg_match('#/(course|courses)(/page/\d+)?/?$#', $path)) {
        $is_course_archive = true;
    }
} elseif (get_query_var('post_type') === 'course' && !is_singular()) {
    $is_course_archive = true;
} elseif ($wp_query->get('post_type') === 'course') {
    $is_course_archive = true;
}

// Получаем параметры пагинации
$paged = get_query_var('paged') ? get_query_var('paged') : 1;
$posts_per_page = isset($wp_query->query_vars['posts_per_page']) ? $wp_query->query_vars['posts_per_page'] : get_option('posts_per_page', 15);
$found_posts = $wp_query->found_posts;
$showing_from = ($paged - 1) * $posts_per_page + 1;
$showing_to = min($paged * $posts_per_page, $found_posts);
?>

<div class="premium-archive-wrapper">
    <!-- Заголовок страницы -->
    <header class="premium-archive-header">
        <div class="premium-header-content">
            <h1 class="premium-archive-title">
                <span class="title-accent"><?php echo esc_html(get_option('course_archive_title_main', __('Курсы', 'course-plugin'))); ?></span>
                <span class="title-sub"><?php echo esc_html(get_option('course_archive_title_sub', __('для вашего развития', 'course-plugin'))); ?></span>
            </h1>
            <p class="premium-archive-subtitle"><?php echo esc_html(get_option('course_archive_subtitle', __('Выберите курс, который поможет вам достичь новых вершин в карьере', 'course-plugin'))); ?></p>
            
            <!-- Статистика -->
            <div class="premium-stats">
                <div class="stat-item">
                    <span class="stat-number"><?php echo $found_posts; ?></span>
                    <span class="stat-label"><?php _e('курсов', 'course-plugin'); ?></span>
                </div>
                <div class="stat-divider"></div>
                <div class="stat-item">
                    <span class="stat-number"><?php 
                        $teachers = get_terms(array('taxonomy' => 'course_teacher', 'hide_empty' => true));
                        echo is_wp_error($teachers) ? 0 : count($teachers);
                    ?></span>
                    <span class="stat-label"><?php _e('преподавателей', 'course-plugin'); ?></span>
                </div>
                <div class="stat-divider"></div>
                <div class="stat-item">
                    <span class="stat-number"><?php 
                        $specializations = get_terms(array('taxonomy' => 'course_specialization', 'hide_empty' => true));
                        echo is_wp_error($specializations) ? 0 : count($specializations);
                    ?></span>
                    <span class="stat-label"><?php _e('направлений', 'course-plugin'); ?></span>
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

    <div class="premium-archive-container">
        <!-- Боковая панель с фильтрами -->
        <aside class="premium-filters-sidebar" id="filters-sidebar">
            <div class="filters-header">
                <h3 class="filters-title">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M3 5H17M6 10H14M9 15H11" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    <?php _e('Фильтры', 'course-plugin'); ?>
                </h3>
                <button class="filters-close-btn" id="filters-close">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>
            
            <form method="get" class="premium-filters-form" id="course-filters-form">
                <!-- Поиск -->
                <div class="filter-search-box">
                    <svg class="search-icon" width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="2"/>
                        <path d="M13 13L16 16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    <input type="text" class="filter-search-input" id="filter-search-input" placeholder="<?php _e('Поиск курса...', 'course-plugin'); ?>" name="search" value="<?php echo isset($_GET['search']) ? esc_attr($_GET['search']) : ''; ?>">
                </div>
                
                <!-- Преподаватель -->
                <div class="filter-group filter-group-select">
                    <label class="filter-select-label">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="8" cy="5" r="3" stroke="currentColor" stroke-width="1.5"/>
                            <path d="M3 14C3 11.2386 5.23858 9 8 9C10.7614 9 13 11.2386 13 14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        </svg>
                        <?php _e('Преподаватель', 'course-plugin'); ?>
                    </label>
                    <select name="teacher" class="filter-select">
                        <?php
                        $teachers = get_terms(array(
                            'taxonomy' => 'course_teacher',
                            'hide_empty' => false,
                        ));
                        $selected_teacher = isset($_GET['teacher']) ? $_GET['teacher'] : '';
                        ?>
                        <option value="" <?php selected($selected_teacher, ''); ?>><?php _e('Все преподаватели', 'course-plugin'); ?></option>
                        <?php
                        if ($teachers && !is_wp_error($teachers)) {
                            foreach ($teachers as $teacher) {
                                ?>
                                <option value="<?php echo esc_attr($teacher->slug); ?>" <?php selected($selected_teacher, $teacher->slug); ?>>
                                    <?php echo esc_html($teacher->name); ?> (<?php echo $teacher->count; ?>)
                                </option>
                                <?php
                            }
                        }
                        ?>
                    </select>
                </div>
                
                <!-- Уровень -->
                <div class="filter-group">
                    <button type="button" class="filter-group-toggle active" data-target="level-options">
                        <span class="filter-group-title">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="2" y="10" width="3" height="4" rx="1" stroke="currentColor" stroke-width="1.5"/>
                                <rect x="6.5" y="6" width="3" height="8" rx="1" stroke="currentColor" stroke-width="1.5"/>
                                <rect x="11" y="2" width="3" height="12" rx="1" stroke="currentColor" stroke-width="1.5"/>
                            </svg>
                            <?php _e('Уровень сложности', 'course-plugin'); ?>
                        </span>
                        <svg class="toggle-arrow" width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                    <div class="filter-options" id="level-options">
                        <?php
                        $levels = get_terms(array(
                            'taxonomy' => 'course_level',
                            'hide_empty' => false,
                        ));
                        
                        if ($levels && !is_wp_error($levels)) {
                            $selected_levels = isset($_GET['level']) ? (array)$_GET['level'] : array();
                            foreach ($levels as $level) {
                                $checked = in_array($level->slug, $selected_levels) ? 'checked' : '';
                                ?>
                                <label class="filter-option">
                                    <input type="checkbox" name="level[]" value="<?php echo esc_attr($level->slug); ?>" <?php echo $checked; ?>>
                                    <span class="option-checkbox"></span>
                                    <span class="option-text"><?php echo esc_html($level->name); ?></span>
                                    <span class="option-count"><?php echo $level->count; ?></span>
                                </label>
                                <?php
                            }
                        }
                        ?>
                    </div>
                </div>
                
                <!-- Программа -->
                <div class="filter-group filter-group-select">
                    <label class="filter-select-label">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect x="2" y="2" width="12" height="12" rx="2" stroke="currentColor" stroke-width="1.5"/>
                            <path d="M5 6H11M5 8H11M5 10H9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        </svg>
                        <?php _e('Программа', 'course-plugin'); ?>
                    </label>
                    <select name="specialization" class="filter-select">
                        <?php
                        $specializations = get_terms(array(
                            'taxonomy' => 'course_specialization',
                            'hide_empty' => false,
                            'orderby' => 'name',
                        ));
                        $selected_spec = isset($_GET['specialization']) ? $_GET['specialization'] : '';
                        ?>
                        <option value="" <?php selected($selected_spec, ''); ?>><?php _e('Все программы', 'course-plugin'); ?></option>
                        <?php
                        if ($specializations && !is_wp_error($specializations)) {
                            foreach ($specializations as $spec) {
                                ?>
                                <option value="<?php echo esc_attr($spec->slug); ?>" <?php selected($selected_spec, $spec->slug); ?>>
                                    <?php echo esc_html($spec->name); ?> (<?php echo $spec->count; ?>)
                                </option>
                                <?php
                            }
                        }
                        ?>
                    </select>
                </div>
                
                <!-- Тема -->
                <div class="filter-group">
                    <button type="button" class="filter-group-toggle" data-target="topic-options">
                        <span class="filter-group-title">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M2 4H14M2 8H10M2 12H12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                            <?php _e('Тема', 'course-plugin'); ?>
                        </span>
                        <svg class="toggle-arrow" width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                    <div class="filter-options collapsed" id="topic-options">
                        <?php
                        $topics = get_terms(array(
                            'taxonomy' => 'course_topic',
                            'hide_empty' => false,
                        ));
                        
                        if ($topics && !is_wp_error($topics)) {
                            $selected_topics = isset($_GET['topic']) ? (array)$_GET['topic'] : array();
                            foreach ($topics as $topic) {
                                $checked = in_array($topic->slug, $selected_topics) ? 'checked' : '';
                                ?>
                                <label class="filter-option">
                                    <input type="checkbox" name="topic[]" value="<?php echo esc_attr($topic->slug); ?>" <?php echo $checked; ?>>
                                    <span class="option-checkbox"></span>
                                    <span class="option-text"><?php echo esc_html($topic->name); ?></span>
                                    <span class="option-count"><?php echo $topic->count; ?></span>
                                </label>
                                <?php
                            }
                        }
                        ?>
                    </div>
                </div>
                
                <!-- Место прохождения -->
                <div class="filter-group">
                    <button type="button" class="filter-group-toggle active" data-target="location-options">
                        <span class="filter-group-title">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M8 2C5.79086 2 4 3.79086 4 6C4 8.5 8 12 8 12C8 12 12 8.5 12 6C12 3.79086 10.2091 2 8 2Z" stroke="currentColor" stroke-width="1.5"/>
                                <circle cx="8" cy="6" r="1.5" fill="currentColor"/>
                            </svg>
                            <?php _e('Место прохождения', 'course-plugin'); ?>
                        </span>
                        <svg class="toggle-arrow" width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                    <div class="filter-options" id="location-options">
                        <?php
                        $locations = array(
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
                        
                        $selected_locations = isset($_GET['location']) ? (array)$_GET['location'] : array();
                        foreach ($locations as $location_slug => $location_name) {
                            $checked = in_array($location_slug, $selected_locations) ? 'checked' : '';
                            ?>
                            <label class="filter-option">
                                <input type="checkbox" name="location[]" value="<?php echo esc_attr($location_slug); ?>" <?php echo $checked; ?>>
                                <span class="option-checkbox"></span>
                                <span class="option-text"><?php echo esc_html($location_name); ?></span>
                            </label>
                            <?php
                        }
                        ?>
                    </div>
                </div>
                
                <!-- Дата начала курса -->
                <div class="filter-group">
                    <button type="button" class="filter-group-toggle active" data-target="date-options">
                        <span class="filter-group-title">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="2" y="3" width="12" height="11" rx="2" stroke="currentColor" stroke-width="1.5"/>
                                <path d="M5 1V4M11 1V4M2 7H14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                            <?php _e('Дата начала курса', 'course-plugin'); ?>
                        </span>
                        <svg class="toggle-arrow" width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                    <div class="filter-options" id="date-options">
                        <div style="padding: 12px 16px;">
                            <label for="date_from" style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--premium-gray-700);">
                                <?php _e('С', 'course-plugin'); ?>
                            </label>
                            <input type="date" id="date_from" name="date_from" value="<?php echo isset($_GET['date_from']) ? esc_attr($_GET['date_from']) : ''; ?>" class="regular-text" style="width: 100%; padding: 8px; border: 1px solid var(--premium-gray-300); border-radius: 6px; margin-bottom: 12px;" />
                            
                            <label for="date_to" style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--premium-gray-700);">
                                <?php _e('По', 'course-plugin'); ?>
                            </label>
                            <input type="date" id="date_to" name="date_to" value="<?php echo isset($_GET['date_to']) ? esc_attr($_GET['date_to']) : ''; ?>" class="regular-text" style="width: 100%; padding: 8px; border: 1px solid var(--premium-gray-300); border-radius: 6px;" />
                        </div>
                    </div>
                </div>
                
                <!-- Стоимость -->
                <div class="filter-group">
                    <button type="button" class="filter-group-toggle active" data-target="price-options">
                        <span class="filter-group-title">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.5"/>
                                <path d="M8 4V8L10 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                            <?php _e('Стоимость', 'course-plugin'); ?>
                        </span>
                        <svg class="toggle-arrow" width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                    <div class="filter-options" id="price-options">
                        <?php
                        $selected_price = isset($_GET['price']) ? $_GET['price'] : '';
                        ?>
                        <label class="filter-option">
                            <input type="radio" name="price" value="free" <?php checked($selected_price, 'free'); ?>>
                            <span class="option-checkbox"></span>
                            <span class="option-text"><?php _e('Бесплатные', 'course-plugin'); ?></span>
                        </label>
                        <label class="filter-option">
                            <input type="radio" name="price" value="paid" <?php checked($selected_price, 'paid'); ?>>
                            <span class="option-checkbox"></span>
                            <span class="option-text"><?php _e('Платные', 'course-plugin'); ?></span>
                        </label>
                        <label class="filter-option">
                            <input type="radio" name="price" value="" <?php checked($selected_price, ''); ?>>
                            <span class="option-checkbox"></span>
                            <span class="option-text"><?php _e('Все', 'course-plugin'); ?></span>
                        </label>
                    </div>
                </div>
                
                <!-- Кнопки -->
                <div class="filter-actions">
                    <button type="submit" class="filter-apply-btn">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M13.5 4.5L6 12L2.5 8.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <?php _e('Применить фильтры', 'course-plugin'); ?>
                    </button>
                    <a href="<?php echo get_post_type_archive_link('course'); ?>" class="filter-reset-btn">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M2 8C2 4.68629 4.68629 2 8 2C10.2214 2 12.1575 3.21379 13.2 5M14 8C14 11.3137 11.3137 14 8 14C5.77856 14 3.84251 12.7862 2.8 11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            <path d="M10 5H14V1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M6 11H2V15" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <?php _e('Сбросить', 'course-plugin'); ?>
                    </a>
                </div>
            </form>
        </aside>
        
        <!-- Основная область -->
        <main class="premium-main-content">
            <!-- Панель управления -->
            <div class="premium-toolbar">
                <div class="toolbar-left">
                    <button class="mobile-filter-btn" id="mobile-filter-btn">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M3 5H17M6 10H14M9 15H11" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                        <?php _e('Фильтры', 'course-plugin'); ?>
                    </button>
                    <span class="results-count">
                        <?php printf(__('Найдено: <strong>%d</strong> курсов', 'course-plugin'), $found_posts); ?>
                    </span>
                </div>
                
                <div class="toolbar-right">
                    <!-- Переключатель вида -->
                    <div class="view-switcher">
                        <button class="view-btn active" data-view="grid" title="<?php _e('Сетка', 'course-plugin'); ?>">
                            <svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="1" y="1" width="6" height="6" rx="1.5" stroke="currentColor" stroke-width="1.5"/>
                                <rect x="11" y="1" width="6" height="6" rx="1.5" stroke="currentColor" stroke-width="1.5"/>
                                <rect x="1" y="11" width="6" height="6" rx="1.5" stroke="currentColor" stroke-width="1.5"/>
                                <rect x="11" y="11" width="6" height="6" rx="1.5" stroke="currentColor" stroke-width="1.5"/>
                            </svg>
                        </button>
                        <button class="view-btn" data-view="list" title="<?php _e('Список', 'course-plugin'); ?>">
                            <svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="1" y="2" width="16" height="3" rx="1" stroke="currentColor" stroke-width="1.5"/>
                                <rect x="1" y="7.5" width="16" height="3" rx="1" stroke="currentColor" stroke-width="1.5"/>
                                <rect x="1" y="13" width="16" height="3" rx="1" stroke="currentColor" stroke-width="1.5"/>
                            </svg>
                        </button>
                    </div>
                    
                    <!-- Сортировка -->
                    <?php $current_sort = !empty($_GET['sort']) ? sanitize_text_field($_GET['sort']) : 'date_start_asc'; ?>
                    <div class="sort-dropdown">
                        <select id="course-sort-select" name="sort" class="premium-sort-select">
                            <option value="default" <?php selected($current_sort, 'default'); ?>><?php _e('По умолчанию', 'course-plugin'); ?></option>
                            <option value="date_start_asc" <?php selected($current_sort, 'date_start_asc'); ?>><?php _e('Дата: сначала ближайшие', 'course-plugin'); ?></option>
                            <option value="price_asc" <?php selected($current_sort, 'price_asc'); ?>><?php _e('Цена: по возрастанию', 'course-plugin'); ?></option>
                            <option value="level_asc" <?php selected($current_sort, 'level_asc'); ?>><?php _e('Уровень: сначала проще', 'course-plugin'); ?></option>
                            <option value="title_asc" <?php selected($current_sort, 'title_asc'); ?>><?php _e('По названию А-Я', 'course-plugin'); ?></option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Активные фильтры -->
            <?php
            $has_active_filters = !empty($_GET['teacher']) || !empty($_GET['level']) || !empty($_GET['specialization']) || !empty($_GET['topic']) || !empty($_GET['search']) || !empty($_GET['location']) || !empty($_GET['date_from']) || !empty($_GET['date_to']) || !empty($_GET['price']);
            if ($has_active_filters) :
            ?>
            <div class="active-filters">
                <span class="active-filters-label"><?php _e('Активные фильтры:', 'course-plugin'); ?></span>
                <div class="active-filters-list">
                    <?php if (!empty($_GET['search'])) : ?>
                        <span class="active-filter-tag">
                            <?php echo esc_html($_GET['search']); ?>
                            <button type="button" class="remove-filter" data-filter="search">&times;</button>
                        </span>
                    <?php endif; ?>
                    
                    <?php if (!empty($_GET['teacher'])) : 
                        $teacher_term = get_term_by('slug', $_GET['teacher'], 'course_teacher');
                        if ($teacher_term) :
                    ?>
                        <span class="active-filter-tag">
                            <?php echo esc_html($teacher_term->name); ?>
                            <button type="button" class="remove-filter" data-filter="teacher">&times;</button>
                        </span>
                    <?php endif; endif; ?>
                    
                    <?php if (!empty($_GET['level'])) : 
                        foreach ((array)$_GET['level'] as $level_slug) :
                            $level_term = get_term_by('slug', $level_slug, 'course_level');
                            if ($level_term) :
                    ?>
                        <span class="active-filter-tag">
                            <?php echo esc_html($level_term->name); ?>
                            <button type="button" class="remove-filter" data-filter="level" data-value="<?php echo esc_attr($level_slug); ?>">&times;</button>
                        </span>
                    <?php endif; endforeach; endif; ?>
                    
                    <?php
                    // Активные фильтры по месту прохождения
                    if (!empty($_GET['location'])) {
                        $locations = is_array($_GET['location']) ? $_GET['location'] : array($_GET['location']);
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
                        foreach ($locations as $location_slug) {
                            if (isset($location_names[$location_slug])) {
                                ?>
                                <span class="active-filter-tag">
                                    <?php echo esc_html($location_names[$location_slug]); ?>
                                    <button type="button" class="remove-filter" data-filter="location" data-value="<?php echo esc_attr($location_slug); ?>">&times;</button>
                                </span>
                                <?php
                            }
                        }
                    }
                    
                    // Активные фильтры по дате
                    if (!empty($_GET['date_from']) || !empty($_GET['date_to'])) {
                        $date_text = '';
                        if (!empty($_GET['date_from']) && !empty($_GET['date_to'])) {
                            $date_text = sprintf(__('С %s по %s', 'course-plugin'), esc_html($_GET['date_from']), esc_html($_GET['date_to']));
                        } elseif (!empty($_GET['date_from'])) {
                            $date_text = sprintf(__('С %s', 'course-plugin'), esc_html($_GET['date_from']));
                        } elseif (!empty($_GET['date_to'])) {
                            $date_text = sprintf(__('По %s', 'course-plugin'), esc_html($_GET['date_to']));
                        }
                        if ($date_text) {
                            ?>
                            <span class="active-filter-tag">
                                <?php echo $date_text; ?>
                                <button type="button" class="remove-filter" data-filter="date">&times;</button>
                            </span>
                            <?php
                        }
                    }
                    
                    // Активный фильтр по стоимости
                    if (!empty($_GET['price'])) {
                        $price_text = '';
                        if ($_GET['price'] === 'free') {
                            $price_text = __('Бесплатные', 'course-plugin');
                        } elseif ($_GET['price'] === 'paid') {
                            $price_text = __('Платные', 'course-plugin');
                        }
                        if ($price_text) {
                            ?>
                            <span class="active-filter-tag">
                                <?php echo esc_html($price_text); ?>
                                <button type="button" class="remove-filter" data-filter="price">&times;</button>
                            </span>
                            <?php
                        }
                    }
                    ?>
                </div>
                <a href="<?php echo get_post_type_archive_link('course'); ?>" class="clear-all-filters"><?php _e('Очистить все', 'course-plugin'); ?></a>
            </div>
            <?php endif; ?>
            
            <!-- Сетка курсов -->
            <?php if (have_posts()) : ?>
                <div class="premium-courses-grid" id="courses-container" data-view="grid">
                    <?php while (have_posts()) : the_post(); 
                        $price = get_post_meta(get_the_ID(), '_course_price', true);
                        $old_price = get_post_meta(get_the_ID(), '_course_old_price', true);
                        $duration = get_post_meta(get_the_ID(), '_course_duration', true);
                        $course_tag = get_post_meta(get_the_ID(), '_course_tag', true);
                        $course_additional_text = get_post_meta(get_the_ID(), '_course_additional_text', true);
                        $course_start_date = get_post_meta(get_the_ID(), '_course_start_date', true);
                        $course_end_date = get_post_meta(get_the_ID(), '_course_end_date', true);
                        $course_location = get_post_meta(get_the_ID(), '_course_location', true);
                        $show_card_icon = get_post_meta(get_the_ID(), '_course_show_card_icon', true);
                        $card_icon_type = get_post_meta(get_the_ID(), '_course_card_icon_type', true);
                        
                        // По умолчанию показываем иконку
                        if ($show_card_icon === '') {
                            $show_card_icon = '1';
                        }
                        
                        // Получаем уровень
                        $levels = get_the_terms(get_the_ID(), 'course_level');
                        $level_name = ($levels && !is_wp_error($levels)) ? $levels[0]->name : '';
                        
                        if (!$course_tag && $level_name) {
                            $course_tag = $level_name;
                        }
                        
                        // Получаем преподавателя
                        $teachers = get_the_terms(get_the_ID(), 'course_teacher');
                        $teacher_name = ($teachers && !is_wp_error($teachers)) ? $teachers[0]->name : '';
                        
                        // Определяем цветовую схему карточки в зависимости от региона
                        $location_colors = array(
                            'online' => array('gradient' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)', 'accent' => '#667eea', 'name' => __('Онлайн', 'course-plugin')),
                            'zoom' => array('gradient' => 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)', 'accent' => '#4facfe', 'name' => __('Зум', 'course-plugin')),
                            'moscow' => array('gradient' => 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)', 'accent' => '#f5576c', 'name' => __('Москва', 'course-plugin')),
                            'prokhladny' => array('gradient' => 'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)', 'accent' => '#43e97b', 'name' => __('Прохладный', 'course-plugin')),
                            'nizhny-novgorod' => array('gradient' => 'linear-gradient(135deg, #fa709a 0%, #fee140 100%)', 'accent' => '#fa709a', 'name' => __('Нижний Новгород', 'course-plugin')),
                            'chelyabinsk' => array('gradient' => 'linear-gradient(135deg, #a8edea 0%, #fed6e3 100%)', 'accent' => '#a8edea', 'name' => __('Челябинск', 'course-plugin')),
                            'norilsk' => array('gradient' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)', 'accent' => '#667eea', 'name' => __('Норильск', 'course-plugin')),
                            'izhevsk' => array('gradient' => 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)', 'accent' => '#4facfe', 'name' => __('Ижевск', 'course-plugin')),
                            'yug' => array('gradient' => 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)', 'accent' => '#f5576c', 'name' => __('Юг', 'course-plugin')),
                            'novokuznetsk' => array('gradient' => 'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)', 'accent' => '#43e97b', 'name' => __('Новокузнецк', 'course-plugin')),
                        );
                        
                        // Если есть регион, используем его цвет, иначе используем дефолтную схему
                        if ($course_location && isset($location_colors[$course_location])) {
                            $scheme = $location_colors[$course_location];
                            $location_name = $location_colors[$course_location]['name'];
                        } else {
                            // Дефолтная цветовая схема
                            $color_schemes = array(
                                array('gradient' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)', 'accent' => '#667eea'),
                                array('gradient' => 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)', 'accent' => '#f5576c'),
                                array('gradient' => 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)', 'accent' => '#4facfe'),
                                array('gradient' => 'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)', 'accent' => '#43e97b'),
                                array('gradient' => 'linear-gradient(135deg, #fa709a 0%, #fee140 100%)', 'accent' => '#fa709a'),
                                array('gradient' => 'linear-gradient(135deg, #a8edea 0%, #fed6e3 100%)', 'accent' => '#a8edea'),
                            );
                            $scheme_index = get_the_ID() % count($color_schemes);
                            $scheme = $color_schemes[$scheme_index];
                            $scheme['name'] = '';
                            $location_name = '';
                        }
                        
                        // Форматируем дату и считаем дни до начала
                        // Для онлайн-курсов дату не показываем
                        $date_text = '';
                        $days_until_start = null;
                        $date_class = '';
                        $show_date_for_course = $course_start_date && $course_location !== 'online';
                        
                        if ($show_date_for_course) {
                            $start_timestamp = strtotime($course_start_date);
                            $current_timestamp = current_time('timestamp');
                            $days_diff = floor(($start_timestamp - $current_timestamp) / (60 * 60 * 24));
                            
                            // Определяем класс в зависимости от количества дней
                            if ($days_diff < 0) {
                                // Курс уже начался или прошел
                                $date_class = 'date-past';
                                $days_until_start = abs($days_diff);
                            } elseif ($days_diff == 0) {
                                // Курс начинается сегодня
                                $date_class = 'date-today';
                                $days_until_start = 0;
                            } elseif ($days_diff <= 7) {
                                // Курс начинается в течение недели
                                $date_class = 'date-soon';
                                $days_until_start = $days_diff;
                            } elseif ($days_diff <= 30) {
                                // Курс начинается в течение месяца
                                $date_class = 'date-coming';
                                $days_until_start = $days_diff;
                            } else {
                                // Курс начинается более чем через месяц
                                $date_class = 'date-future';
                                $days_until_start = $days_diff;
                            }
                            
                            if ($course_end_date) {
                                $end_timestamp = strtotime($course_end_date);
                                // Формат: "Дата: 10-13 мая 2026"
                                $start_day = date('j', $start_timestamp);
                                $end_day = date('j', $end_timestamp);
                                // Проверяем, в одном ли месяце даты
                                if (date('m', $start_timestamp) === date('m', $end_timestamp)) {
                                    $month = date_i18n('F', $start_timestamp);
                                    $year = date('Y', $start_timestamp);
                                    $date_text = sprintf(__('Дата: %s-%s %s %s', 'course-plugin'), $start_day, $end_day, $month, $year);
                                } else {
                                    // Разные месяцы
                                    $start_month = date_i18n('F', $start_timestamp);
                                    $end_month = date_i18n('F', $end_timestamp);
                                    $year = date('Y', $start_timestamp);
                                    $date_text = sprintf(__('Дата: %s %s - %s %s %s', 'course-plugin'), $start_day, $start_month, $end_day, $end_month, $year);
                                }
                            } else {
                                // Только дата начала
                                $day = date('j', $start_timestamp);
                                $month = date_i18n('F', $start_timestamp);
                                $year = date('Y', $start_timestamp);
                                $date_text = sprintf(__('Дата: %s %s %s', 'course-plugin'), $day, $month, $year);
                            }
                        }
                    ?>
                        <article class="premium-course-card" data-id="<?php the_ID(); ?>">
                            <a href="<?php the_permalink(); ?>" class="card-link">
                                <!-- Верхняя часть карточки с градиентом -->
                                <div class="card-header" style="background: <?php echo esc_attr($scheme['gradient']); ?>">
                                    <?php if ($course_tag) : ?>
                                        <span class="card-badge"><?php echo esc_html($course_tag); ?></span>
                                    <?php endif; ?>
                                    
                                    <?php if ($old_price && $price && $old_price > $price) : 
                                        $discount = round((($old_price - $price) / $old_price) * 100);
                                    ?>
                                        <span class="card-discount">-<?php echo $discount; ?>%</span>
                                    <?php endif; ?>
                                    
                                    <!-- Название региона в правом нижнем углу -->
                                    <?php if ($location_name) : ?>
                                        <span class="card-location-label"><?php echo esc_html($location_name); ?></span>
                                    <?php endif; ?>
                                    
                                    <!-- Декоративный элемент -->
                                    <div class="card-decoration">
                                        <svg width="120" height="120" viewBox="0 0 120 120" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <circle cx="60" cy="60" r="50" stroke="rgba(255,255,255,0.2)" stroke-width="2"/>
                                            <circle cx="60" cy="60" r="35" stroke="rgba(255,255,255,0.15)" stroke-width="2"/>
                                            <circle cx="60" cy="60" r="20" fill="rgba(255,255,255,0.1)"/>
                                        </svg>
                                    </div>
                                    
                                    <!-- Иконка категории -->
                                    <?php if ($show_card_icon === '1') : ?>
                                        <div class="card-icon">
                                            <?php
                                            $icon_options = Course_Meta_Boxes::get_card_icon_options();
                                            $icon_keys = array_keys($icon_options);
                                            $icon_count = count($icon_keys);
                                            if ($card_icon_type && $card_icon_type !== 'default' && isset($icon_options[$card_icon_type])) {
                                                echo $icon_options[$card_icon_type]['svg'];
                                            } else {
                                                $icon_index = get_the_ID() % $icon_count;
                                                echo $icon_options[$icon_keys[$icon_index]]['svg'];
                                            }
                                            ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Контент карточки -->
                                <div class="card-content">
                                    <h2 class="card-title"><?php the_title(); ?></h2>
                                    
                                    <?php if ($teacher_name) : ?>
                                        <p class="card-teacher">
                                            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <circle cx="7" cy="4" r="2.5" stroke="currentColor" stroke-width="1.5"/>
                                                <path d="M2.5 12C2.5 9.51472 4.51472 7.5 7 7.5C9.48528 7.5 11.5 9.51472 11.5 12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                            </svg>
                                            <?php echo esc_html($teacher_name); ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <div class="card-meta">
                                        <?php if ($duration) : ?>
                                            <span class="meta-item">
                                                <svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                    <circle cx="7" cy="7" r="6" stroke="currentColor" stroke-width="1.5"/>
                                                    <path d="M7 4V7L9 8.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                </svg>
                                                <?php echo esc_html($duration); ?>
                                            </span>
                                        <?php endif; ?>
                                        
                                        <?php if ($level_name) : ?>
                                            <span class="meta-item">
                                                <svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                    <rect x="1" y="8" width="3" height="4" rx="0.5" stroke="currentColor" stroke-width="1.2"/>
                                                    <rect x="5.5" y="5" width="3" height="7" rx="0.5" stroke="currentColor" stroke-width="1.2"/>
                                                    <rect x="10" y="2" width="3" height="10" rx="0.5" stroke="currentColor" stroke-width="1.2"/>
                                                </svg>
                                                <?php echo esc_html($level_name); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($date_text) : ?>
                                        <div class="card-date-wrapper <?php echo esc_attr($date_class); ?>">
                                            <p class="card-date"><?php echo esc_html($date_text); ?></p>
                                            <?php if ($days_until_start !== null) : ?>
                                                <?php if ($days_until_start == 0) : ?>
                                                    <span class="days-badge days-today"><?php _e('Начинается сегодня', 'course-plugin'); ?></span>
                                                <?php elseif ($days_until_start < 0) : ?>
                                                    <span class="days-badge days-past"><?php printf(__('Начался %d дн. назад', 'course-plugin'), abs($days_until_start)); ?></span>
                                                <?php elseif ($days_until_start <= 7) : ?>
                                                    <span class="days-badge days-soon"><?php printf(_n('Начинается через %d день', 'Начинается через %d дня', $days_until_start, 'course-plugin'), $days_until_start); ?></span>
                                                <?php elseif ($days_until_start <= 30) : ?>
                                                    <span class="days-badge days-coming"><?php printf(_n('Начинается через %d день', 'Начинается через %d дней', $days_until_start, 'course-plugin'), $days_until_start); ?></span>
                                                <?php else : ?>
                                                    <span class="days-badge days-future"><?php printf(_n('Начинается через %d день', 'Начинается через %d дней', $days_until_start, 'course-plugin'), $days_until_start); ?></span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php elseif (!$show_date_for_course) : ?>
                                        <p class="card-date card-date-now"><?php _e('Можно приступать к изучению прямо сейчас!', 'course-plugin'); ?></p>
                                    <?php elseif ($course_additional_text) : ?>
                                        <p class="card-additional"><?php echo esc_html($course_additional_text); ?></p>
                                    <?php endif; ?>
                                    
                                    <div class="card-footer">
                                        <div class="card-price">
                                            <?php if ($price) : ?>
                                                <span class="price-current"><?php echo number_format($price, 0, ',', ' '); ?> ₽</span>
                                                <?php if ($old_price && $old_price > $price) : ?>
                                                    <span class="price-old"><?php echo number_format($old_price, 0, ',', ' '); ?> ₽</span>
                                                <?php endif; ?>
                                            <?php else : ?>
                                                <span class="price-free"><?php _e('Бесплатно', 'course-plugin'); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <span class="card-cta" style="color: <?php echo $scheme['accent']; ?>">
                                            <?php _e('Подробнее', 'course-plugin'); ?>
                                            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M1 7H13M13 7L8 2M13 7L8 12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </span>
                                    </div>
                                </div>
                            </a>
                        </article>
                    <?php endwhile; ?>
                </div>
                
                <!-- Пагинация -->
                <div class="premium-pagination">
                    <?php
                    $big = 999999999;
                    echo paginate_links(array(
                        'base' => str_replace($big, '%#%', esc_url(get_pagenum_link($big))),
                        'format' => '?paged=%#%',
                        'current' => max(1, $paged),
                        'total' => $wp_query->max_num_pages,
                        'prev_text' => '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M10 12L6 8L10 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
                        'next_text' => '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 12L10 8L6 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
                    ));
                    ?>
                </div>
                
            <?php else : ?>
                <div class="no-results">
                    <div class="no-results-icon">
                        <svg width="80" height="80" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="40" cy="40" r="35" stroke="#e0e0e0" stroke-width="3"/>
                            <path d="M30 35C30 33.3431 31.3431 32 33 32C34.6569 32 36 33.3431 36 35" stroke="#e0e0e0" stroke-width="3" stroke-linecap="round"/>
                            <path d="M44 35C44 33.3431 45.3431 32 47 32C48.6569 32 50 33.3431 50 35" stroke="#e0e0e0" stroke-width="3" stroke-linecap="round"/>
                            <path d="M28 52C28 52 33 46 40 46C47 46 52 52 52 52" stroke="#e0e0e0" stroke-width="3" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <h2 class="no-results-title"><?php _e('Курсы не найдены', 'course-plugin'); ?></h2>
                    <p class="no-results-text"><?php _e('Попробуйте изменить параметры фильтрации или сбросьте фильтры', 'course-plugin'); ?></p>
                    <a href="<?php echo get_post_type_archive_link('course'); ?>" class="no-results-btn"><?php _e('Показать все курсы', 'course-plugin'); ?></a>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Переключение групп фильтров
    document.querySelectorAll('.filter-group-toggle').forEach(function(toggle) {
        toggle.addEventListener('click', function() {
            var targetId = this.getAttribute('data-target');
            var options = document.getElementById(targetId);
            if (options) {
                options.classList.toggle('collapsed');
                this.classList.toggle('active');
            }
        });
    });
    
    // Мобильный фильтр
    var mobileBtn = document.getElementById('mobile-filter-btn');
    var sidebar = document.getElementById('filters-sidebar');
    var closeBtn = document.getElementById('filters-close');
    
    if (mobileBtn && sidebar) {
        mobileBtn.addEventListener('click', function() {
            sidebar.classList.add('active');
            document.body.style.overflow = 'hidden';
        });
    }
    
    if (closeBtn && sidebar) {
        closeBtn.addEventListener('click', function() {
            sidebar.classList.remove('active');
            document.body.style.overflow = '';
        });
    }
    
    // Переключатель вида
    document.querySelectorAll('.view-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var view = this.getAttribute('data-view');
            var container = document.getElementById('courses-container');
            
            document.querySelectorAll('.view-btn').forEach(function(b) {
                b.classList.remove('active');
            });
            this.classList.add('active');
            
            if (container) {
                container.setAttribute('data-view', view);
            }
        });
    });
    
    // Автоматическая отправка формы при изменении сортировки
    var sortSelect = document.getElementById('course-sort-select');
    if (sortSelect) {
        sortSelect.addEventListener('change', function() {
            var form = document.getElementById('course-filters-form');
            if (form) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'sort';
                input.value = this.value;
                form.appendChild(input);
                form.submit();
            }
        });
    }
    
    // Удаление отдельных фильтров
    document.querySelectorAll('.remove-filter').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var filter = this.getAttribute('data-filter');
            var value = this.getAttribute('data-value');
            var url = new URL(window.location.href);
            
            if (filter === 'date') {
                // Удаляем оба параметра даты
                url.searchParams.delete('date_from');
                url.searchParams.delete('date_to');
            } else if (value) {
                // Для фильтров с массивом значений (location[], level[] и т.д.)
                var params = url.searchParams.getAll(filter + '[]');
                url.searchParams.delete(filter + '[]');
                params.filter(function(p) { return p !== value; }).forEach(function(p) {
                    url.searchParams.append(filter + '[]', p);
                });
            } else {
                // Для одиночных фильтров (price, teacher, search)
                url.searchParams.delete(filter);
            }
            
            window.location.href = url.toString();
        });
    });
});
</script>

<?php get_footer(); ?>
