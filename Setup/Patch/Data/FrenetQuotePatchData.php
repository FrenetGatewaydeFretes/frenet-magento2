<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 *
 * @author Frenet Gateway <suporte@frenet.com.br>
 * @link https://github.com/FrenetGatewaydeFretes/frenet-magento2
 * @link https://www.frenet.com.br
 *
 * Copyright (c) 2023.
 */

declare(strict_types = 1);

namespace Frenet\Shipping\Setup\Patch\Data;

use Frenet\Shipping\Model\Catalog\ProductType; 
use Frenet\Shipping\Model\Cache\Type\Frenet;
use Magento\Framework\Console\Cli;
use Magento\Framework\Locale\Bundle\DataBundle;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchVersionInterface;

class FrenetQuotePatchData implements DataPatchInterface, PatchVersionInterface
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
     * @var EavAttributeInstaller
     */
    private $attributeInstaller;

    /**
     * Constructor
     *
     * @param Frenet $cacheType
     * @param AttributeContainer                       $attributeContainer
     * @param EavAttributeInstaller                    $attributeInstaller
     */
    public function __construct(
        Frenet $cacheType,
        AttributeContainer $attributeContainer,
        EavAttributeInstaller $attributeInstaller
    ) {
        $this->cacheType = $cacheType;
        $this->attributeContainer = $attributeContainer;
        $this->attributeInstaller = $attributeInstaller;
    }

    /**
     * @inheritdoc
     */
    public function apply()
    {
        /**
         * Run for new installation only.
         */
        $this->configureNewInstallation();
        return Cli::RETURN_SUCCESS;
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public static function getVersion()
    {
        return '2.4.5';
    }

    /**
     * @inheritdoc
     */
    public function getAliases()
    {
        return [ "frenetshipping-2.4.5.4" ];
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