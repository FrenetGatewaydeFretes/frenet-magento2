<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 * @package Frenet\Shipping
 *
 * @author Tiago Sampaio <tiago@tiagosampaio.com>
 * @link https://github.com/tiagosampaio
 * @link https://tiagosampaio.com
 *
 * Copyright (c) 2020.
 */

namespace Frenet\Shipping\Model\Catalog\Product\View\RateRequestBuilder;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\DataObject;
use Frenet\Shipping\Model\FrenetMagentoAbstract;
use \Psr\Log\LoggerInterface;

/**
 * Class GroupedBuilder
 * @SuppressWarnings(PHPMD.LongVariable)
 */
class GroupedBuilder extends FrenetMagentoAbstract implements BuilderInterface
{

    /**
     * @param \Psr\Log\LoggerInterface    $logger
     */
    public function __construct(
        \Psr\Log\LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    /**
     * @inheritDoc
     */
    public function build(ProductInterface $product, DataObject $request, array $options = [])
    {
        $this->_logger->debug("grouped-builder-buid-pre");
        if ($options && isset($options['super_group'])) {
            $request->setData('super_group', $options['super_group']);
            $this->_logger->debug("grouped-builder-buid-pos-option");
            return;
        }

        $this->buildDefaultOptions($product, $request);
        $this->_logger->debug("grouped-builder-buid-pos-default-pos");
    }

    /**
     * @param ProductInterface $product
     * @param DataObject       $request
     *
     * @return void
     */
    private function buildDefaultOptions(ProductInterface $product, DataObject $request)
    {
        $this->_logger->debug("grouped-builder-buid-pos-default-pre-getTypeInstance");
        /** @var \Magento\Catalog\Model\Product\Type\AbstractType $typeInstance */
        $typeInstance = $product->getTypeInstance();

        $associatedProductsQty = [];

        $this->_logger->debug("grouped-builder-buid-pos-default-pre-foreach");
        /** @var \Magento\Catalog\Model\Product $associatedProduct */
        foreach ($typeInstance->getAssociatedProducts($product) as $associatedProduct) {
            $this->_logger->debug("grouped-builder-buid-pos-default-pre-associeted-product-getid-qty");
            $associatedProductsQty[$associatedProduct->getId()] = $associatedProduct->getQty() ?: 1;
        }

        $this->_logger->debug("grouped-builder-buid-pos-default-pre-super_group");
        $request->setData('super_group', $associatedProductsQty);
    }
}
