<?php
/**
 * Шаблон архива курсов
 */

get_header(); 

global $wp_query;

// Проверяем, что это действительно архив курсов
// Используем несколько проверок для надежности
$is_course_archive = false;

if (is_post_type_archive('course')) {
    $is_course_archive = true;
} elseif (isset($_SERVER['REQUEST_URI']) && preg_match('#/course/?$#', $_SERVER['REQUEST_URI'])) {
    $is_course_archive = true;
} elseif (get_query_var('post_type') === 'course' && !is_singular()) {
    $is_course_archive = true;
} elseif ($wp_query->get('post_type') === 'course') {
    $is_course_archive = true;
}

if (!$is_course_archive) {
    // Если это не архив курсов, используем стандартный шаблон
    // Но не делаем return, чтобы не сломать вывод
}

// Получаем параметры пагинации
$paged = get_query_var('paged') ? get_query_var('paged') : 1;
$posts_per_page = isset($wp_query->query_vars['posts_per_page']) ? $wp_query->query_vars['posts_per_page'] : get_option('posts_per_page', 15);
$found_posts = $wp_query->found_posts;
$showing_from = ($paged - 1) * $posts_per_page + 1;
$showing_to = min($paged * $posts_per_page, $found_posts);

// Отладочная информация (можно удалить после проверки)
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('Course Archive Debug: found_posts = ' . $found_posts);
    error_log('Course Archive Debug: post_type = ' . $wp_query->get('post_type'));
    error_log('Course Archive Debug: post_status = ' . (isset($wp_query->query_vars['post_status']) ? $wp_query->query_vars['post_status'] : 'not set'));
}
?>

<div class="course-archive-wrapper">
    <div class="course-archive-container">
        <!-- Левая боковая панель с фильтрами -->
        <aside class="course-filters-sidebar">
            <h3 class="filters-title"><?php _e('Фильтры', 'course-plugin'); ?></h3>
            
            <form method="get" class="course-filters-form" id="course-filters-form">
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
                                
                                // Подсчет курсов в категории
                                $args = array(
                                    'post_type' => 'course',
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
                                $course_count = $count_query->found_posts;
                                wp_reset_postdata();
                                ?>
                                <label class="filter-checkbox-label">
                                    <input type="checkbox" name="specialization[]" value="<?php echo esc_attr($spec->slug); ?>" <?php echo $checked; ?>>
                                    <span><?php echo esc_html($spec->name); ?> (<?php echo $course_count; ?>)</span>
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
                <a href="<?php echo get_post_type_archive_link('course'); ?>" class="filter-reset-btn"><?php _e('Сбросить', 'course-plugin'); ?></a>
                
                <!-- Элементы управления в левой панели -->
                <div class="filters-controls">
                    <div class="course-pagination-info">
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
                    
                    <div class="course-sort">
                        <label for="course-sort-select"><?php _e('Сортировать:', 'course-plugin'); ?></label>
                        <select id="course-sort-select" name="sort" class="sort-select">
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
        <main class="course-main-content">
            
            <!-- Список курсов -->
            <?php if (have_posts()) : ?>
                <div class="courses-container" id="courses-container" data-view="grid">
                    <div class="courses-grid">
                        <?php while (have_posts()) : the_post(); 
                            $price = get_post_meta(get_the_ID(), '_course_price', true);
                            $old_price = get_post_meta(get_the_ID(), '_course_old_price', true);
                            $start_date = get_post_meta(get_the_ID(), '_course_start_date', true);
                            $rating = get_post_meta(get_the_ID(), '_course_rating', true) ?: 0;
                            $reviews_count = get_post_meta(get_the_ID(), '_course_reviews_count', true) ?: 0;
                            $discount = 0;
                            if ($old_price && $price) {
                                $discount = round((($old_price - $price) / $old_price) * 100);
                            }
                        ?>
                            <article id="course-<?php the_ID(); ?>" <?php post_class('course-item'); ?>>
                                <div class="course-thumbnail">
                                    <?php if ($discount > 0) : ?>
                                        <span class="course-discount-badge">-<?php echo $discount; ?>%</span>
                                    <?php endif; ?>
                                    
                                    <?php if (has_post_thumbnail()) : ?>
                                        <a href="<?php the_permalink(); ?>">
                                            <?php the_post_thumbnail('medium_large'); ?>
                                        </a>
                                    <?php else : ?>
                                        <a href="<?php the_permalink(); ?>">
                                            <div class="course-placeholder">
                                                <span class="dashicons dashicons-book-alt"></span>
                                            </div>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <!-- Название поверх изображения -->
                                    <h2 class="course-title-overlay">
                                        <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                                    </h2>
                                </div>
                                
                                <div class="course-content">
                                    <div class="course-price-section">
                                        <?php if ($price || $old_price) : ?>
                                            <div class="course-price-wrapper">
                                                <?php if ($old_price && $price < $old_price) : ?>
                                                    <span class="course-old-price"><?php echo number_format($old_price, 2, ',', ' '); ?> Р</span>
                                                <?php endif; ?>
                                                <span class="course-price"><?php echo $price ? number_format($price, 2, ',', ' ') : '0,00'; ?> Р</span>
                                            </div>
                                        <?php else : ?>
                                            <span class="course-price">0,00 Р</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($rating > 0) : ?>
                                        <div class="course-rating">
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
                                    
                                    <?php if ($start_date) : ?>
                                        <div class="course-start-date">
                                            <?php _e('Дата начала:', 'course-plugin'); ?> <?php echo date_i18n('Y-m-d', strtotime($start_date)); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endwhile; ?>
                    </div>
                </div>
                
                <!-- Пагинация -->
                <div class="course-pagination">
                    <?php
                    the_posts_pagination(array(
                        'mid_size' => 2,
                        'prev_text' => __('Назад', 'course-plugin'),
                        'next_text' => __('Вперед', 'course-plugin'),
                    ));
                    ?>
                </div>
            <?php else : ?>
                <div class="no-courses">
                    <p><?php _e('Курсы не найдены.', 'course-plugin'); ?></p>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php get_footer(); ?>
