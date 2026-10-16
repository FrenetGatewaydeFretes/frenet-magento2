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

namespace Frenet\Shipping\Test\Unit\Model\Catalog\Product;

use Frenet\Shipping\Model\Catalog\Product\AttributesMappingInterface;
use Frenet\Shipping\Model\Catalog\Product\DimensionsExtractor;
use Frenet\Shipping\Model\ConfigInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Quote\Model\Quote\Item\AbstractItem as QuoteItem;
use Magento\Store\Model\Store;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests that product dimensions are read from the database once per product, store and attribute within a request.
 */
#[AllowMockObjectsWithoutExpectations]
class DimensionsExtractorTest extends TestCase
{
    /**
     * @var ProductResource&MockObject
     */
    private MockObject $productResource;

    /**
     * @var DimensionsExtractor
     */
    private DimensionsExtractor $subject;

    /**
     * Builds the extractor over a mocked product resource, the default attribute mapping and default measurements.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->productResource = $this->createMock(ProductResource::class);

        $mapping = $this->createMock(AttributesMappingInterface::class);
        $mapping->method('getWeightAttributeCode')->willReturn('weight');
        $mapping->method('getHeightAttributeCode')->willReturn('volume_height');
        $mapping->method('getWidthAttributeCode')->willReturn('volume_width');
        $mapping->method('getLengthAttributeCode')->willReturn('volume_length');

        $config = $this->createMock(ConfigInterface::class);
        $config->method('getDefaultWeight')->willReturn(1.0);
        $config->method('getDefaultHeight')->willReturn(2.0);
        $config->method('getDefaultWidth')->willReturn(11.0);
        $config->method('getDefaultLength')->willReturn(16.0);

        $this->subject = new DimensionsExtractor($this->productResource, $mapping, $config);
    }

    /**
     * Confirms repeated weight reads of the same item hit the database once and keep returning the stored value.
     *
     * @return void
     */
    public function testShouldReadAStoredAttributeOncePerProduct(): void
    {
        $this->productResource->expects($this->once())
            ->method('getAttributeRawValue')
            ->with(7, 'weight', $this->anything())
            ->willReturn('2.5');

        $this->subject->setProductByCartItem($this->cartItem($this->product(7, 1)));

        for ($call = 0; $call < 5; $call++) {
            $this->assertSame(2.5, $this->subject->getWeight());
        }
    }

    /**
     * Confirms an attribute without a value is also remembered, so the default is used without querying again.
     *
     * @return void
     */
    public function testShouldRememberAMissingValueAndKeepUsingTheDefault(): void
    {
        $this->productResource->expects($this->once())->method('getAttributeRawValue')->willReturn(false);

        $this->subject->setProductByCartItem($this->cartItem($this->product(7, 1)));

        $this->assertSame(16.0, $this->subject->getLength());
        $this->assertSame(16.0, $this->subject->getLength());
        $this->assertSame(16.0, $this->subject->getLength());
    }

    /**
     * Confirms each product, store and attribute keeps its own value instead of reusing another one.
     *
     * @return void
     */
    public function testShouldKeepValuesApartPerProductStoreAndAttribute(): void
    {
        $this->productResource->expects($this->exactly(4))
            ->method('getAttributeRawValue')
            ->willReturnCallback(fn (int $id, string $code, Store $store) => match ([$id, $code, $store->getId()]) {
                [7, 'weight', 1] => '2.5',
                [8, 'weight', 1] => '4',
                [7, 'weight', 2] => '9',
                [7, 'volume_height', 1] => '30',
            });

        $first = $this->cartItem($this->product(7, 1));
        $second = $this->cartItem($this->product(8, 1));
        $otherStore = $this->cartItem($this->product(7, 2));

        foreach ([1, 2] as $round) {
            $this->assertSame(2.5, $this->subject->setProductByCartItem($first)->getWeight(), "round $round");
            $this->assertSame(4.0, $this->subject->setProductByCartItem($second)->getWeight(), "round $round");
            $this->assertSame(9.0, $this->subject->setProductByCartItem($otherStore)->getWeight(), "round $round");
            $this->assertSame(30.0, $this->subject->setProductByCartItem($first)->getHeight(), "round $round");
        }
    }

    /**
     * Confirms values already on the cart item are used directly, without any database read.
     *
     * @return void
     */
    public function testShouldPreferTheCartItemValueWithoutQuerying(): void
    {
        $this->productResource->expects($this->never())->method('getAttributeRawValue');

        $this->subject->setProductByCartItem($this->cartItem($this->product(7, 1), ['weight' => '3.25']));

        $this->assertSame(3.25, $this->subject->getWeight());
    }

    /**
     * Confirms resetting the state between requests forces a fresh read.
     *
     * @return void
     */
    public function testShouldReadAgainAfterTheStateIsReset(): void
    {
        $this->productResource->expects($this->exactly(2))->method('getAttributeRawValue')->willReturn('2.5');

        $this->subject->setProductByCartItem($this->cartItem($this->product(7, 1)));
        $this->subject->getWeight();
        $this->subject->_resetState();
        $this->subject->getWeight();
    }

    /**
     * Builds a product mock with the given id and store and no preloaded attribute data.
     *
     * @param int $id
     * @param int $storeId
     *
     * @return Product&MockObject
     */
    private function product(int $id, int $storeId): MockObject
    {
        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn($storeId);

        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn($id);
        $product->method('getStoreId')->willReturn($storeId);
        $product->method('getStore')->willReturn($store);
        $product->method('getData')->willReturn(null);

        return $product;
    }

    /**
     * Builds a cart item mock for the product, carrying the given item data.
     *
     * @param Product $product
     * @param array   $data
     *
     * @return QuoteItem&MockObject
     */
    private function cartItem(Product $product, array $data = []): MockObject
    {
        $item = $this->createMock(QuoteItem::class);
        $item->method('getProduct')->willReturn($product);
        $item->method('getData')->willReturnCallback(fn ($key = null) => $data[$key] ?? null);

        return $item;
    }
}
