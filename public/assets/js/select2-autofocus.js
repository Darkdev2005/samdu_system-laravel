/* global jQuery */
(function () {
    'use strict';

    function focusOpenSearchField(seedChar) {
        window.requestAnimationFrame(function () {
            var input = document.querySelector('.select2-container--open .select2-search__field');
            if (!input) {
                return;
            }

            input.focus();

            if (typeof seedChar === 'string' && seedChar.length === 1) {
                input.value = seedChar;
                input.dispatchEvent(new Event('input', { bubbles: true }));
                return;
            }

            input.select();
        });
    }

    function findSourceSelect(selectionElement) {
        var container = selectionElement ? selectionElement.closest('.select2-container') : null;
        if (!container) {
            return null;
        }

        var previous = container.previousElementSibling;
        if (previous && previous.tagName === 'SELECT') {
            return previous;
        }

        return null;
    }

    function openSelectAndFocus(selectionElement, seedChar) {
        var sourceSelect = findSourceSelect(selectionElement);
        if (!sourceSelect || typeof window.jQuery === 'undefined' || !window.jQuery.fn || !window.jQuery.fn.select2) {
            focusOpenSearchField(seedChar);
            return;
        }

        try {
            window.jQuery(sourceSelect).select2('open');
        } catch (error) {
            // Select2 state not ready bo'lsa ham fokus berishga urinib ko'ramiz.
        }

        focusOpenSearchField(seedChar);
    }

    document.addEventListener('mousedown', function (event) {
        var selection = event.target.closest('.select2-selection--single');
        if (!selection) {
            return;
        }

        window.setTimeout(function () {
            focusOpenSearchField();
        }, 0);
    });

    document.addEventListener('keydown', function (event) {
        var selection = event.target.closest('.select2-selection--single');
        if (!selection) {
            return;
        }

        if (event.altKey || event.ctrlKey || event.metaKey || event.key === 'Tab') {
            return;
        }

        var isPrintable = typeof event.key === 'string' && event.key.length === 1;
        var shouldOpen = isPrintable || event.key === 'Backspace' || event.key === 'Delete';

        if (!shouldOpen) {
            return;
        }

        openSelectAndFocus(selection, isPrintable ? event.key : '');
        event.preventDefault();
    });

    if (typeof window.jQuery !== 'undefined') {
        window.jQuery(document).on('select2:open', function () {
            focusOpenSearchField();
        });
    }
})();

