<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\ViewModel\Labels;

use Frenet\Shipping\Model\Labels\Client;
use Frenet\Shipping\Model\Labels\LabelException;
use Frenet\Shipping\Model\Labels\LabelsConfig;
use Frenet\Shipping\Model\Labels\ShipmentService;
use Frenet\Shipping\Model\Labels\StatusName;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * "Manage your labels" and "Printing": one page of GET /shipments (every label of the Frenet account, also the
 * ones bought in the Frenet panel), newest first, with the status filter in the URL.
 */
class LabelsList implements ArgumentInterface
{
    public const PER_PAGE = 50;

    /** @var array|null */
    private ?array $data = null;

    /**
     * @param RequestInterface $request
     * @param Client $client
     * @param LabelsConfig $config
     * @param ResourceConnection $resource
     * @param UrlInterface $url
     * @param array $statuses Statuses shown by this screen (empty: all); "Printing" passes the paid ones.
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly Client $client,
        private readonly LabelsConfig $config,
        private readonly ResourceConnection $resource,
        private readonly UrlInterface $url,
        private readonly array $statuses = []
    ) {
    }

    /**
     * Current page (0-based, like the Frenet API).
     *
     * @return int
     */
    public function page(): int
    {
        return max(0, (int) $this->request->getParam('p'));
    }

    /**
     * Status filter from the URL (0 = all).
     *
     * @return int
     */
    public function statusFilter(): int
    {
        return (int) $this->request->getParam('status');
    }

    /**
     * Rows of the page (after the filters), total of the account and error text.
     *
     * @return array{total: int, rows: array, error: string, counts: array}
     */
    public function data(): array
    {
        if ($this->data !== null) {
            return $this->data;
        }
        $this->data = ['total' => 0, 'rows' => [], 'error' => '', 'counts' => []];
        if (!$this->config->isAvailable()) {
            return $this->data;
        }
        try {
            $res = $this->client->whitelabel('GET', '/shipments?page=' . $this->page() . '&per_page=' . self::PER_PAGE);
        } catch (LabelException $e) {
            $this->data['error'] = $e->getMessage();
            return $this->data;
        }
        $this->data['total'] = (int) (Client::field($res, 'total') ?? 0);
        $list = (array) (Client::field($res, 'shipments') ?? []);
        $orders = $this->localOrders(array_map(static fn ($s) => (string) ($s['orderId'] ?? ''), $list));
        foreach ($list as $s) {
            $status = (int) ($s['shipmentStatus'] ?? 0);
            if ($this->allowed() && !in_array($status, $this->allowed(), true)) {
                continue;
            }
            $this->data['counts'][$status] = ($this->data['counts'][$status] ?? 0) + 1;
            if ($this->statusFilter() && $status !== $this->statusFilter()) {
                continue;
            }
            $quote = (array) ($s['quotation'] ?? []);
            $volume = (array) (((array) ($s['volumes'] ?? []))[0] ?? []);
            $orderId = (string) ($s['orderId'] ?? '');
            $paid = in_array($status, ShipmentService::PAID, true);
            $this->data['rows'][] = [
                'id' => (string) ($s['shipmentId'] ?? ''),
                'order' => $orderId,
                'order_url' => isset($orders[$orderId])
                    ? $this->url->getUrl('sales/order/view', ['order_id' => $orders[$orderId]]) : '',
                'status' => $status,
                'status_label' => StatusName::label($status),
                'tone' => StatusName::tone($status),
                'service' => trim(($quote['carrierCode'] ?? '') . ' ' . ($quote['shippingServiceCode'] ?? '')),
                'price' => (float) ($quote['shippingPrice'] ?? $quote['platformShippingPrice'] ?? 0),
                'tracking_url' => (string) ($s['trackingUrl'] ?? ''),
                'tracking' => (string) preg_replace('#^.*/#', '', rtrim((string) ($s['trackingUrl'] ?? ''), '/')),
                'label_url' => $paid ? (string) ($s['labelUrl'] ?? '') : '',
                'can_pay' => in_array($status, [ShipmentService::STATUS_CREATED, ShipmentService::STATUS_PENDING_PAYMENT], true),
                'can_print' => $paid,
                'can_cancel' => !in_array($status, [6, 7, 9], true),
                'date' => (string) ($volume['modifiedOn'] ?? ''),
            ];
        }
        return $this->data;
    }

    /**
     * Number of pages of the account.
     *
     * @return int
     */
    public function pages(): int
    {
        return max(1, (int) ceil($this->data()['total'] / self::PER_PAGE));
    }

    /**
     * Status filter options (only statuses this screen shows).
     *
     * @return array<int, string>
     */
    public function statusOptions(): array
    {
        $all = array_map(static fn ($v) => $v[0], StatusName::all());
        return $this->allowed() ? array_intersect_key($all, array_flip($this->allowed())) : $all;
    }

    /**
     * This page with other parameters.
     *
     * @param string $route
     * @param array $params
     * @return string
     */
    public function pageUrl(string $route, array $params): string
    {
        $params += ['p' => $this->page(), 'status' => $this->statusFilter()];
        return $this->url->getUrl($route, array_filter($params));
    }

    /**
     * Statuses this screen shows, as integers (empty: all).
     *
     * @return int[]
     */
    private function allowed(): array
    {
        return array_values(array_map('intval', $this->statuses));
    }

    /**
     * Local orders (increment id → entity id) among the Frenet order ids.
     *
     * @param string[] $incrementIds
     * @return array<string, int>
     */
    private function localOrders(array $incrementIds): array
    {
        $ids = array_values(array_filter(array_unique($incrementIds), 'strlen'));
        if (!$ids) {
            return [];
        }
        $db = $this->resource->getConnection('sales');
        return array_map('intval', $db->fetchPairs($db->select()
            ->from($this->resource->getTableName('sales_order'), ['increment_id', 'entity_id'])
            ->where('increment_id IN (?)', $ids)));
    }
}
