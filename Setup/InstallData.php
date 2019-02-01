<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 * @package  Frenet\Shipping
 * @author   Tiago Sampaio <tiago@tiagosampaio.com>
 * @link     https://github.com/tiagosampaio
 * @link     https://tiagosampaio.com
 *
 * Copyright (c) 2019.
 */

declare(strict_types = 1);

namespace Frenet\Shipping\Setup;

use Frenet\Shipping\Api\Data\AttributesMappingInterface;
use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;

/**
 * Class InstallData
 *
 * @package Frenet\Shipping\Setup
 */
class InstallData implements InstallDataInterface
{
    /**
     * @var \Frenet\Shipping\Model\Cache\Type\Frenet
     */
    protected $cacheType;

    /**
     * @var CatalogProductAttributeInstaller
     */
    private $attributeInstaller;

    /**
     * Constructor
     *
     * @param \Frenet\Shipping\Model\Cache\Type\Frenet $cacheType
     * @param CatalogProductAttributeInstaller         $attributeInstaller
     */
    public function __construct(
        \Frenet\Shipping\Model\Cache\Type\Frenet $cacheType,
        CatalogProductAttributeInstaller $attributeInstaller
    ) {
        $this->attributeInstaller = $attributeInstaller;
        $this->cacheType = $cacheType;
    }

    /**
     * {@inheritdoc}
     */
    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        /**
         * Run for new installation only.
         */
        if (version_compare($context->getVersion(), '2.0.0', '<=')) {
            $this->configureNewInstallation();
        }

        $setup->endSetup();
    }

    /**
     * Creates the new attributes during the module installation.
     */
    private function configureNewInstallation()
    {
        /**
         * @var string $code
         * @var array  $data
         */
        foreach ($this->getAttributes() as $code => $data) {
            $this->attributeInstaller->install($code, (array) $data);
        }

        /** Set the Frenet cache type enabled by default when module is installed. */
        $this->cacheType->setEnabled(true);
    }

    /**
     * @return array
     */
    private function getAttributes()
    {
        $attributes = [
            AttributesMappingInterface::DEFAULT_ATTRIBUTE_LENGTH    => [
                'label'       => __('Length (cm)'),
                'description' => __("Product's package length (for shipping calculation, minimum of 16cm)."),
                'note'        => __("Product's package length (for shipping calculation, minimum of 16cm)."),
                'default'     => 16,
                'type'        => 'int',
            ],
            AttributesMappingInterface::DEFAULT_ATTRIBUTE_HEIGHT    => [
                'label'       => __('Height (cm)'),
                'description' => __("Product's package height (for shipping calculation, minimum of 2cm)."),
                'note'        => __("Product's package height (for shipping calculation, minimum of 2cm)."),
                'default'     => 2,
                'type'        => 'int',
            ],
            AttributesMappingInterface::DEFAULT_ATTRIBUTE_WIDTH     => [
                'label'       => __('Width (cm)'),
                'description' => __("Product's package width (for shipping calculation, minimum of 11cm)."),
                'note'        => __("Product's package width (for shipping calculation, minimum of 11cm)."),
                'default'     => 11,
                'type'        => 'int',
            ],
            AttributesMappingInterface::DEFAULT_ATTRIBUTE_LEAD_TIME => [
                'label'       => __('Lead Time (days)'),
                'description' => __("Product's manufacturing time (for shipping calculation)."),
                'note'        => __("Product's manufacturing time (for shipping calculation)."),
                'default'     => 0,
                'type'        => 'int',
            ],
            AttributesMappingInterface::DEFAULT_ATTRIBUTE_FRAGILE   => [
                'label'       => __('Is Product Fragile?'),
                'description' => __('Whether the product contains any fragile materials (for shipping calculation).'),
                'note'        => __('Whether the product contains any fragile materials (for shipping calculation).'),
                'default'     => false,
                'type'        => 'int',
                'input'       => 'boolean',
                'backend'     => \Magento\Catalog\Model\Product\Attribute\Backend\Boolean::class,
                'source'      => \Magento\Catalog\Model\Product\Attribute\Source\Boolean::class,
            ],
        ];

        return $attributes;
    }
}
