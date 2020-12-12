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

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product\Type;
use Magento\Bundle\Model\Product\Type as Bundle;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\GroupedProduct\Model\Product\Type\Grouped;
use Magento\Downloadable\Model\Product\Type as Downloadable;

/**
 * Class ProductType
 */
class ProductType
{
    /**
     * @var string
     */
    const TYPE_SIMPLE = Type::TYPE_SIMPLE;

    /**
     * @var string
     */
    const TYPE_VIRTUAL = Type::TYPE_VIRTUAL;

    /**
     * @var string
     */
    const TYPE_CONFIGURABLE = Configurable::TYPE_CODE;

    /**
     * @var string
     */
    const TYPE_BUNDLE = Bundle::TYPE_CODE;

    /**
     * @var string
     */
    const TYPE_GROUPED = Grouped::TYPE_CODE;

    /**
     * @var string
     */
    const TYPE_DOWNLOADABLE = Downloadable::TYPE_DOWNLOADABLE;

    /**
     * @var array
     */
    const PRODUCT_TYPES = [
        self::TYPE_SIMPLE,
        self::TYPE_VIRTUAL,
        self::TYPE_CONFIGURABLE,
        self::TYPE_BUNDLE,
        self::TYPE_GROUPED,
        self::TYPE_DOWNLOADABLE,
    ];

    /**
     * @param ProductInterface $product
     *
     * @return bool
     */
    public function isTypeSimple(ProductInterface $product): bool
    {
        return $this->isType($product, self::TYPE_SIMPLE);
    }

    /**
     * @param ProductInterface $product
     *
     * @return bool
     */
    public function isTypeVirtual(ProductInterface $product): bool
    {
        return $this->isType($product, self::TYPE_VIRTUAL);
    }

    /**
     * @param ProductInterface $product
     *
     * @return bool
     */
    public function isTypeConfigurable(ProductInterface $product): bool
    {
        return $this->isType($product, self::TYPE_CONFIGURABLE);
    }

    /**
     * @param ProductInterface $product
     *
     * @return bool
     */
    public function isTypeBundle(ProductInterface $product): bool
    {
        return $this->isType($product, self::TYPE_BUNDLE);
    }

    /**
     * @param ProductInterface $product
     *
     * @return bool
     */
    public function isTypeGrouped(ProductInterface $product): bool
    {
        return $this->isType($product, self::TYPE_GROUPED);
    }

    /**
     * @param ProductInterface $product
     *
     * @return bool
     */
    public function isTypeDownloadable(ProductInterface $product): bool
    {
        return $this->isType($product, self::TYPE_DOWNLOADABLE);
    }

    /**
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
