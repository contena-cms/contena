<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Channel\Capability;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Capability\AbstractChannelTypeCapabilities;
use Contena\Core\System\Channel\Capability\ChannelTypeCapabilityRegistry;

/**
 * @internal
 */
#[CoversClass(ChannelTypeCapabilityRegistry::class)]
class ChannelTypeCapabilityRegistryTest extends TestCase
{
    public function testListsTheTransactionalCoreTypesWithoutRegistrations(): void
    {
        $registry = new ChannelTypeCapabilityRegistry([]);

        static::assertSame(
            [Defaults::CHANNEL_TYPE_FRONTEND, Defaults::CHANNEL_TYPE_API],
            $registry->getTransactionalTypeIds()
        );
    }

    public function testAddsRegisteredTransactionalTypesOnce(): void
    {
        $pluginTypeId = Uuid::randomHex();

        $registry = new ChannelTypeCapabilityRegistry([
            $this->createCapabilities($pluginTypeId, true),
            $this->createCapabilities(Uuid::randomHex(), false),
            $this->createCapabilities(Defaults::CHANNEL_TYPE_FRONTEND, true),
        ]);

        static::assertSame(
            [Defaults::CHANNEL_TYPE_FRONTEND, Defaults::CHANNEL_TYPE_API, $pluginTypeId],
            $registry->getTransactionalTypeIds()
        );
    }

    public function testARegistrationCannotTakeTransactionalAwayFromACoreType(): void
    {
        $registry = new ChannelTypeCapabilityRegistry([
            $this->createCapabilities(Defaults::CHANNEL_TYPE_FRONTEND, false),
        ]);

        static::assertTrue($registry->isTransactional(Defaults::CHANNEL_TYPE_FRONTEND));
    }

    public function testUnknownAndFeedTypesAreNotTransactional(): void
    {
        $registry = new ChannelTypeCapabilityRegistry([]);

        static::assertFalse($registry->isTransactional(Defaults::CHANNEL_TYPE_BLOG_COMPARISON));
        static::assertFalse($registry->isTransactional(Uuid::randomHex()));
    }

    private function createCapabilities(string $channelTypeId, bool $isDeclaredTransactional): AbstractChannelTypeCapabilities
    {
        return new class($channelTypeId, $isDeclaredTransactional) extends AbstractChannelTypeCapabilities {
            public function __construct(
                private readonly string $channelTypeId,
                private readonly bool $isDeclaredTransactional,
            ) {
            }

            public function getChannelTypeId(): string
            {
                return $this->channelTypeId;
            }

            public function isTransactional(): bool
            {
                return $this->isDeclaredTransactional;
            }
        };
    }
}
