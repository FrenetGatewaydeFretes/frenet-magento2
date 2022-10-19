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
 * Class BundleBuilder
 * @SuppressWarnings(PHPMD.LongVariable)
 */
class BundleBuilder extends FrenetMagentoAbstract implements BuilderInterface
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
        $this->_logger->debug("bundle-builder-buid-pre");
        if ($options && isset($options['bundle_option'])) {
            $option = $options['bundle_option'];
            $qty    = $options['bundle_option_qty'] ?? 1;

            $request->setData('bundle_option', $option);
            $request->setData('bundle_option_qty', $qty);
            $this->_logger->debug("bundle-builder-buid-pos-option");
            return;
        }

        $this->buildDefaultOptions($product, $request);
        $this->_logger->debug("bundle-builder-buid-pos-default-pos");
    }

    /**
     * @param ProductInterface $product
     * @param DataObject       $request
     *
     * @return void
     */
    private function buildDefaultOptions(ProductInterface $product, DataObject $request)
    {
        $this->_logger->debug("bundle-builder-buid-pos-default-pre-getTypeInstance");
        /** @var \Magento\Catalog\Model\Product\Type\AbstractType $typeInstance */
        $typeInstance = $product->getTypeInstance();

        $bundleOptions = [];
        $bundleOptionsQty = [];

        /** @var \Magento\Bundle\Model\ResourceModel\Option\Collection $optionsCollection */
        $this->_logger->debug("bundle-builder-buid-pos-default-pre-getOptionsCollection");
        $optionsCollection = $typeInstance->getOptionsCollection($product);

        $this->_logger->debug("bundle-builder-buid-pos-default-pre-foreach");
        /** @var \Magento\Bundle\Model\Option $option */
        foreach ($optionsCollection as $option) {
            /** If the option is not required then we can by pass it. */
            if (!$option->getRequired()) {
                $this->_logger->debug("bundle-builder-buid-pos-default-pre-foreach-not-required");
                continue;
            }

            /** @var \Magento\Bundle\Model\Selection $selection */
            $this->_logger->debug("bundle-builder-buid-pos-default-pre-foreach-default-selection");
            $selection = $option->getDefaultSelection();

            if (!$selection) {
                $this->_logger->debug("bundle-builder-buid-pos-default-pre-foreach-default-not-selection");
                /** @var \Magento\Bundle\Model\ResourceModel\Selection\Collection $selections */
                $selection = $typeInstance->getSelectionsCollection(
                    $option->getId(),
                    $product
                )->getFirstItem();
            }

            if (!$selection) {
                $this->_logger->debug("bundle-builder-buid-pos-default-pre-foreach-default-not-selection-continue");
                continue;
            }

            $this->_logger->debug("bundle-builder-buid-pos-default-pre-foreach-default-get-selecionid");
            $bundleOptions[$option->getId()] = $selection->getSelectionId();
        }

        $this->_logger->debug("bundle-builder-buid-pos-default-pre-bundle-option");
        $request->setData('bundle_option', $bundleOptions);
        $this->_logger->debug("bundle-builder-buid-pos-default-pre-bundle-option-qty");
        $request->setData('bundle_option_qty', $bundleOptionsQty);
    }
}
