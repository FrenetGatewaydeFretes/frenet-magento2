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

namespace Frenet\Shipping\Model\Catalog\Product\View;

use Frenet\Shipping\Api\Data\ProductQuoteOptionsInterface;
use Frenet\Shipping\Model\Catalog\Product\DimensionsExtractorInterface;
use Frenet\Shipping\Model\Catalog\ProductType;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateRequestFactory;
use Magento\Quote\Model\Quote\Item as QuoteItem;

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

    /**
     * @var array
     */
    private $builders;

    /**
     * @var DimensionsExtractorInterface
     */
    private $dimensionsExtractor;

    public function __construct(
        \Magento\Framework\DataObjectFactory $dataObjectFactory,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        RateRequestFactory $rateRequestFactory,
        DimensionsExtractorInterface $dimensionsExtractor,
        array $builders = []
    ) {
        $this->dataObjectFactory = $dataObjectFactory;
        $this->quoteFactory = $quoteFactory;
        $this->rateRequestFactory = $rateRequestFactory;
        $this->builders = $builders;
        $this->dimensionsExtractor = $dimensionsExtractor;
    }

    /**
     * @param ProductInterface $product
     * @param string           $postcode
     * @param int              $qty
     * @param array            $options
     *
     * @return RateRequest
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function build(ProductInterface $product, string $postcode, int $qty = 1, array $options = []) : RateRequest
    {
        $quote = $this->createQuote();
        $quote->getShippingAddress()->setPostcode($postcode);

        $request = $this->prepareProductRequest($product, $qty, $options);
        $candidates = $product->getTypeInstance()->prepareForCartAdvanced($request, $product);

        if (is_string($candidates)) {
            throw new LocalizedException(__($candidates));
        }

        /** @var ProductInterface $candidate */
        foreach ((array) $candidates as $candidate) {
            $cartQty = $candidate->getCartQty() ?: $qty;
            $quote->addProduct($candidate, $cartQty);
        }

        /** @var RateRequest $rateRequest */
        $rateRequest = $this->rateRequestFactory->create();

        $rateRequest->setAllItems($quote->getAllItems());
        $rateRequest->setDestPostcode($postcode);
        $rateRequest->setDestCountryId('BR');

        $totalWeight = 0;

        /** @var QuoteItem $item */
        foreach ($quote->getAllItems() as $item) {
            if (!$item->getId()) {
                $item->setId($item->getProduct()->getId());
            }

            $cartQty = $item->getProduct()->getCartQty() ?: $qty;
            $totalWeight += $this->getItemRowWeight($item, $cartQty);
        }

        $rateRequest->setPackageWeight($totalWeight);

        return $rateRequest;
    }

    /**
     * @param float $itemWeight
     * @param float $qty
     *
     * @return float
     */
    private function getItemRowWeight(QuoteItem $item, $qty): float
    {
        $this->dimensionsExtractor->setProductByCartItem($item);
        $weight = $this->dimensionsExtractor->getWeight();

        return $weight * $qty;
    }

    /**
     * @param ProductInterface $product
     * @param int              $qty
     * @param array            $options
     *
     * @return DataObject
     */
    private function prepareProductRequest(ProductInterface $product, int $qty = 1, array $options = []) : DataObject
    {
        /** @var DataObject $request */
        $request = $this->dataObjectFactory->create();
        $request->setData(['qty' => $qty]);

        $this->getBuilder($product->getTypeId())->build($product, $request, $options);

        return $request;
    }

    /**
     * @return \Magento\Quote\Model\Quote
     */
    private function createQuote() : \Magento\Quote\Model\Quote
    {
        return $this->quoteFactory->create();
    }

    /**
     * @param string $type
     *
     * @return RateRequestBuilder\BuilderInterface
     */
    private function getBuilder(string $type) : RateRequestBuilder\BuilderInterface
    {
        if (isset($this->builders[$type])) {
            return $this->builders[$type];
        }

        return $this->builders['default'];
    }
}
