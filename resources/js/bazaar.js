humhub.module('bazaar', function(module, require, $) {
    'use strict';

    var client = require('client');
    var status = require('ui.status');

    var init = function() {
        bindCardHover();
        bindFilters();
        bindTestConnection();
        bindClearCache();
    };

    var bindCardHover = function() {
        $(document).on('mouseenter', '.module-card', function() {
            $(this).addClass('shadow-lg');
        }).on('mouseleave', '.module-card', function() {
            $(this).removeClass('shadow-lg');
        });
    };

    var bindFilters = function() {
        var searchTimeout;

        $(document).on('input', '#module-search', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(filterModules, 300);
        });
    };

    var filterModules = function() {
        var search = $('#module-search').val().toLowerCase();
        var category = $('.filter-category').val();

        var anyVisible = false;

        $('.module-card').each(function() {
            var $card = $(this);
            var $col = $card.closest('[class*="col-"]');
            var title = $card.find('.card-title').text().toLowerCase();
            var description = $card.find('.card-text').first().text().toLowerCase();
            var moduleCategory = $card.data('category');

            var matchesSearch = !search || title.includes(search) || description.includes(search);
            var matchesCategory = !category || category === moduleCategory;
            var visible = matchesSearch && matchesCategory;

            $col.toggleClass('d-none', !visible);
            if (visible) {
                anyVisible = true;
            }
        });

        var $noResults = $('.no-results');
        if ($noResults.length) {
            $noResults.toggleClass('d-none', anyVisible);
        }
    };

    var bindTestConnection = function() {
        $(document).on('click', '[data-action="testConnection"]', function(e) {
            e.preventDefault();

            var $btn = $(this);
            var url = $btn.data('action-url');
            var $result = $('#bazaar-test-result');

            // Show loading spinner
            $btn.prop('disabled', true);
            $result.removeClass('d-none alert-success alert-danger')
                .addClass('alert alert-info')
                .html(
                    '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>' +
                    'Testing connection...' // plain string
                );

            client.get(url, {}).then(function(response) {
                $btn.prop('disabled', false);
                $result.removeClass('alert-info');

                // Normalize response to avoid undefined
                response = response || {};
                var message = typeof response.message === 'string' ? response.message : '';
                var success = Boolean(response.success);

                // Only show the API message; no "Success"/"Error" labels
                $result.removeClass('alert-success alert-danger')
                    .addClass(success ? 'alert-success' : 'alert-danger')
                    .html(message);

            }).catch(function() {
                $btn.prop('disabled', false);
                $result.removeClass('alert-info alert-success alert-danger')
                    .addClass('alert-danger')
                    .html('Could not reach the API. Check your server logs.'); // safe plain string
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

                if (response.success) {
                    status.success(module.text('Cache cleared. Reloading…'));
                    setTimeout(function() {
                        window.location.reload();
                    }, 800);
                } else {
                    status.error(module.text('Failed to clear cache.'));
                }

            }).catch(function() {
                $btn.prop('disabled', false);
                status.error(module.text('Failed to clear cache.'));
            });
        });
    };

    module.export({
        init: init,
    });
});