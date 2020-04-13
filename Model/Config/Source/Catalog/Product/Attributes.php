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

namespace Frenet\Shipping\Model\Config\Source\Catalog\Product;

/**
 * Class Attributes
 */
class Attributes implements \Magento\Framework\Data\OptionSourceInterface
{
    /**
     * @var array
     */
    private $options = [];

    /**
     * @var \Magento\Catalog\Api\ProductAttributeRepositoryInterface
     */
    private $attributeRepository;

    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    public function __construct(
        \Magento\Catalog\Api\ProductAttributeRepositoryInterface $attributeRepository,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->attributeRepository = $attributeRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    /**
     * Return array of options as value-label pairs
     *
     * @return array Format: array(array('value' => '<value>', 'label' => '<label>'), ...)
     */
    public function toOptionArray()
    {
        $options = [];

        foreach ($this->toArray() as $code => $label) {
            $options[] = [
                'label' => "{$label} [{$code}]",
                'value' => $code,
            ];
        }

        return $options;
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        if (empty($this->options)) {
            /** @var \Magento\Catalog\Model\ResourceModel\Eav\Attribute $attribute */
            foreach ($this->getCollection() as $attribute) {
                $this->options[$attribute->getAttributeCode()] = $attribute->getDefaultFrontendLabel();
            }
        }

        return $this->options;
    }

    /**
     * @return \Magento\Catalog\Api\Data\ProductAttributeInterface[]
     */
    private function getCollection()
    {
        $searchCriteria = $this->searchCriteriaBuilder->create();
        $searchCriteria->setData('entity_type_id', \Magento\Catalog\Model\Product::ENTITY);

        /** @var \Magento\Catalog\Api\Data\ProductAttributeSearchResultsInterface $attributeRepository */
        $attributeRepository = $this->attributeRepository->getList(
            $searchCriteria
        );

        return $attributeRepository->getItems();
    }
}
