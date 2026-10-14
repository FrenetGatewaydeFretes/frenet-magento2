<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Controller\Adminhtml\Labels;

use Frenet\Shipping\Model\Labels\ShipmentService;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;

/**
 * Asks Frenet again for a paid label that was not released yet.
 */
class Refresh extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Frenet_Shipping::labels';

    /**
     * @param Context $context
     * @param ShipmentService $shipments
     */
    public function __construct(Context $context, private readonly ShipmentService $shipments)
    {
        parent::__construct($context);
    }

    /**
     * Checks now and goes back.
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $this->shipments->fetchLabel((string) $this->getRequest()->getParam('id'))
            ? $this->messageManager->addSuccessMessage(__('Frenet label ready to print.'))
            : $this->messageManager->addNoticeMessage(__('Frenet has not released the label yet. Try again in a minute.'));
        return $this->resultRedirectFactory->create()->setRefererOrBaseUrl();
    }
}
