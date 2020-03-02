<?php

declare(strict_types = 1);

namespace Frenet\Shipping\Model\Validator;

/**
 * Class PostcodeValidator
 *  */
class PostcodeValidator
{
    /**
     * @param string|null $postcode
     *
     * @return bool
     */
    public function validate(string $postcode = null) : bool
    {
        if (empty($postcode)) {
            return false;
        }

        if (!((int) $this->postcodeNormalizer->format($request->getDestPostcode()))) {
            return false;
        }

        return true;
    }
}
