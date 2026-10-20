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

namespace Frenet\Shipping\Model\Catalog\Product;

/**
 * Contract for mapping the Frenet package fields to the catalog product attribute codes that hold their values.
 */
interface AttributesMappingInterface
{
    /**
     * @var string
     */
    public const DEFAULT_ATTRIBUTE_WEIGHT = 'weight';

    /**
     * @var string
     */
    public const DEFAULT_ATTRIBUTE_HEIGHT = 'volume_height';

    /**
     * @var string
     */
    public const DEFAULT_ATTRIBUTE_WIDTH = 'volume_width';

    /**
     * @var string
     */
    public const DEFAULT_ATTRIBUTE_LENGTH = 'volume_length';

    /**
     * @var string
     */
    public const DEFAULT_ATTRIBUTE_LEAD_TIME = 'lead_time';

    /**
     * @var string
     */
    public const DEFAULT_ATTRIBUTE_FRAGILE = 'fragile';

    /**
     * Returns the product attribute code that stores the weight, falling back to the default one.
     *
     * @return string
     */
    public function getWeightAttributeCode();

    /**
     * Returns the product attribute code that stores the height, falling back to the default one.
     *
     * @return string
     */
    public function getHeightAttributeCode();

    /**
     * Returns the product attribute code that stores the length, falling back to the default one.
     *
     * @return string
     */
    public function getLengthAttributeCode();

    /**
     * Returns the product attribute code that stores the width, falling back to the default one.
     *
     * @return string
     */
    public function getWidthAttributeCode();

    /**
     * Returns the product attribute code that stores the lead time (extra days to ship).
     *
     * @return string
     */
    public function getLeadTimeAttributeCode();

    /**
     * Returns the product attribute code that flags the product as fragile.
     *
     * @return string
     */
    public function getFragileAttributeCode();
}
