<?php
/**
 * Frenet Shipping Gateway — order statuses of the automatic tracking (Em transporte, Aguardando retirada,
 * Entregue, Em devolução), assigned to the "processing" and "complete" states. Existing statuses untouched.
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Setup\Patch\Data;

use Frenet\Shipping\Model\TrackingSync\StatusMapper;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Sales\Model\Order;

class AddTrackingOrderStatuses implements DataPatchInterface
{
    /**
     * @param ResourceConnection $resource
     */
    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    /**
     * @inheritdoc
     */
    public function apply(): self
    {
        $db = $this->resource->getConnection('sales');
        $statusTable = $this->resource->getTableName('sales_order_status');
        $stateTable = $this->resource->getTableName('sales_order_status_state');
        foreach (StatusMapper::LABELS as $status => $label) {
            $db->insertOnDuplicate($statusTable, ['status' => $status, 'label' => $label], ['label']);
            foreach ([Order::STATE_PROCESSING, Order::STATE_COMPLETE] as $state) {
                $db->insertOnDuplicate(
                    $stateTable,
                    ['status' => $status, 'state' => $state, 'is_default' => 0, 'visible_on_front' => 1],
                    ['visible_on_front']
                );
            }
        }
        return $this;
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
