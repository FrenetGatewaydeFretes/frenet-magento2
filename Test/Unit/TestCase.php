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

namespace Frenet\Shipping\Test\Unit;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;

/**
 * Class TestCase
 *
 * @package Frenet\Shipping\Test\Unit
 */
class TestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @return ObjectManager
     */
    protected function getObjectManager() : ObjectManager
    {
        if (!$this->objectManager) {
            $this->objectManager = new ObjectManager($this);
        }

        return $this->objectManager;
    }
}
