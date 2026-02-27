humhub.module('bazaar', function(module, require, $) {
    'use strict';

    var client = require('client');
    var status = require('ui.status');

    var init = function() {
        bindCardHover();
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