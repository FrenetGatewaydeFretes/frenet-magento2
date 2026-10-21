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
use Frenet\Shipping\Model\Catalog\Product\View\QuoteRateLimiterInterface;
use Frenet\Shipping\Model\Catalog\Product\View\QuoteVisitorResolverInterface;
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
     * @var int
     */
    private const HTTP_TOO_MANY_REQUESTS = 429;

    /**
     * @param Context                       $context
     * @param QuoteProductInterface         $quoteProduct
     * @param ConfigInterface               $config
     * @param LoggerInterface               $logger
     * @param QuoteRateLimiterInterface     $rateLimiter
     * @param QuoteVisitorResolverInterface $visitorResolver
     */
    public function __construct(
        Context $context,
        private readonly QuoteProductInterface $quoteProduct,
        private readonly ConfigInterface $config,
        private readonly LoggerInterface $logger,
        private readonly QuoteRateLimiterInterface $rateLimiter,
        private readonly QuoteVisitorResolverInterface $visitorResolver
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
        $options = (array) $this->getRequest()->getParams();
        $qty = $this->resolveQty($options);

        /** @var Json $page */
        $page = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        if (!$this->isQuoteAvailable()) {
            return $this->jsonError($page, (string) __('The shipping quote is not available.'));
        }

        if ($productId <= 0 || !$this->isValidPostcode($postcode)) {
            return $this->jsonError($page, (string) __('Please inform a valid postcode'));
        }

        if ($qty === null) {
            return $this->jsonError($page, (string) __('Invalid quantity informed.'));
        }

        if (!$this->rateLimiter->isAllowed($this->visitorResolver->resolve($this->getRequest()))) {
            $page->setHttpResponseCode(self::HTTP_TOO_MANY_REQUESTS);

            return $this->jsonError(
                $page,
                (string) __('Too many shipping quotes in a short time. Please wait a minute and try again.')
            );
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
     * Resolves the quantity to quote, taking a grouped product's quantities from its super_group lines.
     *
     * @param mixed[] $options
     *
     * @return int|null
     */
    private function resolveQty(array &$options): ?int
    {
        if (!isset($options['super_group']) || !is_array($options['super_group'])) {
            $qty = (int) $this->getRequest()->getParam('qty');

            return $this->isValidQty($qty) ? $qty : null;
        }

        $requested = 0;

        foreach ($options['super_group'] as $lineQty) {
            $lineQty = is_scalar($lineQty) ? (int) $lineQty : -1;

            if ($lineQty !== 0 && !$this->isValidQty($lineQty)) {
                return null;
            }

            $requested += $lineQty;
        }

        if ($requested === 0) {
            unset($options['super_group']);
        }

        return 1;
    }

    /**
     * Checks whether the quantity is positive and within the configured maximum per shipping line.
     *
     * @param int $qty
     *
     * @return bool
     */
    private function isValidQty(int $qty): bool
    {
        return $qty > 0 && $qty <= $this->config->getMaxUnitQuantity();
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
