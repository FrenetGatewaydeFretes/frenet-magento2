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

namespace Frenet\Shipping\Model\Catalog\Product;

/**
 * Contract for reading the package weight and dimensions sent to the Frenet quote, defaults included.
 */
interface DimensionsExtractorInterface
{
    /**
     * Returns the weight, falling back to the configured default when the value is missing or not positive.
     *
     * @return float
     */
    public function getWeight();

    /**
     * Returns the height, falling back to the configured default when the value is missing or not positive.
     *
     * @return float
     */
    public function getHeight();

    /**
     * Returns the width, falling back to the configured default when the value is missing or not positive.
     *
     * @return float
     */
    public function getWidth();

    /**
     * Returns the length, falling back to the configured default when the value is missing or not positive.
     *
     * @return float
     */
    public function getLength();
}
