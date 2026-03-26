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
        
        // Подсказки при вводе в поиске архива курсов/программ (AJAX)
        function archiveSearchUrlForTerm(term) {
            var url = new URL(window.location.href);
            if (term) {
                url.searchParams.set('search', term);
            } else {
                url.searchParams.delete('search');
            }
            url.searchParams.delete('paged');
            return url.toString();
        }
        function bindArchiveSearchSuggest(inputSelector, postType) {
            if (typeof courseFrontend === 'undefined') {
                return;
            }
            var $input = $(inputSelector);
            if (!$input.length) {
                return;
            }
            var $box = $input.closest('.filter-search-box');
            var $suggest = $box.find('.archive-search-suggest');
            if (!$suggest.length) {
                $suggest = $('<div class="archive-search-suggest" role="listbox"></div>');
                $box.append($suggest);
            }
            var xhr = null;
            var debounceMs = 280;
            var debounceTimeout;
            function hideSuggest() {
                $suggest.empty().attr('hidden', true);
            }
            function showSuggest() {
                $suggest.removeAttr('hidden');
            }
            $input.on('input', function() {
                clearTimeout(debounceTimeout);
                var term = $input.val().trim();
                if (term.length < 2) {
                    if (xhr) {
                        xhr.abort();
                    }
                    hideSuggest();
                    return;
                }
                debounceTimeout = setTimeout(function() {
                    if (xhr) {
                        xhr.abort();
                    }
                    xhr = $.ajax({
                        url: courseFrontend.ajaxurl,
                        method: 'POST',
                        dataType: 'json',
                        data: {
                            action: 'course_archive_search_suggest',
                            nonce: courseFrontend.nonce,
                            term: term,
                            post_type: postType
                        }
                    }).done(function(res) {
                        if (!res || !res.success || !res.data) {
                            hideSuggest();
                            return;
                        }
                        var items = res.data.items || [];
                        $suggest.empty();
                        if (items.length === 0) {
                            $suggest.append(
                                $('<div class="archive-search-suggest-empty"></div>').text(
                                    courseFrontend.searchSuggestEmpty || ''
                                )
                            );
                        } else {
                            items.forEach(function(item) {
                                var $a = $('<a class="archive-search-suggest-item" role="option"></a>');
                                $a.attr('href', item.url).text(item.title);
                                $suggest.append($a);
                            });
                        }
                        var allLabel = courseFrontend.searchSuggestAll || '';
                        var $all = $('<a class="archive-search-suggest-all"></a>');
                        $all.attr('href', archiveSearchUrlForTerm(term)).text(allLabel);
                        $suggest.append($all);
                        showSuggest();
                    }).fail(function() {
                        hideSuggest();
                    });
                }, debounceMs);
            });
            $input.on('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    window.location.href = archiveSearchUrlForTerm($input.val().trim());
                }
                if (e.key === 'Escape') {
                    hideSuggest();
                }
            });
            $(document).on('mousedown.archiveSuggestClose', function(e) {
                if (!$box.is(e.target) && $box.has(e.target).length === 0) {
                    hideSuggest();
                }
            });
        }
        bindArchiveSearchSuggest('#course-archive-search-input', 'course');
        bindArchiveSearchSuggest('#program-archive-search-input', 'program');
        
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

