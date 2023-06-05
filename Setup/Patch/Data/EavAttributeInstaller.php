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

use Frenet\Shipping\Model\Catalog\ProductType;

/**
 * Class EavAttributeInstaller
 */
abstract class EavAttributeInstaller
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var \Magento\Eav\Setup\EavSetupFactory
     */
    private $setupFactory;

    /**
     * EavAttributeInstaller constructor.
     *
     * @param \Magento\Eav\Setup\EavSetupFactory $setupFactory
     * @param \Psr\Log\LoggerInterface           $logger
     */
    public function __construct(
        \Magento\Eav\Setup\EavSetupFactory $setupFactory,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->setupFactory = $setupFactory;
        $this->logger = $logger;
    }

    /**
     * @param string $code
     * @param array  $config
     *
     * @return bool
     */
    public function install($code, array $config = [])
    {
        /** @var \Magento\Eav\Setup\EavSetup $setup */
        $setup = $this->setupFactory->create();
        $attribute = $this->prepareConfiguration($config);
        try {
            $setup->addAttribute($this->getEntityType(), $code, $attribute);

            return true;
        } catch (\Exception $e) {
            $this->logger->critical($e);

            return false;
        }
    }

    /**
     * @return string
     */
    abstract protected function getEntityType();

    /**
     * @param array $config
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
            'global'       => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
            'visible'      => true,
            'required'     => false,
            'user_defined' => true,
        ];
        $config = array_merge($defaultConfig, $config);

        return $config;
    }

    /**
     * @return \Magento\Framework\Phrase
     */
    private function getAttributeGroup()
    {
        return __('Shipping Quote');
    }
}
