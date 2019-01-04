<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 * @package Frenet\Shipping
 * @author Tiago Sampaio <tiago@tiagosampaio.com>
 * @link https://github.com/tiagosampaio
 * @link https://tiagosampaio.com
 *
 * Copyright (c) 2019.
 */

declare(strict_types = 1);

namespace Frenet\Shipping\Api\Data;

/**
 * Class CatalogProductDataExtractorInterface
 *
 * @package Frenet\Shipping\Api
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
