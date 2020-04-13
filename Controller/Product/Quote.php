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
        $qty = (float) $this->getRequest()->getParam('qty');
        $options = (array) $this->getRequest()->getParams();

        try {
            /** @var \Magento\Framework\Controller\Result\Json $result */
            $page = $this->resultFactory->create(ResultFactory::TYPE_JSON);
            $rates = $this->quoteProduct->quoteByProductId($productId, $postcode, $qty, $options);

            $page->setData([
                'error' => false,
                'rates' => $rates
            ]);
        } catch (\Exception $exception) {
            $page->setData([
                'error'   => true,
                'message' => $exception->getMessage()
            ]);
        }

        return $page;
    }
}
