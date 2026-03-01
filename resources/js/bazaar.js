humhub.module('bazaar', function(module, require, $) {
    'use strict';

    var client = require('client');
    var status = require('ui.status');

    var originalOrder = [];

    var init = function() {
        captureOriginalOrder();
        bindPillFilters();
        bindCategorySelect();
        bindFilters();
        bindTestConnection();
        bindClearCache();
    };

    var captureOriginalOrder = function() {
        originalOrder = $('.modules-container > div').toArray();
    };

    /**
     * Pill buttons → update #bazaar-category dropdown value and trigger filter.
     * Also updates active state on the pills.
     */
    var bindPillFilters = function() {
        $(document).on('click', '[data-bzr-cat]', function() {
            var cat = $(this).data('bzr-cat');
            $('#bazaar-category').val(cat).trigger('change');
            syncPillActive(cat);
        });
    };

    /**
     * #bazaar-category dropdown → keep pill active state in sync
     * so changing the dropdown also highlights the correct pill.
     */
    var bindCategorySelect = function() {
        $(document).on('change', '#bazaar-category', function() {
            syncPillActive($(this).val());
        });
    };

    /**
     * Set the active pill to match the given category value.
     */
    var syncPillActive = function(cat) {
        $('[data-bzr-cat]').removeClass('active');
        $('[data-bzr-cat="' + cat + '"]').addClass('active');
    };

    var bindFilters = function() {
        var searchTimeout;

        var $form = $('#bazaar-filter-form');
        if ($form.length) {
            $form.on('submit', function(e) {
                e.preventDefault();
                e.stopPropagation();
                filterModules();
            });
        }

        $(document).on('input', '#bazaar-search', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(filterModules, 300);
        });

        $(document).on('change', '#bazaar-category', function() {
            filterModules();
        });

        $(document).on('change', '#bazaar-sort', function() {
            filterModules();
        });
    };

    var filterModules = function() {
        var search = ($('#bazaar-search').val() || '').toLowerCase().trim();
        var category = ($('#bazaar-category').val() || '');
        var sort = ($('#bazaar-sort').val() || '');

        var $container = $('.modules-container');
        var $allCols = $container.find('> div');
        var anyVisible = false;

        $allCols.each(function() {
            var $card = $(this).find('.module-card');
            var cardCategory = $card.data('category') || '';
            var title = ($card.find('.bzr-c-card-name').first().text() || '').toLowerCase();
            var description = ($card.find('.bzr-c-card-desc').first().text() || '').toLowerCase();

            var matchesSearch = !search ||
                title.indexOf(search) !== -1 ||
                description.indexOf(search) !== -1;

            var matchesCategory = !category ||
                (category === 'purchased'
                    ? parseInt($card.data('purchased')) === 1
                    : cardCategory === category);

            var visible = matchesSearch && matchesCategory;

            $(this).toggleClass('d-none', !visible);

            if (visible) {
                anyVisible = true;
            }
        });

        var colsToSort;

        if (sort === '') {
            colsToSort = originalOrder.filter(function(el) {
                return !$(el).hasClass('d-none');
            });
        } else {
            colsToSort = $allCols.not('.d-none').toArray();

            var sortKeys = colsToSort.map(function(el) {
                var $card = $(el).find('.module-card');
                return {
                    el: el,
                    name: ($card.find('.bzr-c-card-name').first().text() || '').trim().toLowerCase(),
                    price: parseFloat($card.data('price')) || 0,
                    category: ($card.data('category') || '').toLowerCase(),
                };
            });

            sortKeys.sort(function(a, b) {
                if (sort === 'name') {
                    return a.name < b.name ? -1 : a.name > b.name ? 1 : 0;
                }
                if (sort === 'price') {
                    return a.price - b.price;
                }
                if (sort === 'category') {
                    return a.category < b.category ? -1 : a.category > b.category ? 1 : 0;
                }
                return 0;
            });

            colsToSort = sortKeys.map(function(k) { return k.el; });
        }

        var fragment = document.createDocumentFragment();
        colsToSort.forEach(function(el) {
            fragment.appendChild(el);
        });
        $container[0].appendChild(fragment);

        $('.no-results').toggleClass('d-none', anyVisible);
    };

    var bindTestConnection = function() {
        $(document).on('click', '[data-action="testConnection"]', function(e) {
            e.preventDefault();

            var $btn = $(this);
            var url = $btn.data('action-url');
            var $result = $('#bazaar-test-result');

            $btn.prop('disabled', true);
            $result.removeClass('d-none alert-success alert-danger')
                .addClass('alert alert-info')
                .html(
                    '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>' +
                    module.text('testingConnection')
                );

            client.get(url, {}).then(function(response) {
                $btn.prop('disabled', false);

                response = response || {};
                var message = typeof response.message === 'string' ? response.message : '';
                var success = Boolean(response.success);

                $result.removeClass('alert-info alert-success alert-danger')
                    .addClass(success ? 'alert-success' : 'alert-danger')
                    .html(message);

            }).catch(function() {
                $btn.prop('disabled', false);
                $result.removeClass('alert-info alert-success alert-danger')
                    .addClass('alert-danger')
                    .html(module.text('connectionFailed'));
            });
        });
    };

    var bindClearCache = function() {
        $(document).on('click', '[data-action="clearCache"]', function(e) {
            e.preventDefault();

            var $btn = $(this);
            var url = $btn.data('action-url');

            $btn.prop('disabled', true);

            client.post(url, {}).then(function(response) {
                $btn.prop('disabled', false);

                if (response && response.success) {
                    status.success(module.text('cacheCleared'));
                    setTimeout(function() { window.location.reload(); }, 800);
                } else {
                    status.error(module.text('cacheFailed'));
                }

            }).catch(function() {
                $btn.prop('disabled', false);
                status.error(module.text('cacheFailed'));
            });
        });
    };

    $(function() {
        init();
    });

    module.export({
        init: init,
    });
});