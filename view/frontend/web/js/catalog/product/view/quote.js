/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 * @package Frenet\Shipping
 *
 * @author Tiago Sampaio <tiago@tiagosampaio.com>
 * @link https://github.com/tiagosampaio
 * @link https://tiagosampaio.com
 *
 * Copyright (c) 2020.
 */

define([
    'jquery',
    'ko',
    'uiElement',
    'Magento_Catalog/js/price-utils',
    'jquery/ui',
    'mage/translate',
    'loader',
    'domReady'
], function ($, ko, uiElement, priceUtils) {
    'use strict';

    return uiElement.extend({
        defaults: {
            template: 'Frenet_Shipping/catalog/product/view/quote'
        },
        updateRates: function () {
            if (!this.active()) {
                return;
            }

            if (this.postcode()) {
                this.loaderStart();

                var apiUrl = this.api_url + '/' + this.postcode() + '/' + this.qty();
                $.getJSON(
                    apiUrl
                ).done(
                    /** When the request succeed. */
                    this.pushRates.bind(this)
                ).fail(
                    /** When the request fails. */
                ).always(
                    /** When any request finishes. */
                    this.loaderStop.bind(this)
                );
            }

            if (!this.postcode()) {
                this.reset();
            }

            this.deactivate();
        },
        pushRates: function (rates) {
            this.rates.removeAll();
            $.each(rates, this.appendRate.bind(this));

            if (rates.length > 0) {
                this.visible(true);
            }

            if (rates.length === 0) {
                this.visible(false);
            }

            this.displayNoResults(!this.visible());
        },
        loaderStart: function () {
            $('#frenet-loader').trigger('processStart');
        },
        loaderStop: function () {
            $('#frenet-loader').trigger('processStop');
        },
        appendRate: function (index, rate) {
            rate.delivery_time = $.mage.__('{0} day(s)').replace('{0}', rate.delivery_time);
            rate.shipping_price = this.formatPrice(rate.shipping_price);
            this.rates.push(rate);
        },
        reset: function () {
            this.visible(false);
            this.displayNoResults(false);
            this.rates.removeAll();
        },
        formatPrice: function (price) {
            if (price <= 0) {
                return $.mage.__('Free Shipping');
            }

            return priceUtils.formatPrice(price);
        },
        activate: function () {
            this.active(true);
        },
        deactivate: function () {
            this.active(false);
        },
        active: ko.observable(false),
        displayNoResults: ko.observable(false),
        visible: ko.observable(false),
        qty: ko.observable(1),
        postcode: ko.observable(),
        rates: ko.observableArray([]),
    });
});