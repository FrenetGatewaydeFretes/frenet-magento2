<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Controller\Adminhtml\Tracking;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

/**
 * Shipment tracking page of the Frenet menu.
 */
class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Frenet_Shipping::tracking';

    /**
     * @param Context $context
     * @param PageFactory $pageFactory
     */
    public function __construct(Context $context, private readonly PageFactory $pageFactory)
    {
        parent::__construct($context);
    }

    /**
     * Renders the page.
     *
     * @return Page
     */
    public function execute(): Page
    {
        $page = $this->pageFactory->create();
        $page->setActiveMenu('Frenet_Shipping::menu_tracking');
        $page->getConfig()->getTitle()->prepend(__('Shipment tracking'));
        return $page;
    }
}
