(function ($) {
    'use strict';

    $(function () {
        // Color pickers on settings page
        if ($.fn.wpColorPicker) {
            $('.merineo-pb-color-field').wpColorPicker();
        }

        // Accordion toggle: ignore clicks on checkbox itself.
        $('.merineo-pb-accordion-header').on('click', function (event) {
            if ($(event.target).is('input[type="checkbox"]') || $(event.target).closest('label').length) {
                return;
            }

            var $accordion = $(this).closest('.merineo-pb-accordion');
            var isOpen = $accordion.hasClass('is-open');

            $accordion.toggleClass('is-open', !isOpen);
            $accordion
                .find('.merineo-pb-accordion-toggle')
                .attr('aria-expanded', !isOpen ? 'true' : 'false');
        });

        // Copy CSS selector buttons.
        $('.merineo-pb-copy-selector').on('click', function () {
            var $btn = $(this);
            var selector = $btn.data('copy-selector');

            if (!selector) {
                return;
            }

            function markCopied() {
                $btn.addClass('is-copied');
                setTimeout(function () {
                    $btn.removeClass('is-copied');
                }, 1000);
            }

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(selector).then(markCopied).catch(markCopied);
            } else {
                // Fallback for older browsers.
                var $temp = $('<input type="text" />')
                    .val(selector)
                    .appendTo('body');
                $temp[0].select();
                try {
                    document.execCommand('copy');
                } catch (e) {}
                $temp.remove();
                markCopied();
            }
        });
    });
})(jQuery);