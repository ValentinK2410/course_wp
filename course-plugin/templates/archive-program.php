<?php
/**
 * Шаблон архива программ
 * 
 * @copyright Copyright (c) 2024 Кузьменко Валентин (Valentink2410)
 * @author Кузьменко Валентин (Valentink2410)
 */

get_header(); 

global $wp_query;

// Проверяем, что это действительно архив программ
$is_program_archive = false;

if (is_post_type_archive('program')) {
    $is_program_archive = true;
} elseif (isset($_SERVER['REQUEST_URI'])) {
    $request_uri = $_SERVER['REQUEST_URI'];
    $path = parse_url($request_uri, PHP_URL_PATH);
    if (preg_match('#/programs(/page/\d+)?/?$#', $path)) {
        $is_program_archive = true;
    }
} elseif (get_query_var('post_type') === 'program' && !is_singular()) {
    $is_program_archive = true;
} elseif ($wp_query->get('post_type') === 'program') {
    $is_program_archive = true;
}

// Получаем параметры пагинации
$paged = get_query_var('paged') ? get_query_var('paged') : 1;
$posts_per_page = isset($wp_query->query_vars['posts_per_page']) ? $wp_query->query_vars['posts_per_page'] : get_option('posts_per_page', 15);
$found_posts = $wp_query->found_posts;
$showing_from = ($paged - 1) * $posts_per_page + 1;
$showing_to = min($paged * $posts_per_page, $found_posts);
?>

<div class="course-archive-wrapper program-archive-wrapper">
    <div class="course-archive-container program-archive-container">
        <!-- Левая боковая панель с фильтрами -->
        <aside class="course-filters-sidebar program-filters-sidebar">
            <h3 class="filters-title"><?php _e('Фильтры', 'course-plugin'); ?></h3>
            
            <form method="get" class="course-filters-form program-filters-form" id="program-filters-form">
                <!-- Преподаватель -->
                <div class="filter-section">
                    <label class="filter-section-title"><?php _e('Преподаватель', 'course-plugin'); ?></label>
                    <?php
                    wp_dropdown_categories(array(
                        'show_option_all' => __('Все', 'course-plugin'),
                        'taxonomy' => 'course_teacher',
                        'name' => 'teacher',
                        'id' => 'filter-teacher',
                        'selected' => isset($_GET['teacher']) ? $_GET['teacher'] : '',
                        'value_field' => 'slug',
                        'hide_empty' => false,
                        'class' => 'filter-select',
                    ));
                    ?>
                </div>
                
                <!-- Уровень -->
                <div class="filter-section">
                    <label class="filter-section-title"><?php _e('Уровень', 'course-plugin'); ?></label>
                    <div class="filter-checkboxes">
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
                                <label class="filter-checkbox-label">
                                    <input type="checkbox" name="level[]" value="<?php echo esc_attr($level->slug); ?>" <?php echo $checked; ?>>
                                    <span><?php echo esc_html($level->name); ?></span>
                                </label>
                                <?php
                            }
                        }
                        ?>
                    </div>
                </div>
                
                <!-- Специализация (Программа) -->
                <div class="filter-section">
                    <label class="filter-section-title"><?php _e('Программа', 'course-plugin'); ?></label>
                    <div class="filter-checkboxes">
                        <?php
                        $specializations = get_terms(array(
                            'taxonomy' => 'course_specialization',
                            'hide_empty' => false,
                            'orderby' => 'name',
                        ));
                        
                        if ($specializations && !is_wp_error($specializations)) {
                            $selected_specs = isset($_GET['specialization']) ? (array)$_GET['specialization'] : array();
                            foreach ($specializations as $spec) {
                                $checked = in_array($spec->slug, $selected_specs) ? 'checked' : '';
                                
                                // Подсчет программ в категории
                                $args = array(
                                    'post_type' => 'program',
                                    'posts_per_page' => -1,
                                    'tax_query' => array(
                                        array(
                                            'taxonomy' => 'course_specialization',
                                            'field' => 'term_id',
                                            'terms' => $spec->term_id,
                                        ),
                                    ),
                                );
                                $count_query = new WP_Query($args);
                                $program_count = $count_query->found_posts;
                                wp_reset_postdata();
                                ?>
                                <label class="filter-checkbox-label">
                                    <input type="checkbox" name="specialization[]" value="<?php echo esc_attr($spec->slug); ?>" <?php echo $checked; ?>>
                                    <span><?php echo esc_html($spec->name); ?> (<?php echo $program_count; ?>)</span>
                                </label>
                                <?php
                            }
                        }
                        ?>
                    </div>
                </div>
                
                <!-- Тема -->
                <div class="filter-section">
                    <label class="filter-section-title"><?php _e('Тема', 'course-plugin'); ?></label>
                    <div class="filter-checkboxes">
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
                                <label class="filter-checkbox-label">
                                    <input type="checkbox" name="topic[]" value="<?php echo esc_attr($topic->slug); ?>" <?php echo $checked; ?>>
                                    <span><?php echo esc_html($topic->name); ?></span>
                                </label>
                                <?php
                            }
                        }
                        ?>
                    </div>
                </div>
                
                <button type="submit" class="filter-submit-btn"><?php _e('Применить', 'course-plugin'); ?></button>
                <a href="<?php echo get_post_type_archive_link('program'); ?>" class="filter-reset-btn"><?php _e('Сбросить', 'course-plugin'); ?></a>
                
                <!-- Элементы управления в левой панели -->
                <div class="filters-controls">
                    <div class="course-pagination-info program-pagination-info">
                        <?php printf(__('Показаны %d-%d из %d', 'course-plugin'), $showing_from, $showing_to, $found_posts); ?>
                    </div>
                    
                    <div class="view-toggle">
                        <span class="control-label"><?php _e('Показать:', 'course-plugin'); ?></span>
                        <button class="view-btn active" data-view="grid" title="<?php _e('Сетка', 'course-plugin'); ?>">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                                <rect x="1" y="1" width="6" height="6"/>
                                <rect x="9" y="1" width="6" height="6"/>
                                <rect x="1" y="9" width="6" height="6"/>
                                <rect x="9" y="9" width="6" height="6"/>
                            </svg>
                        </button>
                        <button class="view-btn" data-view="list" title="<?php _e('Список', 'course-plugin'); ?>">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                                <rect x="1" y="1" width="14" height="2"/>
                                <rect x="1" y="7" width="14" height="2"/>
                                <rect x="1" y="13" width="14" height="2"/>
                            </svg>
                        </button>
                    </div>
                    
                    <div class="course-sort program-sort">
                        <label for="program-sort-select"><?php _e('Сортировать:', 'course-plugin'); ?></label>
                        <select id="program-sort-select" name="sort" class="sort-select">
                            <option value="default" <?php selected(isset($_GET['sort']) ? $_GET['sort'] : '', 'default'); ?>><?php _e('по умолчанию', 'course-plugin'); ?></option>
                            <option value="price_asc" <?php selected(isset($_GET['sort']) ? $_GET['sort'] : '', 'price_asc'); ?>><?php _e('Цена: по возрастанию', 'course-plugin'); ?></option>
                            <option value="price_desc" <?php selected(isset($_GET['sort']) ? $_GET['sort'] : '', 'price_desc'); ?>><?php _e('Цена: по убыванию', 'course-plugin'); ?></option>
                            <option value="date_asc" <?php selected(isset($_GET['sort']) ? $_GET['sort'] : '', 'date_asc'); ?>><?php _e('Дата: сначала старые', 'course-plugin'); ?></option>
                            <option value="date_desc" <?php selected(isset($_GET['sort']) ? $_GET['sort'] : '', 'date_desc'); ?>><?php _e('Дата: сначала новые', 'course-plugin'); ?></option>
                            <option value="title_asc" <?php selected(isset($_GET['sort']) ? $_GET['sort'] : '', 'title_asc'); ?>><?php _e('Название: А-Я', 'course-plugin'); ?></option>
                            <option value="title_desc" <?php selected(isset($_GET['sort']) ? $_GET['sort'] : '', 'title_desc'); ?>><?php _e('Название: Я-А', 'course-plugin'); ?></option>
                        </select>
                    </div>
                </div>
            </form>
        </aside>
        
        <!-- Основная область -->
        <main class="course-main-content program-main-content">
            
            <!-- Список программ -->
            <?php if (have_posts()) : ?>
                <div class="courses-container programs-container" id="programs-container" data-view="grid">
                    <div class="courses-grid programs-grid">
                        <?php while (have_posts()) : the_post(); 
                            $price = get_post_meta(get_the_ID(), '_program_price', true);
                            $old_price = get_post_meta(get_the_ID(), '_program_old_price', true);
                            $duration = get_post_meta(get_the_ID(), '_program_duration', true);
                            $courses_count = get_post_meta(get_the_ID(), '_program_courses_count', true);
                            $start_date = get_post_meta(get_the_ID(), '_program_start_date', true);
                            $certificate = get_post_meta(get_the_ID(), '_program_certificate', true);
                            $discount = 0;
                            if ($old_price && $price) {
                                $discount = round((($old_price - $price) / $old_price) * 100);
                            }
                        ?>
                            <article id="program-<?php the_ID(); ?>" <?php post_class('course-item program-item'); ?>>
                                <div class="course-thumbnail program-thumbnail">
                                    <?php if ($discount > 0) : ?>
                                        <span class="course-discount-badge program-discount-badge">-<?php echo $discount; ?>%</span>
                                    <?php endif; ?>
                                    
                                    <?php if (has_post_thumbnail()) : ?>
                                        <a href="<?php the_permalink(); ?>">
                                            <?php the_post_thumbnail('medium_large'); ?>
                                        </a>
                                    <?php else : ?>
                                        <a href="<?php the_permalink(); ?>">
                                            <div class="course-placeholder program-placeholder">
                                                <span class="dashicons dashicons-welcome-learn-more"></span>
                                            </div>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <!-- Название поверх изображения -->
                                    <h2 class="course-title-overlay program-title-overlay">
                                        <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                                    </h2>
                                </div>
                                
                                <div class="course-content program-content">
                                    <div class="course-price-section program-price-section">
                                        <?php if ($price || $old_price) : ?>
                                            <div class="course-price-wrapper program-price-wrapper">
                                                <?php if ($old_price && $price < $old_price) : ?>
                                                    <span class="course-old-price program-old-price"><?php echo number_format($old_price, 2, ',', ' '); ?> Р</span>
                                                <?php endif; ?>
                                                <span class="course-price program-price"><?php echo $price ? number_format($price, 2, ',', ' ') : '0,00'; ?> Р</span>
                                            </div>
                                        <?php else : ?>
                                            <span class="course-price program-price">0,00 Р</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($duration) : ?>
                                        <div class="program-duration">
                                            <strong><?php _e('Длительность:', 'course-plugin'); ?></strong> <?php echo esc_html($duration); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($courses_count) : ?>
                                        <div class="program-courses-count">
                                            <strong><?php _e('Курсов в программе:', 'course-plugin'); ?></strong> <?php echo esc_html($courses_count); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($certificate) : ?>
                                        <div class="program-certificate-info">
                                            <span class="dashicons dashicons-awards"></span> <?php _e('Сертификат', 'course-plugin'); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($start_date) : ?>
                                        <div class="course-start-date program-start-date">
                                            <?php _e('Дата начала:', 'course-plugin'); ?> <?php echo date_i18n('Y-m-d', strtotime($start_date)); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endwhile; ?>
                    </div>
                </div>
                
                <!-- Пагинация -->
                <div class="course-pagination program-pagination">
                    <?php
                    the_posts_pagination(array(
                        'mid_size' => 2,
                        'prev_text' => __('Назад', 'course-plugin'),
                        'next_text' => __('Вперед', 'course-plugin'),
                    ));
                    ?>
                </div>
            <?php else : ?>
                <div class="no-courses no-programs">
                    <p><?php _e('Программы не найдены.', 'course-plugin'); ?></p>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php get_footer(); ?>
