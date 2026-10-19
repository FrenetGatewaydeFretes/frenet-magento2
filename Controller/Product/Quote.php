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
use Frenet\Shipping\Model\ConfigInterface;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Storefront AJAX endpoint that validates the product page quote request and answers with the rates as JSON.
 */
class Quote extends Action implements HttpPostActionInterface
{
    /**
     * @var int
     */
    private const POSTCODE_LENGTH = 8;

    /**
     * @param Context               $context
     * @param QuoteProductInterface $quoteProduct
     * @param ConfigInterface       $config
     * @param LoggerInterface       $logger
     */
    public function __construct(
        Context $context,
        private readonly QuoteProductInterface $quoteProduct,
        private readonly ConfigInterface $config,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
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

        if (!$this->isQuoteAvailable()) {
            return $this->jsonError($page, (string) __('The shipping quote is not available.'));
        }

        if ($productId <= 0 || !$this->isValidPostcode($postcode)) {
            return $this->jsonError($page, (string) __('Please inform a valid postcode'));
        }

        if ($qty <= 0 || $qty > $this->config->getMaxUnitQuantity()) {
            return $this->jsonError($page, (string) __('Invalid quantity informed.'));
        }

        try {
            $rates = $this->quoteProduct->quoteByProductId($productId, $postcode, $qty, $options);

            $page->setData([
                'error' => false,
                'rates' => $rates
            ]);
        } catch (LocalizedException $exception) {
            $this->jsonError($page, $exception->getMessage());
        } catch (Throwable $exception) {
            $this->logger->critical('Frenet product quote failed: ' . $exception->getMessage(), [
                'exception' => $exception,
            ]);
            $this->jsonError($page, (string) __('Unable to quote the shipping right now. Please try again later.'));
        }

        return $page;
    }

    /**
     * Checks whether the carrier and the product page quote are enabled with an API token.
     *
     * @return bool
     */
    private function isQuoteAvailable(): bool
    {
        return $this->config->isActive()
            && $this->config->getToken() !== ''
            && $this->config->isProductQuoteEnabled();
    }

    /**
     * Checks whether the postcode holds exactly the eight digits of a Brazilian CEP.
     *
     * @param string $postcode
     *
     * @return bool
     */
    private function isValidPostcode(string $postcode): bool
    {
        return strlen((string) preg_replace('/\D/', '', $postcode)) === self::POSTCODE_LENGTH;
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
