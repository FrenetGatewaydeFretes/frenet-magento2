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

namespace Frenet\Shipping\Test\Unit\Model\Catalog\Product\View;

use Frenet\Shipping\Model\Catalog\Product\View\QuoteRateLimiter;
use Frenet\Shipping\Model\ConfigInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests the per-visitor and per-website product quote limits over an in-memory cache and a controllable clock.
 */
#[AllowMockObjectsWithoutExpectations]
class QuoteRateLimiterTest extends TestCase
{
    /**
     * @var string[]
     */
    private array $cached = [];

    /**
     * @var int[]
     */
    private array $lifetimes = [];

    /**
     * @var int
     */
    private int $now = 1_800_000_000;

    /**
     * @var int
     */
    private int $websiteId = 1;

    /**
     * @var int
     */
    private int $visitorLimit = 3;

    /**
     * @var int
     */
    private int $storeLimit = 100;

    /**
     * @var LoggerInterface&MockObject
     */
    private MockObject $logger;

    /**
     * @var QuoteRateLimiter
     */
    private QuoteRateLimiter $subject;

    /**
     * Builds the limiter over a cache kept in an array, the configured limits and a clock the tests move.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('load')->willReturnCallback(fn (string $key) => $this->cached[$key] ?? false);
        $cache->method('save')->willReturnCallback(
            function (string $value, string $key, array $tags, int $lifetime): bool {
                $this->cached[$key] = $value;
                $this->lifetimes[$key] = $lifetime;

                return true;
            }
        );

        $config = $this->createMock(ConfigInterface::class);
        $config->method('getProductQuoteVisitorLimit')->willReturnCallback(fn () => $this->visitorLimit);
        $config->method('getProductQuoteStoreLimit')->willReturnCallback(fn () => $this->storeLimit);

        $store = $this->createMock(StoreInterface::class);
        $store->method('getWebsiteId')->willReturnCallback(fn () => $this->websiteId);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $dateTime = $this->createMock(DateTime::class);
        $dateTime->method('gmtTimestamp')->willReturnCallback(fn () => $this->now);

        $this->logger = $this->createMock(LoggerInterface::class);

        $this->subject = new QuoteRateLimiter($cache, $config, $storeManager, $dateTime, $this->logger);
    }

    /**
     * Confirms a visitor gets exactly the configured number of quotes in a minute and is refused after that.
     *
     * @return void
     */
    public function testShouldRefuseTheVisitorAfterTheLimitWithinTheSameMinute(): void
    {
        $this->assertSame([true, true, true, false, false], $this->quotes('203.0.113.5', 5));
    }

    /**
     * Confirms a refused visitor can quote again in the next minute, so a block never lasts longer than one minute.
     *
     * @return void
     */
    public function testShouldAllowTheVisitorAgainInTheNextMinute(): void
    {
        $this->quotes('203.0.113.5', 4);
        $this->now += 60;

        $this->assertTrue($this->subject->isAllowed('203.0.113.5'));
    }

    /**
     * Confirms each visitor has an own counter, so one abusive visitor does not block the others.
     *
     * @return void
     */
    public function testShouldCountEachVisitorSeparately(): void
    {
        $this->quotes('203.0.113.5', 4);

        $this->assertTrue($this->subject->isAllowed('198.51.100.7'));
    }

    /**
     * Confirms the store limit refuses quotes from new visitors once the website reached it in the minute.
     *
     * @return void
     */
    public function testShouldRefuseEveryVisitorOnceTheStoreLimitIsReached(): void
    {
        $this->storeLimit = 2;

        $this->assertTrue($this->subject->isAllowed('203.0.113.1'));
        $this->assertTrue($this->subject->isAllowed('203.0.113.2'));
        $this->assertFalse($this->subject->isAllowed('203.0.113.3'));
    }

    /**
     * Confirms quotes refused by the visitor limit do not use up the store limit of the other visitors.
     *
     * @return void
     */
    public function testShouldNotSpendTheStoreLimitOnQuotesRefusedByTheVisitorLimit(): void
    {
        $this->storeLimit = 4;

        $this->quotes('203.0.113.5', 10);

        $this->assertTrue($this->subject->isAllowed('198.51.100.7'));
    }

    /**
     * Confirms each website has an own store counter, since each one can use another Frenet account.
     *
     * @return void
     */
    public function testShouldCountTheStoreLimitPerWebsite(): void
    {
        $this->storeLimit = 1;
        $this->subject->isAllowed('203.0.113.1');
        $this->websiteId = 2;

        $this->assertTrue($this->subject->isAllowed('203.0.113.2'));
    }

    /**
     * Confirms a limit of 0 turns it off while the other limit keeps working.
     *
     * @return void
     */
    public function testShouldNotLimitWhenTheLimitIsZero(): void
    {
        $this->visitorLimit = 0;
        $this->storeLimit = 5;

        $this->assertSame([true, true, true, true, true, false], $this->quotes('203.0.113.5', 6));
    }

    /**
     * Confirms nothing is counted or stored when both limits are off.
     *
     * @return void
     */
    public function testShouldNotTouchTheCacheWhenBothLimitsAreOff(): void
    {
        $this->visitorLimit = 0;
        $this->storeLimit = 0;

        $this->assertSame([true, true], $this->quotes('203.0.113.5', 2));
        $this->assertSame([], $this->cached);
    }

    /**
     * Confirms the counters expire on their own after two minutes, so no cleanup is needed.
     *
     * @return void
     */
    public function testShouldStoreTheCountersForTwoMinutes(): void
    {
        $this->subject->isAllowed('203.0.113.5');

        $this->assertSame([120, 120], array_values($this->lifetimes));
    }

    /**
     * Confirms the visitor address is hashed in the cache key instead of being stored as it is.
     *
     * @return void
     */
    public function testShouldNotKeepTheVisitorAddressInTheCacheKey(): void
    {
        $this->subject->isAllowed('203.0.113.5');

        $this->assertStringNotContainsString('203.0.113.5', implode(' ', array_keys($this->cached)));
    }

    /**
     * Confirms only the first refused quote of a minute is logged, so a flood does not flood the log too.
     *
     * @return void
     */
    public function testShouldLogTheLimitOnlyOncePerMinute(): void
    {
        $this->logger->expects($this->once())
            ->method('warning')
            ->with('Frenet product quote limit of 3 per minute reached by visitor 203.0.113.5.');

        $this->quotes('203.0.113.5', 8);
    }

    /**
     * Asks for the given number of quotes for a visitor and returns whether each one was allowed.
     *
     * @param string $visitor
     * @param int $times
     *
     * @return bool[]
     */
    private function quotes(string $visitor, int $times): array
    {
        $results = [];

        for ($quote = 0; $quote < $times; $quote++) {
            $results[] = $this->subject->isAllowed($visitor);
        }

        return $results;
    }
}
