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

namespace Frenet\Shipping\Block\Form\Element;

use Frenet\Shipping\Model\ModuleMetadata;
use Magento\Framework\Data\Form\Element\CollectionFactory;
use Magento\Framework\Data\Form\Element\Factory;
use Magento\Framework\Data\Form\Element\Label as FormLabel;
use Magento\Framework\Escaper;

/**
 * Admin configuration field that displays the installed module version instead of an editable value.
 */
class Label extends FormLabel
{
    /**
     * @var ModuleMetadata
     */
    private $moduleMetadata;

    /**
     * @param Factory           $factoryElement
     * @param CollectionFactory $factoryCollection
     * @param Escaper           $escaper
     * @param ModuleMetadata    $moduleMetadata
     * @param mixed[]           $data
     */
    public function __construct(
        Factory $factoryElement,
        CollectionFactory $factoryCollection,
        Escaper $escaper,
        ModuleMetadata $moduleMetadata,
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
