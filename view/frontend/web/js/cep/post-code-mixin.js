/**
 * Frenet Shipping Gateway — address by CEP in the checkout (shipping and billing).
 *
 * When the customer types a complete Brazilian CEP, asks GET /rest/V1/frenet/cep/:cep (Frenet, cached
 * 30 days) and fills street, district, city and state. Street lines: 4 = street, number, complement,
 * district · 3 = street, number, district · 2 = street, district. Never overwrites number or complement.
 * While it looks up, the field shows "Buscando endereço…"; an unknown CEP shows a short notice and the
 * customer keeps typing as usual.
 */
define([
    'jquery',
    'uiRegistry',
    'mage/url',
    'mage/translate'
], function ($, registry, urlBuilder, $t) {
    'use strict';

    var cache = {};

    return function (PostCode) {
        return PostCode.extend({
            defaults: {
                frenetLastCep: ''
            },

            initObservable: function () {
                this._super();
                this.value.subscribe(this.frenetLookup, this);

                return this;
            },

            frenetEnabled: function () {
                var cfg = window.checkoutConfig && window.checkoutConfig.frenetCep;

                return !!(cfg && cfg.enabled);
            },

            frenetSibling: function (name) {
                return registry.get(this.parentName + '.' + name);
            },

            frenetNote: function (text) {
                // Uses the field's own notice line so it follows the store theme.
                if (typeof this.notice === 'function') {
                    this.notice(text || '');
                } else {
                    this.notice = text || '';
                }
            },

            frenetLookup: function (value) {
                var country = this.frenetSibling('country_id'),
                    cep = String(value || '').replace(/\D/g, ''),
                    self = this;

                if (!this.frenetEnabled() || cep.length !== 8 || cep === this.frenetLastCep) {
                    return;
                }
                if (country && country.value() && country.value() !== 'BR') {
                    return;
                }
                this.frenetLastCep = cep;
                if (cache[cep]) {
                    this.frenetFill(cache[cep]);

                    return;
                }
                this.frenetNote($t('Looking up the address…'));
                $.ajax({
                    url: urlBuilder.build('rest/V1/frenet/cep/' + cep),
                    type: 'GET',
                    dataType: 'json',
                    global: false,
                    timeout: 8000
                }).done(function (address) {
                    if (address && address.city) {
                        cache[cep] = address;
                        self.frenetFill(address);
                        self.frenetNote('');
                    } else {
                        self.frenetNote($t('We could not find this CEP. Please fill in the address.'));
                    }
                }).fail(function () {
                    self.frenetNote($t('We could not find this CEP. Please fill in the address.'));
                });
            },

            frenetFill: function (a) {
                var street = this.frenetSibling('street'),
                    lines = street && street.elems ? street.elems() : [],
                    count = lines.length,
                    city = this.frenetSibling('city'),
                    region = this.frenetSibling('region_id'),
                    regionText = this.frenetSibling('region'),
                    next;

                if (count > 0 && a.street) {
                    lines[0].value(a.street);
                }
                if (count >= 2 && a.district && !(count >= 3 && lines[count - 1] === lines[1])) {
                    lines[count - 1].value(a.district);
                }
                if (city && a.city) {
                    city.value(a.city);
                }
                if (region && a.region_id) {
                    region.value(String(a.region_id));
                } else if (regionText && a.region_code) {
                    regionText.value(a.region_code);
                }
                // Takes the customer straight to the number.
                next = count >= 3 ? lines[1] : null;
                if (next && !next.value() && next.focused) {
                    next.focused(true);
                }
            }
        });
    };
});
