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

use Frenet\Shipping\Model\Cache\Type\Frenet;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchVersionInterface;

/**
 * Creates the product shipping attributes and enables the Frenet cache type when the module is installed.
 */
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
     * @param Frenet                $cacheType
     * @param AttributeContainer    $attributeContainer
     * @param EavAttributeInstaller $attributeInstaller
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
     * @inheritDoc
     */
    public function apply()
    {
        /**
         * Run for new installation only.
         */
        $this->configureNewInstallation();
        return $this;
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public static function getVersion()
    {
        return '2.4.6';
    }

    /**
     * @inheritDoc
     */
    public function getAliases()
    {
        return [ "frenetshipping-2.4.6.1" ];
    }

    /**
     * Creates the new attributes during the module installation.
     *
     * @return void
     */
    private function configureNewInstallation()
    {
        /**
         * @var string  $code
         * @var mixed[] $data
         */
        foreach ($this->attributeContainer->getAttributeProperties() as $code => $data) {
            $this->attributeInstaller->install($code, (array) $data);
        }

        /** Set the Frenet cache type enabled by default when module is installed. */
        $this->cacheType->setEnabled(true);
    }
}
