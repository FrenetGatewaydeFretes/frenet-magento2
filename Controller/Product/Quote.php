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

use Frenet\Shipping\Api\QuoteProductInterface;
use Frenet\Shipping\Model\Config;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;

/**
 * Class Quote
 *
 * @package Frenet\Shipping\Controller\Catalog\Product
 */
class Quote extends Action implements HttpPostActionInterface
{
    /**
     * @var QuoteProductInterface
     */
    private $quoteProduct;

    /**
     * @var Config
     */
    private $config;

    /**
     * @param Context               $context
     * @param QuoteProductInterface $quoteProduct
     * @param Config                $config
     */
    public function __construct(
        Context $context,
        QuoteProductInterface $quoteProduct,
        Config $config
    ) {
        parent::__construct($context);
        $this->quoteProduct = $quoteProduct;
        $this->config = $config;
    }

    /**
     * Handles the AJAX shipping quote request for a single product.
     *
     * @return ResultInterface
     */
    public function execute()
    {
        $productId = (int) $this->getRequest()->getParam('product');
        $postcode = (string) $this->getRequest()->getParam('postcode');
        $qty = (int) $this->getRequest()->getParam('qty');
        $options = (array) $this->getRequest()->getParams();

        /** @var Json $page */
        $page = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        if ($qty <= 0 || $qty > $this->config->getMaxUnitQuantity()) {
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
     * Builds a JSON error response.
     *
     * @param Json   $page
     * @param string $message
     *
     * @return Json
     */
    private function jsonError(Json $page, string $message): Json
    {
        return $page->setData([
            'error'   => true,
            'message' => $message
        ]);
    }
}
