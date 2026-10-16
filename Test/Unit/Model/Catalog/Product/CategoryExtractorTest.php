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

use ArrayIterator;
use Frenet\Shipping\Model\Catalog\Product\CategoryExtractor;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Category\Collection;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests that the product category names are loaded once per product and store within a request.
 */
#[AllowMockObjectsWithoutExpectations]
class CategoryExtractorTest extends TestCase
{
    /**
     * Confirms repeated calls for the same product load the category collection once and return the same names.
     *
     * @return void
     */
    public function testShouldLoadTheCategoriesOncePerProduct(): void
    {
        $product = $this->product(7, 1, ['Gear', 'Bags'], $this->once());
        $subject = new CategoryExtractor();

        $this->assertSame('Gear|Bags', $subject->getProductCategories($product));
        $this->assertSame('Gear|Bags', $subject->getProductCategories($product));
        $this->assertSame('Gear|Bags', $subject->getProductCategories($product));
    }

    /**
     * Confirms a product without categories is remembered as null and different products keep their own names.
     *
     * @return void
     */
    public function testShouldKeepCategoriesApartPerProduct(): void
    {
        $empty = $this->product(7, 1, [], $this->once());
        $other = $this->product(8, 1, ['Men'], $this->once());
        $subject = new CategoryExtractor();

        $this->assertNull($subject->getProductCategories($empty));
        $this->assertSame('Men', $subject->getProductCategories($other));
        $this->assertNull($subject->getProductCategories($empty));
        $this->assertSame('Men', $subject->getProductCategories($other));
    }

    /**
     * Confirms resetting the state between requests loads the categories again.
     *
     * @return void
     */
    public function testShouldLoadAgainAfterTheStateIsReset(): void
    {
        $product = $this->product(7, 1, ['Gear'], $this->exactly(2));
        $subject = new CategoryExtractor();

        $subject->getProductCategories($product);
        $subject->_resetState();
        $subject->getProductCategories($product);
    }

    /**
     * Builds a product whose category collection yields the given names and is expected to load as stated.
     *
     * @param int    $id
     * @param int    $storeId
     * @param array  $names
     * @param object $loads
     *
     * @return Product&MockObject
     */
    private function product(int $id, int $storeId, array $names, object $loads): MockObject
    {
        $categories = array_map(function (string $name) {
            $category = $this->createMock(Category::class);
            $category->method('getName')->willReturn($name);

            return $category;
        }, $names);

        $collection = $this->createMock(Collection::class);
        $collection->method('addAttributeToSelect')->willReturnSelf();
        $collection->method('getIterator')->willReturnCallback(fn () => new ArrayIterator($categories));

        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn($id);
        $product->method('getStoreId')->willReturn($storeId);
        $product->expects($loads)->method('getCategoryCollection')->willReturn($collection);

        return $product;
    }
}
