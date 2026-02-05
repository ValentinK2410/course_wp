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
        
        // Поиск по фильтрам для курсов
        $('#filter-search-input').on('input', function() {
            var searchTerm = $(this).val().toLowerCase();
            filterSections(searchTerm, '#course-filters-form');
        });
        
        // Поиск по фильтрам для программ
        $('#filter-search-input-program').on('input', function() {
            var searchTerm = $(this).val().toLowerCase();
            filterSections(searchTerm, '#program-filters-form');
        });
        
        // Функция фильтрации секций фильтров
        function filterSections(searchTerm, formSelector) {
            var form = $(formSelector);
            var sections = form.find('.filter-section');
            
            if (searchTerm === '') {
                sections.show();
                sections.find('.filter-checkbox-label').show();
                return;
            }
            
            sections.each(function() {
                var section = $(this);
                var sectionTitle = section.find('.filter-section-title').text().toLowerCase();
                var labels = section.find('.filter-checkbox-label');
                var visibleCount = 0;
                
                // Проверяем заголовок секции
                var sectionMatches = sectionTitle.indexOf(searchTerm) !== -1;
                
                // Проверяем каждый элемент в секции
                labels.each(function() {
                    var label = $(this);
                    var labelText = label.find('span').text().toLowerCase();
                    
                    if (labelText.indexOf(searchTerm) !== -1 || sectionMatches) {
                        label.show();
                        visibleCount++;
                    } else {
                        label.hide();
                    }
                });
                
                // Показываем/скрываем секцию в зависимости от результатов
                if (sectionMatches || visibleCount > 0) {
                    section.show();
                } else {
                    section.hide();
                }
            });
        }
        
        // Анимация появления фильтров при загрузке
        $('.filter-section').each(function(index) {
            var $section = $(this);
            $section.css({
                'opacity': '0',
                'transform': 'translateY(20px)',
                'transition': 'all 0.3s ease'
            });
            
            setTimeout(function() {
                $section.css({
                    'opacity': '1',
                    'transform': 'translateY(0)'
                });
            }, index * 100);
        });
        
        // Плавная прокрутка к результатам при применении фильтров
        $('.filter-submit-btn').on('click', function(e) {
            var form = $(this).closest('form');
            var hasFilters = false;
            
            form.find('input[type="checkbox"]:checked, select').each(function() {
                if ($(this).val() && $(this).val() !== '') {
                    hasFilters = true;
                    return false;
                }
            });
            
            if (hasFilters) {
                setTimeout(function() {
                    $('html, body').animate({
                        scrollTop: $('.course-main-content, .program-main-content').offset().top - 100
                    }, 500);
                }, 100);
            }
        });
        
    });
    
})(jQuery);

