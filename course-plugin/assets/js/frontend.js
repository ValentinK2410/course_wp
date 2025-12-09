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
        
        // Сортировка - автоматическая отправка формы при изменении
        $('#course-sort-select').on('change', function() {
            var form = $('#course-filters-form');
            var sortValue = $(this).val();
            
            // Добавляем параметр сортировки к форме
            if (form.length) {
                var action = form.attr('action') || window.location.pathname;
                var formData = form.serialize();
                
                // Добавляем сортировку
                if (formData) {
                    formData += '&sort=' + sortValue;
                } else {
                    formData = 'sort=' + sortValue;
                }
                
                // Перенаправляем с параметрами
                window.location.href = action + '?' + formData;
            } else {
                // Если формы нет, просто добавляем параметр к URL
                var url = new URL(window.location.href);
                url.searchParams.set('sort', sortValue);
                window.location.href = url.toString();
            }
        });
        
    });
    
})(jQuery);

