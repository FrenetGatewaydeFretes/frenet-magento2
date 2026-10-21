<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 * @package  Frenet\Shipping
 *
 * @author   Tiago Sampaio <tiago@tiagosampaio.com>
 * @link     https://github.com/tiagosampaio
 * @link     https://tiagosampaio.com
 *
 * Copyright (c) 2020.
 */

namespace Frenet\Shipping\Model\Catalog;

use Magento\Bundle\Model\Product\Type as Bundle;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product\Type;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Downloadable\Model\Product\Type as Downloadable;
use Magento\GroupedProduct\Model\Product\Type\Grouped;

/**
 * Lists the product type codes that carry the Frenet shipping attributes and checks a product against them.
 */
class ProductType
{
    /**
     * @var string
     */
    public const TYPE_SIMPLE = Type::TYPE_SIMPLE;

    /**
     * @var string
     */
    public const TYPE_VIRTUAL = Type::TYPE_VIRTUAL;

    /**
     * @var string
     */
    public const TYPE_CONFIGURABLE = Configurable::TYPE_CODE;

    /**
     * @var string
     */
    public const TYPE_BUNDLE = Bundle::TYPE_CODE;

    /**
     * @var string
     */
    public const TYPE_GROUPED = Grouped::TYPE_CODE;

    /**
     * @var string
     */
    public const TYPE_DOWNLOADABLE = Downloadable::TYPE_DOWNLOADABLE;

    /**
     * @var string[]
     */
    public const PRODUCT_TYPES = [
        self::TYPE_SIMPLE,
        self::TYPE_VIRTUAL,
        self::TYPE_CONFIGURABLE,
        self::TYPE_BUNDLE,
        self::TYPE_GROUPED,
        self::TYPE_DOWNLOADABLE,
    ];

    /**
     * Checks whether the product is of the simple type.
     *
     * @param ProductInterface $product
     *
     * @return bool
     */
    public function isTypeSimple(ProductInterface $product): bool
    {
        return $this->isType($product, self::TYPE_SIMPLE);
    }

    /**
     * Checks whether the product is of the virtual type.
     *
     * @param ProductInterface $product
     *
     * @return bool
     */
    public function isTypeVirtual(ProductInterface $product): bool
    {
        return $this->isType($product, self::TYPE_VIRTUAL);
    }

    /**
     * Checks whether the product is of the configurable type.
     *
     * @param ProductInterface $product
     *
     * @return bool
     */
    public function isTypeConfigurable(ProductInterface $product): bool
    {
        return $this->isType($product, self::TYPE_CONFIGURABLE);
    }

    /**
     * Checks whether the product is of the bundle type.
     *
     * @param ProductInterface $product
     *
     * @return bool
     */
    public function isTypeBundle(ProductInterface $product): bool
    {
        return $this->isType($product, self::TYPE_BUNDLE);
    }

    /**
     * Checks whether the product is of the grouped type.
     *
     * @param ProductInterface $product
     *
     * @return bool
     */
    public function isTypeGrouped(ProductInterface $product): bool
    {
        return $this->isType($product, self::TYPE_GROUPED);
    }

    /**
     * Checks whether the product is of the downloadable type.
     *
     * @param ProductInterface $product
     *
     * @return bool
     */
    public function isTypeDownloadable(ProductInterface $product): bool
    {
        return $this->isType($product, self::TYPE_DOWNLOADABLE);
    }

    /**
     * Compares the product type ID with the given one strictly.
     *
     * @param ProductInterface $product
     * @param string           $typeId
     *
     * @return bool
     */
    private function isType(ProductInterface $product, string $typeId): bool
    {
        return $product->getTypeId() === $typeId;
    }
}
