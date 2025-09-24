humhub.module('bazaar', function(module, require, $) {
    'use strict';

    var object = require('util').object;
    var client = require('client');
    var modal = require('ui.modal');

    var Bazaar = function() {
        this.init();
    };

    object.inherits(Bazaar, object.Observable);

    Bazaar.prototype.init = function() {
        this.bindEvents();
        this.initFilters();
    };

    Bazaar.prototype.bindEvents = function() {
        var that = this;

        $(document).on('mouseenter', '.module-card', function() {
            $(this).addClass('shadow-lg');
        }).on('mouseleave', '.module-card', function() {
            $(this).removeClass('shadow-lg');
        });

        $(document).on('click', '[data-action="purchase-module"]', function(e) {
            e.preventDefault();
            that.showPurchaseConfirmation($(this));
        });

        $(document).on('click', '[data-action="clear-cache"]', function(e) {
            e.preventDefault();
            that.clearCache();
        });
    };

    Bazaar.prototype.initFilters = function() {
        var that = this;

        var searchTimeout;
        $('#module-search').on('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                that.filterModules();
            }, 300);
        });

        $('.filter-category, .filter-sort').on('change', function() {
            that.filterModules();
        });
    };

    Bazaar.prototype.filterModules = function() {
        var search = $('#module-search').val().toLowerCase();
        var category = $('.filter-category').val();
        var sort = $('.filter-sort').val();

        var $modules = $('.module-card').parent();

        $modules.each(function() {
            var $module = $(this);
            var $card = $module.find('.module-card');
            var title = $card.find('.card-title').text().toLowerCase();
            var description = $card.find('.card-text').text().toLowerCase();
            var moduleCategory = $card.data('category');

            var matchesSearch = !search || title.includes(search) || description.includes(search);
            var matchesCategory = !category || category === moduleCategory;

            $module.toggle(matchesSearch && matchesCategory);
        });

        if (sort) {
            this.sortModules(sort);
        }

        var visibleModules = $('.module-card:visible').length;
        $('.no-results').toggle(visibleModules === 0);
    };

    Bazaar.prototype.sortModules = function(sortBy) {
        var $container = $('.modules-container');
        var $modules = $container.children().detach();

        $modules.sort(function(a, b) {
            var $a = $(a);
            var $b = $(b);
            var aVal, bVal;

            switch(sortBy) {
                case 'name':
                    aVal = $a.find('.card-title').text();
                    bVal = $b.find('.card-title').text();
                    return aVal.localeCompare(bVal);

                case 'price':
                    aVal = parseFloat($a.find('.price').data('price') || 0);
                    bVal = parseFloat($b.find('.price').data('price') || 0);
                    return aVal - bVal;

                case 'category':
                    aVal = $a.find('.module-card').data('category') || '';
                    bVal = $b.find('.module-card').data('category') || '';
                    return aVal.localeCompare(bVal);

                default:
                    return 0;
            }
        });

        $container.append($modules);
    };

    Bazaar.prototype.showPurchaseConfirmation = function($button) {
        var moduleId = $button.data('module-id');
        var moduleName = $button.data('module-name');
        var modulePrice = $button.data('module-price');

        modal.confirm(
            module.text('Confirm Purchase'),
            module.text('Are you sure you want to purchase "{name}" for {price}?', {
                name: moduleName,
                price: modulePrice
            })
        ).then(function(confirmed) {
            if (confirmed) {
                window.location.href = $button.attr('href');
            }
        });
    };

    Bazaar.prototype.clearCache = function() {
        var that = this;

        client.post('/bazaar/admin/clear-cache').then(function(response) {
            if (response.success) {
                module.log.success('text', module.text('Cache cleared successfully!'));
                // Optionally reload the page to show updated data
                setTimeout(function() {
                    window.location.reload();
                }, 1000);
            } else {
                module.log.error('text', module.text('Failed to clear cache'));
            }
        });
    };

    module.export({
        Bazaar: Bazaar,
        init: function() {
            new Bazaar();
        }
    });
});

$(document).ready(function() {
    if (typeof humhub !== 'undefined') {
        humhub.require('bazaar').init();
    }
});