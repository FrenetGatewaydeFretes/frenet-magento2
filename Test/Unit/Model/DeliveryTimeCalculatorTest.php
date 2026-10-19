<?php
/**
 * Frenet_Shipping
 *
 * @vendor    Frenet
 * @package   Shipping
 *
 * @copyright © 2026 Diego M. Miyabara. All rights reserved.
 * @author    Diego M. Miyabara <diego.miyabara@frenet.com.br>
 */

declare(strict_types=1);

namespace Frenet\Shipping\Test\Unit\Model;

use Frenet\ObjectType\Entity\Shipping\Quote\ServiceInterface;
use Frenet\Shipping\Model\ConfigInterface;
use Frenet\Shipping\Model\DeliveryTimeCalculator;
use Frenet\Shipping\Service\RateRequestProviderInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Item;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests the delivery time sum and that each product lead time is read from the database once per request.
 */
#[AllowMockObjectsWithoutExpectations]
class DeliveryTimeCalculatorTest extends TestCase
{
    /**
     * @var ProductResource&MockObject
     */
    private MockObject $productResource;

    /**
     * @var DeliveryTimeCalculator
     */
    private DeliveryTimeCalculator $subject;

    /**
     * Builds the calculator over a rate request with one item missing its lead time and one carrying it.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->productResource = $this->createMock(ProductResource::class);

        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn(1);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $config = $this->createMock(ConfigInterface::class);
        $config->method('getAdditionalLeadTime')->willReturn(1);

        $rateRequest = new RateRequest([
            'all_items' => [$this->item($this->product(7, 0)), $this->item($this->product(8, 2))],
        ]);
        $provider = $this->createMock(RateRequestProviderInterface::class);
        $provider->method('getRateRequest')->willReturn($rateRequest);

        $this->subject = new DeliveryTimeCalculator($this->productResource, $storeManager, $config, $provider);
    }

    /**
     * Confirms the forecast adds the slowest lead time and the extra days, reading the missing lead time only once.
     *
     * @return void
     */
    public function testShouldReadTheLeadTimeOnceWhenSeveralServicesNeedIt(): void
    {
        $this->productResource->expects($this->once())
            ->method('getAttributeRawValue')
            ->with(7, 'lead_time', $this->anything())
            ->willReturn('5');

        foreach ([1, 3, 6] as $forecast) {
            $this->assertSame($forecast + 5 + 1, $this->subject->calculate($this->service($forecast)));
        }
    }

    /**
     * Confirms resetting the state between requests reads the lead time again.
     *
     * @return void
     */
    public function testShouldReadAgainWhenTheStateIsReset(): void
    {
        $this->productResource->expects($this->exactly(2))->method('getAttributeRawValue')->willReturn('0');

        $this->assertSame(1 + 2 + 1, $this->subject->calculate($this->service(1)));
        $this->subject->_resetState();
        $this->assertSame(1 + 2 + 1, $this->subject->calculate($this->service(1)));
    }

    /**
     * Builds a product mock with the given id and preloaded lead time.
     *
     * @param int $id
     * @param int $leadTime
     *
     * @return Product&MockObject
     */
    private function product(int $id, int $leadTime): MockObject
    {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn($id);
        $product->method('getData')->willReturnCallback(fn ($key = null) => $key === 'lead_time' ? $leadTime : null);

        return $product;
    }

    /**
     * Builds a quote item mock for the product.
     *
     * @param Product $product
     *
     * @return Item&MockObject
     */
    private function item(Product $product): MockObject
    {
        $item = $this->createMock(Item::class);
        $item->method('getProduct')->willReturn($product);

        return $item;
    }

    /**
     * Builds a Frenet service mock with the given carrier forecast in days.
     *
     * @param int $days
     *
     * @return ServiceInterface&MockObject
     */
    private function service(int $days): MockObject
    {
        $service = $this->createMock(ServiceInterface::class);
        $service->method('getDeliveryTime')->willReturn($days);

        return $service;
    }
}
