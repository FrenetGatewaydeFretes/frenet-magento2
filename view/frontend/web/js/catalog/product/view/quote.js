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
        active: ko.observable(false),
        displayNoResults: ko.observable(false),
        visible: ko.observable(false),
        error: ko.observable(false),
        errorMessage: ko.observable(''),
        qty: ko.observable(1),
        postcode: ko.observable(),
        rates: ko.observableArray([]),
        loader: '#frenet-loader',
        form: '#product_addtocart_form',
        updateRates: function () {
            if (!this.active()) {
                return;
            }

            if (this.postcode()) {
                this.loaderStart();

                $.ajax({
                    url: this.url,
                    method: 'POST',
                    // contentType: 'application/json',
                    // dataType: 'json',
                    data: this.getPreparedData()
                }).done(
                    /** When the request succeed. */
                    this.processSuccess.bind(this)
                ).fail(
                    /** When the request fails. */
                    this.processFailure.bind(this)
                ).always(
                    /** When any request finishes. */
                    this.processAlways.bind(this)
                );
            }

            if (!this.postcode()) {
                this.reset();
            }
        },
        processSuccess: function (result) {
            // console.log("RESULT SUCCESS", result);
            if (result.error) {
                this.processFailure(result);
                return;
            }

            this.pushRates(result.rates);
        },
        processFailure: function (result) {
            // console.log("RESULT FAILURE", result);
            this.errorMessage(result.message);
            this.error(true);
        },
        processAlways: function (result) {
            // console.log("RESULT ALWAYS", result);
            this.loaderStop();
        },
        getPreparedData: function () {
            var data = $(this.form).serializeArray();
            data.push({name: 'postcode', value: this.postcode()});
            return data;
        },
        pushRates: function (rates) {
            this.rates.removeAll();

            if (rates.length > 0) {
                $.each(rates, this.appendRate.bind(this));

                this.visible(true);
                this.error(false);
                // this.deactivate();
            }

            if (rates.length === 0) {
                this.visible(false);
            }

            this.displayNoResults(!this.visible());
        },
        loaderStart: function () {
            $(this.loader).show();
            $(this.loader).trigger('processStart');
        },
        loaderStop: function () {
            $(this.loader).hide();
            $(this.loader).trigger('processStop');
        },
        appendRate: function (index, rate) {
            rate.delivery_time = $.mage.__('{0} day(s)').replace('{0}', rate.delivery_time);
            rate.shipping_price = this.formatPrice(rate.shipping_price);
            this.rates.push(rate);
        },
        reset: function () {
            this.error(false);
            this.errorMessage('');
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
        enabled: function () {
            return this.active();
        },
        activate: function () {
            this.active(true);
            return this;
        },
        deactivate: function () {
            this.active(false);
            return this;
        },
    });
});
