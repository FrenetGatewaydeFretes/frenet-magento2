<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 *
 * @author   Tiago Sampaio <tiago@tiagosampaio.com>
 * @link     https://github.com/tiagosampaio
 * @link     https://tiagosampaio.com
 *
 * Copyright (c) 2020.
 */
declare(strict_types = 1);

namespace Frenet\Shipping\Test\Unit;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;

/**
 * Class TestCase
 */
class TestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @return ObjectManager
     */
    protected function getObjectManager() : ObjectManager
    {
        if (!$this->objectManager) {
            $this->objectManager = new ObjectManager($this);
        }

        return $this->objectManager;
    }

    /**
     * @param string $className
     * @param array  $arguments
     *
     * @return object
     */
    protected function getObject(string $className, array $arguments = [])
    {
        return $this->getObjectManager()->getObject($className, $arguments);
    }

    public function mockRateRequest()
    {
        $rateRequestData = [
            'all_items'                   => [
                $this->mockQuoteItem(),
            ],
            'dest_country'                => 'BR',
            'dest_country_id'             => "BR",
            'dest_region_id'              => null,
            'dest_region_code'            => "",
            'dest_street'                 => "",
            'dest_city'                   => null,
            'dest_postcode'               => "06395-010",
            'package_value'               => 45.0,
            'package_value_with_discount' => 45.0,
            'package_weight'              => 0.0,
            'package_qty'                 => 1.0,
            'package_physical_value'      => 45.0,
            'free_method_weight'          => 0.0,
            'store_id'                    => 1,
            'website_id'                  => "1",
            'free_shipping'               => false,
            'limit_carrier'               => null,
            'base_subtotal_incl_tax'      => 45.0,
            'country_id'                  => "BR",
            'region_id'                   => "508",
            'city'                        => "São Paulo",
            'postcode'                    => "04551-010",
            'condition_name'              => "package_value_with_discount",
            'base_currency'               => $this->getObject(\Magento\Directory\Model\Currency::class, [
                'currency_code' => 'BRL'
            ]),
            'package_currency'            => $this->getObject(\Magento\Directory\Model\Currency::class, [
                'currency_code' => 'BRL'
            ]),
        ];

        $this->getObject(\Magento\Quote\Model\Quote\Address\RateRequest::class, $rateRequestData);
    }

    /**
     * @return \Magento\Quote\Model\Quote\Item\AbstractItem
     */
    private function mockQuoteItem()
    {
        /** @var \Magento\Quote\Model\Quote\Item\AbstractItem $item */
        $item = $this->getObject(\Magento\Quote\Model\Quote\Item::class, [
            'item_id'                               => "5",
            'quote_id'                              => "3",
            'created_at'                            => "2020-04-08 15:21:26",
            'updated_at'                            => "2020-04-08 15:21:26",
            'product_id'                            => "14",
            'store_id'                              => 1,
            'parent_item_id'                        => null,
            'is_virtual'                            => "0",
            'sku'                                   => "24-WB04",
            'name'                                  => "Push It Messenger Bag",
            'description'                           => null,
            'applied_rule_ids'                      => "",
            'additional_data'                       => null,
            'is_qty_decimal'                        => false,
            'no_discount'                           => "0",
            'weight'                                => null,
            'qty'                                   => 1.0,
            'price'                                 => 45.0,
            'base_price'                            => 45.0,
            'custom_price'                          => null,
            'discount_percent'                      => 0,
            'discount_amount'                       => 0,
            'base_discount_amount'                  => 0,
            'tax_percent'                           => 0,
            'tax_amount'                            => 0,
            'base_tax_amount'                       => 0,
            'row_total'                             => 45.0,
            'base_row_total'                        => 45.0,
            'row_total_with_discount'               => "0.0000",
            'row_weight'                            => 0.0,
            'product_type'                          => "simple",
            'base_tax_before_discount'              => null,
            'tax_before_discount'                   => null,
            'original_custom_price'                 => null,
            'redirect_url'                          => null,
            'base_cost'                             => null,
            'price_incl_tax'                        => 45.0,
            'base_price_incl_tax'                   => 45.0,
            'row_total_incl_tax'                    => 45.0,
            'base_row_total_incl_tax'               => 45.0,
            'discount_tax_compensation_amount'      => 0,
            'base_discount_tax_compensation_amount' => 0,
            'event_id'                              => null,
            'gift_message_id'                       => null,
            'gw_id'                                 => null,
            'gw_base_price'                         => null,
            'gw_price'                              => null,
            'gw_base_tax_amount'                    => null,
            'gw_tax_amount'                         => null,
            'weee_tax_applied'                      => null,
            'weee_tax_applied_amount'               => null,
            'weee_tax_applied_row_amount'           => null,
            'weee_tax_disposition'                  => null,
            'weee_tax_row_disposition'              => null,
            'base_weee_tax_applied_amount'          => null,
            'base_weee_tax_applied_row_amnt'        => null,
            'base_weee_tax_disposition'             => null,
            'base_weee_tax_row_disposition'         => null,
            'free_shipping'                         => false,
            'giftregistry_item_id'                  => null,
            'qty_options'                           => [],
            'product'                               => $this->getObject(\Magento\Catalog\Model\Product::class),
            'tax_class_id'                          => "2",
            'has_error'                             => false,
            'stock_state_result'                    => $this->getObject(\Magento\Framework\DataObject::class),
            'calculation_price'                     => 45.0,
            'converted_price'                       => 45.0,
            'base_original_price'                   => "45.000000",
            'base_calculation_price'                => 45.0,
            'tax_calculation_item_id'               => "sequence-1",
            'tax_calculation_price'                 => 45.0,
            'base_tax_calculation_price'            => 45.0,
            'discount_calculation_price'            => 45.0,
            'base_discount_calculation_price'       => 45.0,
            'extension_attributes'                  => $this->getObject(\Magento\Quote\Api\Data\CartItemExtension::class)
        ]);

        return $item;
    }
}
