<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Channel\File;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Adapter\Cache\CacheInvalidator;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\Framework\Plugin\Event\PluginPostActivateEvent;
use Contena\Core\Framework\Plugin\Event\PluginPostDeactivateEvent;
use Contena\Core\Framework\Plugin\Event\PluginPostUpdateEvent;
use Contena\Core\Framework\Update\Event\UpdatePostFinishEvent;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelException;
use Contena\Core\System\Channel\File\ChannelFileCacheInvalidator;

/**
 * @internal
 */
#[CoversClass(ChannelFileCacheInvalidator::class)]
class ChannelFileCacheInvalidatorTest extends TestCase
{
    public function testItInvalidatesChannelFileIdTagsForWrites(): void
    {
        $firstId = Uuid::randomHex();
        $secondId = Uuid::randomHex();
        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator
            ->expects($this->once())
            ->method('invalidate')
            ->with([
                ChannelFileCacheInvalidator::buildCacheTag($firstId),
                ChannelFileCacheInvalidator::buildCacheTag($secondId),
            ], true);

        $event = new EntityWrittenEvent('channel_file', [
            new EntityWriteResult($firstId, [
                'channelId' => Uuid::randomHex(),
                'fileFamily' => 'agentic',
                'fileName' => 'llms.txt',
            ], 'channel_file', EntityWriteResult::OPERATION_UPDATE),
            new EntityWriteResult($secondId, [], 'channel_file', EntityWriteResult::OPERATION_UPDATE),
        ], Context::createDefaultContext());

        new ChannelFileCacheInvalidator($cacheInvalidator)->invalidate($event);
    }

    public function testItInvalidatesChannelFileIdTagsForDeletes(): void
    {
        $id = Uuid::randomHex();
        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator
            ->expects($this->once())
            ->method('invalidate')
            ->with([ChannelFileCacheInvalidator::buildCacheTag($id)], true);

        $event = new EntityDeletedEvent('channel_file', [
            new EntityWriteResult($id, [], 'channel_file', EntityWriteResult::OPERATION_DELETE),
        ], Context::createDefaultContext());

        new ChannelFileCacheInvalidator($cacheInvalidator)->invalidate($event);
    }

    public function testItThrowsOnCombinedPrimaryKey(): void
    {
        $entityName = 'entity_with_combined_primary_key';
        $event = new EntityWrittenEvent($entityName, [
            new EntityWriteResult(
                ['firstId' => Uuid::randomHex(), 'secondId' => Uuid::randomHex()],
                [],
                $entityName,
                EntityWriteResult::OPERATION_UPDATE
            ),
        ], Context::createDefaultContext());

        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator->expects($this->never())->method('invalidate');

        $this->expectExceptionObject(ChannelException::unexpectedCombinedPrimaryKey($entityName));

        new ChannelFileCacheInvalidator($cacheInvalidator)->invalidate($event);
    }

    public function testItBuildsChannelFileIdCacheTag(): void
    {
        static::assertSame('channel-file-example-id', ChannelFileCacheInvalidator::buildCacheTag('example-id'));
    }

    public function testItInvalidatesDiscoveryTag(): void
    {
        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator
            ->expects($this->once())
            ->method('invalidate')
            ->with([ChannelFileCacheInvalidator::buildDiscoveryCacheTag()], true);

        new ChannelFileCacheInvalidator($cacheInvalidator)->invalidateDiscovery();
    }

    public function testItSubscribesToTemplateDiscoveryInvalidationEvents(): void
    {
        $events = ChannelFileCacheInvalidator::getSubscribedEvents();

        foreach ([
            PluginPostActivateEvent::class,
            PluginPostDeactivateEvent::class,
            PluginPostUpdateEvent::class,
            UpdatePostFinishEvent::class,
        ] as $event) {
            static::assertSame('invalidateDiscovery', $events[$event]);
        }
    }
}
