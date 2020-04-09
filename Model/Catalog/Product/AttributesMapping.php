<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 * @package Frenet\Shipping
 *
 * @author Tiago Sampaio <tiago@tiagosampaio.com>
 * @link https://github.com/tiagosampaio
 * @link https://tiagosampaio.com
 *
 * Copyright (c) 2020.
 */

declare(strict_types = 1);

namespace Frenet\Shipping\Model\Catalog\Product;

use Frenet\Shipping\Model\Config;

/**
 * Class AttributesMapping
 *
 * @package Frenet\Shipping\Model\Catalog\Product
 */
class AttributesMapping implements AttributesMappingInterface
{
    /**
     * @var Config
     */
    private $config;

    public function __construct(
        Config $config
    ) {
        $this->config = $config;
    }

    /**
     * {@inheritdoc}
     */
    public function getWeightAttributeCode()
    {
        return $this->config->getWeightAttribute() ?: self::DEFAULT_ATTRIBUTE_WEIGHT;
    }

    /**
     * {@inheritdoc}
     */
    public function getHeightAttributeCode()
    {
        return $this->config->getHeightAttribute() ?: self::DEFAULT_ATTRIBUTE_HEIGHT;
    }

    /**
     * {@inheritdoc}
     */
    public function getLengthAttributeCode()
    {
        return $this->config->getLengthAttribute() ?: self::DEFAULT_ATTRIBUTE_LENGTH;
    }

    /**
     * {@inheritdoc}
     */
    public function getWidthAttributeCode()
    {
        return $this->config->getWidthAttribute() ?: self::DEFAULT_ATTRIBUTE_WIDTH;
    }

    /**
     * {@inheritdoc}
     */
    public function getLeadTimeAttributeCode()
    {
        return self::DEFAULT_ATTRIBUTE_LEAD_TIME;
    }

    /**
     * {@inheritdoc}
     */
    public function getFragileAttributeCode()
    {
        return self::DEFAULT_ATTRIBUTE_FRAGILE;
    }
}
