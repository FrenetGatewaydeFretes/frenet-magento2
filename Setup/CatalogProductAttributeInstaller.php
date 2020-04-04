<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 * @package Frenet\Shipping
 *
 * @author Tiago Sampaio <tiago@tiagosampaio.com>
 * @link https://github.com/tiagosampaio
 * @link https://tiagosampaio.com
 *
 * Copyright (c) 2020.
 */

declare(strict_types = 1);

namespace Frenet\Shipping\Setup;

/**
 * Class CatalogProductAttributeInstaller
 *
 * @package Frenet\Shipping\Setup
 */
class CatalogProductAttributeInstaller extends EavAttributeInstaller
{
    /**
     * @return string
     */
    protected function getEntityType()
    {
        return \Magento\Catalog\Model\Product::ENTITY;
    }
}
