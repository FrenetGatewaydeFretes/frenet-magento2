<?php
/**
 * Frenet Shipping Gateway — Frenet tracking events on the admin order view.
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\ViewModel\Labels;

use Frenet\Shipping\Model\TrackingSync\StatusMapper;
use Frenet\Shipping\Model\TrackingSync\Sync;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class OrderTracking implements ArgumentInterface
{
    /**
     * @param RequestInterface $request
     * @param Sync $sync
     * @param ResourceConnection $resource
     * @param UrlInterface $url
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly Sync $sync,
        private readonly ResourceConnection $resource,
        private readonly UrlInterface $url
    ) {
    }

    /**
     * Order id from the URL.
     *
     * @return int
     */
    public function orderId(): int
    {
        return (int) $this->request->getParam('order_id');
    }

    /**
     * Frenet codes of the order with their stored events (codes never checked included).
     *
     * @return array
     */
    public function codes(): array
    {
        $db = $this->resource->getConnection('sales');
        $tracks = $db->fetchPairs($db->select()
            ->from($this->resource->getTableName('sales_shipment_track'), ['entity_id', 'track_number'])
            ->where('order_id = ?', $this->orderId())
            ->where('carrier_code = ?', 'frenetshipping'));
        $stored = [];
        foreach ($this->sync->forOrder($this->orderId()) as $t) {
            $stored[(int) $t['track_id']] = $t;
        }
        $out = [];
        foreach ($tracks as $id => $number) {
            $t = $stored[(int) $id] ?? null;
            $out[] = ['number' => (string) $number, 'synced_at' => $t['synced_at'] ?? null, 'list' => $t['list'] ?? []];
        }
        return $out;
    }

    /**
     * Label of a Frenet tracking status, '' for any other status.
     *
     * @param string $status
     * @return string
     */
    public function statusLabel(string $status): string
    {
        return StatusMapper::LABELS[$status] ?? '';
    }

    /**
     * "Update now" for this order.
     *
     * @return string
     */
    public function syncUrl(): string
    {
        return $this->url->getUrl('frenetshipping/tracking/sync', ['order_id' => $this->orderId()]);
    }
}
