<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Controller\Wallet;

use Frenet\Shipping\Model\Labels\Wallet;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;

/**
 * NotificationUrl of "Adicionar saldo" (/frenet/wallet/notify): Frenet credits the wallet itself, so this only drops
 * the cached balance. It reads nothing from the request and changes nothing else.
 */
class Notify implements HttpGetActionInterface, HttpPostActionInterface, CsrfAwareActionInterface
{
    /**
     * @param Wallet $wallet
     * @param JsonFactory $jsonFactory
     */
    public function __construct(private readonly Wallet $wallet, private readonly JsonFactory $jsonFactory)
    {
    }

    /**
     * Forgets the cached balance.
     *
     * @return Json
     */
    public function execute(): Json
    {
        $this->wallet->clear();
        return $this->jsonFactory->create()->setData(['ok' => true]);
    }

    /**
     * @inheritDoc
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
