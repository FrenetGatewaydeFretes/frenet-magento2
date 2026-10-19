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

namespace Frenet\Shipping\Model\Config\Backend;

use Frenet\Shipping\Model\Config\ApiHostnameValidator;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\ValidatorException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

/**
 * Rejects an API hostname override outside frenet.com.br before it is saved.
 */
class ApiHostname extends Value
{
    /**
     * @param Context               $context
     * @param Registry              $registry
     * @param ScopeConfigInterface  $config
     * @param TypeListInterface     $cacheTypeList
     * @param ApiHostnameValidator  $validator
     * @param AbstractResource|null $resource
     * @param AbstractDb|null       $resourceCollection
     * @param mixed[]               $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        private readonly ApiHostnameValidator $validator,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * Normalizes the hostname and refuses it when it is not a frenet.com.br address.
     *
     * @return $this
     *
     * @throws ValidatorException
     */
    public function beforeSave()
    {
        $hostname = strtolower(trim((string) $this->getValue()));

        if ($hostname !== '' && !$this->validator->isAllowed($hostname)) {
            throw new ValidatorException(
                __('The API hostname must be a frenet.com.br address, such as api.frenet.com.br.')
            );
        }

        $this->setValue($hostname);

        return parent::beforeSave();
    }
}
