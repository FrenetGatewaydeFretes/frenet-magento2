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

namespace Frenet\Shipping\Model\Catalog\Product\View;

use Frenet\Shipping\Model\Catalog\ProductType;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\DataObject;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateRequestFactory;

class RateRequestBuilder
{
    /**
     * @var \Magento\Framework\DataObjectFactory
     */
    private $dataObjectFactory;

    /**
     * @var \Magento\Quote\Model\QuoteFactory
     */
    private $quoteFactory;

    /**
     * @var RateRequestFactory
     */
    private $rateRequestFactory;

    public function __construct(
        \Magento\Framework\DataObjectFactory $dataObjectFactory,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        RateRequestFactory $rateRequestFactory
    ) {
        $this->dataObjectFactory = $dataObjectFactory;
        $this->quoteFactory = $quoteFactory;
        $this->rateRequestFactory = $rateRequestFactory;
    }

    /**
     * @param ProductInterface $product
     * @param string           $postcode
     * @param int              $qty
     *
     * @return RateRequest
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function build(ProductInterface $product, string $postcode, int $qty = 1) : RateRequest
    {
        $quote = $this->createQuote();
        $quote->getShippingAddress()->setPostcode($postcode);

        $request = $this->prepareProductRequest($product, $qty);
        $candidates = $product->getTypeInstance()->prepareForCartAdvanced($request, $product);

        foreach ((array) $candidates as $candidate) {
            $quote->addProduct($candidate, $qty);
        }

        /** @var RateRequest $rateRequest */
        $rateRequest = $this->rateRequestFactory->create();

        $rateRequest->setAllItems($quote->getAllItems());
        $rateRequest->setDestPostcode($postcode);
        $rateRequest->setDestCountryId('BR');

        return $rateRequest;
    }

    /**
     * @param ProductInterface $product
     * @param int              $qty
     *
     * @return DataObject
     */
    private function prepareProductRequest(ProductInterface $product, int $qty = 1) : DataObject
    {
        /** @var DataObject $request */
        $request = $this->dataObjectFactory->create();
        $request->setData(['qty' => $qty]);

        $this->prepareOptions($product, $request);

        return $request;
    }

    /**
     * @param ProductInterface $product
     * @param DataObject       $request
     */
    private function prepareOptions(ProductInterface $product, DataObject $request)
    {
        $options = [];

        /** @var \Magento\Catalog\Model\Product\Type\AbstractType $typeInstance */
        $typeInstance = $product->getTypeInstance();

        switch ($product->getTypeId()) {
            case ProductType::TYPE_CONFIGURABLE:
                $configurableOptions = $typeInstance->getConfigurableOptions($product);

                /**
                 * Get the default attribute options.
                 */
                foreach ($configurableOptions as $configurableOptionId => $configurableOption) {
                    /** @var array $option */
                    $option = array_shift($configurableOption);
                    $options[$configurableOptionId] = $option['value_index'];
                }

                $request->setData('super_attribute', $options);
                break;
            case ProductType::TYPE_BUNDLE:
                $bundleOptions = [];
                $bundleOptionsQty = [];

                /** @var \Magento\Bundle\Model\ResourceModel\Option\Collection $optionsCollection */
                $optionsCollection = $typeInstance->getOptionsCollection($product);

                /** @var \Magento\Bundle\Model\Option $option */
                foreach ($optionsCollection as $option) {
                    /** If the option is not required then we can by pass it. */
                    if (!$option->getRequired()) {
                        continue;
                    }

                    /** @var \Magento\Bundle\Model\Selection $selection */
                    $selection = $option->getDefaultSelection();

                    if (!$selection) {
                        /** @var \Magento\Bundle\Model\ResourceModel\Selection\Collection $selections */
                        $selection = $typeInstance->getSelectionsCollection(
                            $option->getId(),
                            $product
                        )->getFirstItem();
                    }

                    if (!$selection) {
                        continue;
                    }

                    $bundleOptions[$option->getId()] = $selection->getSelectionId();
                }

                $request->setData('bundle_option', $bundleOptions);
                $request->setData('bundle_option_qty', $bundleOptionsQty);
                break;
            case ProductType::TYPE_GROUPED:
                $associatedProductsQty = [];

                /** @var \Magento\Catalog\Model\Product $associatedProduct */
                foreach ($typeInstance->getAssociatedProducts($product) as $associatedProduct) {
                    $associatedProductsQty[$associatedProduct->getId()] = $associatedProduct->getQty() ?: 1;
                }

                $request->setData('super_group', $associatedProductsQty);
                break;
        }
    }

    /**
     * @return \Magento\Quote\Model\Quote
     */
    private function createQuote() : \Magento\Quote\Model\Quote
    {
        return $this->quoteFactory->create();
    }
}
