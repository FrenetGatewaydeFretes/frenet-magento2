<?php

declare(strict_types = 1);

namespace Frenet\Shipping\Model\Catalog\Product;

/**
 * Class CategoryExtractor
 *
 * @package Frenet\Shipping\Model\Catalog\Product
 */
class CategoryExtractor
{
    /**
     * @var string
     */
    const CATEGORY_SEPARATOR = '|';

    /**
     * @param \Magento\Catalog\Model\Product $product
     *
     * @return string|null
     */
    public function getProductCategories(\Magento\Catalog\Model\Product $product)
    {
        try {
            /** @var \Magento\Catalog\Model\ResourceModel\Category\Collection $collection */
            $collection = $product->getCategoryCollection();
            $collection->addAttributeToSelect('name');
        } catch (\Exception $e) {
            return null;
        }

        $categories = [];

        /** @var \Magento\Catalog\Model\Category $category */
        foreach ($collection as $category) {
            $categories[] = $category->getName();
        }

        if (!empty($categories)) {
            return implode(self::CATEGORY_SEPARATOR, $categories);
        }

        return null;
    }
}
