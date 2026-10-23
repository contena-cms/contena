<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Channel\Capability;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\System\Channel\Capability\AbstractChannelTypeCapabilities;

/**
 * @internal
 */
#[CoversClass(AbstractChannelTypeCapabilities::class)]
class AbstractChannelTypeCapabilitiesTest extends TestCase
{
    public function testATypeThatKeepsTheDefaultsIsNotDeclaredTransactional(): void
    {
        $capabilities = new class extends AbstractChannelTypeCapabilities {
            public function getChannelTypeId(): string
            {
                return Defaults::CHANNEL_TYPE_BLOG_COMPARISON;
            }
        };

        static::assertFalse($capabilities->isTransactional());
    }
}
