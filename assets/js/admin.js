/**
 * NitRedis — Admin JS
 * Global utilities; per-page logic lives in the view files.
 */
(function ($) {
    'use strict';

    // Auto-load metrics on dashboard page load.
    $(document).ready(function () {
        // Activate the correct nav link based on query param.
        var page = new URLSearchParams(window.location.search).get('page') || 'nitredis';
        $('.nitredis-nav__link').each(function () {
            var href = $(this).attr('href') || '';
            var linkPage = new URLSearchParams(href.split('?')[1] || '').get('page') || '';
            if (linkPage === page) {
                $(this).addClass('nitredis-nav__link--active');
            } else {
                $(this).removeClass('nitredis-nav__link--active');
            }
        });
    });

}(jQuery));
