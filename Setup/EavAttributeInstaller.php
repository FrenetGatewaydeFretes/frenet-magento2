<?php

declare(strict_types = 1);

namespace Frenet\Shipping\Setup;

/**
 * Class EavAttributeInstaller
 *
 * @package Frenet\Shipping\Setup
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
        $setup     = $this->setupFactory->create();
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
    protected abstract function getEntityType();
    
    /**
     * @param array $config
     */
    private function prepareConfiguration(array $config = [])
    {
        $defaultConfig = [
            'label'                   => null,
            'default'                 => null,
            'note'                    => null,
            'input'                   => 'text',
            'apply_to'                => implode(',', $this->getProductTypes()),
            'type'                    => 'text',
            'group'                   => $this->getAttributeGroup(),
            'backend'                 => null,
            //            'frontend'                => '',
            'global'                  => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
            'visible'                 => false,
            'required'                => false,
            'user_defined'            => true,
            //            'input_renderer'          => \Magento\Msrp\Block\Adminhtml\Product\Helper\Form\Type::class,
            //            'frontend_input_renderer' => \Magento\Msrp\Block\Adminhtml\Product\Helper\Form\Type::class,
            //            'visible_on_front'        => true,
            //            'used_in_product_listing' => true,
            //            'is_used_in_grid'         => true,
            //            'is_visible_in_grid'      => false,
            //            'is_filterable_in_grid'   => true
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
    
    /**
     * @return array
     */
    private function getProductTypes()
    {
        return [
            \Magento\Catalog\Model\Product\Type::TYPE_SIMPLE,
            \Magento\Catalog\Model\Product\Type::TYPE_VIRTUAL,
            \Magento\Downloadable\Model\Product\Type::TYPE_DOWNLOADABLE,
            \Magento\Catalog\Model\Product\Type::TYPE_BUNDLE,
        ];
    }
}
