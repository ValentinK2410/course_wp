/**
 * JavaScript для фронтенда плагина "Курсы Про"
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // Переключение вида отображения
        $('.view-btn').on('click', function() {
            var view = $(this).data('view');
            var container = $('#courses-container');
            
            // Обновляем активную кнопку
            $('.view-btn').removeClass('active');
            $(this).addClass('active');
            
            // Обновляем вид контейнера
            container.attr('data-view', view);
            
            // Сохраняем выбор в localStorage
            localStorage.setItem('courseView', view);
        });
        
        // Загружаем сохраненный вид
        var savedView = localStorage.getItem('courseView');
        if (savedView) {
            $('#courses-container').attr('data-view', savedView);
            $('.view-btn[data-view="' + savedView + '"]').addClass('active').siblings().removeClass('active');
        }
        
        // Автоматическая отправка формы при изменении фильтров (опционально)
        // Раскомментируйте, если хотите автоматическую фильтрацию
        /*
        $('#course-filters-form select').on('change', function() {
            $('#course-filters-form').submit();
        });
        */
        
    });
    
})(jQuery);

