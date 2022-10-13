<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 *
 * @author Alexander Campos <alexandercamps@gmail.com>
 *
 * Copyright (c) 2022.
 */
declare(strict_types = 1);

namespace Frenet\Shipping\Model;

use \Psr\Log\LoggerInterface;

abstract class FrenetMagentoAbstract
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger;

    /**
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->_logger = $logger;
    }

    /**
     * Log debug data to file
     *
     * @param mixed $debugData
     * @return void
     */
    protected function _debug($debugData)
    {
        $this->_logger->debug(var_export($debugData, true));
    }

    /**
     * Used to call debug method from not Payment Method context
     *
     * @param mixed $debugData
     * @return void
     */
    public function debugData($debugData)
    {
        $this->_debug($debugData);
    }
}