<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 *
 * @author Tiago Sampaio <tiago@tiagosampaio.com>
 * @link https://github.com/tiagosampaio
 * @link https://tiagosampaio.com
 *
 * Copyright (c) 2020.
 */

declare(strict_types = 1);

namespace Frenet\Shipping\Model;

/**
 * Converts a weight between pounds and kilograms according to the store's configured catalog weight unit.
 */
interface WeightConverterInterface
{
    /**
     * @var float
     */
    public const LBS_TO_KG_FACTOR = 0.453592;

    /**
     * @var float
     */
    public const KG_TO_LBS_FACTOR = 2.20462;

    /**
     * Converts a catalog weight to kilograms (a no-op when the store already uses kilograms).
     *
     * @param float $weight
     *
     * @return float
     */
    public function convertToKg(float $weight): float;

    /**
     * Converts a catalog weight to pounds (a no-op when the store already uses pounds).
     *
     * @param float $weight
     *
     * @return float
     */
    public function convertToLbs(float $weight): float;
}
