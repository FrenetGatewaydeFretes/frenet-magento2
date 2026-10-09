<?php
/**
 * Frenet_Shipping
 *
 * @vendor    Frenet
 * @package   Shipping
 *
 * @copyright © 2026 Diego M. Miyabara. All rights reserved.
 * @author    Diego M. Miyabara <diego.miyabara@frenet.com.br>
 */

declare(strict_types=1);

namespace Frenet\Shipping\Service;

use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote\Address\RateRequest;

/**
 * Carries the current quote's rate request so collaborators down the calculation chain read it without re-plumbing it.
 */
interface RateRequestProviderInterface
{
    /**
     * Stores the rate request for the current quote.
     *
     * @param RateRequest $rateRequest
     *
     * @return self
     */
    public function setRateRequest(RateRequest $rateRequest): self;

    /**
     * Returns the rate request set for the current quote.
     *
     * @return RateRequest
     * @throws LocalizedException
     */
    public function getRateRequest(): RateRequest;

    /**
     * Drops the stored rate request once the quote is done.
     *
     * @return self
     */
    public function clear(): self;
}
