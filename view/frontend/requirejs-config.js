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

var config = {
    map: {
        '*': {
            frenetProductViewQuote: 'Frenet_Shipping/js/catalog/product/view/quote'
        }
    },
    config: {
        mixins: {
            // Address by CEP in the checkout (shipping and billing forms).
            'Magento_Ui/js/form/element/post-code': {
                'Frenet_Shipping/js/cep/post-code-mixin': true
            }
        }
    }
};
