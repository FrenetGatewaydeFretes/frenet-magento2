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

namespace Frenet\Shipping\Test\Unit\Controller\Product;

use Frenet\Shipping\Api\QuoteProductInterface;
use Frenet\Shipping\Controller\Product\Quote;
use Frenet\Shipping\Model\ConfigInterface;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Tests the product page quote endpoint guards and the error payload it returns to anonymous visitors.
 */
#[AllowMockObjectsWithoutExpectations]
class QuoteTest extends TestCase
{
    /**
     * @var array<string, mixed>
     */
    private array $params = ['product' => 7, 'postcode' => '01310-100', 'qty' => 2];

    /**
     * @var array<string, mixed>
     */
    private array $payload = [];

    /**
     * @var QuoteProductInterface&MockObject
     */
    private MockObject $quoteProduct;

    /**
     * @var ConfigInterface&MockObject
     */
    private MockObject $config;

    /**
     * @var LoggerInterface&MockObject
     */
    private MockObject $logger;

    /**
     * @var Quote
     */
    private Quote $subject;

    /**
     * Wires the controller over a mocked request and a JSON result that records the payload it receives.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(fn (string $key) => $this->params[$key] ?? null);
        $request->method('getParams')->willReturnCallback(fn () => $this->params);

        $json = $this->createMock(Json::class);
        $json->method('setData')->willReturnCallback(function (array $data) use ($json) {
            $this->payload = $data;

            return $json;
        });
        $resultFactory = $this->createMock(ResultFactory::class);
        $resultFactory->method('create')->with(ResultFactory::TYPE_JSON)->willReturn($json);

        $context = $this->createMock(Context::class);
        $context->method('getRequest')->willReturn($request);
        $context->method('getResultFactory')->willReturn($resultFactory);

        $this->quoteProduct = $this->createMock(QuoteProductInterface::class);
        $this->config = $this->createMock(ConfigInterface::class);
        $this->config->method('getMaxUnitQuantity')->willReturn(5000);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->subject = new Quote($context, $this->quoteProduct, $this->config, $this->logger);
    }

    /**
     * Confirms no quote is requested while the product page quote is turned off.
     *
     * @return void
     */
    public function testShouldRefuseTheQuoteWhenTheFeatureIsDisabled(): void
    {
        $this->enableQuote(false);
        $this->quoteProduct->expects($this->never())->method('quoteByProductId');

        $this->subject->execute();

        $this->assertSame(['error' => true, 'message' => 'The shipping quote is not available.'], $this->payload);
    }

    /**
     * Confirms a postcode without the eight CEP digits is refused before reaching the Frenet API.
     *
     * @return void
     */
    public function testShouldRefuseAnIncompletePostcode(): void
    {
        $this->enableQuote(true);
        $this->params['postcode'] = '013';
        $this->quoteProduct->expects($this->never())->method('quoteByProductId');

        $this->subject->execute();

        $this->assertSame(['error' => true, 'message' => 'Please inform a valid postcode'], $this->payload);
    }

    /**
     * Confirms the calculated rates are returned for a valid request.
     *
     * @return void
     */
    public function testShouldReturnTheRatesForAValidRequest(): void
    {
        $this->enableQuote(true);
        $this->quoteProduct->method('quoteByProductId')
            ->with(7, '01310-100', 2)
            ->willReturn([['service_code' => '03298']]);

        $this->subject->execute();

        $this->assertSame(['error' => false, 'rates' => [['service_code' => '03298']]], $this->payload);
    }

    /**
     * Confirms a localized exception keeps its message, since it is written for the customer.
     *
     * @return void
     */
    public function testShouldShowTheMessageOfALocalizedException(): void
    {
        $this->enableQuote(true);
        $this->quoteProduct->method('quoteByProductId')
            ->willThrowException(new LocalizedException(__('Out of range.')));

        $this->subject->execute();

        $this->assertSame(['error' => true, 'message' => 'Out of range.'], $this->payload);
    }

    /**
     * Confirms an unexpected error is logged and replaced by a generic message instead of leaking its details.
     *
     * @return void
     */
    public function testShouldHideAndLogAnUnexpectedError(): void
    {
        $this->enableQuote(true);
        $this->quoteProduct->method('quoteByProductId')->willThrowException(new RuntimeException('SQLSTATE[42S02]'));
        $this->logger->expects($this->once())->method('critical');

        $this->subject->execute();

        $this->assertSame(
            ['error' => true, 'message' => 'Unable to quote the shipping right now. Please try again later.'],
            $this->payload
        );
    }

    /**
     * Configures the carrier as active with a token and toggles the product page quote.
     *
     * @param bool $enabled
     *
     * @return void
     */
    private function enableQuote(bool $enabled): void
    {
        $this->config->method('isActive')->willReturn(true);
        $this->config->method('getToken')->willReturn('a-frenet-token');
        $this->config->method('isProductQuoteEnabled')->willReturn($enabled);
    }
}
