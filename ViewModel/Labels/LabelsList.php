<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\ViewModel\Labels;

use Frenet\Shipping\Model\Labels\LabelRows;
use Frenet\Shipping\Model\Labels\StatusName;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * "Manage your labels" and "Printing": the page and status filter from the URL, the rows from LabelRows and the
 * admin URLs the templates need.
 */
class LabelsList implements ArgumentInterface
{
    public const PER_PAGE = 50;

    /** @var mixed[]|null */
    private ?array $data = null;

    /**
     * @param RequestInterface $request
     * @param LabelRows $rows
     * @param UrlInterface $url
     * @param StatusName $statusNames
     * @param mixed[] $statuses Statuses shown by this screen (empty: all); "Printing" passes the paid ones.
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly LabelRows $rows,
        private readonly UrlInterface $url,
        private readonly StatusName $statusNames,
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
     * @return array{total: int, rows: mixed[], error: string, counts: int[]}
     */
    public function data(): array
    {
        if ($this->data === null) {
            $this->data = $this->rows->page($this->page(), self::PER_PAGE, $this->allowed(), $this->statusFilter());
            foreach ($this->data['rows'] as &$row) {
                $row['order_url'] = $row['order_id']
                    ? $this->url->getUrl('sales/order/view', ['order_id' => $row['order_id']])
                    : '';
            }
            unset($row);
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
        $all = array_map(static fn ($option) => $option[0], $this->statusNames->all());
        return $this->allowed() ? array_intersect_key($all, array_flip($this->allowed())) : $all;
    }

    /**
     * This page with other parameters.
     *
     * @param string $route
     * @param mixed[] $params
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
}
