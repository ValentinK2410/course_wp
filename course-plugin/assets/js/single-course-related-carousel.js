/**
 * Карусель «Другие курсы по теме» на странице курса.
 */
(function () {
    'use strict';

    document.querySelectorAll('.js-related-courses-carousel').forEach(function (root) {
        var viewport = root.querySelector('.related-carousel__viewport');
        var track = root.querySelector('.related-carousel__track');
        var slides = track ? track.querySelectorAll('.related-carousel__slide') : [];
        var prevBtn = root.querySelector('.related-carousel__prev');
        var nextBtn = root.querySelector('.related-carousel__next');

        if (!viewport || !track || !slides.length || !prevBtn || !nextBtn) {
            return;
        }

        var index = 0;
        var gap = 24;
        var slideWidth = 0;
        var visible = 3;
        var resizeTimer = null;

        function getVisible() {
            var w = window.innerWidth;
            if (w <= 640) {
                return 1;
            }
            if (w <= 1024) {
                return 2;
            }
            return 3;
        }

        function layout() {
            visible = getVisible();
            var n = slides.length;
            var vw = viewport.getBoundingClientRect().width;
            slideWidth = n > 0 ? (vw - gap * (visible - 1)) / visible : 0;

            slides.forEach(function (s) {
                s.style.flexShrink = '0';
                s.style.width = slideWidth + 'px';
            });

            var maxIdx = Math.max(0, n - visible);
            if (index > maxIdx) {
                index = maxIdx;
            }

            var needNav = n > visible;
            prevBtn.hidden = !needNav;
            nextBtn.hidden = !needNav;
            root.classList.toggle('related-carousel--no-nav', !needNav);

            updateTransform();
        }

        function updateTransform() {
            var offset = index * (slideWidth + gap);
            track.style.transform = 'translateX(-' + offset + 'px)';
        }

        function goNext() {
            var n = slides.length;
            var maxIdx = Math.max(0, n - visible);
            if (index >= maxIdx) {
                index = 0;
            } else {
                index += 1;
            }
            updateTransform();
        }

        function goPrev() {
            var n = slides.length;
            var maxIdx = Math.max(0, n - visible);
            if (index <= 0) {
                index = maxIdx;
            } else {
                index -= 1;
            }
            updateTransform();
        }

        prevBtn.addEventListener('click', goPrev);
        nextBtn.addEventListener('click', goNext);

        window.addEventListener('resize', function () {
            if (resizeTimer) {
                clearTimeout(resizeTimer);
            }
            resizeTimer = setTimeout(layout, 120);
        });

        layout();
    });
})();
