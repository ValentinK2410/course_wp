<?php
/**
 * Шаблон архива преподавателей - страница /teachers/
 *
 * @copyright Copyright (c) 2024 Кузьменко Валентин (Valentink2410)
 * @author Кузьменко Валентин (Valentink2410)
 */

get_header();

// Получаем параметры фильтрации и сортировки
$search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
$sort = isset($_GET['sort']) ? sanitize_text_field($_GET['sort']) : 'name';
$specialization_filter = isset($_GET['specialization']) ? (array)$_GET['specialization'] : array();

// Получаем всех преподавателей
$teachers_args = array(
    'taxonomy' => 'course_teacher',
    'hide_empty' => false,
    'orderby' => 'name',
    'order' => 'ASC',
);

if ($search) {
    $teachers_args['search'] = $search;
}

$teachers = get_terms($teachers_args);

// Подсчёт курсов и получение дополнительных данных для каждого преподавателя
$teachers_with_data = array();
if (!is_wp_error($teachers)) {
    foreach ($teachers as $term) {
        $teacher_photo = get_term_meta($term->term_id, 'teacher_photo', true);
        $teacher_position = get_term_meta($term->term_id, 'teacher_position', true);
        $teacher_description = get_term_meta($term->term_id, 'teacher_description', true);
        $teacher_email = get_term_meta($term->term_id, 'teacher_email', true);
        $teacher_phone = get_term_meta($term->term_id, 'teacher_phone', true);
        $teacher_facebook = get_term_meta($term->term_id, 'teacher_facebook', true);
        $teacher_twitter = get_term_meta($term->term_id, 'teacher_twitter', true);
        $teacher_linkedin = get_term_meta($term->term_id, 'teacher_linkedin', true);
        
        // Получаем курсы преподавателя
        $courses_query = new WP_Query(array(
            'post_type' => 'course',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'tax_query' => array(
                array(
                    'taxonomy' => 'course_teacher',
                    'field' => 'term_id',
                    'terms' => $term->term_id,
                ),
            ),
        ));
        
        $courses_count = $courses_query->found_posts;
        $courses_list = array();
        
        if ($courses_query->have_posts()) {
            while ($courses_query->have_posts()) {
                $courses_query->the_post();
                $courses_list[] = array(
                    'title' => get_the_title(),
                    'link' => get_permalink(),
                );
            }
            wp_reset_postdata();
        }
        
        // Получаем специализации преподавателя (из его курсов)
        $specializations = get_terms(array(
            'taxonomy' => 'course_specialization',
            'object_ids' => wp_list_pluck($courses_query->posts, 'ID'),
            'hide_empty' => true,
        ));
        
        $teacher_specializations = array();
        if (!is_wp_error($specializations)) {
            foreach ($specializations as $spec) {
                $teacher_specializations[] = $spec->slug;
            }
        }
        
        // Фильтрация по специализации
        if (!empty($specialization_filter)) {
            $has_match = false;
            foreach ($specialization_filter as $filter_spec) {
                if (in_array($filter_spec, $teacher_specializations)) {
                    $has_match = true;
                    break;
                }
            }
            if (!$has_match) {
                continue;
            }
        }
        
        $teachers_with_data[] = array(
            'term' => $term,
            'courses_count' => $courses_count,
            'courses_list' => $courses_list,
            'photo' => $teacher_photo,
            'position' => $teacher_position,
            'description' => $teacher_description,
            'email' => $teacher_email,
            'phone' => $teacher_phone,
            'facebook' => $teacher_facebook,
            'twitter' => $teacher_twitter,
            'linkedin' => $teacher_linkedin,
            'specializations' => $specializations,
        );
    }
}

// Сортировка
if ($sort === 'courses_desc') {
    usort($teachers_with_data, function($a, $b) {
        return $b['courses_count'] - $a['courses_count'];
    });
} elseif ($sort === 'courses_asc') {
    usort($teachers_with_data, function($a, $b) {
        return $a['courses_count'] - $b['courses_count'];
    });
} elseif ($sort === 'name_desc') {
    usort($teachers_with_data, function($a, $b) {
        return strcmp($b['term']->name, $a['term']->name);
    });
}

// Получаем все специализации для фильтра
$all_specializations = get_terms(array(
    'taxonomy' => 'course_specialization',
    'hide_empty' => true,
));
?>

<style>
/* Inline критические стили для предотвращения конфликтов с темой */
body .premium-archive-wrapper.teachers-archive {
    width: 100% !important;
    max-width: 100% !important;
    margin: 0 !important;
    padding: 0 !important;
    float: none !important;
    clear: both !important;
}
body .premium-archive-wrapper.teachers-archive .premium-archive-container {
    width: 100% !important;
    max-width: 1400px !important;
    margin-left: auto !important;
    margin-right: auto !important;
}
body .premium-archive-wrapper.teachers-archive .teachers-main-content {
    width: 100% !important;
    max-width: none !important;
    float: none !important;
}
</style>
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
                    <span class="stat-number"><?php echo count($teachers_with_data); ?></span>
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
        <!-- Панель фильтров и поиска -->
        <div class="teachers-toolbar">
            <div class="toolbar-left">
                <div class="filter-search-box">
                    <svg class="search-icon" width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="2"/>
                        <path d="M13 13L16 16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    <input type="text" class="filter-search-input" id="teacher-search-input" placeholder="<?php _e('Поиск преподавателя...', 'course-plugin'); ?>" value="<?php echo esc_attr($search); ?>">
                </div>
                
                <?php if (!empty($all_specializations) && !is_wp_error($all_specializations)) : ?>
                <div class="filter-dropdown">
                    <button type="button" class="filter-dropdown-toggle" id="specialization-filter-toggle">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect x="2" y="2" width="12" height="12" rx="2" stroke="currentColor" stroke-width="1.5"/>
                            <path d="M5 6H11M5 8H11M5 10H9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        </svg>
                        <?php _e('Направление', 'course-plugin'); ?>
                        <?php if (!empty($specialization_filter)) : ?>
                            <span class="filter-count"><?php echo count($specialization_filter); ?></span>
                        <?php endif; ?>
                    </button>
                    <div class="filter-dropdown-menu" id="specialization-filter-menu">
                        <?php foreach ($all_specializations as $spec) : 
                            $checked = in_array($spec->slug, $specialization_filter) ? 'checked' : '';
                        ?>
                            <label class="filter-option">
                                <input type="checkbox" name="specialization[]" value="<?php echo esc_attr($spec->slug); ?>" <?php echo $checked; ?>>
                                <span class="option-checkbox"></span>
                                <span class="option-text"><?php echo esc_html($spec->name); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="toolbar-right">
                <span class="results-count">
                    <?php printf(__('Показано: <strong>%d</strong>', 'course-plugin'), count($teachers_with_data)); ?>
                </span>
                
                <div class="sort-dropdown">
                    <select id="teacher-sort-select" name="sort" class="premium-sort-select">
                        <option value="name" <?php selected($sort, 'name'); ?>><?php _e('По имени А-Я', 'course-plugin'); ?></option>
                        <option value="name_desc" <?php selected($sort, 'name_desc'); ?>><?php _e('По имени Я-А', 'course-plugin'); ?></option>
                        <option value="courses_desc" <?php selected($sort, 'courses_desc'); ?>><?php _e('Больше курсов', 'course-plugin'); ?></option>
                        <option value="courses_asc" <?php selected($sort, 'courses_asc'); ?>><?php _e('Меньше курсов', 'course-plugin'); ?></option>
                    </select>
                </div>
            </div>
        </div>
        
        <main class="premium-main-content teachers-main-content">
            <?php if (!empty($teachers_with_data)) : ?>
                <div class="teachers-grid" id="teachers-container">
                    <?php 
                    $color_schemes = array(
                        array('gradient' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)', 'accent' => '#667eea'),
                        array('gradient' => 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)', 'accent' => '#f5576c'),
                        array('gradient' => 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)', 'accent' => '#4facfe'),
                        array('gradient' => 'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)', 'accent' => '#43e97b'),
                        array('gradient' => 'linear-gradient(135deg, #fa709a 0%, #fee140 100%)', 'accent' => '#fa709a'),
                        array('gradient' => 'linear-gradient(135deg, #a8edea 0%, #fed6e3 100%)', 'accent' => '#a8edea'),
                    );
                    
                    foreach ($teachers_with_data as $index => $item) :
                        $term = $item['term'];
                        $courses_count = $item['courses_count'];
                        $courses_list = $item['courses_list'];
                        $teacher_photo = $item['photo'];
                        $teacher_position = $item['position'];
                        $teacher_description = $item['description'];
                        $teacher_email = $item['email'];
                        $teacher_phone = $item['phone'];
                        $teacher_facebook = $item['facebook'];
                        $teacher_twitter = $item['twitter'];
                        $teacher_linkedin = $item['linkedin'];
                        $specializations = $item['specializations'];
                        
                        $teacher_link = get_term_link($term);
                        if (is_wp_error($teacher_link)) {
                            $teacher_link = '#';
                        }
                        
                        $scheme = $color_schemes[$index % count($color_schemes)];
                    ?>
                        <article class="teacher-card" data-teacher-id="<?php echo $term->term_id; ?>">
                            <div class="teacher-card-inner">
                                <div class="teacher-card-image" style="background: <?php echo $scheme['gradient']; ?>">
                                    <?php if ($teacher_photo) : ?>
                                        <img src="<?php echo esc_url($teacher_photo); ?>" alt="<?php echo esc_attr($term->name); ?>" loading="lazy" />
                                    <?php else : ?>
                                        <div class="teacher-card-placeholder">
                                            <svg width="80" height="80" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="1.5"/>
                                                <path d="M4 20C4 16.6863 7.58172 14 12 14C16.4183 14 20 16.6863 20 20" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                            </svg>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($teacher_position) : 
                                        $badge_class = '';
                                        if (stripos($teacher_position, 'проректор') !== false) {
                                            $badge_class = 'badge-primary';
                                        } elseif (stripos($teacher_position, 'старший') !== false) {
                                            $badge_class = 'badge-senior';
                                        } else {
                                            $badge_class = 'badge-default';
                                        }
                                    ?>
                                        <span class="teacher-badge <?php echo $badge_class; ?>"><?php echo esc_html($teacher_position); ?></span>
                                    <?php endif; ?>
                                    
                                    <div class="teacher-card-overlay">
                                        <button type="button" class="teacher-quick-view-btn" data-teacher-id="<?php echo $term->term_id; ?>">
                                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M10 3C5 3 1.73 7.11 1 10c.73 2.89 4 7 9 7s8.27-4.11 9-7c-.73-2.89-4-7-9-7z" stroke="currentColor" stroke-width="1.5"/>
                                                <circle cx="10" cy="10" r="3" stroke="currentColor" stroke-width="1.5"/>
                                            </svg>
                                            <?php _e('Быстрый просмотр', 'course-plugin'); ?>
                                        </button>
                                    </div>
                                    
                                    <!-- Список курсов при hover -->
                                    <?php if (!empty($courses_list)) : ?>
                                        <div class="teacher-courses-hover">
                                            <h4><?php _e('Курсы преподавателя:', 'course-plugin'); ?></h4>
                                            <ul>
                                                <?php foreach (array_slice($courses_list, 0, 5) as $course) : ?>
                                                    <li><a href="<?php echo esc_url($course['link']); ?>"><?php echo esc_html($course['title']); ?></a></li>
                                                <?php endforeach; ?>
                                                <?php if (count($courses_list) > 5) : ?>
                                                    <li class="more-courses">+ <?php printf(__('ещё %d', 'course-plugin'), count($courses_list) - 5); ?></li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="teacher-card-content">
                                    <h2 class="teacher-card-name">
                                        <a href="<?php echo esc_url($teacher_link); ?>"><?php echo esc_html($term->name); ?></a>
                                    </h2>
                                    
                                    <?php if (!empty($specializations) && !is_wp_error($specializations)) : ?>
                                        <div class="teacher-specializations">
                                            <?php foreach (array_slice($specializations, 0, 2) as $spec) : ?>
                                                <span class="specialization-tag"><?php echo esc_html($spec->name); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($teacher_description) : ?>
                                        <p class="teacher-card-description"><?php echo esc_html(wp_trim_words($teacher_description, 20, '...')); ?></p>
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
                                    
                                    <!-- Контакты -->
                                    <?php if ($teacher_email || $teacher_phone || $teacher_facebook || $teacher_twitter || $teacher_linkedin) : ?>
                                        <div class="teacher-contacts">
                                            <?php if ($teacher_email) : ?>
                                                <a href="mailto:<?php echo esc_attr($teacher_email); ?>" class="contact-icon" title="Email">
                                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                        <rect x="1" y="3" width="14" height="10" rx="2" stroke="currentColor" stroke-width="1.5"/>
                                                        <path d="M1 5L8 9L15 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                    </svg>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if ($teacher_phone) : ?>
                                                <a href="tel:<?php echo esc_attr($teacher_phone); ?>" class="contact-icon" title="<?php _e('Телефон', 'course-plugin'); ?>">
                                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                        <path d="M2 3C2 2.44772 2.44772 2 3 2H5.15287C5.64171 2 6.0589 2.35341 6.13927 2.8356L6.87858 7.27147C6.95075 7.70451 6.73206 8.13397 6.3394 8.3303L4.79126 9.10437C5.90756 11.8783 8.12168 14.0924 10.8956 15.2087L11.6697 13.6606C11.866 13.2679 12.2955 13.0492 12.7285 13.1214L17.1644 13.8607C17.6466 13.9411 18 14.3583 18 14.8471V17C18 17.5523 17.5523 18 17 18H15C7.8203 18 2 12.1797 2 5V3Z" stroke="currentColor" stroke-width="1.5"/>
                                                    </svg>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if ($teacher_facebook) : ?>
                                                <a href="<?php echo esc_url($teacher_facebook); ?>" target="_blank" rel="noopener" class="contact-icon social-facebook" title="Facebook">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                                        <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                                                    </svg>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if ($teacher_twitter) : ?>
                                                <a href="<?php echo esc_url($teacher_twitter); ?>" target="_blank" rel="noopener" class="contact-icon social-twitter" title="Twitter">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                                        <path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/>
                                                    </svg>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if ($teacher_linkedin) : ?>
                                                <a href="<?php echo esc_url($teacher_linkedin); ?>" target="_blank" rel="noopener" class="contact-icon social-linkedin" title="LinkedIn">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                                        <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                                                    </svg>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <a href="<?php echo esc_url($teacher_link); ?>" class="teacher-view-profile" style="color: <?php echo $scheme['accent']; ?>">
                                        <?php _e('Смотреть профиль', 'course-plugin'); ?>
                                        <svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M1 7H13M13 7L8 2M13 7L8 12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </a>
                                </div>
                            </div>
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
                    <p class="no-results-text"><?php _e('Попробуйте изменить параметры поиска или фильтрации', 'course-plugin'); ?></p>
                    <a href="/teachers/" class="no-results-btn"><?php _e('Сбросить фильтры', 'course-plugin'); ?></a>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<!-- Quick View Modal -->
<div class="teacher-quick-view-modal" id="teacher-quick-view-modal">
    <div class="modal-overlay"></div>
    <div class="modal-content">
        <button type="button" class="modal-close" id="modal-close-btn">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
        </button>
        <div class="modal-body" id="modal-body">
            <!-- Контент загружается динамически -->
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Поиск
    var searchInput = document.getElementById('teacher-search-input');
    var searchTimeout;
    
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                applyFilters();
            }, 500);
        });
    }
    
    // Сортировка
    var sortSelect = document.getElementById('teacher-sort-select');
    if (sortSelect) {
        sortSelect.addEventListener('change', function() {
            applyFilters();
        });
    }
    
    // Фильтр по специализации
    var specToggle = document.getElementById('specialization-filter-toggle');
    var specMenu = document.getElementById('specialization-filter-menu');
    
    if (specToggle && specMenu) {
        specToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            specMenu.classList.toggle('active');
        });
        
        document.addEventListener('click', function() {
            specMenu.classList.remove('active');
        });
        
        specMenu.addEventListener('click', function(e) {
            e.stopPropagation();
        });
        
        var specCheckboxes = specMenu.querySelectorAll('input[type="checkbox"]');
        specCheckboxes.forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                applyFilters();
            });
        });
    }
    
    // Применение фильтров
    function applyFilters() {
        var url = new URL(window.location.href);
        url.search = '';
        
        var search = searchInput ? searchInput.value : '';
        if (search) {
            url.searchParams.set('search', search);
        }
        
        var sort = sortSelect ? sortSelect.value : 'name';
        if (sort && sort !== 'name') {
            url.searchParams.set('sort', sort);
        }
        
        if (specMenu) {
            var checkedSpecs = specMenu.querySelectorAll('input[type="checkbox"]:checked');
            checkedSpecs.forEach(function(checkbox) {
                url.searchParams.append('specialization[]', checkbox.value);
            });
        }
        
        window.location.href = url.toString();
    }
    
    // Quick View Modal
    var modal = document.getElementById('teacher-quick-view-modal');
    var modalBody = document.getElementById('modal-body');
    var closeBtn = document.getElementById('modal-close-btn');
    
    document.querySelectorAll('.teacher-quick-view-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var teacherId = this.getAttribute('data-teacher-id');
            openQuickView(teacherId);
        });
    });
    
    if (closeBtn) {
        closeBtn.addEventListener('click', function() {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        });
    }
    
    if (modal) {
        modal.querySelector('.modal-overlay').addEventListener('click', function() {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        });
    }
    
    function openQuickView(teacherId) {
        // Находим данные преподавателя
        var teacherCard = document.querySelector('.teacher-card[data-teacher-id="' + teacherId + '"]');
        if (!teacherCard) return;
        
        var name = teacherCard.querySelector('.teacher-card-name a').textContent;
        var position = teacherCard.querySelector('.teacher-badge') ? teacherCard.querySelector('.teacher-badge').textContent : '';
        var description = teacherCard.querySelector('.teacher-card-description') ? teacherCard.querySelector('.teacher-card-description').textContent : '';
        var photo = teacherCard.querySelector('.teacher-card-image img');
        var photoSrc = photo ? photo.src : '';
        var coursesCount = teacherCard.querySelector('.teacher-courses-count').textContent;
        var profileLink = teacherCard.querySelector('.teacher-view-profile').href;
        var specializations = teacherCard.querySelectorAll('.specialization-tag');
        var contacts = teacherCard.querySelector('.teacher-contacts');
        var coursesHover = teacherCard.querySelector('.teacher-courses-hover');
        
        var html = '<div class="quick-view-content">';
        html += '<div class="quick-view-header">';
        if (photoSrc) {
            html += '<img src="' + photoSrc + '" alt="' + name + '" class="quick-view-photo" />';
        }
        html += '<div class="quick-view-info">';
        html += '<h2>' + name + '</h2>';
        if (position) {
            html += '<p class="quick-view-position">' + position + '</p>';
        }
        if (specializations.length > 0) {
            html += '<div class="quick-view-specializations">';
            specializations.forEach(function(tag) {
                html += '<span class="specialization-tag">' + tag.textContent + '</span>';
            });
            html += '</div>';
        }
        html += '</div>';
        html += '</div>';
        
        if (description) {
            html += '<div class="quick-view-description">' + description + '</div>';
        }
        
        html += '<div class="quick-view-meta">';
        html += '<span class="meta-item">' + coursesCount + '</span>';
        html += '</div>';
        
        if (coursesHover) {
            var coursesList = coursesHover.querySelector('ul');
            if (coursesList) {
                html += '<div class="quick-view-courses">';
                html += '<h4><?php _e('Курсы преподавателя:', 'course-plugin'); ?></h4>';
                html += coursesList.outerHTML;
                html += '</div>';
            }
        }
        
        if (contacts) {
            html += '<div class="quick-view-contacts">';
            html += contacts.innerHTML;
            html += '</div>';
        }
        
        html += '<a href="' + profileLink + '" class="quick-view-profile-btn"><?php _e('Перейти в профиль', 'course-plugin'); ?></a>';
        html += '</div>';
        
        modalBody.innerHTML = html;
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
});
</script>

<?php get_footer(); ?>
