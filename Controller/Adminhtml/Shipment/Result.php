<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Controller\Adminhtml\Shipment;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Page as BackendPage;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

/**
 * Shipments created page of the Frenet menu.
 */
class Result extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Frenet_Shipping::labels_create';

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
        /** @var BackendPage $page */
        $page = $this->pageFactory->create();
        $page->setActiveMenu('Frenet_Shipping::menu_create');
        $page->getConfig()->getTitle()->prepend((string) __('Shipments created'));
        return $page;
    }
}
