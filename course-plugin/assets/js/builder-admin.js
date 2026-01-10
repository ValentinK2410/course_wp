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
            
            // Добавление новой секции
            $(document).on('click', '#course-builder-add-section', function() {
                CourseBuilderAdmin.addSection();
            });
            
            // Закрытие модального окна
            $(document).on('click', '.course-builder-modal-close, .course-builder-modal-cancel, .course-builder-modal-overlay', function() {
                $('#course-builder-widget-modal').hide();
            });
            
            // Сохранение настроек виджета
            $(document).on('click', '.course-builder-modal-save', function() {
                CourseBuilderAdmin.saveWidgetSettings();
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
                var $section = $(this);
                var section = {
                    id: $section.data('section-id') || 'section_' + Date.now(),
                    settings: {},
                    columns: []
                };
                
                $section.find('.course-builder-column').each(function() {
                    var $column = $(this);
                    // Получаем ширину из стиля или вычисляем процентное соотношение
                    var widthStyle = $column.css('width');
                    var width = 100; // По умолчанию 100%
                    
                    if (widthStyle) {
                        // Если ширина указана в процентах, извлекаем число
                        var match = widthStyle.match(/(\d+(?:\.\d+)?)%/);
                        if (match) {
                            width = parseFloat(match[1]);
                        } else {
                            // Если в пикселях, вычисляем процент от родителя
                            var parentWidth = $column.parent().width();
                            if (parentWidth > 0) {
                                width = (parseInt(widthStyle) / parentWidth) * 100;
                            }
                        }
                    }
                    
                    var column = {
                        id: $column.data('column-id') || 'col_' + Date.now(),
                        width: width,
                        settings: {},
                        widgets: []
                    };
                    
                    $column.find('.course-builder-widget').each(function() {
                        var $widget = $(this);
                        var widget = {
                            id: $widget.data('widget-id') || 'widget_' + Date.now(),
                            type: $widget.data('widget-type'),
                            settings: CourseBuilderAdmin.getWidgetSettings($widget)
                        };
                        column.widgets.push(widget);
                    });
                    
                    section.columns.push(column);
                });
                
                sections.push(section);
            });
            
            var data = {
                version: '1.0.0',
                sections: sections
            };
            
            console.log('Getting builder data:', data);
            return data;
        },
        
        getWidgetSettings: function($widget) {
            // Получаем настройки из jQuery data
            var settings = $widget.data('widget-settings');
            
            // Если настройки не найдены в jQuery data, пытаемся получить из HTML атрибута
            if (!settings || Object.keys(settings).length === 0) {
                var settingsAttr = $widget.attr('data-widget-settings');
                if (settingsAttr) {
                    try {
                        // Декодируем HTML entities и парсим JSON
                        settingsAttr = settingsAttr.replace(/&quot;/g, '"');
                        settings = JSON.parse(settingsAttr);
                        // Сохраняем в jQuery data для будущего использования
                        $widget.data('widget-settings', settings);
                    } catch (e) {
                        console.error('Error parsing widget settings:', e);
                        settings = {};
                    }
                } else {
                    settings = {};
                }
            }
            
            return settings || {};
        },
        
        addWidget: function(widgetType, sectionId) {
            console.log('Adding widget:', widgetType, 'to section:', sectionId);
            
            var $editor = $('#course-builder-editor');
            var $sections = $editor.find('.course-builder-section');
            
            // Если секций нет, создаем первую секцию с колонкой
            if ($sections.length === 0) {
                console.log('Creating first section and column');
                var newSectionId = 'section_' + Date.now();
                var columnId = 'col_' + Date.now();
                
                var sectionHtml = '<div class="course-builder-section" data-section-id="' + newSectionId + '">';
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
                $sections = $editor.find('.course-builder-section');
            }
            
            // Определяем целевую секцию
            var $targetSection;
            if (sectionId) {
                // Если указан ID секции, используем её
                $targetSection = $editor.find('.course-builder-section[data-section-id="' + sectionId + '"]');
            } else {
                // Иначе используем последнюю секцию (или первую, если только одна)
                $targetSection = $sections.last();
            }
            
            if ($targetSection.length === 0) {
                console.error('Target section not found');
                return;
            }
            
            // Находим колонки в целевой секции
            var $columns = $targetSection.find('.course-builder-column');
            
            if ($columns.length === 0) {
                // Создаем колонку, если её нет
                var columnId = 'col_' + Date.now();
                var columnHtml = '<div class="course-builder-column" data-column-id="' + columnId + '" style="width: 100%;">';
                columnHtml += '<div class="course-builder-widgets-list"></div>';
                columnHtml += '</div>';
                $targetSection.find('.course-builder-section-content').html(columnHtml);
                $columns = $targetSection.find('.course-builder-column');
            }
            
            // Используем первую колонку в целевой секции
            var $targetColumn = $columns.first();
            var $widgetsList = $targetColumn.find('.course-builder-widgets-list');
            
            if ($widgetsList.length === 0) {
                // Создаем список виджетов, если его нет
                $targetColumn.append('<div class="course-builder-widgets-list"></div>');
                $widgetsList = $targetColumn.find('.course-builder-widgets-list');
            }
            
            // Создаем виджет
            var widgetId = 'widget_' + Date.now();
            var $widget = $(CourseBuilderAdmin.renderWidget({
                id: widgetId,
                type: widgetType,
                settings: {}
            }));
            
            // Сохраняем настройки в data-атрибуте
            $widget.data('widget-settings', {});
            
            $widgetsList.append($widget);
            CourseBuilderAdmin.initSortable();
            
            console.log('Widget added successfully to section:', $targetSection.data('section-id'));
        },
        
        addSection: function() {
            var sectionId = 'section_' + Date.now();
            var columnId = 'col_' + Date.now();
            var sectionNumber = $('#course-builder-editor').find('.course-builder-section').length + 1;
            
            var sectionHtml = '<div class="course-builder-section" data-section-id="' + sectionId + '">';
            sectionHtml += '<div class="course-builder-section-header">';
            sectionHtml += '<h3>Секция ' + sectionNumber + '</h3>';
            sectionHtml += '<button class="course-builder-delete-section" style="float: right;">Удалить секцию</button>';
            sectionHtml += '</div>';
            sectionHtml += '<div class="course-builder-section-content">';
            sectionHtml += '<div class="course-builder-column" data-column-id="' + columnId + '" style="width: 100%;">';
            sectionHtml += '<div class="course-builder-widgets-list"></div>';
            sectionHtml += '</div>';
            sectionHtml += '</div>';
            sectionHtml += '</div>';
            
            // Удаляем пустое состояние, если оно есть
            $('#course-builder-editor').find('.course-builder-empty-state').remove();
            
            // Добавляем секцию
            if ($('#course-builder-editor').find('.course-builder-section').length === 0) {
                $('#course-builder-editor').html(sectionHtml);
            } else {
                $('#course-builder-editor').append(sectionHtml);
            }
            
            CourseBuilderAdmin.initSortable();
            CourseBuilderAdmin.saveBuilder();
        },
        
        editWidget: function(widgetId) {
            var $widget = $('.course-builder-widget[data-widget-id="' + widgetId + '"]');
            var widgetType = $widget.data('widget-type');
            var currentSettings = CourseBuilderAdmin.getWidgetSettings($widget);
            
            console.log('Editing widget:', widgetId, 'type:', widgetType, 'current settings:', currentSettings);
            
            // Сохраняем ID виджета в модальном окне
            $('#course-builder-widget-modal').data('editing-widget-id', widgetId);
            
            // Загружаем настройки виджета через AJAX
            $.ajax({
                url: courseBuilderAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'course_builder_get_widget_settings',
                    widget_type: widgetType,
                    nonce: courseBuilderAdmin.nonce
                },
                success: function(response) {
                    console.log('Widget settings response:', response);
                    if (response.success && response.data && response.data.fields) {
                        var html = '';
                        $.each(response.data.fields, function(index, field) {
                            var fieldValue = currentSettings[field.name];
                            if (fieldValue === undefined || fieldValue === null) {
                                fieldValue = (field.default !== undefined ? field.default : '');
                            }
                            html += CourseBuilderAdmin.renderSettingsField(field, fieldValue);
                        });
                        $('#course-builder-widget-settings').html(html);
                        $('#course-builder-widget-modal').show();
                    } else {
                        var errorMsg = 'Не удалось загрузить настройки виджета';
                        if (response.data && response.data.message) {
                            errorMsg += ': ' + response.data.message;
                        }
                        if (response.data && response.data.debug) {
                            console.error('Debug info:', response.data.debug);
                        }
                        alert(errorMsg);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading widget settings:', xhr, status, error);
                    alert('Ошибка загрузки настроек виджета: ' + error);
                }
            });
        },
        
        saveWidgetSettings: function() {
            var widgetId = $('#course-builder-widget-modal').data('editing-widget-id');
            var $widget = $('.course-builder-widget[data-widget-id="' + widgetId + '"]');
            var settings = {};
            
            // Собираем значения полей из формы
            $('#course-builder-widget-settings').find('input, textarea, select').each(function() {
                var $field = $(this);
                var name = $field.attr('name');
                if (name) {
                    // Извлекаем имя поля из формата widgets[widget_id][settings][field_name]
                    var match = name.match(/\[settings\]\[(.+?)\]$/);
                    if (match) {
                        var fieldName = match[1];
                        if ($field.attr('type') === 'checkbox') {
                            settings[fieldName] = $field.is(':checked') ? 1 : 0;
                        } else {
                            settings[fieldName] = $field.val();
                        }
                    }
                }
            });
            
            // Сохраняем настройки в виджете через data-атрибут
            $widget.data('widget-settings', settings);
            
            // Также обновляем HTML атрибут для сохранения при перезагрузке страницы
            var settingsJson = JSON.stringify(settings).replace(/"/g, '&quot;');
            $widget.attr('data-widget-settings', settingsJson);
            
            // Обновляем отображение виджета
            CourseBuilderAdmin.updateWidgetDisplay($widget);
            
            // Закрываем модальное окно
            $('#course-builder-widget-modal').hide();
            
            // Сохраняем данные
            CourseBuilderAdmin.saveBuilder();
        },
        
        renderSettingsField: function(field, value) {
            var fieldId = 'widget_setting_' + field.name + '_' + Date.now();
            var fieldName = 'widgets[temp][settings][' + field.name + ']';
            var html = '<div class="course-builder-field course-builder-field-' + field.type + '" style="margin-bottom: 15px;">';
            html += '<label for="' + fieldId + '" style="display: block; margin-bottom: 5px; font-weight: bold;">' + field.label + '</label>';
            
            switch (field.type) {
                case 'text':
                case 'url':
                case 'email':
                    html += '<input type="' + field.type + '" id="' + fieldId + '" name="' + fieldName + '" value="' + (value || '') + '" class="widefat">';
                    break;
                case 'textarea':
                    html += '<textarea id="' + fieldId + '" name="' + fieldName + '" class="widefat" rows="5">' + (value || '') + '</textarea>';
                    break;
                case 'select':
                    html += '<select id="' + fieldId + '" name="' + fieldName + '" class="widefat">';
                    if (field.options) {
                        $.each(field.options, function(optValue, optLabel) {
                            html += '<option value="' + optValue + '" ' + (value == optValue ? 'selected' : '') + '>' + optLabel + '</option>';
                        });
                    }
                    html += '</select>';
                    break;
                case 'checkbox':
                    html += '<input type="checkbox" id="' + fieldId + '" name="' + fieldName + '" value="1" ' + (value ? 'checked' : '') + '>';
                    break;
                case 'number':
                    html += '<input type="number" id="' + fieldId + '" name="' + fieldName + '" value="' + (value || '') + '" class="widefat" min="' + (field.min || '') + '" max="' + (field.max || '') + '" step="' + (field.step || '1') + '">';
                    break;
                case 'color':
                    html += '<input type="color" id="' + fieldId + '" name="' + fieldName + '" value="' + (value || '#000000') + '">';
                    break;
            }
            
            if (field.description) {
                html += '<p class="description" style="margin-top: 5px; color: #666; font-size: 12px;">' + field.description + '</p>';
            }
            
            html += '</div>';
            return html;
        },
        
        updateWidgetDisplay: function($widget) {
            var widgetType = $widget.data('widget-type');
            var settings = $widget.data('widget-settings') || {};
            
            // Обновляем содержимое виджета в зависимости от типа
            var $content = $widget.find('.course-builder-widget-content');
            var displayText = 'Widget: ' + widgetType;
            
            // Показываем основные настройки в превью
            if (settings.content) {
                displayText = settings.content.substring(0, 50) + (settings.content.length > 50 ? '...' : '');
            } else if (settings.title) {
                displayText = settings.title;
            } else if (settings.text) {
                displayText = settings.text.substring(0, 50) + (settings.text.length > 50 ? '...' : '');
            }
            
            $content.html('<p>' + displayText + '</p>');
        },
        
        loadBuilder: function() {
            var postId = courseBuilderAdmin.postId;
            
            console.log('Loading builder data for post:', postId);
            
            $.ajax({
                url: courseBuilderAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'course_builder_load',
                    post_id: postId,
                    nonce: courseBuilderAdmin.loadNonce
                },
                success: function(response) {
                    console.log('Load builder response:', response);
                    if (response.success && response.data) {
                        CourseBuilderAdmin.renderBuilder(response.data);
                    } else {
                        console.error('Failed to load builder data:', response);
                        // Показываем пустое состояние при ошибке
                        $('#course-builder-editor').html('<div class="course-builder-empty-state"><p>Начните добавлять виджеты из боковой панели</p></div>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading builder data:', xhr, status, error);
                    // Показываем пустое состояние при ошибке
                    $('#course-builder-editor').html('<div class="course-builder-empty-state"><p>Ошибка загрузки данных. Начните добавлять виджеты из боковой панели</p></div>');
                }
            });
        },
        
        renderBuilder: function(data) {
            console.log('Rendering builder data:', data);
            
            // Рендеринг структуры builder из данных
            if (data.sections && data.sections.length > 0) {
                var html = '';
                
                $.each(data.sections, function(index, section) {
                    html += '<div class="course-builder-section" data-section-id="' + section.id + '">';
                    html += '<div class="course-builder-section-header">';
                    html += '<h3>Секция ' + (index + 1) + '</h3>';
                    html += '<button class="course-builder-delete-section" style="float: right;">Удалить секцию</button>';
                    html += '</div>';
                    html += '<div class="course-builder-section-content">';
                    
                    if (section.columns && section.columns.length > 0) {
                        $.each(section.columns, function(colIndex, column) {
                            var columnWidth = column.width || 100;
                            html += '<div class="course-builder-column" data-column-id="' + column.id + '" style="width: ' + columnWidth + '%;">';
                            html += '<div class="course-builder-widgets-list">';
                            
                            if (column.widgets && column.widgets.length > 0) {
                                $.each(column.widgets, function(widgetIndex, widget) {
                                    html += CourseBuilderAdmin.renderWidget(widget);
                                });
                            }
                            
                            html += '</div>';
                            html += '</div>';
                        });
                    } else {
                        // Если колонок нет, создаем одну по умолчанию
                        var columnId = 'col_' + Date.now();
                        html += '<div class="course-builder-column" data-column-id="' + columnId + '" style="width: 100%;">';
                        html += '<div class="course-builder-widgets-list"></div>';
                        html += '</div>';
                    }
                    
                    html += '</div>';
                    html += '</div>';
                });
                
                $('#course-builder-editor').html(html);
                
                // Восстанавливаем настройки виджетов из HTML атрибутов в jQuery data
                $('#course-builder-editor').find('.course-builder-widget').each(function() {
                    var $widget = $(this);
                    // Это автоматически восстановит настройки из атрибута data-widget-settings
                    CourseBuilderAdmin.getWidgetSettings($widget);
                });
                
                CourseBuilderAdmin.initSortable();
                
                console.log('Builder rendered successfully');
            } else {
                console.log('No sections found in data, showing empty state');
                // Показываем пустое состояние, если секций нет
                $('#course-builder-editor').html('<div class="course-builder-empty-state"><p>Начните добавлять виджеты из боковой панели</p></div>');
            }
        },
        
        renderWidget: function(widget) {
            var settings = widget.settings || {};
            var displayText = 'Widget: ' + widget.type;
            
            // Показываем основные настройки в превью
            if (settings.content) {
                displayText = settings.content.substring(0, 50) + (settings.content.length > 50 ? '...' : '');
            } else if (settings.title) {
                displayText = settings.title;
            } else if (settings.text) {
                displayText = settings.text.substring(0, 50) + (settings.text.length > 50 ? '...' : '');
            }
            
            // Экранируем JSON для безопасного использования в HTML атрибуте
            var settingsJson = JSON.stringify(settings).replace(/"/g, '&quot;');
            
            var html = '<div class="course-builder-widget" data-widget-id="' + widget.id + '" data-widget-type="' + widget.type + '" data-widget-settings="' + settingsJson + '">';
            html += '<div class="course-builder-widget-handle">';
            html += '<span class="dashicons dashicons-move"></span>';
            html += '<span class="widget-title">' + widget.type + '</span>';
            html += '</div>';
            html += '<div class="course-builder-widget-content">';
            html += '<p>' + displayText + '</p>';
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
