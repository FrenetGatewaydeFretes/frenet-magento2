<?php
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

declare(strict_types = 1);

namespace Frenet\Shipping\Test\Unit\Model\Carrier;

use Frenet\Shipping\Test\Unit\TestCase;

/**
 * Class Frenet
 *
 * @package Frenet\Shipping\Test\Unit\Model\Carrier
 */
class FrenetTest extends TestCase
{
    /**
     * @var \Magento\Quote\Model\Quote\Address\RateRequest
     */
    private $request;

    protected function setUp()
    {
        $objectManager = new \Magento\Framework\TestFramework\Unit\Helper\ObjectManager($this);
        $this->request = $objectManager->getObject(\Magento\Quote\Model\Quote\Address\RateRequest::class);
        $this->request->setData($this->mockRequestData());
    }

    private function mockRequestData()
    {
        $objectManager = new \Magento\Framework\TestFramework\Unit\Helper\ObjectManager($this);

        /** @var \Magento\Quote\Model\Quote\Item $item */
        $item = $objectManager->getObject(\Magento\Quote\Model\Quote\Item::class);

        /** @var \Magento\Directory\Model\Currency $currency */
        $currency = $objectManager->getObject(\Magento\Directory\Model\Currency::class);
        $currency->setData('currency_code', 'BRL');

        $packageValue = 1.59;

        return [
            'all_items' => [
                $item
            ],
            'dest_country_id' => 'BR',
            'package_value' => $packageValue,
            'package_value_with_discount' => $packageValue,
            'package_weight' => 1,
            'package_qty' => 1,
            'package_physical_value' => $packageValue,
            'free_method_weight' => 1,
            'store_id' => 1,
            'website_id' => 1,
            'free_shipping' => false,
            'base_currency' => $currency,
            'package_currency' => $currency,
            'limit_carrier' => null,
            'base_subtotal_incl_tax' => $packageValue,
            'country_id' => 'BR',
            'region_id' => '508',
            'city' => 'Carapicuiba',
            'postcode' => '06395010',
        ];
    }
}
