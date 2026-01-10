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
                    var $widget = $(this).closest('.course-builder-widget');
                    $widget.remove();
                    
                    // Проверяем, остались ли виджеты в секции
                    var $section = $widget.closest('.course-builder-section');
                    var $remainingWidgets = $section.find('.course-builder-widget');
                    if ($remainingWidgets.length === 0) {
                        // Если виджетов не осталось, проверяем все секции
                        var $allSections = $('#course-builder-editor').find('.course-builder-section');
                        if ($allSections.length === 0) {
                            $('#course-builder-editor').html('<div class="course-builder-empty-state"><p>Начните добавлять виджеты из боковой панели</p></div>');
                        }
                    }
                    
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
                // Уничтожаем предыдущую инициализацию, если была
                $('.course-builder-widgets-list').sortable('destroy');
                
                $('.course-builder-widgets-list').sortable({
                    handle: '.course-builder-widget-handle',
                    placeholder: 'course-builder-widget-placeholder',
                    tolerance: 'pointer',
                    update: function() {
                        console.log('Widget order changed, saving...');
                        CourseBuilderAdmin.saveBuilder();
                    }
                });
            }
        },
        
        saveBuilder: function() {
            // Проверяем доступность переменных
            if (typeof courseBuilderAdmin === 'undefined') {
                console.error('courseBuilderAdmin is not defined!');
                alert('Ошибка: не удалось инициализировать builder. Перезагрузите страницу.');
                return;
            }
            
            var postId = courseBuilderAdmin.postId;
            if (!postId || postId === 0) {
                console.error('Post ID is missing or invalid:', postId);
                alert('Ошибка: не указан ID поста. Перезагрузите страницу.');
                return;
            }
            
            var data = CourseBuilderAdmin.getBuilderData();
            
            console.log('Saving builder data for post:', postId);
            console.log('Data to save:', JSON.stringify(data, null, 2));
            console.log('Sections count:', data.sections ? data.sections.length : 0);
            
            // Проверяем, что есть данные для сохранения
            if (!data.sections || data.sections.length === 0) {
                console.warn('No sections to save, but saving anyway to clear old data');
            }
            
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
                    if ($('.course-builder-save').length > 0) {
                        $('.course-builder-save').text(courseBuilderAdmin.strings.saving).prop('disabled', true);
                    }
                },
                success: function(response) {
                    console.log('Save response:', response);
                    if (response.success) {
                        if ($('.course-builder-save').length > 0) {
                            $('.course-builder-save').text(courseBuilderAdmin.strings.saved).prop('disabled', false);
                            setTimeout(function() {
                                $('.course-builder-save').text(courseBuilderAdmin.strings.save);
                            }, 2000);
                        }
                        console.log('Data saved successfully');
                        if (response.data) {
                            console.log('Saved data verified:', response.data);
                        }
                    } else {
                        console.error('Save failed:', response.data);
                        var errorMsg = courseBuilderAdmin.strings.error + ': ' + (response.data && response.data.message ? response.data.message : 'Unknown error');
                        alert(errorMsg);
                        if ($('.course-builder-save').length > 0) {
                            $('.course-builder-save').text(courseBuilderAdmin.strings.save).prop('disabled', false);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Save AJAX error:', xhr, status, error);
                    console.error('Response text:', xhr.responseText);
                    var errorMsg = courseBuilderAdmin.strings.error + ': ' + error;
                    if (xhr.responseText) {
                        try {
                            var errorResponse = JSON.parse(xhr.responseText);
                            if (errorResponse.data && errorResponse.data.message) {
                                errorMsg += ' - ' + errorResponse.data.message;
                            }
                        } catch (e) {
                            // Игнорируем ошибку парсинга
                        }
                    }
                    alert(errorMsg);
                    if ($('.course-builder-save').length > 0) {
                        $('.course-builder-save').text(courseBuilderAdmin.strings.save).prop('disabled', false);
                    }
                }
            });
        },
        
        getBuilderData: function() {
            var sections = [];
            var $editor = $('#course-builder-editor');
            
            // Проверяем, что редактор существует
            if ($editor.length === 0) {
                console.error('Course builder editor not found!');
                return {
                    version: '1.0.0',
                    sections: []
                };
            }
            
            var $sections = $editor.find('.course-builder-section');
            console.log('Found ' + $sections.length + ' sections in editor');
            
            $sections.each(function(index) {
                var $section = $(this);
                var sectionId = $section.data('section-id');
                
                // Если ID нет, создаем новый
                if (!sectionId) {
                    sectionId = 'section_' + Date.now() + '_' + index;
                    $section.attr('data-section-id', sectionId);
                }
                
                var section = {
                    id: sectionId,
                    settings: {},
                    columns: []
                };
                
                var $columns = $section.find('.course-builder-column');
                console.log('Section ' + sectionId + ' has ' + $columns.length + ' columns');
                
                $columns.each(function(colIndex) {
                    var $column = $(this);
                    var columnId = $column.data('column-id');
                    
                    // Если ID нет, создаем новый
                    if (!columnId) {
                        columnId = 'col_' + Date.now() + '_' + colIndex;
                        $column.attr('data-column-id', columnId);
                    }
                    
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
                        id: columnId,
                        width: width,
                        settings: {},
                        widgets: []
                    };
                    
                    var $widgets = $column.find('.course-builder-widget');
                    console.log('Column ' + columnId + ' has ' + $widgets.length + ' widgets');
                    
                    $widgets.each(function(widgetIndex) {
                        var $widget = $(this);
                        var widgetId = $widget.data('widget-id');
                        var widgetType = $widget.data('widget-type');
                        
                        // Если ID нет, создаем новый
                        if (!widgetId) {
                            widgetId = 'widget_' + Date.now() + '_' + widgetIndex;
                            $widget.attr('data-widget-id', widgetId);
                        }
                        
                        // Если тип не указан, пропускаем виджет
                        if (!widgetType) {
                            console.warn('Widget ' + widgetId + ' has no type, skipping');
                            return;
                        }
                        
                        var widget = {
                            id: widgetId,
                            type: widgetType,
                            settings: CourseBuilderAdmin.getWidgetSettings($widget)
                        };
                        
                        column.widgets.push(widget);
                        console.log('Added widget:', widgetId, 'type:', widgetType, 'settings:', widget.settings);
                    });
                    
                    section.columns.push(column);
                });
                
                sections.push(section);
            });
            
            var data = {
                version: '1.0.0',
                sections: sections
            };
            
            console.log('Getting builder data - total sections:', sections.length);
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
            
            // Автоматическое сохранение после добавления виджета
            CourseBuilderAdmin.saveBuilder();
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
            // Проверяем доступность переменных
            if (typeof courseBuilderAdmin === 'undefined') {
                console.error('courseBuilderAdmin is not defined in loadBuilder!');
                return;
            }
            
            var postId = courseBuilderAdmin.postId;
            
            if (!postId || postId === 0) {
                console.error('Post ID is missing or invalid in loadBuilder:', postId);
                return;
            }
            
            console.log('Loading builder data for post:', postId);
            console.log('AJAX URL:', courseBuilderAdmin.ajaxUrl);
            console.log('Load nonce:', courseBuilderAdmin.loadNonce ? 'present' : 'missing');
            
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
                    console.log('Response success:', response.success);
                    console.log('Response data:', response.data);
                    
                    if (response.success && response.data) {
                        // PHP возвращает данные в структуре: { success: true, data: { data: { sections: [...] } } }
                        // Нужно извлечь вложенные данные
                        var builderData = response.data.data || response.data;
                        
                        console.log('Extracted builder data:', builderData);
                        console.log('Builder data sections:', builderData.sections);
                        
                        // Проверяем наличие секций в данных
                        if (builderData.sections && builderData.sections.length > 0) {
                            console.log('Found ' + builderData.sections.length + ' sections, rendering...');
                            CourseBuilderAdmin.renderBuilder(builderData);
                        } else {
                            console.log('No sections found in data, showing empty state');
                            console.log('Data structure:', JSON.stringify(builderData, null, 2));
                            $('#course-builder-editor').html('<div class="course-builder-empty-state"><p>Начните добавлять виджеты из боковой панели</p></div>');
                        }
                    } else {
                        console.error('Failed to load builder data:', response);
                        if (response.data && response.data.message) {
                            console.error('Error message:', response.data.message);
                        }
                        // Показываем пустое состояние при ошибке
                        $('#course-builder-editor').html('<div class="course-builder-empty-state"><p>Начните добавлять виджеты из боковой панели</p></div>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading builder data:', xhr, status, error);
                    console.error('Response text:', xhr.responseText);
                    // Показываем пустое состояние при ошибке
                    $('#course-builder-editor').html('<div class="course-builder-empty-state"><p>Ошибка загрузки данных. Начните добавлять виджеты из боковой панели</p></div>');
                }
            });
        },
        
        renderBuilder: function(data) {
            console.log('Rendering builder data:', data);
            console.log('Data structure:', JSON.stringify(data, null, 2));
            
            // Рендеринг структуры builder из данных
            if (data.sections && data.sections.length > 0) {
                console.log('Found ' + data.sections.length + ' sections to render');
                var html = '';
                var totalWidgets = 0;
                
                $.each(data.sections, function(index, section) {
                    console.log('Rendering section ' + (index + 1) + ':', section.id);
                    html += '<div class="course-builder-section" data-section-id="' + (section.id || 'section_' + Date.now() + '_' + index) + '">';
                    html += '<div class="course-builder-section-header">';
                    html += '<h3>Секция ' + (index + 1) + '</h3>';
                    html += '<button class="course-builder-delete-section" style="float: right;">Удалить секцию</button>';
                    html += '</div>';
                    html += '<div class="course-builder-section-content">';
                    
                    if (section.columns && section.columns.length > 0) {
                        console.log('Section ' + (index + 1) + ' has ' + section.columns.length + ' columns');
                        $.each(section.columns, function(colIndex, column) {
                            var columnWidth = column.width || 100;
                            var columnId = column.id || 'col_' + Date.now() + '_' + colIndex;
                            html += '<div class="course-builder-column" data-column-id="' + columnId + '" style="width: ' + columnWidth + '%;">';
                            html += '<div class="course-builder-widgets-list">';
                            
                            if (column.widgets && column.widgets.length > 0) {
                                console.log('Column ' + (colIndex + 1) + ' has ' + column.widgets.length + ' widgets');
                                $.each(column.widgets, function(widgetIndex, widget) {
                                    if (!widget.id) {
                                        widget.id = 'widget_' + Date.now() + '_' + widgetIndex;
                                    }
                                    if (!widget.type) {
                                        console.warn('Widget without type found, skipping:', widget);
                                        return;
                                    }
                                    html += CourseBuilderAdmin.renderWidget(widget);
                                    totalWidgets++;
                                });
                            }
                            
                            html += '</div>';
                            html += '</div>';
                        });
                    } else {
                        console.log('Section ' + (index + 1) + ' has no columns, creating default');
                        // Если колонок нет, создаем одну по умолчанию
                        var columnId = 'col_' + Date.now();
                        html += '<div class="course-builder-column" data-column-id="' + columnId + '" style="width: 100%;">';
                        html += '<div class="course-builder-widgets-list"></div>';
                        html += '</div>';
                    }
                    
                    html += '</div>';
                    html += '</div>';
                });
                
                console.log('Total widgets to render: ' + totalWidgets);
                $('#course-builder-editor').html(html);
                
                // Восстанавливаем настройки виджетов из HTML атрибутов в jQuery data
                var restoredCount = 0;
                $('#course-builder-editor').find('.course-builder-widget').each(function() {
                    var $widget = $(this);
                    // Это автоматически восстановит настройки из атрибута data-widget-settings
                    var settings = CourseBuilderAdmin.getWidgetSettings($widget);
                    if (settings && Object.keys(settings).length > 0) {
                        restoredCount++;
                        console.log('Restored settings for widget:', $widget.data('widget-id'), settings);
                    }
                    // Обновляем отображение виджета с восстановленными настройками
                    CourseBuilderAdmin.updateWidgetDisplay($widget);
                });
                
                console.log('Restored settings for ' + restoredCount + ' widgets');
                
                // Инициализируем сортировку после небольшой задержки, чтобы DOM обновился
                setTimeout(function() {
                    CourseBuilderAdmin.initSortable();
                }, 100);
                
                console.log('Builder rendered successfully with ' + totalWidgets + ' widgets');
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
