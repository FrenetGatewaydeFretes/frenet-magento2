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

        foreach ($candidates as $candidate) {
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

        switch ($product->getTypeId()) {
            case \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE:
                /** @var \Magento\ConfigurableProduct\Model\Product\Type\Configurable $typeInstance */
                $typeInstance = $product->getTypeInstance();
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
