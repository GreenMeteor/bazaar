humhub.module('bazaar', function(module, require, $) {
    'use strict';

    var client = require('client');
    var status = require('ui.status');

    var init = function() {
        bindCardHover();
        bindTestConnection();
        bindClearCache();
    };

    var bindFilters = function() {
        var searchTimeout;

        $(document).on('submit', '#bazaar-filter-form', function(e) {
            e.preventDefault();
            filterModules();
        });

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
        var $allCols = $container.find('.col-lg-4, .col-md-6');
        var anyVisible = false;

        $allCols.each(function() {
            var $card = $(this).find('.module-card');
            var cardCategory = $card.data('category') || '';
            var title = $card.find('.card-title').text().toLowerCase();
            var description = $card.find('.card-text').first().text().toLowerCase();

            var matchesSearch = !search || title.indexOf(search) !== -1 || description.indexOf(search) !== -1;
            var matchesCategory = !category || cardCategory === category;

            var visible = matchesSearch && matchesCategory;
            $(this).toggleClass('d-none', !visible);

            if (visible) {
                anyVisible = true;
            }
        });

        if (sort !== '') {
            var $visibleCols = $allCols.not('.d-none').toArray();

            $visibleCols.sort(function(a, b) {
                var $ca = $(a).find('.module-card');
                var $cb = $(b).find('.module-card');

                if (sort === 'name') {
                    var na = $ca.find('.card-title').text().trim().toLowerCase();
                    var nb = $cb.find('.card-title').text().trim().toLowerCase();
                    return na < nb ? -1 : na > nb ? 1 : 0;
                }

                if (sort === 'price') {
                    var pa = parseFloat($ca.data('price')) || 0;
                    var pb = parseFloat($cb.data('price')) || 0;
                    return pa - pb;
                }

                if (sort === 'category') {
                    var ca = ($ca.data('category') || '').toLowerCase();
                    var cb = ($cb.data('category') || '').toLowerCase();
                    return ca < cb ? -1 : ca > cb ? 1 : 0;
                }

                return 0;
            });

            $.each($visibleCols, function(i, el) {
                $container.append(el);
            });
        }

        $('.no-results').toggleClass('d-none', anyVisible);
    };

    var bindCardHover = function() {
        $(document).on('mouseenter', '.module-card', function() {
            $(this).addClass('shadow-lg');
        }).on('mouseleave', '.module-card', function() {
            $(this).removeClass('shadow-lg');
        });
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
                    'Testing connection...'
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
                    .html('Could not reach the API. Check your server logs.');
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
                    status.success('Cache cleared. Reloading…');
                    setTimeout(function() { window.location.reload(); }, 800);
                } else {
                    status.error('Failed to clear cache.');
                }

            }).catch(function() {
                $btn.prop('disabled', false);
                status.error('Failed to clear cache.');
            });
        });
    };

    module.export({
        init: init,
    });
});
