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

use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Catalog\Api\Data\ProductAttributeSearchResultsInterface;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Lists the product attributes as "label [code]" options for mapping the weight and dimension attributes.
 *
 * @SuppressWarnings(PHPMD.LongVariable)
 */
class Attributes implements OptionSourceInterface
{
    /**
     * @var string[]
     */
    private $options = [];

    /**
     * @var ProductAttributeRepositoryInterface
     */
    private $attributeRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @param ProductAttributeRepositoryInterface $attributeRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        ProductAttributeRepositoryInterface $attributeRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->attributeRepository = $attributeRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    /**
     * Returns the attributes as value-label option rows, with the attribute code appended to each label.
     *
     * @return string[][]
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
     * Returns the attribute default frontend labels keyed by attribute code, loaded once per instance.
     *
     * @return string[]
     */
    public function toArray()
    {
        if (empty($this->options)) {
            /** @var Attribute $attribute */
            foreach ($this->getCollection() as $attribute) {
                $this->options[$attribute->getAttributeCode()] = $attribute->getDefaultFrontendLabel();
            }
        }

        return $this->options;
    }

    /**
     * Loads every product attribute through the attribute repository.
     *
     * @return ProductAttributeInterface[]
     */
    private function getCollection()
    {
        $searchCriteria = $this->searchCriteriaBuilder->create();
        $searchCriteria->setData('entity_type_id', Product::ENTITY);

        /** @var ProductAttributeSearchResultsInterface $attributeRepository */
        $attributeRepository = $this->attributeRepository->getList(
            $searchCriteria
        );

        return $attributeRepository->getItems();
    }
}
