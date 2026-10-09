<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Controller\Adminhtml\Wallet;

use Frenet\Shipping\Model\Labels\LabelException;
use Frenet\Shipping\Model\Labels\Wallet;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Url as FrontendUrl;

/**
 * "Adicionar saldo": opens the Mercado Pago checkout of a Frenet wallet deposit.
 */
class Deposit extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Frenet_Shipping::labels';

    /**
     * @param Context $context
     * @param Wallet $wallet
     * @param FrontendUrl $frontendUrl Store front URLs (the admin UrlInterface would add the admin key).
     */
    public function __construct(
        Context $context,
        private readonly Wallet $wallet,
        private readonly FrontendUrl $frontendUrl
    ) {
        parent::__construct($context);
    }

    /**
     * Starts the deposit and sends the admin to Mercado Pago.
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $redirect = $this->resultRedirectFactory->create();
        $raw = (string) $this->getRequest()->getParam('value');
        $value = (float) str_replace(',', '.', (string) preg_replace('/[^\d,.]/', '', $raw));
        $back = [];
        foreach (['success', 'failure', 'pending'] as $status) {
            $back[$status] = $this->getUrl('frenetshipping/wallet/back', ['status' => $status]);
        }
        try {
            $url = $this->wallet->deposit(
                $value,
                $this->frontendUrl->getUrl('frenet/wallet/notify', ['_nosid' => true]),
                $back
            );
        } catch (LabelException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            return $redirect->setRefererOrBaseUrl();
        }
        return $redirect->setUrl($url);
    }
}
