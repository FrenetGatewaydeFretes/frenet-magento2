<?php
declare(strict_types = 1);

namespace Frenet\Shipping\Api\Data;

/**
 * Class ProductExtractorInterface
 *
 * @package Frenet\Shipping\Api\Data
 */
interface ProductExtractorInterface extends DimensionsExtractorInterface
{
    /**
     * @param \Magento\Catalog\Api\Data\ProductInterface $product
     *
     * @return $this
     */
    public function setProduct(\Magento\Catalog\Api\Data\ProductInterface $product);
}
