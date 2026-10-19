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

namespace Frenet\Shipping\Test\Unit\Model\Packages;

use Frenet\Shipping\Model\Catalog\Product\CategoryExtractor;
use Frenet\Shipping\Model\Catalog\Product\DimensionsExtractorInterface;
use Frenet\Shipping\Model\Packages\PackageItem;
use Frenet\Shipping\Model\Quote\ItemPriceCalculator;
use Frenet\Shipping\Model\WeightConverterInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\ProductFactory;
use Magento\Quote\Model\Quote\Item;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests that PackageItem reads the parent product of a child cart item, such as a configurable option, when asked to.
 */
#[AllowMockObjectsWithoutExpectations]
class PackageItemTest extends TestCase
{
    /**
     * @var CategoryExtractor&MockObject
     */
    private MockObject $categoryExtractor;

    /**
     * @var Product&MockObject
     */
    private MockObject $parentProduct;

    /**
     * @var Product&MockObject
     */
    private MockObject $childProduct;

    /**
     * @var PackageItem
     */
    private PackageItem $subject;

    protected function setUp(): void
    {
        $this->categoryExtractor = $this->createMock(CategoryExtractor::class);
        $this->parentProduct = $this->createMock(Product::class);
        $this->childProduct = $this->createMock(Product::class);

        $parentItem = $this->createMock(Item::class);
        $parentItem->method('getProduct')->willReturn($this->parentProduct);

        $childItem = $this->createMock(Item::class);
        $childItem->method('getProduct')->willReturn($this->childProduct);
        $childItem->method('getParentItem')->willReturn($parentItem);

        $this->subject = new PackageItem(
            $childItem,
            $this->createMock(ProductFactory::class),
            $this->createMock(WeightConverterInterface::class),
            $this->categoryExtractor,
            $this->createMock(DimensionsExtractorInterface::class),
            $this->createMock(ItemPriceCalculator::class)
        );
    }

    /**
     * Confirms asking for the parent returns the parent item's product instead of the child's.
     *
     * @return void
     */
    public function testShouldReturnTheParentProductWhenTheItemHasAParentAndItIsRequested(): void
    {
        $this->assertSame($this->parentProduct, $this->subject->getProduct(true));
    }

    /**
     * Confirms the child item's own product is returned by default.
     *
     * @return void
     */
    public function testShouldReturnTheItemProductWhenTheParentIsNotRequested(): void
    {
        $this->assertSame($this->childProduct, $this->subject->getProduct());
    }

    /**
     * Confirms the categories sent to Frenet come from the parent product, where the catalog assigns them.
     *
     * @return void
     */
    public function testShouldReadTheParentCategoriesWhenTheItemIsAChild(): void
    {
        $this->categoryExtractor->expects($this->once())
            ->method('getProductCategories')
            ->with($this->identicalTo($this->parentProduct))
            ->willReturn('Men|Tops');

        $this->assertSame('Men|Tops', $this->subject->getProductCategories());
    }
}
