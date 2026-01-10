<?php
/**
 * Шаблон для страниц с Course Builder
 */

get_header();

global $post;

if (!$post) {
    return;
}

// Получаем данные builder
$builder = Course_Builder::get_instance();
$builder_frontend = Course_Builder_Frontend::get_instance();

// Рендерим контент builder
$builder_content = $builder_frontend->render($post->ID);

if (empty($builder_content)) {
    // Если контент пустой, показываем стандартный контент
    while (have_posts()) : the_post();
        the_content();
    endwhile;
} else {
    // Показываем контент builder
    echo $builder_content;
}

get_footer();
