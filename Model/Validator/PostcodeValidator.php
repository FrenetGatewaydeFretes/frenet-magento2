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

use Frenet\Shipping\Model\FrenetMagentoAbstract;
use \Psr\Log\LoggerInterface;

/**
 * Class PostcodeValidator
 */
class PostcodeValidator extends FrenetMagentoAbstract
{
    /**
     * @var \Frenet\Shipping\Model\Formatters\PostcodeNormalizer
     */
    private $postcodeNormalizer;

    public function __construct(
        \Frenet\Shipping\Model\Formatters\PostcodeNormalizer $postcodeNormalizer,
        \Psr\Log\LoggerInterface $logger
    ) {
        parent::__construct($logger);
        $this->postcodeNormalizer = $postcodeNormalizer;
    }

    /**
     * @param string|null $postcode
     *
     * @return bool
     */
    public function validate(string $postcode = null): bool
    {
        $this->_logger->debug("postcode-validator-validate-pre");
        if (empty($postcode)) {
            return false;
        }

        if (!((int) $this->postcodeNormalizer->format($postcode))) {
            return false;
        }

        return true;
    }
}
