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
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;

/**
 * "Atualizar saldo": balance is added in the Frenet panel; back in the store, read it again.
 */
class Refresh extends Action implements HttpPostActionInterface
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
     * Reads the balance from Frenet and goes back.
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $this->wallet->clear();
        $this->wallet->refresh() !== null
            ? $this->messageManager->addSuccessMessage(__('Balance updated.'))
            : $this->messageManager->addErrorMessage(__('Frenet did not answer. Try again in a minute.'));
        return $this->resultRedirectFactory->create()->setRefererOrBaseUrl();
    }
}
