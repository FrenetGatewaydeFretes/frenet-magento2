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

namespace Frenet\Shipping\Model\Formatters;

/**
 * Turns any user-typed postcode into the eight-digit form the Frenet API expects.
 */
class PostcodeNormalizer
{
    /**
     * Strips non-digit characters and left-pads the result with zeros to eight digits.
     *
     * @param string|null $postcode
     *
     * @return string
     */
    public function format(?string $postcode = null)
    {
        $postcode = preg_replace('/[^0-9]/', "", "" . $postcode);
        $postcode = str_pad($postcode, 8, '0', STR_PAD_LEFT);

        return $postcode;
    }
}
