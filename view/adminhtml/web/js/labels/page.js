/**
 * Frenet Shipping Gateway — behaviour of the labels screens (no inline handlers: CSP friendly).
 * - [data-frenet-selectable] forms: "select all", selected counter, buttons that need a selection.
 * - [data-frenet-review]: recalculates the totals of the chosen services, checks NF-e keys (module 11),
 *   warns when the wallet does not cover the labels and asks for confirmation before creating.
 * - [data-frenet-compare]: "Select" puts a carrier (its cheapest service), the best price or the fastest option in
 *   every selected order. Orders without an NF-e key never get, by "best", a carrier that requires one.
 * - [data-frenet-confirm] / [data-frenet-pay]: Magento confirm modal before cancelling or paying.
 * - [data-frenet-dropoff]: default drop-off point of each carrier (overview, then a filterable list of points).
 */
define(['jquery', 'Magento_Ui/js/modal/confirm', 'mage/translate'], function ($, confirm) {
    'use strict';

    function money(v) {
        return 'R$ ' + Number(v).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function fill(template, a, b, c) {
        return String(template).replace('%1', a).replace('%2', b).replace('%3', c);
    }

    function nfeValid(raw) {
        var key = String(raw).replace(/\D/g, ''), sum = 0, weight = 2, i, rest;
        if (key === '') {
            return true;
        }
        if (key.length !== 44) {
            return false;
        }
        for (i = 42; i >= 0; i--) {
            sum += Number(key[i]) * weight;
            weight = weight === 9 ? 2 : weight + 1;
        }
        rest = sum % 11;
        return (rest < 2 ? 0 : 11 - rest) === Number(key[43]);
    }

    function ask(message, onYes) {
        var $content = $('<div/>');
        $.each(String(message).split('\n\n'), function (i, part) {
            $('<p/>').text(part).appendTo($content);
        });
        confirm({content: $content.html(), actions: {confirm: onYes}});
    }

    function submitWith(form, button) {
        if (button && button.getAttribute('formaction')) {
            form.setAttribute('action', button.getAttribute('formaction'));
        }
        if (button && button.getAttribute('formtarget')) {
            form.setAttribute('target', button.getAttribute('formtarget'));
        } else {
            form.removeAttribute('target');
        }
        form.submit();
    }

    function selectable(form) {
        var $form = $(form),
            $rows = function () { return $form.find('[data-frenet-row]'); },
            $count = $form.find('[data-frenet-count]');

        function refresh() {
            var $checked = $rows().filter(':checked'),
                n = $checked.length,
                pay = $checked.filter('[data-pay="1"]'),
                payTotal = 0,
                $pay = $form.find('[data-frenet-pay]');

            $count.text(n ? fill($count.data('some'), n) : $count.data('zero'));
            $form.find('[data-frenet-sel-n]').text(n);
            $form.find('[data-frenet-needs-selection]').prop('disabled', n === 0);
            $form.find('[data-frenet-print]').prop('disabled', $checked.filter('[data-print="1"]').length === 0);
            pay.each(function () { payTotal += Number(this.getAttribute('data-price')) || 0; });
            $pay.prop('disabled', pay.length === 0);
            $form.find('[data-frenet-sel-pay]').text(pay.length ? money(payTotal) : '—');
            if ($pay.length && pay.length) {
                $pay.find('span').text(fill($pay.data('label'), pay.length, money(payTotal)));
            }
            $form.find('[data-frenet-select-all]').prop('checked', n > 0 && n === $rows().length);
            $form.trigger('frenet:selection');
        }

        $form.on('change', '[data-frenet-select-all]', function () {
            $rows().prop('checked', this.checked);
            refresh();
        });
        $form.on('change', '[data-frenet-row]', refresh);

        // Only send the labels each action applies to (never pay a paid label, never print an unpaid one).
        $form.on('click', '[data-frenet-pay]', function (e) {
            var button = this, $pay = $rows().filter(':checked[data-pay="1"]'), total = 0;
            e.preventDefault();
            $pay.each(function () { total += Number(this.getAttribute('data-price')) || 0; });
            ask(fill($(button).data('confirm'), $pay.length, money(total)), function () {
                $rows().filter(':checked[data-pay="0"]').prop('checked', false);
                submitWith(form, button);
            });
        });
        $form.on('click', '[data-frenet-print]', function (e) {
            e.preventDefault();
            $rows().filter(':checked[data-print="0"]').prop('checked', false);
            refresh();
            submitWith(form, this);
        });
        refresh();
    }

    // Carrier logos: the bundled official files (see Model\Labels\CarrierLogo); a monogram in the brand colour otherwise.
    function carriers(form) {
        var brand = JSON.parse(form.getAttribute('data-carriers') || '{}');
        brand.logos = brand.logos || {};
        brand.colors = brand.colors || {};
        brand.names = brand.names || {};
        brand.palette = brand.palette || ['#0c005a'];

        function code(raw, name) {
            var known = String(raw || '').toUpperCase().trim(), lower, k;
            if (known && brand.logos[known]) {
                return known;
            }
            lower = (String(name || '') + ' ' + known).toLowerCase();
            for (k in brand.names) {
                if (Object.prototype.hasOwnProperty.call(brand.names, k) && lower.indexOf(k) !== -1) {
                    return brand.names[k];
                }
            }
            return known;
        }

        function color(known) {
            var sum = 0, i;
            if (brand.colors[known]) {
                return brand.colors[known];
            }
            for (i = 0; i < known.length; i++) {
                sum += known.charCodeAt(i);
            }
            return brand.palette[sum % brand.palette.length];
        }

        function initials(name, known) {
            var words = String(name || '').trim().split(/[\s\-_]+/).filter(function (w) {
                return /[A-Za-z0-9\u00C0-\u024F]/.test(w);
            });
            if (words.length >= 2) {
                return (words[0].charAt(0) + words[1].charAt(0)).toUpperCase();
            }
            return String(name || known).replace(/[^A-Za-z0-9\u00C0-\u024F]/g, '').slice(0, 2).toUpperCase();
        }

        return function (raw, name) {
            var known = code(raw, name), label = String(name || '').trim() || known,
                $wrap = $('<span class="frenet-carrier"/>');
            if (brand.logos[known]) {
                return $wrap.append($('<img class="frenet-carrier__logo" height="20" loading="lazy" decoding="async"/>')
                    .attr({src: brand.logos[known], alt: label}));
            }
            return $wrap.append($('<span class="frenet-carrier__mark" aria-hidden="true"/>').text(initials(label, known))
                .css('background', color(known))).append($('<span class="frenet-carrier__name"/>').text(label));
        };
    }

    function review(form) {
        var $form = $(form),
            balance = form.getAttribute('data-balance'),
            $create = $form.find('[data-frenet-create]'),
            $compare = $('[data-frenet-compare]'),
            carrierNode = carriers(form),
            applied = null;

        // Logo of the service chosen in each row; repainted on every recalculation (by hand or by the summary).
        function paintLogos() {
            $form.find('[data-frenet-carrier-logo]').each(function () {
                var $slot = $(this), $opt = $slot.closest('tr').find('[data-frenet-service] option:selected');
                $slot.empty();
                if ($opt.length) {
                    $slot.append(carrierNode($opt.attr('data-code') || '', $opt.attr('data-carrier') || ''));
                }
            });
        }

        // Labels are always bought from the store: there is no "send to the Frenet panel" choice any more.
        function journey() {
            return 'here';
        }

        function hasNfe($tr) {
            var key = String($tr.find('[data-frenet-nfe]').val() || '').replace(/\D/g, '');
            return key.length === 44 && nfeValid(key);
        }

        // Services of a row as {code, price, days, carrier, needsNfe}.
        function services($tr) {
            return $tr.find('[data-frenet-service] option').map(function () {
                return {code: this.value, price: Number(this.getAttribute('data-price')) || 0,
                    days: Number(this.getAttribute('data-days')) || 0, carrier: this.getAttribute('data-carrier'),
                    needsNfe: this.getAttribute('data-needs-nfe') === '1'};
            }).get();
        }

        // What an order can really ship with: without an NF-e key, not the carriers that require one (all of them
        // when every carrier requires it, so there is always a choice).
        function usable($tr, list) {
            var ok;
            if (hasNfe($tr)) {
                return list;
            }
            ok = $.grep(list, function (s) { return !s.needsNfe; });
            return ok.length ? ok : list;
        }

        function cheapest(list) {
            return list.slice().sort(function (a, b) { return a.price - b.price || a.days - b.days; })[0];
        }

        function fastest(list) {
            return list.slice().sort(function (a, b) { return (a.days || 999) - (b.days || 999) || a.price - b.price; })[0];
        }

        // "requires NF-e" next to those carriers while the order has no valid key.
        function tagOptions($tr) {
            var $select = $tr.find('[data-frenet-service]'), tag = $select.data('needs-nfe-label') || '', keyed = hasNfe($tr);
            $select.find('option').each(function () {
                this.text = this.getAttribute('data-text') + (this.getAttribute('data-needs-nfe') === '1' && !keyed ? tag : '');
            });
        }

        function checkedRows() {
            return $form.find('[data-frenet-row]:checked').map(function () { return $(this).closest('tr'); }).get();
        }

        // Selected orders without an NF-e key going with a carrier that requires one: Frenet refuses them.
        function blocked() {
            return $.grep(checkedRows(), function ($tr) {
                return $tr.find('[data-frenet-service] option:selected').attr('data-needs-nfe') === '1' && !hasNfe($tr);
            });
        }

        function recalc() {
            paintLogos();
            var count = 0, total = 0, carriers = {}, badKey = false, $list = $form.find('[data-frenet-sum-carriers]').empty(),
                charges = journey() === 'here' && $create.data('wallet-payment') === 1, refused = blocked(), noNfe = 0,
                names = [], $refused = $form.find('[data-frenet-refused]'), $dce = $form.find('[data-frenet-dce]');

            $form.find('[data-frenet-row]').each(function () {
                var $tr = $(this).closest('tr'), $opt = $tr.find('[data-frenet-service] option:selected'),
                    $nfe = $tr.find('[data-frenet-nfe]'), ok = nfeValid($nfe.val() || '');
                $tr.find('[data-frenet-nfe-error]').prop('hidden', ok);
                $nfe.attr('aria-invalid', ok ? null : 'true');
                if (!this.checked) {
                    return;
                }
                badKey = badKey || !ok;
                count++;
                total += Number($opt.data('price')) || 0;
                carriers[$opt.data('carrier')] = carriers[$opt.data('carrier')] || {n: 0, t: 0};
                carriers[$opt.data('carrier')].n++;
                carriers[$opt.data('carrier')].t += Number($opt.data('price')) || 0;
                if (!hasNfe($tr) && $.inArray($tr.get(0), $.map(refused, function ($r) { return $r.get(0); })) === -1) {
                    noNfe++;
                }
            });
            $.each(carriers, function (name, c) {
                $('<li/>').text(name + ': ' + c.n + ' · ' + money(c.t)).appendTo($list);
            });
            $form.find('[data-frenet-sum-count]').text(count);
            $form.find('[data-frenet-sum-total]').text(money(total));
            if (balance !== '') {
                $form.find('[data-frenet-sum-after]').text(money(Number(balance) - (charges ? total : 0)));
                $form.find('[data-frenet-low-balance]').prop('hidden', !charges || Number(balance) >= total);
            }
            // Carriers that require an NF-e key refuse orders without one: say it before anything is sent.
            $.each(refused, function (i, $tr) {
                var name = $tr.find('[data-frenet-service] option:selected').data('carrier');
                if ($.inArray(name, names) === -1) {
                    names.push(name);
                }
            });
            $refused.prop('hidden', !refused.length)
                .text(refused.length ? fill($refused.data(refused.length === 1 ? 'one' : 'some'), refused.length, names.join(', ')) : '');
            // No NF-e key: Frenet issues a DC-e. Only an informative note (v1); stores required to issue NF-e add the key.
            $dce.prop('hidden', !noNfe);
            $dce.find('[data-frenet-dce-title]').text(noNfe === count
                ? (noNfe === 1 ? $dce.data('one') : fill($dce.data('all'), noNfe))
                : fill($dce.data(noNfe === 1 ? 'some-one' : 'some'), noNfe, count));
            $create.prop('disabled', count === 0 || badKey);
            $create.find('span').text(fill($create.data('label'), count, money(total)));
            $create.data('count', count).data('total', total);
        }

        // Puts the summary choice in every selected order; returns how many orders lack that carrier.
        function applyChoice() {
            var missing = 0;
            $.each(checkedRows(), function (i, $tr) {
                var list = services($tr), pick;
                if (applied.carrier) {
                    pick = cheapest($.grep(list, function (s) { return s.carrier === applied.carrier; }));
                } else {
                    pick = applied.best === 'time' ? fastest(usable($tr, list)) : cheapest(usable($tr, list));
                }
                if (pick) {
                    $tr.find('[data-frenet-service]').val(pick.code);
                } else {
                    missing++;
                }
            });
            return missing;
        }

        function paintPicks() {
            $compare.find('[data-frenet-pick-carrier], [data-frenet-pick-best]').each(function () {
                var on = !!applied && (applied.carrier ? this.getAttribute('data-frenet-pick-carrier') === applied.carrier
                    : this.getAttribute('data-frenet-pick-best') === applied.best);
                $(this).toggleClass('action-primary', on).toggleClass('action-default', !on).attr('aria-pressed', on ? 'true' : 'false')
                    .text($compare.data(on ? 'selected' : 'select'));
                $(this).closest('[data-frenet-pick-row]').toggleClass('is-applied', on);
            });
        }

        $compare.on('click', '[data-frenet-pick-carrier], [data-frenet-pick-best]', function () {
            var label = this.getAttribute('data-frenet-pick-carrier') || this.getAttribute('data-name'), n, missing, refused,
                $status = $compare.find('[data-frenet-pick-status]');
            applied = this.hasAttribute('data-frenet-pick-carrier') ? {carrier: label} : {best: this.getAttribute('data-frenet-pick-best')};
            missing = applyChoice();
            n = checkedRows().length;
            paintPicks();
            recalc();
            refused = blocked().length;
            $status.text((missing ? fill($compare.data('applied-partly'), label, n - missing, missing)
                : fill($compare.data(n === 1 ? 'applied-one' : 'applied'), label, n))
                + (refused ? ' ' + fill($compare.data(refused === 1 ? 'needs-nfe-one' : 'needs-nfe-some'), refused) : ''));
        });

        $form.on('change', '[data-frenet-service]', function () {
            applied = null;
            paintPicks();
            $compare.find('[data-frenet-pick-status]').text('');
        });
        $form.on('input change', '[data-frenet-nfe]', function () {
            tagOptions($(this).closest('tr'));
        });
        $form.on('change input', '[data-frenet-service], [data-frenet-row], [data-frenet-nfe]', recalc);
        $form.on('frenet:selection', recalc);
        $create.on('click', function (e) {
            var message, j = journey(), notes = $form.find('[data-frenet-refused]:not([hidden]), [data-frenet-dce]:not([hidden]) [data-frenet-dce-title]');
            e.preventDefault();
            if (j === 'panel') {
                message = $create.data('confirm-panel');
            } else {
                message = $create.data('wallet-payment') === 1 ? $create.data('confirm-here') : $create.data('confirm-later');
            }
            message = fill(message, $create.data('count'), money($create.data('total')));
            notes.each(function () { message += '\n\n' + $(this).text(); });
            ask(message, function () {
                $create.prop('disabled', true);
                form.submit();
            });
        });
        $form.find('[data-frenet-service]').each(function () {
            tagOptions($(this).closest('tr'));
        });
        recalc();
    }

    function dropoff(root) {
        var $root = $(root), t = function (k) { return root.getAttribute('data-t-' + k) || ''; },
            $editor = $root.find('[data-frenet-dropoff-editor]'), $cep = $root.find('[data-frenet-dropoff-cep]'),
            $filter = $root.find('[data-frenet-dropoff-filter]'), $count = $root.find('[data-frenet-dropoff-count]'),
            $branch = $root.find('[data-frenet-dropoff-branch]'), $address = $root.find('[data-frenet-dropoff-address]'),
            $save = $root.find('[data-frenet-dropoff-save]'), $status = $root.find('[data-frenet-dropoff-status]'),
            $row = null, points = [], defaults = {};

        function post(url, data) {
            return $.ajax({url: url, type: 'POST', dataType: 'json', data: $.extend({form_key: window.FORM_KEY}, data)})
                .then(null, function () { return $.Deferred().resolve({ok: false, message: t('error')}); });
        }

        function label(b) {
            return b.name + (b.initials ? ' (' + b.initials + ')' : '');
        }

        function describe(b) {
            return b ? b.name + (b.address ? ' — ' + b.address : '') : t('none');
        }

        // Overview cell: point name in bold, address below; or "no default point".
        function paintRow($r, b) {
            var $cell = $r.find('[data-frenet-dropoff-default]').empty();
            if (b) {
                $('<strong class="frenet-block"/>').text(label(b)).appendTo($cell);
                $('<span class="frenet-muted"/>').text(b.address).appendTo($cell);
            } else {
                $('<span class="frenet-muted"/>').text(t('none')).appendTo($cell);
            }
            $r.find('[data-frenet-dropoff-edit]').prop('disabled', false).text(b ? t('change') : t('choose'));
        }

        function current() {
            return $row ? defaults[$row.attr('data-frenet-dropoff-row')] || null : null;
        }

        function syncSave() {
            var id = $branch.val(), p = $.grep(points, function (x) { return x.id === id; })[0];
            $address.text(p ? p.address : '');
            $save.prop('disabled', !p || !!(current() && current().id === p.id));
        }

        // The list can have hundreds of points: the filter keeps only the matching ones (accents ignored).
        function plain(v) {
            return String(v || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
        }

        function renderPoints() {
            var q = plain($filter.val()).trim(), keep = $branch.val(), shown = $.grep(points, function (b) {
                return !q || plain(b.name + ' ' + b.initials + ' ' + b.address).indexOf(q) !== -1;
            });
            $branch.empty();
            $.each(shown, function (i, b) {
                $('<option/>').val(b.id).text(label(b) + (b.address ? ' — ' + b.address : ''))
                    .prop('selected', b.id === keep).appendTo($branch);
            });
            $count.text(q ? fill(t('shown'), shown.length, points.length)
                : fill(points.length === 1 ? t('found-one') : t('found'), points.length));
            syncSave();
        }

        function search() {
            points = [];
            $branch.empty().prop('disabled', true);
            $filter.prop('disabled', true);
            $save.prop('disabled', true);
            $address.text('');
            $status.text('');
            $count.text(t('loading'));
            post(root.getAttribute('data-branches-url'), {carrier: $row.attr('data-frenet-dropoff-row'), cep: $cep.val()}).then(function (res) {
                var now = current();
                if (!res.ok) {
                    $count.text(res.message || t('error'));
                    return;
                }
                points = res.branches || [];
                // The saved point may be far from this CEP: keep it first so the list never hides it.
                if (now && !$.grep(points, function (b) { return b.id === now.id; }).length) {
                    points.unshift(now);
                }
                if (!points.length) {
                    $count.text(t('no-points'));
                    return;
                }
                $branch.prop('disabled', false);
                $filter.prop('disabled', false);
                renderPoints();
                if (now) {
                    $branch.val(now.id);
                    syncSave();
                }
                $filter.trigger('focus');
            });
        }

        function open($r) {
            $row = $r;
            $filter.val('');
            $editor.find('[data-frenet-dropoff-title]').text(fill(t('for'), $r.attr('data-name')));
            $editor.find('[data-frenet-dropoff-current]').text(t('current') + ' ' + describe(current()));
            $root.find('[data-frenet-dropoff-row]').removeClass('is-applied');
            $r.addClass('is-applied');
            $editor.prop('hidden', false);
            $editor.get(0).scrollIntoView({behavior: 'smooth', block: 'start'});
            search();
        }

        function close() {
            $editor.prop('hidden', true);
            if ($row) {
                $row.removeClass('is-applied');
                $row.find('[data-frenet-dropoff-edit]').trigger('focus');
            }
            $row = null;
        }

        $root.find('[data-frenet-dropoff-row]').each(function () {
            var $r = $(this), code = $r.attr('data-frenet-dropoff-row');
            post(root.getAttribute('data-current-url'), {carrier: code}).then(function (res) {
                if (res.ok) {
                    defaults[code] = res['default'];
                    paintRow($r, defaults[code]);
                } else {
                    $r.find('[data-frenet-dropoff-default]').empty().append($('<span class="frenet-bad"/>').text(res.message || t('error')));
                    $r.find('[data-frenet-dropoff-edit]').prop('disabled', false);
                }
            });
        });
        $root.on('click', '[data-frenet-dropoff-edit]', function () {
            open($(this).closest('[data-frenet-dropoff-row]'));
        });
        $root.find('[data-frenet-dropoff-search]').on('click', search);
        $cep.on('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                search();
            }
        });
        $filter.on('input', renderPoints);
        $branch.on('change', syncSave);
        $root.find('[data-frenet-dropoff-close]').on('click', close);
        $editor.on('keydown', function (e) {
            if (e.key === 'Escape') {
                close();
            }
        });
        $save.on('click', function () {
            var code = $row.attr('data-frenet-dropoff-row'), $r = $row;
            $save.prop('disabled', true);
            $status.text(t('working'));
            post(root.getAttribute('data-save-url'), {carrier: code, id: $branch.val()}).then(function (res) {
                if (res.ok) {
                    defaults[code] = res['default'];
                    paintRow($r, defaults[code]);
                    $editor.find('[data-frenet-dropoff-current]').text(t('current') + ' ' + describe(defaults[code]));
                    $status.text(res.message);
                } else {
                    $status.empty().append($('<span class="frenet-bad frenet-block"/>').text(res.message || t('error')));
                    if (res.panel) {
                        $('<a class="frenet-block" target="_blank" rel="noopener"/>').attr('href', res.panel).text(t('panel')).appendTo($status);
                    }
                }
                syncSave();
            });
        });
    }

    return function () {
        $('[data-frenet-selectable], [data-frenet-review]').each(function () {
            selectable(this);
        });
        $('[data-frenet-review]').each(function () {
            review(this);
        });
        $('[data-frenet-dropoff]').each(function () {
            dropoff(this);
        });
        $(document).on('click', '[data-frenet-confirm]', function (e) {
            var button = this;
            e.preventDefault();
            ask(button.getAttribute('data-frenet-confirm'), function () {
                submitWith($(button).closest('form').get(0), button);
            });
        });
    };
});
