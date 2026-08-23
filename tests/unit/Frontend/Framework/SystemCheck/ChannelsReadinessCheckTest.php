<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Framework\SystemCheck;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext;
use Contena\Frontend\Framework\SystemCheck\ChannelsReadinessCheck;
use Contena\Frontend\Framework\SystemCheck\Util\AbstractChannelDomainProvider;
use Contena\Frontend\Framework\SystemCheck\Util\ChannelDomainUtil;

/**
 * @internal
 */
#[CoversClass(ChannelsReadinessCheck::class)]
class ChannelsReadinessCheckTest extends TestCase
{
    private ChannelsReadinessCheck $channelsReadinessCheck;

    protected function setUp(): void
    {
        $this->channelsReadinessCheck = new ChannelsReadinessCheck(
            static::createStub(ChannelDomainUtil::class),
            static::createStub(AbstractChannelDomainProvider::class)
        );
    }

    public function testOnlyAllowedToRunInReadinessContexts(): void
    {
        foreach (SystemCheckExecutionContext::cases() as $context) {
            if (\in_array($context, SystemCheckExecutionContext::readiness(), true)) {
                continue;
            }

            static::assertFalse($this->channelsReadinessCheck->allowedToRunIn($context));
        }

        foreach (SystemCheckExecutionContext::readiness() as $context) {
            static::assertTrue($this->channelsReadinessCheck->allowedToRunIn($context));
        }
    }
}
