<?php
/**
 * Шаблон архива курсов
 */

get_header(); ?>

<div class="course-archive-wrapper">
    <div class="container">
        <header class="course-archive-header">
            <h1 class="page-title"><?php post_type_archive_title(); ?></h1>
        </header>
        
        <div class="course-filters-wrapper">
            <form method="get" class="course-filters-form" id="course-filters-form">
                <div class="filters-row">
                    <div class="filter-group">
                        <label for="specialization"><?php _e('Специализация', 'course-plugin'); ?></label>
                        <?php
                        wp_dropdown_categories(array(
                            'show_option_all' => __('Все специализации', 'course-plugin'),
                            'taxonomy' => 'course_specialization',
                            'name' => 'specialization',
                            'id' => 'specialization',
                            'selected' => isset($_GET['specialization']) ? $_GET['specialization'] : '',
                            'value_field' => 'slug',
                            'hide_empty' => false,
                        ));
                        ?>
                    </div>
                    
                    <div class="filter-group">
                        <label for="level"><?php _e('Уровень образования', 'course-plugin'); ?></label>
                        <?php
                        wp_dropdown_categories(array(
                            'show_option_all' => __('Все уровни', 'course-plugin'),
                            'taxonomy' => 'course_level',
                            'name' => 'level',
                            'id' => 'level',
                            'selected' => isset($_GET['level']) ? $_GET['level'] : '',
                            'value_field' => 'slug',
                            'hide_empty' => false,
                        ));
                        ?>
                    </div>
                    
                    <div class="filter-group">
                        <label for="topic"><?php _e('Тема', 'course-plugin'); ?></label>
                        <?php
                        wp_dropdown_categories(array(
                            'show_option_all' => __('Все темы', 'course-plugin'),
                            'taxonomy' => 'course_topic',
                            'name' => 'topic',
                            'id' => 'topic',
                            'selected' => isset($_GET['topic']) ? $_GET['topic'] : '',
                            'value_field' => 'slug',
                            'hide_empty' => false,
                        ));
                        ?>
                    </div>
                    
                    <div class="filter-group">
                        <label for="teacher"><?php _e('Преподаватель', 'course-plugin'); ?></label>
                        <?php
                        wp_dropdown_categories(array(
                            'show_option_all' => __('Все преподаватели', 'course-plugin'),
                            'taxonomy' => 'course_teacher',
                            'name' => 'teacher',
                            'id' => 'teacher',
                            'selected' => isset($_GET['teacher']) ? $_GET['teacher'] : '',
                            'value_field' => 'slug',
                            'hide_empty' => false,
                        ));
                        ?>
                    </div>
                    
                    <div class="filter-group">
                        <button type="submit" class="filter-submit-btn"><?php _e('Применить фильтры', 'course-plugin'); ?></button>
                        <a href="<?php echo get_post_type_archive_link('course'); ?>" class="filter-reset-btn"><?php _e('Сбросить', 'course-plugin'); ?></a>
                    </div>
                </div>
            </form>
        </div>
        
        <div class="course-view-controls">
            <div class="view-toggle">
                <button class="view-btn active" data-view="grid" title="<?php _e('Сетка', 'course-plugin'); ?>">
                    <span class="dashicons dashicons-grid-view"></span>
                </button>
                <button class="view-btn" data-view="list" title="<?php _e('Список', 'course-plugin'); ?>">
                    <span class="dashicons dashicons-list-view"></span>
                </button>
            </div>
            <div class="courses-count">
                <?php
                global $wp_query;
                printf(
                    __('Найдено курсов: %d', 'course-plugin'),
                    $wp_query->found_posts
                );
                ?>
            </div>
        </div>
        
        <?php if (have_posts()) : ?>
            <div class="courses-container" id="courses-container" data-view="grid">
                <div class="courses-grid">
                    <?php while (have_posts()) : the_post(); ?>
                        <article id="course-<?php the_ID(); ?>" <?php post_class('course-item'); ?>>
                            <div class="course-thumbnail">
                                <?php if (has_post_thumbnail()) : ?>
                                    <a href="<?php the_permalink(); ?>">
                                        <?php the_post_thumbnail('medium'); ?>
                                    </a>
                                <?php else : ?>
                                    <a href="<?php the_permalink(); ?>">
                                        <div class="course-placeholder">
                                            <span class="dashicons dashicons-book-alt"></span>
                                        </div>
                                    </a>
                                <?php endif; ?>
                            </div>
                            
                            <div class="course-content">
                                <h2 class="course-title">
                                    <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                                </h2>
                                
                                <div class="course-excerpt">
                                    <?php the_excerpt(); ?>
                                </div>
                                
                                <div class="course-meta">
                                    <?php
                                    $specializations = get_the_terms(get_the_ID(), 'course_specialization');
                                    if ($specializations && !is_wp_error($specializations)) :
                                        ?>
                                        <span class="course-specialization">
                                            <span class="dashicons dashicons-category"></span>
                                            <?php echo esc_html($specializations[0]->name); ?>
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php
                                    $level = get_the_terms(get_the_ID(), 'course_level');
                                    if ($level && !is_wp_error($level)) :
                                        ?>
                                        <span class="course-level">
                                            <span class="dashicons dashicons-awards"></span>
                                            <?php echo esc_html($level[0]->name); ?>
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php
                                    $price = get_post_meta(get_the_ID(), '_course_price', true);
                                    if ($price) :
                                        ?>
                                        <span class="course-price">
                                            <span class="dashicons dashicons-money-alt"></span>
                                            <?php echo number_format($price, 0, ',', ' '); ?> ₽
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="course-footer">
                                    <a href="<?php the_permalink(); ?>" class="course-read-more">
                                        <?php _e('Подробнее', 'course-plugin'); ?>
                                    </a>
                                </div>
                            </div>
                        </article>
                    <?php endwhile; ?>
                </div>
            </div>
            
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
    </div>
</div>

<?php get_footer(); ?>

