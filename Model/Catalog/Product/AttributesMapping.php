<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 *
 * @author   Tiago Sampaio <tiago@tiagosampaio.com>
 * @link     https://github.com/tiagosampaio
 * @link     https://tiagosampaio.com
 *
 * Copyright (c) 2020.
 */

declare(strict_types=1);

namespace Frenet\Shipping\Model\Catalog\Product;

use Frenet\Shipping\Model\ConfigInterface;

/**
 * Resolves the product attribute codes used for weight, dimensions, lead time and fragility, with module defaults.
 */
class AttributesMapping implements AttributesMappingInterface
{
    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @param ConfigInterface $config
     */
    public function __construct(
        ConfigInterface $config
    ) {
        $this->config = $config;
    }

    /**
     * @inheritDoc
     */
    public function getWeightAttributeCode()
    {
        return $this->config->getWeightAttribute() ?: self::DEFAULT_ATTRIBUTE_WEIGHT;
    }

    /**
     * @inheritDoc
     */
    public function getHeightAttributeCode()
    {
        return $this->config->getHeightAttribute() ?: self::DEFAULT_ATTRIBUTE_HEIGHT;
    }

    /**
     * @inheritDoc
     */
    public function getLengthAttributeCode()
    {
        return $this->config->getLengthAttribute() ?: self::DEFAULT_ATTRIBUTE_LENGTH;
    }

    /**
     * @inheritDoc
     */
    public function getWidthAttributeCode()
    {
        return $this->config->getWidthAttribute() ?: self::DEFAULT_ATTRIBUTE_WIDTH;
    }

    /**
     * @inheritDoc
     */
    public function getLeadTimeAttributeCode()
    {
        return self::DEFAULT_ATTRIBUTE_LEAD_TIME;
    }

    /**
     * @inheritDoc
     */
    public function getFragileAttributeCode()
    {
        return self::DEFAULT_ATTRIBUTE_FRAGILE;
    }
}
