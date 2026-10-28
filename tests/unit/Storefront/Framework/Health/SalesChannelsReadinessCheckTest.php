<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Storefront\Framework\Health;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext;
use Contena\Storefront\Framework\SystemCheck\SalesChannelsReadinessCheck;
use Contena\Storefront\Framework\SystemCheck\Util\AbstractSalesChannelDomainProvider;
use Contena\Storefront\Framework\SystemCheck\Util\SalesChannelDomainUtil;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SalesChannelsReadinessCheck::class)]
class SalesChannelsReadinessCheckTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();

        $this->salesChannelReadinessCheck = new SalesChannelsReadinessCheck(
            static::createStub(SalesChannelDomainUtil::class),
            static::createStub(AbstractSalesChannelDomainProvider::class)
        );
    }

    public function testOnlyAllowedToRunInReadinessContexts(): void
    {
        foreach (SystemCheckExecutionContext::cases() as $context) {
            if (\in_array($context, SystemCheckExecutionContext::readiness(), true)) {
                continue;
            }

            static::assertFalse($this->salesChannelReadinessCheck->allowedToRunIn($context));
        }

        foreach (SystemCheckExecutionContext::readiness() as $context) {
            static::assertTrue($this->salesChannelReadinessCheck->allowedToRunIn($context));
        }
    }
}
