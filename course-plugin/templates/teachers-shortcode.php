<?php
/**
 * Шаблон для шорткода преподавателей
 *
 * @copyright Copyright (c) 2024 Кузьменко Валентин (Valentink2410)
 * @author Кузьменко Валентин (Valentink2410)
 */

if (!defined('ABSPATH')) {
    exit;
}

$columns = isset($shortcode_atts['columns']) ? intval($shortcode_atts['columns']) : 3;
$columns = max(2, min(4, $columns));
$button_style = isset($shortcode_atts['button_style']) ? sanitize_key($shortcode_atts['button_style']) : 'default';
$theme_class = !empty($shortcode_atts['theme_class']) ? sanitize_html_class($shortcode_atts['theme_class']) : '';

$wrapper_classes = array('teachers-shortcode-wrapper', 'premium-archive-wrapper', 'teachers-archive', 'ts-style-' . $button_style);
if ($theme_class) {
    $wrapper_classes[] = $theme_class;
}

$color_schemes = array(
    array('gradient' => 'linear-gradient(135deg, #8B2D3D 0%, #68202D 100%)', 'accent' => '#68202D'),
    array('gradient' => 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)', 'accent' => '#f5576c'),
    array('gradient' => 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)', 'accent' => '#4facfe'),
    array('gradient' => 'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)', 'accent' => '#43e97b'),
    array('gradient' => 'linear-gradient(135deg, #fa709a 0%, #fee140 100%)', 'accent' => '#fa709a'),
);
?>

<div class="<?php echo esc_attr(implode(' ', $wrapper_classes)); ?>" data-columns="<?php echo esc_attr($columns); ?>">
    <div class="teachers-shortcode-grid" style="display: grid; grid-template-columns: repeat(<?php echo $columns; ?>, 1fr); gap: 24px;">
        <?php foreach ($teachers_with_data as $index => $item) :
            $term = $item['term'];
            $teacher_photo = $item['photo'];
            $teacher_position = $item['position'];
            $teacher_description = $item['description'];
            $courses_count = $item['courses_count'];
            $specializations = $item['specializations'];
            
            $teacher_link = get_term_link($term);
            if (is_wp_error($teacher_link)) {
                $teacher_link = '#';
            }
            
            $scheme = $color_schemes[$index % count($color_schemes)];
        ?>
            <article class="teacher-card teacher-card-shortcode">
                <div class="teacher-card-inner">
                    <div class="teacher-card-image" style="background: <?php echo esc_attr($scheme['gradient']); ?>">
                        <?php if ($teacher_photo) : ?>
                            <a href="<?php echo esc_url($teacher_link); ?>">
                                <img src="<?php echo esc_url($teacher_photo); ?>" alt="<?php echo esc_attr($term->name); ?>" loading="lazy" />
                            </a>
                        <?php else : ?>
                            <a href="<?php echo esc_url($teacher_link); ?>">
                                <div class="teacher-card-placeholder">
                                    <svg width="60" height="60" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="1.5"/>
                                        <path d="M4 20C4 16.6863 7.58172 14 12 14C16.4183 14 20 16.6863 20 20" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                    </svg>
                                </div>
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($teacher_position) :
                            $badge_class = (stripos($teacher_position, 'проректор') !== false) ? 'badge-primary' : ((stripos($teacher_position, 'старший') !== false) ? 'badge-senior' : 'badge-default');
                        ?>
                            <span class="teacher-badge <?php echo $badge_class; ?>"><?php echo esc_html($teacher_position); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="teacher-card-content">
                        <h2 class="teacher-card-name">
                            <a href="<?php echo esc_url($teacher_link); ?>"><?php echo esc_html($term->name); ?></a>
                        </h2>
                        
                        <?php if (!empty($specializations)) : ?>
                            <div class="teacher-specializations">
                                <?php foreach (array_slice($specializations, 0, 2) as $spec) : ?>
                                    <span class="specialization-tag"><?php echo esc_html($spec->name); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($teacher_description) : ?>
                            <p class="teacher-card-description"><?php echo esc_html(wp_trim_words($teacher_description, 15, '...')); ?></p>
                        <?php endif; ?>
                        
                        <div class="teacher-card-meta">
                            <span class="teacher-courses-count">
                                <?php printf(_n('%d курс', '%d курсов', $courses_count, 'course-plugin'), $courses_count); ?>
                            </span>
                            <a href="<?php echo esc_url($teacher_link); ?>" class="teacher-view-profile">
                                <?php _e('Профиль', 'course-plugin'); ?> →
                            </a>
                        </div>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    
    <div class="teachers-shortcode-more">
        <a href="<?php echo esc_url(home_url('/teachers/')); ?>" class="teachers-more-link">
            <?php _e('Все преподаватели', 'course-plugin'); ?>
        </a>
    </div>
</div>
