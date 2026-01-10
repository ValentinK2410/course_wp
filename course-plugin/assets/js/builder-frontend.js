/**
 * Course Builder Frontend JavaScript
 */

(function($) {
    'use strict';
    
    var CourseBuilderFrontend = {
        
        init: function() {
            this.initFilters();
        },
        
        initFilters: function() {
            // AJAX фильтрация курсов
            $('.course-builder-course-filter[data-ajax="1"]').on('submit', function(e) {
                e.preventDefault();
                
                var $form = $(this);
                var formData = $form.serialize();
                var actionUrl = $form.attr('action');
                
                $.ajax({
                    url: actionUrl,
                    type: 'GET',
                    data: formData,
                    success: function(response) {
                        // Обновляем список курсов
                        var $coursesContainer = $('.courses-container');
                        if ($coursesContainer.length) {
                            var $newContent = $(response).find('.courses-container');
                            if ($newContent.length) {
                                $coursesContainer.html($newContent.html());
                            }
                        }
                    }
                });
            });
        }
    };
    
    $(document).ready(function() {
        CourseBuilderFrontend.init();
    });
    
})(jQuery);
