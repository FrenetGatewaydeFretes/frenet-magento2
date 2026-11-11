<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Model\Labels;

/**
 * Carrier comparison used by "Create shipment" (injected service, no Magento dependencies, unit tested).
 * A service is ['code', 'carrier', 'carrier_code', 'name', 'price', 'days'].
 */
class Compare
{
    /**
     * Index of the cheapest service (ties: the faster one), or null.
     *
     * @param mixed[] $services
     * @return int|null
     */
    public function bestPrice(array $services): ?int
    {
        return $this->best(
            $services,
            static fn ($left, $right) => [$left['price'], $left['days']] <=> [$right['price'], $right['days']]
        );
    }

    /**
     * Index of the fastest service (ties: the cheaper one), or null.
     *
     * @param mixed[] $services
     * @return int|null
     */
    public function bestTime(array $services): ?int
    {
        return $this->best(
            $services,
            static fn ($left, $right) => [$left['days'], $left['price']] <=> [$right['days'], $right['price']]
        );
    }

    /**
     * Service that comes selected: the customer's choice, or the best one when $autoBest.
     *
     * The best one is also selected when the customer's service is no longer offered.
     *
     * @param mixed[] $services
     * @param string $customerCode
     * @param string $mode "price" or "time"
     * @param bool $autoBest
     * @return int|null
     */
    public function preselect(array $services, string $customerCode, string $mode, bool $autoBest): ?int
    {
        if (!$autoBest && $customerCode !== '') {
            foreach ($services as $i => $service) {
                if ((string) $service['code'] === $customerCode) {
                    return $i;
                }
            }
        }
        return $mode === 'time' ? $this->bestTime($services) : $this->bestPrice($services);
    }

    /**
     * Header of the comparison: per carrier, in how many orders it is available, the totals of its cheapest and of
     * its fastest service and the slowest delivery among those orders; plus the two "any carrier" options (the
     * cheapest and the fastest service of every order) with their totals and slowest delivery.
     * $ordersUsable (same keys) are the services each order can really ship with (see usableWithoutNfe()): the "any
     * carrier" options use them, so an order without an NF-e key never counts a carrier that refuses it.
     *
     * @param mixed[] $ordersServices One services list per order.
     * @param mixed[] $ordersUsable Usable services per order; $ordersServices when empty.
     * @return array{carriers: mixed[], best_total: float, best_price: mixed[], best_time: mixed[], orders: int}
     */
    public function carriers(array $ordersServices, array $ordersUsable = []): array
    {
        $carriers = [];
        $best = ['price' => ['total' => 0.0, 'days' => 0], 'time' => ['total' => 0.0, 'days' => 0]];
        foreach ($ordersServices as $k => $services) {
            $pool = $ordersUsable[$k] ?? $services;
            foreach (['price' => $this->bestPrice($pool), 'time' => $this->bestTime($pool)] as $mode => $i) {
                if ($i !== null) {
                    $best[$mode]['total'] += (float) $pool[$i]['price'];
                    $best[$mode]['days'] = max($best[$mode]['days'], (int) $pool[$i]['days']);
                }
            }
            $byCarrier = [];
            foreach ($services as $service) {
                $byCarrier[$this->carrierName($service)][] = $service;
            }
            foreach ($byCarrier as $name => $list) {
                $row = $carriers[$name] ?? [
                    'carrier' => $name,
                    'code' => (string) ($list[0]['carrier_code'] ?? ''),
                    'available' => 0,
                    'best_price' => 0.0,
                    'best_time' => 0.0,
                    'best_price_days' => 0,
                    'best_time_days' => 0,
                ];
                $cheap = $list[$this->bestPrice($list)];
                $fast = $list[$this->bestTime($list)];
                $row['available']++;
                $row['best_price'] += (float) $cheap['price'];
                $row['best_time'] += (float) $fast['price'];
                $row['best_price_days'] = max($row['best_price_days'], (int) $cheap['days']);
                $row['best_time_days'] = max($row['best_time_days'], (int) $fast['days']);
                $carriers[$name] = $row;
            }
        }
        foreach ($carriers as &$row) {
            $row['best_price'] = round($row['best_price'], 2);
            $row['best_time'] = round($row['best_time'], 2);
            $row['all'] = $row['available'] === count($ordersServices);
        }
        unset($row);
        uasort(
            $carriers,
            static fn ($left, $right) => [$right['available'], $left['best_price']]
                <=> [$left['available'], $right['best_price']]
        );
        $best['price']['total'] = round($best['price']['total'], 2);
        $best['time']['total'] = round($best['time']['total'], 2);
        return [
            'carriers' => array_values($carriers),
            'best_total' => $best['price']['total'],
            'best_price' => $best['price'],
            'best_time' => $best['time'],
            'orders' => count($ordersServices),
        ];
    }

    /**
     * Flags the services of the carriers that need an NF-e key ("needs_nfe").
     *
     * @param mixed[] $services
     * @param string[] $nfeCarriers Carrier codes, upper case.
     * @return mixed[]
     */
    public function markNfe(array $services, array $nfeCarriers): array
    {
        foreach ($services as &$service) {
            $service['needs_nfe'] = in_array(strtoupper((string) $service['carrier_code']), $nfeCarriers, true);
        }
        unset($service);
        return $services;
    }

    /**
     * Services an order without an NF-e key can use; all of them when every carrier needs one (never an empty choice).
     *
     * @param mixed[] $services markNfe() result.
     * @return mixed[]
     */
    public function usableWithoutNfe(array $services): array
    {
        $usable = array_values(array_filter($services, static fn ($service) => empty($service['needs_nfe'])));
        return $usable ?: $services;
    }

    /**
     * Index in $services of the service with this code, or null.
     *
     * @param mixed[] $services
     * @param string|null $code
     * @return int|null
     */
    public function indexOf(array $services, ?string $code): ?int
    {
        foreach ($services as $i => $service) {
            if ($code !== null && (string) $service['code'] === $code) {
                return $i;
            }
        }
        return null;
    }

    /**
     * Totals of the chosen services: count and total per carrier, and the overall total.
     *
     * @param mixed[] $chosen Services chosen (one per order).
     * @return array{count: int, total: float, carriers: mixed[]}
     */
    public function summarize(array $chosen): array
    {
        $carriers = [];
        $total = 0.0;
        foreach ($chosen as $service) {
            $name = $this->carrierName($service);
            $carriers[$name]['count'] = ($carriers[$name]['count'] ?? 0) + 1;
            $carriers[$name]['total'] = round(($carriers[$name]['total'] ?? 0) + (float) $service['price'], 2);
            $total += (float) $service['price'];
        }
        ksort($carriers);
        return ['count' => count($chosen), 'total' => round($total, 2), 'carriers' => $carriers];
    }

    /**
     * Name of the carrier of a service (its code when Frenet sends no name).
     *
     * @param mixed[] $service
     * @return string
     */
    public function carrierName(array $service): string
    {
        return (string) ($service['carrier'] ?: $service['carrier_code']);
    }

    /**
     * Index of the minimum by $cmp.
     *
     * @param mixed[] $services
     * @param callable $cmp
     * @return int|null
     */
    private function best(array $services, callable $cmp): ?int
    {
        $best = null;
        foreach ($services as $i => $service) {
            if ($best === null || $cmp($service, $services[$best]) < 0) {
                $best = $i;
            }
        }
        return $best;
    }
}
