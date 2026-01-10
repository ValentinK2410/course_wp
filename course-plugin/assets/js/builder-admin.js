/**
 * Course Builder Admin JavaScript
 */

(function($) {
    'use strict';
    
    // Делаем объект глобально доступным
    window.CourseBuilderAdmin = {
        
        init: function() {
            this.bindEvents();
            this.initSortable();
        },
        
        bindEvents: function() {
            // Кнопка включения builder
            $(document).on('click', '#course-builder-enable-button', function(e) {
                e.preventDefault();
                var postId = courseBuilderAdmin.postId;
                
                $.ajax({
                    url: courseBuilderAdmin.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'course_builder_enable',
                        post_id: postId,
                        nonce: courseBuilderAdmin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        }
                    }
                });
            });
            
            // Сохранение данных builder
            $(document).on('click', '.course-builder-save', function() {
                CourseBuilderAdmin.saveBuilder();
            });
            
            // Добавление виджета
            $(document).on('click', '.course-builder-add-widget', function() {
                var widgetType = $(this).data('widget-type');
                CourseBuilderAdmin.addWidget(widgetType);
            });
            
            // Удаление виджета
            $(document).on('click', '.course-builder-delete-widget', function() {
                if (confirm(courseBuilderAdmin.strings.delete + '?')) {
                    $(this).closest('.course-builder-widget').remove();
                    CourseBuilderAdmin.saveBuilder();
                }
            });
            
            // Редактирование виджета
            $(document).on('click', '.course-builder-edit-widget', function() {
                var widgetId = $(this).closest('.course-builder-widget').data('widget-id');
                CourseBuilderAdmin.editWidget(widgetId);
            });
            
            // Удаление секции
            $(document).on('click', '.course-builder-delete-section', function(e) {
                e.preventDefault();
                if (confirm('Удалить эту секцию?')) {
                    $(this).closest('.course-builder-section').remove();
                    CourseBuilderAdmin.saveBuilder();
                    
                    // Если секций не осталось, показываем пустое состояние
                    if ($('#course-builder-editor').find('.course-builder-section').length === 0) {
                        $('#course-builder-editor').html('<div class="course-builder-empty-state"><p>Начните добавлять виджеты из боковой панели</p></div>');
                    }
                }
            });
        },
        
        initSortable: function() {
            // Инициализация drag-and-drop для виджетов
            if ($.fn.sortable) {
                $('.course-builder-widgets-list').sortable({
                    handle: '.course-builder-widget-handle',
                    placeholder: 'course-builder-widget-placeholder',
                    tolerance: 'pointer',
                    update: function() {
                        CourseBuilderAdmin.saveBuilder();
                    }
                });
            }
        },
        
        saveBuilder: function() {
            var postId = courseBuilderAdmin.postId;
            var data = CourseBuilderAdmin.getBuilderData();
            
            $.ajax({
                url: courseBuilderAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'course_builder_save',
                    post_id: postId,
                    data: JSON.stringify(data),
                    nonce: courseBuilderAdmin.nonce
                },
                beforeSend: function() {
                    $('.course-builder-save').text(courseBuilderAdmin.strings.saving).prop('disabled', true);
                },
                success: function(response) {
                    if (response.success) {
                        $('.course-builder-save').text(courseBuilderAdmin.strings.saved).prop('disabled', false);
                        setTimeout(function() {
                            $('.course-builder-save').text(courseBuilderAdmin.strings.save);
                        }, 2000);
                    } else {
                        alert(courseBuilderAdmin.strings.error + ': ' + (response.data.message || 'Unknown error'));
                        $('.course-builder-save').text(courseBuilderAdmin.strings.save).prop('disabled', false);
                    }
                },
                error: function() {
                    alert(courseBuilderAdmin.strings.error);
                    $('.course-builder-save').text(courseBuilderAdmin.strings.save).prop('disabled', false);
                }
            });
        },
        
        getBuilderData: function() {
            var sections = [];
            
            $('.course-builder-section').each(function() {
                var section = {
                    id: $(this).data('section-id') || 'section_' + Date.now(),
                    settings: {},
                    columns: []
                };
                
                $(this).find('.course-builder-column').each(function() {
                    var column = {
                        id: $(this).data('column-id') || 'col_' + Date.now(),
                        width: parseInt($(this).css('width')) || 50,
                        settings: {},
                        widgets: []
                    };
                    
                    $(this).find('.course-builder-widget').each(function() {
                        var widget = {
                            id: $(this).data('widget-id') || 'widget_' + Date.now(),
                            type: $(this).data('widget-type'),
                            settings: CourseBuilderAdmin.getWidgetSettings($(this))
                        };
                        column.widgets.push(widget);
                    });
                    
                    section.columns.push(column);
                });
                
                sections.push(section);
            });
            
            return {
                version: '1.0.0',
                sections: sections
            };
        },
        
        getWidgetSettings: function($widget) {
            var settings = {};
            
            // Получаем настройки из полей формы виджета
            $widget.find('input, select, textarea').each(function() {
                var $field = $(this);
                var name = $field.attr('name');
                
                if (name && name.indexOf('settings[') !== -1) {
                    var key = name.match(/settings\[(.+)\]/)[1];
                    var value = $field.val();
                    
                    if ($field.attr('type') === 'checkbox') {
                        value = $field.is(':checked') ? 1 : 0;
                    }
                    
                    settings[key] = value;
                }
            });
            
            return settings;
        },
        
        addWidget: function(widgetType) {
            console.log('Adding widget:', widgetType);
            
            // Проверяем, есть ли уже секции в редакторе
            var $editor = $('#course-builder-editor');
            var $sections = $editor.find('.course-builder-section');
            
            // Если секций нет, создаем первую секцию с колонкой
            if ($sections.length === 0) {
                console.log('Creating first section and column');
                var sectionId = 'section_' + Date.now();
                var columnId = 'col_' + Date.now();
                
                var sectionHtml = '<div class="course-builder-section" data-section-id="' + sectionId + '">';
                sectionHtml += '<div class="course-builder-section-header">';
                sectionHtml += '<h3>Секция 1</h3>';
                sectionHtml += '<button class="course-builder-delete-section" style="float: right;">Удалить секцию</button>';
                sectionHtml += '</div>';
                sectionHtml += '<div class="course-builder-section-content">';
                sectionHtml += '<div class="course-builder-column" data-column-id="' + columnId + '" style="width: 100%;">';
                sectionHtml += '<div class="course-builder-widgets-list"></div>';
                sectionHtml += '</div>';
                sectionHtml += '</div>';
                sectionHtml += '</div>';
                
                // Удаляем пустое состояние, если оно есть
                $editor.find('.course-builder-empty-state').remove();
                $editor.html(sectionHtml);
            }
            
            // Находим первую колонку или создаем новую
            var $firstSection = $editor.find('.course-builder-section').first();
            var $columns = $firstSection.find('.course-builder-column');
            
            if ($columns.length === 0) {
                // Создаем колонку, если её нет
                var columnId = 'col_' + Date.now();
                var columnHtml = '<div class="course-builder-column" data-column-id="' + columnId + '" style="width: 100%;">';
                columnHtml += '<div class="course-builder-widgets-list"></div>';
                columnHtml += '</div>';
                $firstSection.find('.course-builder-section-content').html(columnHtml);
                $columns = $firstSection.find('.course-builder-column');
            }
            
            // Используем первую колонку
            var $firstColumn = $columns.first();
            var $widgetsList = $firstColumn.find('.course-builder-widgets-list');
            
            if ($widgetsList.length === 0) {
                // Создаем список виджетов, если его нет
                $firstColumn.append('<div class="course-builder-widgets-list"></div>');
                $widgetsList = $firstColumn.find('.course-builder-widgets-list');
            }
            
            // Создаем виджет
            var widgetId = 'widget_' + Date.now();
            var widgetHtml = CourseBuilderAdmin.renderWidget({
                id: widgetId,
                type: widgetType,
                settings: {}
            });
            
            $widgetsList.append(widgetHtml);
            CourseBuilderAdmin.initSortable();
            
            console.log('Widget added successfully');
        },
        
        editWidget: function(widgetId) {
            // Открыть модальное окно с настройками виджета
            var $widget = $('.course-builder-widget[data-widget-id="' + widgetId + '"]');
            var widgetType = $widget.data('widget-type');
            
            // Здесь должна быть логика открытия модального окна с настройками
            alert('Редактирование виджета: ' + widgetType);
        },
        
        loadBuilder: function() {
            var postId = courseBuilderAdmin.postId;
            
            $.ajax({
                url: courseBuilderAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'course_builder_load',
                    post_id: postId,
                    nonce: courseBuilderAdmin.loadNonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        CourseBuilderAdmin.renderBuilder(response.data);
                    }
                }
            });
        },
        
        renderBuilder: function(data) {
            // Рендеринг структуры builder из данных
            if (data.sections && data.sections.length > 0) {
                var html = '';
                
                $.each(data.sections, function(index, section) {
                    html += '<div class="course-builder-section" data-section-id="' + section.id + '">';
                    html += '<div class="course-builder-section-header">';
                    html += '<h3>Секция ' + (index + 1) + '</h3>';
                    html += '</div>';
                    html += '<div class="course-builder-section-content">';
                    
                    if (section.columns && section.columns.length > 0) {
                        $.each(section.columns, function(colIndex, column) {
                            html += '<div class="course-builder-column" data-column-id="' + column.id + '" style="width: ' + column.width + '%;">';
                            html += '<div class="course-builder-widgets-list">';
                            
                            if (column.widgets && column.widgets.length > 0) {
                                $.each(column.widgets, function(widgetIndex, widget) {
                                    html += CourseBuilderAdmin.renderWidget(widget);
                                });
                            }
                            
                            html += '</div>';
                            html += '</div>';
                        });
                    }
                    
                    html += '</div>';
                    html += '</div>';
                });
                
                $('.course-builder-editor').html(html);
                CourseBuilderAdmin.initSortable();
            }
        },
        
        renderWidget: function(widget) {
            var html = '<div class="course-builder-widget" data-widget-id="' + widget.id + '" data-widget-type="' + widget.type + '">';
            html += '<div class="course-builder-widget-handle">';
            html += '<span class="dashicons dashicons-move"></span>';
            html += '<span class="widget-title">' + widget.type + '</span>';
            html += '</div>';
            html += '<div class="course-builder-widget-content">';
            html += '<p>Widget: ' + widget.type + '</p>';
            html += '</div>';
            html += '<div class="course-builder-widget-actions">';
            html += '<button class="course-builder-edit-widget">' + courseBuilderAdmin.strings.edit + '</button>';
            html += '<button class="course-builder-delete-widget">' + courseBuilderAdmin.strings.delete + '</button>';
            html += '</div>';
            html += '</div>';
            
            return html;
        }
    };
    
    $(document).ready(function() {
        CourseBuilderAdmin.init();
    });
    
})(jQuery);
