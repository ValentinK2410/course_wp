<?php
/**
 * Шаблон архива курсов
 * 
 * @copyright Copyright (c) 2024 Кузьменко Валентин (Valentink2410)
 * @author Кузьменко Валентин (Valentink2410)
 */

get_header(); 

global $wp_query;

// Проверяем, что это действительно архив курсов
// Используем несколько проверок для надежности
$is_course_archive = false;

if (is_post_type_archive('course')) {
    $is_course_archive = true;
} elseif (isset($_SERVER['REQUEST_URI'])) {
    $request_uri = $_SERVER['REQUEST_URI'];
    // Убираем query string для проверки
    $path = parse_url($request_uri, PHP_URL_PATH);
    if (preg_match('#/(course|courses)(/page/\d+)?/?$#', $path)) {
        $is_course_archive = true;
    }
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
                <!-- Поиск по фильтрам -->
                <div class="filter-search-wrapper" style="margin-bottom: 20px;">
                    <input type="text" class="filter-search" id="filter-search-input" placeholder="<?php _e('Поиск по фильтрам...', 'course-plugin'); ?>" autocomplete="off">
                </div>
                
                <!-- Преподаватель -->
                <div class="filter-section" data-filter-type="teacher">
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
                <div class="filter-section" data-filter-type="level">
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
                <div class="filter-section" data-filter-type="program">
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
                <div class="filter-section" data-filter-type="topic">
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
                            $duration = get_post_meta(get_the_ID(), '_course_duration', true);
                            $rating = get_post_meta(get_the_ID(), '_course_rating', true) ?: 0;
                            $reviews_count = get_post_meta(get_the_ID(), '_course_reviews_count', true) ?: 0;
                            
                            // Получаем тег курса (из метаполя или таксономии)
                            $course_tag = get_post_meta(get_the_ID(), '_course_tag', true);
                            if (!$course_tag) {
                                // Пробуем получить из таксономии "Уровень"
                                $levels = get_the_terms(get_the_ID(), 'course_level');
                                if ($levels && !is_wp_error($levels) && !empty($levels)) {
                                    $course_tag = $levels[0]->name;
                                }
                            }
                            
                            // Получаем дополнительный текст
                            $course_additional_text = get_post_meta(get_the_ID(), '_course_additional_text', true);
                            if (!$course_additional_text && $duration) {
                                $course_additional_text = $duration;
                            }
                            
                            // Определяем градиент фона на основе ID курса
                            $gradient_index = (get_the_ID() % 4);
                            $gradient_classes = array(
                                'gradient-blue-gray',
                                'gradient-peach-orange',
                                'gradient-cream',
                                'gradient-light-blue'
                            );
                            $gradient_class = $gradient_classes[$gradient_index];
                            
                            // Определяем цвет тега
                            $tag_colors = array(
                                'tag-yellow-green',
                                'tag-orange',
                                'tag-red-orange',
                                'tag-yellow-green'
                            );
                            $tag_color_class = $tag_colors[$gradient_index];
                        ?>
                            <article id="course-<?php the_ID(); ?>" <?php post_class('course-item course-card-modern'); ?>>
                                <a href="<?php the_permalink(); ?>" class="course-card-link">
                                    <div class="course-card-wrapper <?php echo esc_attr($gradient_class); ?>">
                                        <div class="course-card-header">
                                            <h2 class="course-card-title"><?php the_title(); ?></h2>
                                            <p class="course-card-subtitle"><?php _e('Оплата во время обучения', 'course-plugin'); ?></p>
                                        </div>
                                        
                                        <?php if ($course_tag) : ?>
                                            <div class="course-card-tag <?php echo esc_attr($tag_color_class); ?>">
                                                <?php echo esc_html($course_tag); ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($course_additional_text) : ?>
                                            <p class="course-card-additional"><?php echo esc_html($course_additional_text); ?></p>
                                        <?php endif; ?>
                                        
                                        <div class="course-card-illustration">
                                            <?php
                                            // Разные иллюстрации для разных курсов
                                            $illustration_index = (get_the_ID() % 4);
                                            ?>
                                            <?php if ($illustration_index == 0) : ?>
                                                <!-- Иллюстрация 1: Человек за компьютером -->
                                                <svg width="140" height="140" viewBox="0 0 140 140" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                    <!-- Монитор -->
                                                    <rect x="30" y="40" width="80" height="50" rx="4" fill="rgba(255,255,255,0.4)"/>
                                                    <rect x="35" y="45" width="70" height="40" rx="2" fill="rgba(255,255,255,0.6)"/>
                                                    <!-- Экран -->
                                                    <rect x="40" y="50" width="60" height="30" rx="2" fill="rgba(102,126,234,0.3)"/>
                                                    <circle cx="50" cy="60" r="2" fill="rgba(255,255,255,0.8)"/>
                                                    <circle cx="70" cy="60" r="2" fill="rgba(255,255,255,0.8)"/>
                                                    <rect x="45" y="70" width="50" height="2" rx="1" fill="rgba(255,255,255,0.6)"/>
                                                    <!-- Подставка -->
                                                    <rect x="55" y="90" width="30" height="4" rx="2" fill="rgba(255,255,255,0.3)"/>
                                                    <!-- Человек (силуэт) -->
                                                    <circle cx="70" cy="110" r="12" fill="rgba(255,255,255,0.3)"/>
                                                    <rect x="58" y="110" width="24" height="20" rx="12" fill="rgba(255,255,255,0.3)"/>
                                                </svg>
                                            <?php elseif ($illustration_index == 1) : ?>
                                                <!-- Иллюстрация 2: Ноутбук с кодом -->
                                                <svg width="140" height="140" viewBox="0 0 140 140" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                    <!-- Ноутбук -->
                                                    <rect x="25" y="50" width="90" height="55" rx="3" fill="rgba(255,255,255,0.4)"/>
                                                    <rect x="30" y="55" width="80" height="45" rx="2" fill="rgba(255,255,255,0.6)"/>
                                                    <!-- Экран -->
                                                    <rect x="35" y="60" width="70" height="35" rx="2" fill="rgba(118,75,162,0.3)"/>
                                                    <!-- Код на экране -->
                                                    <rect x="40" y="65" width="20" height="3" rx="1" fill="rgba(255,255,255,0.7)"/>
                                                    <rect x="40" y="72" width="30" height="3" rx="1" fill="rgba(255,255,255,0.5)"/>
                                                    <rect x="40" y="79" width="25" height="3" rx="1" fill="rgba(255,255,255,0.5)"/>
                                                    <rect x="40" y="86" width="35" height="3" rx="1" fill="rgba(255,255,255,0.5)"/>
                                                    <!-- Клавиатура -->
                                                    <rect x="30" y="105" width="80" height="8" rx="2" fill="rgba(255,255,255,0.3)"/>
                                                    <!-- Человек -->
                                                    <circle cx="70" cy="125" r="10" fill="rgba(255,255,255,0.3)"/>
                                                </svg>
                                            <?php elseif ($illustration_index == 2) : ?>
                                                <!-- Иллюстрация 3: Планшет -->
                                                <svg width="140" height="140" viewBox="0 0 140 140" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                    <!-- Планшет -->
                                                    <rect x="40" y="45" width="60" height="75" rx="6" fill="rgba(255,255,255,0.4)"/>
                                                    <rect x="43" y="48" width="54" height="69" rx="4" fill="rgba(255,255,255,0.6)"/>
                                                    <!-- Экран -->
                                                    <rect x="46" y="52" width="48" height="61" rx="3" fill="rgba(254,202,202,0.3)"/>
                                                    <!-- Контент -->
                                                    <circle cx="70" cy="70" r="8" fill="rgba(255,255,255,0.6)"/>
                                                    <rect x="60" y="82" width="20" height="3" rx="1" fill="rgba(255,255,255,0.5)"/>
                                                    <rect x="55" y="88" width="30" height="3" rx="1" fill="rgba(255,255,255,0.5)"/>
                                                    <!-- Человек (сидящий) -->
                                                    <circle cx="70" cy="125" r="10" fill="rgba(255,255,255,0.3)"/>
                                                    <rect x="60" y="125" width="20" height="15" rx="10" fill="rgba(255,255,255,0.3)"/>
                                                </svg>
                                            <?php else : ?>
                                                <!-- Иллюстрация 4: Человек с ноутбуком Apple -->
                                                <svg width="140" height="140" viewBox="0 0 140 140" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                    <!-- Ноутбук -->
                                                    <rect x="30" y="50" width="80" height="50" rx="4" fill="rgba(255,255,255,0.4)"/>
                                                    <rect x="35" y="55" width="70" height="40" rx="2" fill="rgba(255,255,255,0.6)"/>
                                                    <!-- Экран -->
                                                    <rect x="40" y="60" width="60" height="30" rx="2" fill="rgba(230,243,255,0.4)"/>
                                                    <!-- Логотип Apple -->
                                                    <path d="M65 72 L70 75 L75 72 L70 68 Z" fill="rgba(0,0,0,0.3)"/>
                                                    <!-- Человек -->
                                                    <circle cx="70" cy="110" r="12" fill="rgba(255,255,255,0.3)"/>
                                                    <rect x="58" y="110" width="24" height="20" rx="12" fill="rgba(255,255,255,0.3)"/>
                                                    <!-- Чашка -->
                                                    <rect x="95" y="75" width="8" height="12" rx="2" fill="rgba(255,255,255,0.4)"/>
                                                    <rect x="93" y="87" width="12" height="2" rx="1" fill="rgba(255,255,255,0.3)"/>
                                                </svg>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </a>
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
