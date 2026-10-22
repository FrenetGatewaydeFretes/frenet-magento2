<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Controller\Adminhtml\Shipment;

use Frenet\Shipping\Model\Labels\Client;
use Frenet\Shipping\Model\Labels\InvoiceStore;
use Frenet\Shipping\Model\Labels\IssueStore;
use Frenet\Shipping\Model\Labels\LabelsConfig;
use Frenet\Shipping\Model\Labels\PayloadBuilder;
use Frenet\Shipping\Model\Labels\ShipmentExistsException;
use Frenet\Shipping\Model\Labels\ShipmentService;
use Frenet\Shipping\Model\Labels\Wallet;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Session;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Throwable;

/**
 * Creates the Frenet shipments of the confirmed orders. The service is re-quoted now (prices change), the NF-e keys
 * typed in the comparison are saved first, and one failure never stops the others.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Create extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Frenet_Shipping::labels_create';
    public const SESSION_KEY = 'frenet_shipping_labels_result';

    /**
     * @param Context $context
     * @param ShipmentService $shipments
     * @param Client $client
     * @param PayloadBuilder $payload
     * @param InvoiceStore $invoices
     * @param LabelsConfig $config
     * @param Wallet $wallet
     * @param OrderRepositoryInterface $orderRepository
     * @param Session $session
     * @param IssueStore $issues
     */
    public function __construct(
        Context $context,
        private readonly ShipmentService $shipments,
        private readonly Client $client,
        private readonly PayloadBuilder $payload,
        private readonly InvoiceStore $invoices,
        private readonly LabelsConfig $config,
        private readonly Wallet $wallet,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly Session $session,
        private readonly IssueStore $issues
    ) {
        parent::__construct($context);
    }

    /**
     * Creates one shipment per selected order and shows the result.
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $request = $this->getRequest();
        $orderIds = array_slice(array_values(array_unique(array_filter(array_map(
            'intval',
            (array) $request->getParam('order_ids', [])
        )))), 0, 50);
        $services = (array) $request->getParam('service', []);
        $keys = (array) $request->getParam('nfe', []);
        // Labels are always bought from the store (the "send to the Frenet panel" choice was dropped on 2026-10-20).
        $journey = LabelsConfig::JOURNEY_HERE;
        if (!$orderIds) {
            $this->messageManager->addErrorMessage(__('Select at least one order.'));
            return $this->resultRedirectFactory->create()->setPath('*/*/index');
        }
        $before = $this->wallet->refresh();
        $results = [];
        foreach ($orderIds as $orderId) {
            $results[] = $this->createOne(
                $orderId,
                (string) ($services[$orderId] ?? ''),
                (string) ($keys[$orderId] ?? ''),
                $journey
            );
        }
        $this->wallet->clear();
        $this->session->setData(self::SESSION_KEY, [
            'journey' => $journey,
            'results' => $results,
            'before' => $before,
            'after' => $this->wallet->refresh(),
        ]);
        return $this->resultRedirectFactory->create()->setPath('*/*/result');
    }

    /**
     * One order: save the NF-e key, re-quote the chosen service and create the shipment.
     *
     * @param int $orderId
     * @param string $serviceCode
     * @param string $nfe
     * @param string $journey
     * @return mixed[]
     */
    private function createOne(int $orderId, string $serviceCode, string $nfe, string $journey): array
    {
        $result = ['order_id' => $orderId, 'number' => '', 'ok' => false, 'message' => '', 'shipment_id' => '',
            'service' => '', 'carrier_code' => '', 'price' => 0.0, 'status' => 0];
        try {
            /** @var Order $order */
            $order = $this->orderRepository->get($orderId);
            $result['number'] = (string) $order->getIncrementId();
            $this->invoices->saveChecked($orderId, $nfe);
            $service = $this->service($order, $serviceCode);
            $row = $this->send($orderId, $service, $journey);
            $result['shipment_id'] = (string) $row['shipment_id'];
            $result['service'] = trim($service['carrier'] . ' ' . $service['name']);
            $result['carrier_code'] = (string) ($service['carrier_code'] ?? '');
            $result['price'] = (float) $row['price'];
            $result['status'] = (int) $row['status'];
            $result['ok'] = true;
            if ($journey === LabelsConfig::JOURNEY_HERE
                && !in_array((int) $row['status'], ShipmentService::PAID, true)
            ) {
                $result['message'] = $this->config->walletPayment()
                    ? (string) __(
                        'Created but not paid: not enough balance or the payment did not answer. Pay it in Labels.'
                    )
                    : (string) __('Created. Pay it in Labels to release the label.');
            }
        } catch (ShipmentExistsException $e) {
            $result['message'] = $e->getMessage();
        } catch (LocalizedException $e) {
            $result['message'] = $e->getMessage();
            // "Notes" of the order (Create shipment, Labels): why its label failed, until one goes through.
            $this->issues->set($orderId, $result['message']);
        } catch (Throwable $e) {
            $result['message'] = (string) __('Unexpected error: %1', $e->getMessage());
            $this->issues->set($orderId, $result['message']);
        }
        return $result;
    }

    /**
     * Creates the shipment; with "Buy here" and wallet payment on, pays it at once.
     *
     * @param int $orderId
     * @param mixed[] $service
     * @param string $journey
     * @return mixed[] Stored row.
     * @throws LocalizedException
     */
    private function send(int $orderId, array $service, string $journey): array
    {
        if ($journey === LabelsConfig::JOURNEY_HERE && $this->config->walletPayment()) {
            return $this->shipments->createAndPay($orderId, $service);
        }
        return $this->shipments->create($orderId, $service, $journey);
    }

    /**
     * The chosen service, quoted again now (Frenet prices change).
     *
     * @param Order $order
     * @param string $serviceCode
     * @return mixed[]
     * @throws LocalizedException
     */
    private function service(Order $order, string $serviceCode): array
    {
        $address = $order->getShippingAddress();
        $all = $this->client->quoteAll(
            $this->config->storeValue('shipping/origin/postcode', (int) $order->getStoreId()),
            $address ? (string) $address->getPostcode() : '',
            (float) $order->getSubtotal(),
            $this->payload->quoteItems($order)
        );
        foreach ($all as $service) {
            if ($service['code'] === $serviceCode) {
                return $service;
            }
        }
        throw new LocalizedException(
            __(
                'The chosen carrier no longer delivers this order. Calculate again and choose another one. '
                . 'Nothing was created.'
            )
        );
    }
}
