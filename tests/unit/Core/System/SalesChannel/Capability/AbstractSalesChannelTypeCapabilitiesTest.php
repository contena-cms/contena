<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\SalesChannel\Capability;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\Capability\AbstractSalesChannelTypeCapabilities;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(AbstractSalesChannelTypeCapabilities::class)]
class AbstractSalesChannelTypeCapabilitiesTest extends TestCase
{
    public function testATypeThatKeepsTheDefaultsIsNotDeclaredTransactional(): void
    {
        $capabilities = new class extends AbstractSalesChannelTypeCapabilities {
            public function getSalesChannelTypeId(): string
            {
                return Defaults::SALES_CHANNEL_TYPE_PRODUCT_COMPARISON;
            }
        };

        static::assertFalse($capabilities->isTransactional());
    }
}
