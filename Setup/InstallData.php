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

use Frenet\Shipping\Model\Cache\Type\Frenet;
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
     * @var Frenet
     */
    protected $cacheType;

    /**
     * @var AttributeContainer
     */
    protected $attributeContainer;

    /**
     * @var CatalogProductAttributeInstaller
     */
    private $attributeInstaller;

    /**
     * Constructor
     *
     * @param Frenet $cacheType
     * @param AttributeContainer                       $attributeContainer
     * @param CatalogProductAttributeInstaller         $attributeInstaller
     */
    public function __construct(
        Frenet $cacheType,
        AttributeContainer $attributeContainer,
        CatalogProductAttributeInstaller $attributeInstaller
    ) {
        $this->cacheType = $cacheType;
        $this->attributeContainer = $attributeContainer;
        $this->attributeInstaller = $attributeInstaller;
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
        foreach ($this->attributeContainer->getAttributeProperties() as $code => $data) {
            $this->attributeInstaller->install($code, (array) $data);
        }

        /** Set the Frenet cache type enabled by default when module is installed. */
        $this->cacheType->setEnabled(true);
    }
}
