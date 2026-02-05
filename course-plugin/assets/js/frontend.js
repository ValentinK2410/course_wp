/**
 * JavaScript для фронтенда плагина "Курсы Про"
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // Переключение вида отображения для курсов
        $('.view-btn').on('click', function() {
            var view = $(this).data('view');
            var container = $('#courses-container, #programs-container');
            
            // Обновляем активную кнопку
            $('.view-btn').removeClass('active');
            $(this).addClass('active');
            
            // Обновляем вид контейнера
            container.attr('data-view', view);
            
            // Сохраняем выбор в localStorage
            var pageType = $('#programs-container').length ? 'program' : 'course';
            localStorage.setItem(pageType + 'View', view);
        });
        
        // Загружаем сохраненный вид для курсов
        var savedCourseView = localStorage.getItem('courseView');
        if (savedCourseView && $('#courses-container').length) {
            $('#courses-container').attr('data-view', savedCourseView);
            $('.view-btn[data-view="' + savedCourseView + '"]').addClass('active').siblings().removeClass('active');
        }
        
        // Загружаем сохраненный вид для программ
        var savedProgramView = localStorage.getItem('programView');
        if (savedProgramView && $('#programs-container').length) {
            $('#programs-container').attr('data-view', savedProgramView);
            $('.view-btn[data-view="' + savedProgramView + '"]').addClass('active').siblings().removeClass('active');
        }
        
        // Сортировка для курсов - автоматическая отправка формы при изменении
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
        
        // Сортировка для программ - автоматическая отправка формы при изменении
        $('#program-sort-select').on('change', function() {
            var form = $('#program-filters-form');
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

