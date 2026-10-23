<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\Manifest\Xml\Gateways;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\App\Manifest\Manifest;
use Contena\Core\Framework\App\Manifest\Xml\Gateway\InAppPurchasesGateway;

/**
 * @internal
 */
#[CoversClass(InAppPurchasesGateway::class)]
class InAppPurchasesGatewayTest extends TestCase
{
    public function testParse(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/testGateway/manifest.xml');

        static::assertNotNull($manifest->getGateways());

        $gateways = $manifest->getGateways();

        static::assertNotNull($gateways->getInAppPurchasesGateway());

        $inAppPurchasesFilter = $gateways->getInAppPurchasesGateway();
        static::assertSame('https://foo.bar/example/iap-filter', $inAppPurchasesFilter->getUrl());
    }
}
