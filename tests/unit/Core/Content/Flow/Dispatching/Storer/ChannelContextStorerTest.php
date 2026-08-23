<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Flow\Dispatching\Storer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Content\Flow\Dispatching\Storer\ChannelContextStorer;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ChannelContextAware;
use Contena\Core\Framework\Event\FlowEventAware;
use Contena\Core\Framework\Event\MailAware;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\AbstractChannelContextFactory;
use Contena\Tests\Unit\Core\Content\Flow\Dispatching\Storer\Stub\ChannelContextAwareEvent;

/**
 * @internal
 */
#[CoversClass(ChannelContextStorer::class)]
class ChannelContextStorerTest extends TestCase
{
    private AbstractChannelContextFactory&Stub $factory;

    private ChannelContextStorer $storer;

    protected function setUp(): void
    {
        $this->factory = static::createStub(AbstractChannelContextFactory::class);
        $this->storer = new ChannelContextStorer($this->factory);
    }

    public function testStoreWithNonAwareEventReturnsUnchanged(): void
    {
        $event = static::createStub(FlowEventAware::class);

        $stored = $this->storer->store($event, ['existing' => 'value']);

        static::assertSame(['existing' => 'value'], $stored);
    }

    public function testStoreWithAuthenticatedContext(): void
    {
        $channelContext = static::createStub(ChannelContext::class);
        $channelContext->method('getDomainId')->willReturn('domain-id');
        $channelContext->method('getMemberId')->willReturn('member-id');
        $event = new ChannelContextAwareEvent('channel-id', $channelContext);

        $stored = $this->storer->store($event, []);

        static::assertSame('channel-id', $stored[MailAware::CHANNEL_ID]);
        static::assertSame('domain-id', $stored[ChannelContextAware::CHANNEL_DOMAIN_ID]);
        static::assertSame('member-id', $stored[ChannelContextAware::CHANNEL_MEMBER_ID]);
    }

    public function testStoreWithAnonymousContextDoesNotStoreMemberId(): void
    {
        $channelContext = static::createStub(ChannelContext::class);
        $channelContext->method('getDomainId')->willReturn('domain-id');
        $channelContext->method('getMemberId')->willReturn(null);
        $event = new ChannelContextAwareEvent('channel-id', $channelContext);

        $stored = $this->storer->store($event, []);

        static::assertSame('channel-id', $stored[MailAware::CHANNEL_ID]);
        static::assertSame('domain-id', $stored[ChannelContextAware::CHANNEL_DOMAIN_ID]);
        static::assertArrayNotHasKey(ChannelContextAware::CHANNEL_MEMBER_ID, $stored);
    }

    public function testStoreWithNullDomainIdDoesNotStoreDomainId(): void
    {
        $channelContext = static::createStub(ChannelContext::class);
        $channelContext->method('getDomainId')->willReturn(null);
        $channelContext->method('getMemberId')->willReturn(null);
        $event = new ChannelContextAwareEvent('channel-id', $channelContext);

        $stored = $this->storer->store($event, []);

        static::assertSame('channel-id', $stored[MailAware::CHANNEL_ID]);
        static::assertArrayNotHasKey(ChannelContextAware::CHANNEL_DOMAIN_ID, $stored);
    }

    public function testRestoreWithoutChannelIdDoesNothing(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext(), []);

        $factory = $this->createMock(AbstractChannelContextFactory::class);
        $factory->expects($this->never())->method('create');
        $storer = new ChannelContextStorer($factory);

        $storer->restore($storable);

        static::assertNull($storable->getData(ChannelContextAware::CHANNEL_CONTEXT));
    }

    public function testRestoreSkipsReconstructionForAuthenticatedContext(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext(), [
            MailAware::CHANNEL_ID => 'channel-id',
            ChannelContextAware::CHANNEL_MEMBER_ID => 'member-id',
        ]);

        $factory = $this->createMock(AbstractChannelContextFactory::class);
        $factory->expects($this->never())->method('create');
        $storer = new ChannelContextStorer($factory);

        $storer->restore($storable);

        static::assertNull($storable->getData(ChannelContextAware::CHANNEL_CONTEXT));
    }

    public function testRestoreReconstructsAnonymousContextLazily(): void
    {
        $channelContext = static::createStub(ChannelContext::class);
        $this->factory->method('create')->willReturn($channelContext);

        $storable = new StorableFlow('name', Context::createDefaultContext(), [
            MailAware::CHANNEL_ID => 'channel-id',
            ChannelContextAware::CHANNEL_DOMAIN_ID => 'domain-id',
        ]);

        $this->storer->restore($storable);

        static::assertSame($channelContext, $storable->getData(ChannelContextAware::CHANNEL_CONTEXT));
    }
}
