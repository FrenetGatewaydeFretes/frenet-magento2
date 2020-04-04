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
declare(strict_types = 1);

namespace Frenet\Shipping\Model\Catalog\Product\View;

use Frenet\Framework\Data\DataObject;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\Data\CartItemInterface;

/**
 * Class Quote
 *
 * @package Frenet\Shipping\Model\Catalog\Product\View
 */
class Quote implements QuoteInterface
{
    /**
     * @var \Magento\Quote\Model\Quote\Item\Processor
     */
    private $quoteItemProcessor;

    /**
     * @var \Magento\Framework\DataObjectFactory
     */
    private $objectFactory;

    /**
     * @var \Magento\Catalog\Api\ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    public function __construct(
        \Magento\Quote\Model\Quote\Item\Processor $quoteItemProcessor,
        \Magento\Framework\DataObjectFactory $objectFactory,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->quoteItemProcessor = $quoteItemProcessor;
        $this->objectFactory = $objectFactory;
        $this->productRepository = $productRepository;
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function quote(ProductInterface $product) : array
    {
        $item = $this->getQuoteItem($product);
    }

    /**
     * @inheritDoc
     */
    public function quoteByProductId(int $productId) : array
    {
        try {
            $product = $this->productRepository->getById($productId);
        } catch (NoSuchEntityException $exception) {
            $this->logger->warning(__('Product ID %1 does not exist.', $productId));
            return [];
        }

        return $this->quote($product);
    }

    /**
     * @inheritDoc
     */
    public function quoteByProductSku(string $productSku) : array
    {
        try {
            $product = $this->productRepository->get($productSku);
        } catch (NoSuchEntityException $exception) {
            $this->logger->warning(__('Product SKU %1 does not exist.', $productSku));
            return [];
        }

        return $this->quote($product);
    }

    /**
     * @param ProductInterface $product
     *
     * @return CartItemInterface
     */
    private function getQuoteItem(ProductInterface $product) : CartItemInterface
    {
        /**
         * @var DataObject $request
         * @var CartItemInterface $item
         */
        $request = $this->objectFactory->create(['qty' => 1]);
        $item = $this->quoteItemProcessor->init($product, $request);
        $this->quoteItemProcessor->prepare($item, $request, $product);

        return $item;
    }
}
