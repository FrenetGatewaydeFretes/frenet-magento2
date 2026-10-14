<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Model\Labels;

/**
 * Pure carrier comparison used by "Create shipment" (no Magento dependencies, unit tested).
 * A service is ['code', 'carrier', 'carrier_code', 'name', 'price', 'days'].
 */
class Compare
{
    /**
     * Index of the cheapest service (ties: the faster one), or null.
     *
     * @param array $services
     * @return int|null
     */
    public static function bestPrice(array $services): ?int
    {
        return self::best($services, static fn ($a, $b) => [$a['price'], $a['days']] <=> [$b['price'], $b['days']]);
    }

    /**
     * Index of the fastest service (ties: the cheaper one), or null.
     *
     * @param array $services
     * @return int|null
     */
    public static function bestTime(array $services): ?int
    {
        return self::best($services, static fn ($a, $b) => [$a['days'], $a['price']] <=> [$b['days'], $b['price']]);
    }

    /**
     * Service that comes selected: the customer's choice, or the best one when $autoBest (or when the customer's
     * service is no longer offered).
     *
     * @param array $services
     * @param string $customerCode
     * @param string $mode "price" or "time"
     * @param bool $autoBest
     * @return int|null
     */
    public static function preselect(array $services, string $customerCode, string $mode, bool $autoBest): ?int
    {
        if (!$autoBest && $customerCode !== '') {
            foreach ($services as $i => $service) {
                if ((string) $service['code'] === $customerCode) {
                    return $i;
                }
            }
        }
        return $mode === 'time' ? self::bestTime($services) : self::bestPrice($services);
    }

    /**
     * Header of the comparison: per carrier, in how many orders it is available and the totals of its cheapest and
     * of its fastest service; plus the total of the cheapest option of every order ("best option").
     *
     * @param array $ordersServices One services list per order.
     * @return array{carriers: array, best_total: float, orders: int}
     */
    public static function carriers(array $ordersServices): array
    {
        $carriers = [];
        $bestTotal = 0.0;
        foreach ($ordersServices as $services) {
            $best = self::bestPrice($services);
            if ($best !== null) {
                $bestTotal += (float) $services[$best]['price'];
            }
            $byCarrier = [];
            foreach ($services as $service) {
                $byCarrier[$service['carrier'] ?: $service['carrier_code']][] = $service;
            }
            foreach ($byCarrier as $name => $list) {
                $row = $carriers[$name] ?? ['carrier' => $name, 'available' => 0, 'best_price' => 0.0, 'best_time' => 0.0];
                $row['available']++;
                $row['best_price'] += (float) $list[self::bestPrice($list)]['price'];
                $row['best_time'] += (float) $list[self::bestTime($list)]['price'];
                $carriers[$name] = $row;
            }
        }
        foreach ($carriers as &$row) {
            $row['best_price'] = round($row['best_price'], 2);
            $row['best_time'] = round($row['best_time'], 2);
            $row['all'] = $row['available'] === count($ordersServices);
        }
        unset($row);
        uasort($carriers, static fn ($a, $b) => [$b['available'], $a['best_price']] <=> [$a['available'], $b['best_price']]);
        return ['carriers' => array_values($carriers), 'best_total' => round($bestTotal, 2), 'orders' => count($ordersServices)];
    }

    /**
     * Totals of the chosen services: count and total per carrier, and the overall total.
     *
     * @param array $chosen Services chosen (one per order).
     * @return array{count: int, total: float, carriers: array}
     */
    public static function summarize(array $chosen): array
    {
        $carriers = [];
        $total = 0.0;
        foreach ($chosen as $service) {
            $name = $service['carrier'] ?: $service['carrier_code'];
            $carriers[$name]['count'] = ($carriers[$name]['count'] ?? 0) + 1;
            $carriers[$name]['total'] = round(($carriers[$name]['total'] ?? 0) + (float) $service['price'], 2);
            $total += (float) $service['price'];
        }
        ksort($carriers);
        return ['count' => count($chosen), 'total' => round($total, 2), 'carriers' => $carriers];
    }

    /**
     * Index of the minimum by $cmp.
     *
     * @param array $services
     * @param callable $cmp
     * @return int|null
     */
    private static function best(array $services, callable $cmp): ?int
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
