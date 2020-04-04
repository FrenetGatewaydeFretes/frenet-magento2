<?php
/**
 * Copyright © MagedIn. All rights reserved.
 * See COPYING.txt for license details.
 *
 * @author Tiago Sampaio <tiago.sampaio@magedin.com>
 */
declare(strict_types = 1);

namespace Frenet\Shipping\Block\Form\Element;

/**
 * Class Label
 *
 * @package Frenet\Shipping\Block\Form\Element
 */
class Label extends \Magento\Framework\Data\Form\Element\Label
{
    /**
     * @var \Frenet\Shipping\Model\ModuleMetadata
     */
    private $moduleMetadata;

    public function __construct(
        \Magento\Framework\Data\Form\Element\Factory $factoryElement,
        \Magento\Framework\Data\Form\Element\CollectionFactory $factoryCollection,
        \Magento\Framework\Escaper $escaper,
        \Frenet\Shipping\Model\ModuleMetadata $moduleMetadata,
        $data = []
    ) {
        parent::__construct($factoryElement, $factoryCollection, $escaper, $data);
        $this->moduleMetadata = $moduleMetadata;
    }

    /**
     * Get module's version. First try to get it from composer installation otherwise use the config information.
     *
     * @return string
     */
    public function getValue() : string
    {
        return (string) $this->moduleMetadata->getVersion() ?: $this->getData('version');
    }
}
