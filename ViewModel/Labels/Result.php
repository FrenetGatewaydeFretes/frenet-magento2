<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\ViewModel\Labels;

use Frenet\Shipping\Controller\Adminhtml\Shipment\Create;
use Magento\Backend\Model\Session;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Result of "Create shipment" (kept in the admin session by the Create action).
 */
class Result implements ArgumentInterface
{
    /**
     * @param Session $session
     */
    public function __construct(private readonly Session $session)
    {
    }

    /**
     * Results, wallet before and after; null when there is nothing to show.
     *
     * @return array|null
     */
    public function data(): ?array
    {
        $data = $this->session->getData(Create::SESSION_KEY);
        return is_array($data) ? $data : null;
    }
}
