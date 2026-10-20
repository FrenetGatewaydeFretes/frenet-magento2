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

namespace Frenet\Shipping\Model\Validator;

use Frenet\Shipping\Model\Formatters\PostcodeNormalizer;

/**
 * Rejects empty postcodes and those that normalize to all zeros before a quote is requested.
 */
class PostcodeValidator
{
    /**
     * @var PostcodeNormalizer
     */
    private $postcodeNormalizer;

    /**
     * @param PostcodeNormalizer $postcodeNormalizer
     */
    public function __construct(
        PostcodeNormalizer $postcodeNormalizer
    ) {
        $this->postcodeNormalizer = $postcodeNormalizer;
    }

    /**
     * Checks that the postcode is filled in and holds at least one non-zero digit once normalized.
     *
     * @param string|null $postcode
     *
     * @return bool
     */
    public function validate(?string $postcode = null): bool
    {
        if (empty($postcode)) {
            return false;
        }

        if (!((int) $this->postcodeNormalizer->format($postcode))) {
            return false;
        }

        return true;
    }
}
