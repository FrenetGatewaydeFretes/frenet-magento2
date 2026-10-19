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

namespace Frenet\Shipping\Setup\Patch\Data;

use Exception;
use Frenet\Shipping\Model\Catalog\ProductType;
use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Console\Cli;
use Magento\Framework\Phrase;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Psr\Log\LoggerInterface;

/**
 * Creates catalog product EAV attributes from partial definitions merged over the module's shipping defaults.
 */
class EavAttributeInstaller implements DataPatchInterface
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var EavSetupFactory
     */
    private $setupFactory;

    /**
     * @param EavSetupFactory $setupFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        EavSetupFactory $setupFactory,
        LoggerInterface $logger
    ) {
        $this->setupFactory = $setupFactory;
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function apply()
    {
        return Cli::RETURN_SUCCESS;
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * Returns the module version this patch was introduced in.
     *
     * @return string
     */
    public static function getVersion()
    {
        return '2.4.5';
    }

    /**
     * @inheritDoc
     */
    public function getAliases()
    {
        return [ "frenetshipping-2.4.5.4" ];
    }

    /**
     * Adds the product attribute and logs instead of failing the setup when Magento rejects it.
     *
     * @param string  $code
     * @param mixed[] $config
     *
     * @return bool
     */
    public function install($code, array $config = [])
    {
        /** @var EavSetup $setup */
        $setup = $this->setupFactory->create();
        $attribute = $this->prepareConfiguration($config);
        try {
            $setup->addAttribute($this->getEntityType(), $code, $attribute);

            return true;
        } catch (Exception $e) {
            $this->logger->critical($e);

            return false;
        }
    }

    /**
     * Returns the EAV entity type the attributes are attached to.
     *
     * @return string
     */
    protected function getEntityType()
    {
        return Product::ENTITY;
    }

    /**
     * Fills the attribute definition with the defaults for a global, user-defined shipping attribute.
     *
     * @param mixed[] $config
     *
     * @return mixed[]
     */
    private function prepareConfiguration(array $config = [])
    {
        $defaultConfig = [
            'label'        => null,
            'default'      => null,
            'note'         => null,
            'input'        => 'text',
            'apply_to'     => implode(',', ProductType::PRODUCT_TYPES),
            'type'         => 'text',
            'group'        => $this->getAttributeGroup(),
            'backend'      => null,
            'global'       => ScopedAttributeInterface::SCOPE_GLOBAL,
            'visible'      => true,
            'required'     => false,
            'user_defined' => true,
        ];
        $config = array_merge($defaultConfig, $config);

        return $config;
    }

    /**
     * Returns the product attribute group the shipping attributes are placed in.
     *
     * @return Phrase
     */
    private function getAttributeGroup()
    {
        return __('Shipping Quote');
    }
}
