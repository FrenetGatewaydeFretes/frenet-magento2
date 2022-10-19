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
 * Class ConfigurableBuilder
 * @SuppressWarnings(PHPMD.LongVariable)
 */
class ConfigurableBuilder extends FrenetMagentoAbstract implements BuilderInterface
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
        $this->_logger->debug("configurable-builder-buid-pre");
        if ($options && isset($options['super_attribute'])) {
            $request->setData('super_attribute', $options['super_attribute']);
            $this->_logger->debug("configurable-builder-buid-pos-option");
            return;
        }

        $this->buildDefaultOptions($product, $request);
        $this->_logger->debug("configurable-builder-buid-pos-default-pos");
    }

    /**
     * @param ProductInterface $product
     * @param DataObject       $request
     *
     * @return void
     */
    private function buildDefaultOptions(ProductInterface $product, DataObject $request)
    {
        $options = [];

        $this->_logger->debug("configurable-builder-buid-pos-default-pre-getTypeInstance");

        /** @var \Magento\Catalog\Model\Product\Type\AbstractType $typeInstance */
        $typeInstance = $product->getTypeInstance();

        $this->_logger->debug("configurable-builder-buid-pos-default-pre-getOptionsCollection");
        $configurableOptions = $typeInstance->getConfigurableOptions($product);

        $this->_logger->debug("configurable-builder-buid-pos-default-pre-foreach");
        /**
         * Get the default attribute options.
         */
        foreach ($configurableOptions as $configurableOptionId => $configurableOption) {
            /** @var array $option */
            $option = array_shift($configurableOption);
            $options[$configurableOptionId] = $option['value_index'];
        }

        $this->_logger->debug("configurable-builder-buid-pos-default-pre-super_attribute");
        $request->setData('super_attribute', $options);
    }
}
