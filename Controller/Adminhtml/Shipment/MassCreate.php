<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Controller\Adminhtml\Shipment;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Sales\Controller\Adminhtml\Order\AbstractMassAction;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Ui\Component\MassAction\Filter;

/**
 * Order grid action "Frenet: create shipment": opens the comparison with the selected orders (nothing is bought yet).
 */
class MassCreate extends AbstractMassAction implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Frenet_Shipping::labels_create';

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(Context $context, Filter $filter, CollectionFactory $collectionFactory)
    {
        parent::__construct($context, $filter);
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * Redirects to "Create shipment" with the selected orders.
     *
     * @param AbstractCollection $collection
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    protected function massAction(AbstractCollection $collection)
    {
        $ids = array_slice(array_map('intval', $collection->getAllIds()), 0, \Frenet\Shipping\Model\Labels\Review::MAX_ORDERS);
        return $this->resultRedirectFactory->create()->setPath('frenetshipping/shipment/index', ['ids' => implode(',', $ids)]);
    }
}
