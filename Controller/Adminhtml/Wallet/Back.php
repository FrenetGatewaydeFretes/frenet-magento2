<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Controller\Adminhtml\Wallet;

use Frenet\Shipping\Model\Labels\Wallet;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;

/**
 * Where Mercado Pago sends the admin back after a wallet deposit.
 */
class Back extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Frenet_Shipping::labels';

    /**
     * @param Context $context
     * @param Wallet $wallet
     */
    public function __construct(Context $context, private readonly Wallet $wallet)
    {
        parent::__construct($context);
    }

    /**
     * Tells how the payment went and opens the labels list.
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $this->wallet->clear();
        match ((string) $this->getRequest()->getParam('status')) {
            'success' => $this->messageManager->addSuccessMessage(
                __('Payment sent. The balance shows up as soon as Mercado Pago confirms it (Pix in a few minutes).')
            ),
            'pending' => $this->messageManager->addNoticeMessage(
                __('Payment pending at Mercado Pago. The balance shows up when it is confirmed.')
            ),
            default => $this->messageManager->addErrorMessage(
                __('The payment was not completed. Nothing was added to the wallet.')
            ),
        };
        return $this->resultRedirectFactory->create()->setPath('frenetshipping/labels/index');
    }
}
