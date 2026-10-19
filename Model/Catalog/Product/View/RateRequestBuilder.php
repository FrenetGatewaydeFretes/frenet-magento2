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

use Frenet\Shipping\Model\Catalog\Product\DimensionsExtractorInterface;
use Frenet\Shipping\Model\Catalog\Product\View\RateRequestBuilder\BuilderInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Option as ProductOption;
use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote as CartQuote;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateRequestFactory;
use Magento\Quote\Model\Quote\Item\AbstractItem as QuoteItem;
use Magento\Quote\Model\QuoteFactory;

/**
 * Builds the rate request of a product page quote by adding the product to a throwaway quote to resolve its items.
 */
class RateRequestBuilder
{
    /**
     * @var DataObjectFactory
     */
    private $dataObjectFactory;

    /**
     * @var QuoteFactory
     */
    private $quoteFactory;

    /**
     * @var RateRequestFactory
     */
    private $rateRequestFactory;

    /**
     * @var BuilderInterface[]
     */
    private $builders;

    /**
     * @var DimensionsExtractorInterface
     */
    private $dimensionsExtractor;

    /**
     * @param DataObjectFactory $dataObjectFactory
     * @param QuoteFactory $quoteFactory
     * @param RateRequestFactory $rateRequestFactory
     * @param DimensionsExtractorInterface $dimensionsExtractor
     * @param ProductOption $productOption
     * @param BuilderInterface[] $builders
     */
    public function __construct(
        DataObjectFactory $dataObjectFactory,
        QuoteFactory $quoteFactory,
        RateRequestFactory $rateRequestFactory,
        DimensionsExtractorInterface $dimensionsExtractor,
        private readonly ProductOption $productOption,
        array $builders = []
    ) {
        $this->dataObjectFactory = $dataObjectFactory;
        $this->quoteFactory = $quoteFactory;
        $this->rateRequestFactory = $rateRequestFactory;
        $this->builders = $builders;
        $this->dimensionsExtractor = $dimensionsExtractor;
    }

    /**
     * Adds the product to a temporary quote and builds a rate request to the postcode with its items and total weight.
     *
     * @param ProductInterface $product
     * @param string           $postcode
     * @param int              $qty
     * @param mixed[]          $options
     *
     * @return RateRequest
     * @throws LocalizedException
     */
    public function build(ProductInterface $product, string $postcode, int $qty = 1, array $options = []): RateRequest
    {
        if (!$product instanceof Product) {
            throw new LocalizedException(__('The shipping quote is not available.'));
        }

        $quote = $this->createQuote();
        $quote->getShippingAddress()->setPostcode($postcode);
        $request = $this->prepareProductRequest($product, $qty, $options);
        $addResult = $quote->addProduct($product, $request);
        $this->fixQuoteItems($quote);
        $allitems = $quote->getAllItems();

        if (count($allitems) == 0) {
            $request = $this->prepareProductOptionRequest($product, $qty, $options);
            $addResult = $quote->addProduct($product, $request);
            if (is_string($addResult)) {
                throw new LocalizedException(
                    __($addResult)
                );
            }

            $this->fixQuoteItems($quote);
            $allitems = $quote->getAllItems();
        }

        /** @var RateRequest $rateRequest */
        $rateRequest = $this->rateRequestFactory->create();
        $rateRequest->setAllItems($allitems);
        $rateRequest->setDestPostcode($postcode);
        $rateRequest->setDestCountryId('BR');

        $totalWeight = 0;

        /** @var QuoteItem $item */
        foreach ($allitems as $item) {
            $totalWeight += $item->getRowWeight();
        }

        $rateRequest->setPackageWeight($totalWeight);

        return $rateRequest;
    }

    /**
     * Gives each quote item its product ID when it has none and sets its row weight from the extracted weight.
     *
     * @param CartQuote $quote
     *
     * @return void
     */
    private function fixQuoteItems(CartQuote $quote)
    {
        /** @var QuoteItem $item */
        foreach ($quote->getAllItems() as $item) {
            if (!$item->getId()) {
                $item->setId($item->getProduct()->getId());
            }

            $qty = $item->getProduct()->getCartQty();
            $item->setRowWeight($this->getItemRowWeight($item, $qty));
        }
    }

    /**
     * Multiplies the item weight, with the configured default as fallback, by the quantity.
     *
     * @param QuoteItem $item
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
     * Builds a retry buy request with only the given custom options that exist on the product.
     *
     * @param Product $product
     * @param int     $qty
     * @param mixed[] $options
     *
     * @return DataObject
     */
    private function prepareProductOptionRequest(
        Product $product,
        int $qty = 1,
        array $options = []
    ): DataObject {
        /** @var DataObject $request */
        $request = $this->dataObjectFactory->create();
        if (isset($options['options']) && !empty($options['options'])) {
            // sync options: https://magento.stackexchange.com/questions/286402/error-exception-message-the-products-required-options-werent-entered-make
            $optionsRequest = $options["options"];
            $customOptions = $this->productOption->getProductOptionCollection($product);

            $optionsValues = [];
            foreach ($customOptions->getItems() as $option) {
                if (array_key_exists($option->getId(), $optionsRequest)) {
                    $optionsValues[$option->getId()] = $optionsRequest[$option->getId()];
                }
            }

            $request->setData(['qty' => $qty, 'options' =>  $optionsValues]);
        }

        return $request;
    }

    /**
     * Builds the buy request with the quantity and the options filled by the builder of the product type.
     *
     * @param ProductInterface $product
     * @param int              $qty
     * @param mixed[]          $options
     *
     * @return DataObject
     */
    private function prepareProductRequest(ProductInterface $product, int $qty = 1, array $options = []): DataObject
    {
        /** @var DataObject $request */
        $request = $this->dataObjectFactory->create();
        $request->setData(['qty' => $qty]);

        $this->getBuilder($product->getTypeId())->build($product, $request, $options);

        return $request;
    }

    /**
     * Creates the empty quote used only to resolve the items of the rate request.
     *
     * @return CartQuote
     */
    private function createQuote(): CartQuote
    {
        return $this->quoteFactory->create();
    }

    /**
     * Returns the buy request builder registered for the product type, or the default one.
     *
     * @param string $type
     *
     * @return BuilderInterface
     */
    private function getBuilder(string $type): RateRequestBuilder\BuilderInterface
    {
        if (isset($this->builders[$type])) {
            return $this->builders[$type];
        }

        return $this->builders['default'];
    }
}
