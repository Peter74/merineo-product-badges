/**
 * Category-level badges editor (product_cat term).
 *
 * Handles:
 * - rendering badges list from hidden JSON input;
 * - adding/removing badges;
 * - syncing label + colors (bg_color, text_color) back to JSON;
 * - initializing WP color picker for each color field.
 */
/* global jQuery, merineoProductBadgesL10n */
(function () {
    'use strict';

    /**
     * Initialize a single term badges box.
     *
     * @param {HTMLElement} boxEl
     */
    function initBox(boxEl) {
        var inputSelector = boxEl.getAttribute('data-merineo-pb-target');
        if (!inputSelector) {
            return;
        }

        var input = document.querySelector(inputSelector);
        if (!input) {
            return;
        }

        var badges = [];

        // Parse initial JSON from hidden input.
        try {
            var parsed = JSON.parse(input.value || '[]');
            if (Array.isArray(parsed)) {
                badges = parsed;
            }
        } catch (e) {
            badges = [];
        }

        var listEl = boxEl.querySelector('.merineo-pb-badges-list');
        var addBtn = boxEl.querySelector('.merineo-pb-add-badge');
        var $box   = window.jQuery ? jQuery(boxEl) : null;

        /**
         * Update hidden input and optionally re-render UI.
         *
         * @param {boolean} doRender Whether to rerender after updating.
         */
        function update(doRender) {
            input.value = JSON.stringify(badges);
            if (doRender) {
                render();
            }
        }

        /**
         * Render badges UI and initialize color pickers.
         */
        function render() {
            if (!listEl) {
                return;
            }

            listEl.innerHTML = '';

            if (!badges.length) {
                var p = document.createElement('p');
                p.textContent =
                    (window.merineoProductBadgesL10n && merineoProductBadgesL10n.noBadges) ||
                    'No badges yet. Click "Add badge" to create one.';
                listEl.appendChild(p);
            }

            badges.forEach(function (badge, index) {
                var wrap = document.createElement('div');
                wrap.className = 'merineo-pb-badge-item postbox';

                var inner = document.createElement('div');
                inner.className = 'inside';

                // Header: "Text for badge" + remove X.
                var header = document.createElement('div');
                header.className = 'merineo-pb-badge-header';

                var title = document.createElement('strong');
                title.className = 'merineo-pb-badge-title';

                var headerTitle =
                    (window.merineoProductBadgesL10n && merineoProductBadgesL10n.titleLabel) ||
                    (window.merineoProductBadgesL10n && merineoProductBadgesL10n.labelPlaceholder) ||
                    'Text for badge';

                title.textContent = headerTitle;

                var removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'merineo-pb-badge-remove';
                removeBtn.setAttribute('aria-label', 'Remove badge');
                removeBtn.innerHTML = '&times;';
                removeBtn.addEventListener('click', function () {
                    badges.splice(index, 1);
                    update(true);
                });

                header.appendChild(title);
                header.appendChild(removeBtn);

                // Label input.
                var labelP = document.createElement('p');
                var labelInput = document.createElement('input');
                labelInput.type = 'text';
                labelInput.className = 'widefat';
                labelInput.value = badge.label || '';
                labelInput.placeholder =
                    (window.merineoProductBadgesL10n && merineoProductBadgesL10n.labelPlaceholder) ||
                    'Text for badge';
                labelInput.setAttribute('data-index', String(index));
                labelInput.addEventListener('input', function () {
                    var idx = parseInt(labelInput.getAttribute('data-index') || '', 10);
                    if (!isNaN(idx) && badges[idx]) {
                        badges[idx].label = labelInput.value;
                        update(false);
                    }
                });
                labelP.appendChild(labelInput);

                // Colors row: Background / Text.
                var colorsRow = document.createElement('div');
                colorsRow.className = 'merineo-pb-badge-colors';

                // Background color.
                var bgWrap = document.createElement('div');
                bgWrap.className = 'merineo-pb-color-wrap';

                var bgLabel = document.createElement('span');
                bgLabel.className = 'merineo-pb-color-label';
                bgLabel.textContent =
                    (window.merineoProductBadgesL10n && merineoProductBadgesL10n.backgroundLabel) ||
                    'Background';

                var bgInput = document.createElement('input');
                bgInput.type = 'text';
                bgInput.className = 'merineo-pb-color-field';
                bgInput.value = badge.bg_color || '';
                bgInput.placeholder = '#000000';
                bgInput.setAttribute('data-index', String(index));
                bgInput.setAttribute('data-type', 'bg_color');

                bgWrap.appendChild(bgLabel);
                bgWrap.appendChild(bgInput);

                // Text color.
                var textWrap = document.createElement('div');
                textWrap.className = 'merineo-pb-color-wrap';

                var textLabel = document.createElement('span');
                textLabel.className = 'merineo-pb-color-label';
                textLabel.textContent =
                    (window.merineoProductBadgesL10n && merineoProductBadgesL10n.textLabel) ||
                    'Text';

                var textInput = document.createElement('input');
                textInput.type = 'text';
                textInput.className = 'merineo-pb-color-field';
                textInput.value = badge.text_color || '';
                textInput.placeholder = '#ffffff';
                textInput.setAttribute('data-index', String(index));
                textInput.setAttribute('data-type', 'text_color');

                textWrap.appendChild(textLabel);
                textWrap.appendChild(textInput);

                colorsRow.appendChild(bgWrap);
                colorsRow.appendChild(textWrap);

                inner.appendChild(header);
                inner.appendChild(labelP);
                inner.appendChild(colorsRow);
                wrap.appendChild(inner);
                listEl.appendChild(wrap);
            });

            // Initialize WP color picker for all color fields in this box.
            if ($box && jQuery.fn.wpColorPicker) {
                $box
                    .find('.merineo-pb-color-field')
                    .wpColorPicker({
                        change: function (event, ui) {
                            jQuery(event.target)
                                .val(ui.color.toString())
                                .trigger('change');
                        },
                        clear: function (event) {
                            jQuery(event.target)
                                .val('')
                                .trigger('change');
                        },
                    });
            }
        }

        // Delegated handler for colors (bg_color / text_color).
        if ($box) {
            $box.off('change.merineoPbTerm').on('change.merineoPbTerm', '.merineo-pb-color-field', function () {
                var idx = parseInt(this.getAttribute('data-index') || '', 10);
                var type = this.getAttribute('data-type'); // bg_color | text_color

                if (isNaN(idx) || !badges[idx] || !type) {
                    return;
                }

                if (type !== 'bg_color' && type !== 'text_color') {
                    return;
                }

                badges[idx][type] = this.value;
                update(false);
            });
        }

        if (addBtn) {
            addBtn.addEventListener('click', function () {
                badges.push({
                    label: '',
                    bg_color: '',
                    text_color: '',
                });
                update(true);
            });
        }

        // Initial render.
        render();
    }

    document.addEventListener('DOMContentLoaded', function () {
        var boxes = document.querySelectorAll('.merineo-pb-term-badges');
        if (!boxes.length) {
            return;
        }

        boxes.forEach(function (box) {
            initBox(box);
        });
    });
})();