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

declare(strict_types=1);

namespace Frenet\Shipping\Controller\Product;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;

/**
 * Class Quote
 *
 * @package Frenet\Shipping\Controller\Catalog\Product
 */
class Quote extends Action implements HttpPostActionInterface
{
    /**
     * Maximum quantity accepted for a single shipping quote request.
     *
     * This endpoint builds an in-memory quote item directly from the
     * request, bypassing the cart's own qty validation. A crafted qty
     * value would otherwise be free to inflate the number of shipping
     * packages (and outbound API calls to Frenet, one per package - see
     * PackagesCalculator::processPackages()) that a single request can
     * trigger, regardless of the product's configured weight.
     */
    private const MAX_QTY = 1000;

    /**
     * @var \Frenet\Shipping\Api\QuoteProductInterface
     */
    private $quoteProduct;

    public function __construct(
        Context $context,
        \Frenet\Shipping\Api\QuoteProductInterface $quoteProduct
    ) {
        parent::__construct($context);
        $this->quoteProduct = $quoteProduct;
    }

    /**
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $productId = (int) $this->getRequest()->getParam('product');
        $postcode = (string) $this->getRequest()->getParam('postcode');
        $qty = (int) $this->getRequest()->getParam('qty');
        $options = (array) $this->getRequest()->getParams();

        /** @var \Magento\Framework\Controller\Result\Json $page */
        $page = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        if ($qty <= 0 || $qty > self::MAX_QTY) {
            return $this->jsonError($page, (string) __('Invalid quantity informed.'));
        }

        try {
            $rates = $this->quoteProduct->quoteByProductId($productId, $postcode, $qty, $options);

            $page->setData([
                'error' => false,
                'rates' => $rates
            ]);
        } catch (\Exception $exception) {
            $this->jsonError($page, $exception->getMessage());
        }

        return $page;
    }

    /**
     * @param \Magento\Framework\Controller\Result\Json $page
     * @param string                                    $message
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    private function jsonError(\Magento\Framework\Controller\Result\Json $page, string $message): \Magento\Framework\Controller\Result\Json
    {
        return $page->setData([
            'error'   => true,
            'message' => $message
        ]);
    }
}
