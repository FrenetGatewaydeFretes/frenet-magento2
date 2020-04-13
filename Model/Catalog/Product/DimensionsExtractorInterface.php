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
 * Interface DimensionsExtractorInterface
 */
interface DimensionsExtractorInterface
{
    /**
     * @return float
     */
    public function getWeight();

    /**
     * @return float
     */
    public function getHeight();

    /**
     * @return float
     */
    public function getWidth();

    /**
     * @return float
     */
    public function getLength();
}
