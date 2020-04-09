<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 * @package  Frenet\Shipping
 *
 * @author   Tiago Sampaio <tiago@tiagosampaio.com>
 * @link     https://github.com/tiagosampaio
 * @link     https://tiagosampaio.com
 *
 * Copyright (c) 2020.
 */

namespace Frenet\Shipping\Test\Unit\Model\Catalog\Product\View;

use Frenet\ObjectType\Entity\Shipping\Quote\ServiceInterface;
use Frenet\ObjectType\Entity\Shipping\Quote\Service;
use Frenet\ObjectType\Entity\Shipping\Quote\ServiceFactory;
use Frenet\Shipping\Model\Catalog\Product\View\Quote;
use Frenet\Shipping\Model\Packages\Package;
use Frenet\Shipping\Model\Packages\PackageItem;
use Frenet\Shipping\Model\Packages\PackageManager;
use Frenet\Shipping\Model\Packages\PackageProcessor;
use Frenet\Shipping\Test\Unit\TestCase;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\DataObject;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;

class QuoteTest extends TestCase
{
    /**
     * @var Quote
     */
    private $quote;

    /**
     * @var Product
     */
    private $product;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var int
     */
    private $productId = 45;

    /**
     * @var int
     */
    private $productSku = 'PRODUCT-SIMPLE-SKU';

    /**
     * @var MockObject | DataObject
     */
    private $dataObject;

    /**
     * @var \Magento\Framework\DataObjectFactory
     */
    private $objectFactory;

    /**
     * @var Package
     */
    private $package;

    /**
     * @var PackageManager
     */
    private $packageManager;

    /**
     * @var PackageProcessor
     */
    private $packageProcessor;

    /**
     * @var ServiceFactory
     */
    private $serviceFactory;

    /**
     * @var ServiceInterface
     */
    private $service;

    protected function setUp()
    {
        $this->product = $this->prepareProduct();

        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->productRepository->method('get')->willReturn($this->product);
        $this->productRepository->method('getById')->willReturn($this->product);

        $this->dataObject = $this->createMock(DataObject::class);
        $this->objectFactory = $this->createMock(\Magento\Framework\DataObjectFactory::class);
        $this->objectFactory->method('create')->willReturn($this->dataObject);

        /**
         * Build Package
         */
        $dimensionsExtractor = $this->createMock(\Frenet\Shipping\Model\Catalog\Product\DimensionsExtractor::class);
        $dimensionsExtractor->method('setProductByCartItem')->willReturn($dimensionsExtractor);

        $packageItem = $this->getObject(PackageItem::class);

        $packageItemFactory = $this->createMock(\Frenet\Shipping\Model\Packages\PackageItemFactory::class);
        $packageItemFactory->method('create')->willReturn($packageItem);

        $this->package = $this->getObject(Package::class, [
            'dimensionsExtractor' => $dimensionsExtractor,
            'packageItemFactory' => $packageItemFactory,
        ]);

        $this->packageManager = $this->createMock(PackageManager::class);
        $this->packageManager->method('createPackage')->willReturn($this->package);


        $quoteItemValidator = $this->createMock(\Frenet\Shipping\Api\QuoteItemValidatorInterface::class);
        $quoteItemValidator->method('validate')->willReturn(true);

        $config = $this->createMock(\Frenet\Shipping\Model\Config::class);
        $config->method('getOriginPostcode')->willReturn('06999-000');

        $quote = $this->getObject(\Frenet\Command\Shipping\Quote::class);

        $apiService = $this->createMock(\Frenet\Shipping\Model\ApiService::class);
        $apiService->method('shipping')->method('quote')->willReturn($quote);

        $this->packageProcessor = $this->getObject(PackageProcessor::class, [
            'quoteItemValidator' => $quoteItemValidator,
            'config' => $config,
            'apiService' => $apiService,
        ]);

        /**
         * Service
         */
        $this->service = $this->createServiceInstance();

        /**
         * Quote Item Processor
         */
        $item = $this->getObject(\Magento\Quote\Model\Quote\Item::class);

        $quoteItemFactory = $this->createMock(\Magento\Quote\Model\Quote\ItemFactory::class);
        $quoteItemFactory->method('create')->willReturn($item);

        $store = $this->createMock(\Magento\Store\Model\Store::class);
        $store->method('getId')->willReturn(1);

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $quoteItemProcessor = $this->getObject(\Magento\Quote\Model\Quote\Item\Processor::class, [
            'quoteItemFactory' => $quoteItemFactory,
            'storeManager' => $storeManager,
        ]);

        $this->quote = $this->getObject(Quote::class, [
            'objectFactory' => $this->objectFactory,
            'productRepository' => $this->productRepository,
            'packageManager' => $this->packageManager,
            'packageProcessor' => $this->packageProcessor,
            'quoteItemProcessor' => $quoteItemProcessor,
        ]);
    }

    /**
     * @test
     */
    public function quote()
    {
        /** @var array $services */
        $services = $this->quote->quote($this->product);

        $this->assertTrue(is_array($services));
        $this->assertFalse(empty($services));

        /** @var ServiceInterface $service */
        foreach ($services as $service) {
            $this->assertInstanceOf(ServiceInterface::class, $service);
            $this->assertEquals($this->service, $service);
        }
    }

    /**
     * @ test
     */
    public function quoteByProductId()
    {
        $this->objectFactory->method('create')->willReturn($this->dataObject);

        $expected = [];
        $this->assertEquals($expected, $this->quote->quoteByProductId($this->productId));
    }

    /**
     * @ test
     */
    public function quoteByProductSku()
    {
        $this->objectFactory->method('create')->willReturn($this->dataObject);

        $expected = [];
        $this->assertEquals($expected, $this->quote->quoteByProductSku($this->productSku));
    }

    /**
     * @return Product
     */
    private function prepareProduct() : Product
    {
        /** @var Product | MockObject $product */
        $product = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'getId',
                'getSku',
                'getPrice',
                'getFinalPrice',
                'getName',
                'getWeight',
                'getTypeId',
                'getCartQty',
            ])
            ->getMock();

        $product->method('getId')->willReturn($this->productId);
        $product->method('getSku')->willReturn($this->productSku);
        $product->method('getPrice')->willReturn(123.95);
        $product->method('getFinalPrice')->willReturn(123.95);
        $product->method('getName')->willReturn('Frenet Testing Product');
        $product->method('getWeight')->willReturn(1);
        $product->method('getTypeId')->willReturn(\Magento\Catalog\Model\Product\Type::TYPE_SIMPLE);

        $product->method('getCartQty')->will($this->returnValue(1));

        return $product;
    }

    /**
     * @return ServiceInterface
     */
    private function createServiceInstance() : ServiceInterface
    {
        if (!$this->serviceFactory) {
            $this->serviceFactory = $this->getObject(ServiceFactory::class, [
                'objectManager' => $this->getObject(\Frenet\Framework\ObjectManager::class)
            ]);
        }

        return $this->serviceFactory->create();
    }
}