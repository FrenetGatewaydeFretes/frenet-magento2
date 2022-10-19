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
use Frenet\Shipping\Service\RateRequestProvider;
use Frenet\Shipping\Model\FrenetMagentoAbstract;
use \Psr\Log\LoggerInterface;

/**
 * Class DefaultBuilder
 */
class DefaultBuilder extends FrenetMagentoAbstract implements BuilderInterface
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
     * @codingStandardsIgnoreStart
     */
    public function build(ProductInterface $product, DataObject $request, array $options = [])
    {
        $this->_logger->debug("default-builder-buid-pre");
        //@codingStandardsIgnoreEnd
    }
}
