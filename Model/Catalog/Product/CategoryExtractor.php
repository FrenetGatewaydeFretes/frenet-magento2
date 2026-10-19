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

declare(strict_types=1);

namespace Frenet\Shipping\Model\Catalog\Product;

use Exception;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Category\Collection;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;

/**
 * Joins a product's category names for the Frenet quote, loading them once per product and store within a request.
 */
class CategoryExtractor implements ResetAfterRequestInterface
{
    /**
     * @var string
     */
    public const CATEGORY_SEPARATOR = '|';

    /**
     * @var array<string, string|null>
     */
    private array $categories = [];

    /**
     * Returns the product category names joined by the separator, cached per product and store for the request.
     *
     * @param Product $product
     *
     * @return string|null
     */
    public function getProductCategories(Product $product): ?string
    {
        $cacheKey = $product->getId() . '|' . $product->getStoreId();

        if (!array_key_exists($cacheKey, $this->categories)) {
            $this->categories[$cacheKey] = $this->loadProductCategories($product);
        }

        return $this->categories[$cacheKey];
    }

    /**
     * @inheritDoc
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     */
    public function _resetState(): void
    {
        $this->categories = [];
    }

    /**
     * Loads the product category names joined by the separator, or null when it has none.
     *
     * @param Product $product
     *
     * @return string|null
     */
    private function loadProductCategories(Product $product): ?string
    {
        try {
            /** @var Collection $collection */
            $collection = $product->getCategoryCollection();
            $collection->addAttributeToSelect('name');
        } catch (Exception $e) {
            return null;
        }

        $categories = [];

        /** @var Category $category */
        foreach ($collection as $category) {
            $categories[] = $category->getName();
        }

        if (!empty($categories)) {
            return implode(self::CATEGORY_SEPARATOR, $categories);
        }

        return null;
    }
}
