/**
 * Frenet Shipping Gateway — behaviour of the labels screens (no inline handlers: CSP friendly).
 * - [data-frenet-selectable] forms: "select all", selected counter, buttons that need a selection.
 * - [data-frenet-review]: recalculates the totals of the chosen services, checks NF-e keys (module 11),
 *   warns when the wallet does not cover the labels and asks for confirmation before creating.
 * - [data-frenet-confirm] / [data-frenet-pay]: Magento confirm modal before cancelling or paying.
 */
define(['jquery', 'Magento_Ui/js/modal/confirm', 'mage/translate'], function ($, confirm) {
    'use strict';

    function money(v) {
        return 'R$ ' + Number(v).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function fill(template, a, b) {
        return String(template).replace('%1', a).replace('%2', b);
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
        confirm({content: $('<p/>').text(message).html(), actions: {confirm: onYes}});
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
            $form.find('[data-frenet-needs-selection]').prop('disabled', n === 0);
            $form.find('[data-frenet-print]').prop('disabled', $checked.filter('[data-print="1"]').length === 0);
            pay.each(function () { payTotal += Number(this.getAttribute('data-price')) || 0; });
            $pay.prop('disabled', pay.length === 0);
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

    function review(form) {
        var $form = $(form),
            balance = form.getAttribute('data-balance'),
            $create = $form.find('[data-frenet-create]');

        function journey() {
            return $form.find('input[name="journey"]:checked').val() || 'here';
        }

        function recalc() {
            var count = 0, total = 0, carriers = {}, badKey = false, $list = $form.find('[data-frenet-sum-carriers]').empty(),
                charges = journey() === 'here' && $create.data('wallet-payment') === 1;

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
            $create.prop('disabled', count === 0 || badKey);
            $create.find('span').text(fill($create.data('label'), count, money(total)));
            $create.data('count', count).data('total', total);
        }

        $form.on('change input', '[data-frenet-service], [data-frenet-row], [data-frenet-nfe], input[name="journey"]', recalc);
        $form.on('frenet:selection', recalc);
        $create.on('click', function (e) {
            var message, j = journey();
            e.preventDefault();
            if (j === 'panel') {
                message = $create.data('confirm-panel');
            } else {
                message = $create.data('wallet-payment') === 1 ? $create.data('confirm-here') : $create.data('confirm-later');
            }
            ask(fill(message, $create.data('count'), money($create.data('total'))), function () {
                $create.prop('disabled', true);
                form.submit();
            });
        });
        recalc();
    }

    return function () {
        $('[data-frenet-selectable], [data-frenet-review]').each(function () {
            selectable(this);
        });
        $('[data-frenet-review]').each(function () {
            review(this);
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
