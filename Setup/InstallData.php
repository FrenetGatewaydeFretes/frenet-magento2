<?php
declare(strict_types = 1);

namespace Frenet\Shipping\Setup;

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
     * @var CatalogProductAttributeInstaller
     */
    private $attributeInstaller;
    
    /**
     * Constructor
     *
     * @param \Magento\Eav\Setup\EavSetupFactory $eavSetupFactory
     */
    public function __construct(
        CatalogProductAttributeInstaller $attributeInstaller
    ) {
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
        foreach ($this->getAttributes() as $code => $data) {
            $this->attributeInstaller->install($code, (array) $data);
        }
    }
    
    /**
     * @return array
     */
    private function getAttributes()
    {
        $attributes = [
            'volume_length' => [
                'label' => __('Length (cm)'),
                'description' => __("Product's package length (for shipping calculation, minimum of 16cm)."),
                'note' => __("Product's package length (for shipping calculation, minimum of 16cm)."),
                'default' => 16,
                'type' => 'int',
            ],
            'volume_height' => [
                'label' => __('Height (cm)'),
                'description' => __("Product's package height (for shipping calculation, minimum of 2cm)."),
                'note' => __("Product's package height (for shipping calculation, minimum of 2cm)."),
                'default' => 2,
                'type' => 'int',
            ],
            'volume_width' => [
                'label' => __('Width (cm)'),
                'description' => __("Product's package width (for shipping calculation, minimum of 11cm)."),
                'note' => __("Product's package width (for shipping calculation, minimum of 11cm)."),
                'default' => 11,
                'type' => 'int',
            ],
            'lead_time' => [
                'label' => __('Lead Time (days)'),
                'description' => __("Product's manufacturing time (for shipping calculation)."),
                'note' => __("Product's manufacturing time (for shipping calculation)."),
                'default' => 0,
                'type' => 'int',
            ],
            'fragile' => [
                'label' => __('Is Product Fragile?'),
                'description' => __('Whether the product contains any fragile materials (for shipping calculation).'),
                'note' => __('Whether the product contains any fragile materials (for shipping calculation).'),
                'default' => false,
                'type' => 'int',
                'input' => 'boolean',
                'backend' => \Magento\Eav\Model\Entity\Attribute\Source\Boolean::class
            ],
        ];
        
        return $attributes;
    }
}
