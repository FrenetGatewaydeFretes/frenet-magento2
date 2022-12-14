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
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateRequestFactory;
use Magento\Quote\Model\Quote\Item\AbstractItem as QuoteItem;

/**
 * Class RateRequestBuilder
 */
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
     * @throws LocalizedException
     */
    public function build(ProductInterface $product, string $postcode, int $qty = 1, array $options = []): RateRequest
    {
        $quote = $this->createQuote();
        $quote->getShippingAddress()->setPostcode($postcode);
        $request = $this->prepareProductRequest($product, $qty, $options);
		
        $rs = $quote->addProduct($product, $request);

		if (is_string($rs)) {
			throw new \Magento\Framework\Exception\LocalizedException(
				__($rs)
			);
		}

        $this->fixQuoteItems($quote);

        /** @var RateRequest $rateRequest */
        $rateRequest = $this->rateRequestFactory->create();
		$allitems = $quote->getAllItems();

		if (count($allitems) == 0) {
			$quoteItem = $quote->getItemByProduct($product);
			if (is_bool($quoteItem)) {
				throw new \Magento\Framework\Exception\LocalizedException(
					__('inclusão do produto falhou')
				);
			}
			$allitems = [ $quoteItem ];
		}

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

    private function fixQuoteItems(\Magento\Quote\Model\Quote $quote)
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
    private function prepareProductRequest(ProductInterface $product, int $qty = 1, array $options = []): DataObject
    {
        /** @var DataObject $request */
        $request = $this->dataObjectFactory->create();
        
        $this->getBuilder($product->getTypeId())->build($product, $request, $options);
				
		if (isset($options['options']) && !empty($options['options'])) {   
			// sync options: https://magento.stackexchange.com/questions/286402/error-exception-message-the-products-required-options-werent-entered-make
			$optionsRequest = $options["options"];
			$objtManager = \Magento\Framework\App\ObjectManager::getInstance();
			$customOptions = $objtManager->get('Magento\Catalog\Model\Product\Option')->getProductOptionCollection($product);
			
			$optionsValues = [];
			foreach ($customOptions->getItems() as $option) {
				$optionsValues[$option->getId()] = $optionsRequest[$option->getId()];
			}

			$request->setData(['qty' => $qty, 'options' =>  $optionsValues]);
		}
		else {
			$request->setData(['qty' => $qty]);
		}

        return $request;
    }

    /**
     * @return \Magento\Quote\Model\Quote
     */
    private function createQuote(): \Magento\Quote\Model\Quote
    {
        return $this->quoteFactory->create();
    }

    /**
     * @param string $type
     *
     * @return RateRequestBuilder\BuilderInterface
     */
    private function getBuilder(string $type): RateRequestBuilder\BuilderInterface
    {
        if (isset($this->builders[$type])) {
            return $this->builders[$type];
        }

        return $this->builders['default'];
    }
}
