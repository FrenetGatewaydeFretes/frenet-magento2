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

namespace Frenet\Shipping\Test\Unit\Model\Config\Backend;

use Frenet\Shipping\Model\Config\ApiHostnameValidator;
use Frenet\Shipping\Model\Config\Backend\ApiHostname;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\ValidatorException;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Tests that the API hostname field refuses non-Frenet hosts before the value is saved.
 */
#[AllowMockObjectsWithoutExpectations]
class ApiHostnameTest extends TestCase
{
    /**
     * @var ApiHostname
     */
    private ApiHostname $subject;

    /**
     * Builds the backend model over mocked framework collaborators and the real validator.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $context = $this->createMock(Context::class);
        $context->method('getEventDispatcher')->willReturn($this->createMock(ManagerInterface::class));

        $this->subject = new ApiHostname(
            $context,
            $this->createMock(Registry::class),
            $this->createMock(ScopeConfigInterface::class),
            $this->createMock(TypeListInterface::class),
            new ApiHostnameValidator()
        );
    }

    /**
     * Confirms an empty value is saved, so the default Frenet hostname is used.
     *
     * @return void
     */
    public function testShouldSaveWhenTheHostnameIsEmpty(): void
    {
        $this->subject->setValue('  ');
        $this->subject->beforeSave();

        $this->assertSame('', $this->subject->getValue());
    }

    /**
     * Confirms a Frenet hostname is saved trimmed and in lower case.
     *
     * @return void
     */
    public function testShouldSaveItNormalizedWhenTheHostnameIsAFrenetAddress(): void
    {
        $this->subject->setValue(' Sandbox.API.frenet.com.br ');
        $this->subject->beforeSave();

        $this->assertSame('sandbox.api.frenet.com.br', $this->subject->getValue());
    }

    /**
     * Confirms a hostname outside frenet.com.br is refused with a message for the admin.
     *
     * @return void
     */
    public function testShouldRefuseTheSaveWhenTheHostnameIsOutsideFrenet(): void
    {
        $this->subject->setValue('api.example.com');

        $this->expectException(ValidatorException::class);
        $this->expectExceptionMessage('The API hostname must be a frenet.com.br address, such as api.frenet.com.br.');

        $this->subject->beforeSave();
    }
}
