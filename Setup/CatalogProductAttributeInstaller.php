<?php

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
