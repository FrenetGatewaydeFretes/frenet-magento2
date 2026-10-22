<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\Test\Unit\Model\Labels;

use Frenet\Shipping\Model\Labels\CarrierLogo;
use PHPUnit\Framework\TestCase;

class CarrierLogoTest extends TestCase
{
    /** @var CarrierLogo */
    private CarrierLogo $logo;

    protected function setUp(): void
    {
        $this->logo = new CarrierLogo();
    }

    public function testKnowsFrenetCodesAndGuessesFromNames(): void
    {
        self::assertSame('JTE', $this->logo->code('JTE', 'J&T Express'));
        self::assertSame('JTE', $this->logo->code('jte', ''));
        self::assertSame('JTE', $this->logo->code('JT_INT', 'J&T Express'));
        self::assertSame('COR', $this->logo->code('', 'Correios'));
        self::assertSame('TOT', $this->logo->code('TOTAL', 'Total Express'));
        self::assertSame('LAT', $this->logo->code('LATAM', 'LATAM Cargo'));
        self::assertSame('XYZ', $this->logo->code('xyz', 'Nova Transportadora'));
    }

    public function testOnlyBundledLogosHaveAFileAndTheFilesExist(): void
    {
        self::assertSame('Frenet_Shipping::images/carriers/cor.svg', $this->logo->file('COR'));
        self::assertNull($this->logo->file('XYZ'));
        $web = dirname(__DIR__, 4) . '/view/adminhtml/web/';
        foreach ($this->logo->files() as $code => $asset) {
            $path = $web . substr($asset, strlen('Frenet_Shipping::'));
            self::assertFileExists($path, $code . ' logo is bundled');
        }
    }

    public function testMonogramIsStableAndReadable(): void
    {
        self::assertSame('NT', $this->logo->initials('Nova Transportadora', 'XYZ'));
        self::assertSame('XY', $this->logo->initials('', 'XYZ'));
        self::assertSame('JE', $this->logo->initials('J&T Express', 'JTE'));
        self::assertSame('#1f8a4c', $this->logo->color('BUS'));
        self::assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $this->logo->color('XYZ'));
        self::assertSame($this->logo->color('XYZ'), $this->logo->color('XYZ'));
        self::assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $this->logo->color(''));
    }
}
