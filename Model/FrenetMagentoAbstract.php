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

use Psr\Log\LoggerInterface;

/**
 * Former base class for debug logging; nothing in the module extends it any more.
 *
 * @deprecated 2.4.8-p4 Inject LoggerInterface directly.
 * @SuppressWarnings(PHPMD.CamelCasePropertyName)
 * @SuppressWarnings(PHPMD.CamelCaseMethodName)
 */
abstract class FrenetMagentoAbstract
{
    /**
     * @var LoggerInterface
     */
    protected $_logger;

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(
        LoggerInterface $logger
    ) {
        $this->_logger = $logger;
    }

    /**
     * Writes the exported value of the data to the debug log.
     *
     * @param mixed $debugData
     *
     * @return void
     */
    protected function _debug($debugData)
    {
        $this->_logger->debug(var_export($debugData, true));
    }

    /**
     * Logs debug data from outside the class hierarchy.
     *
     * @param mixed $debugData
     *
     * @return void
     */
    public function debugData($debugData)
    {
        $this->_debug($debugData);
    }
}
