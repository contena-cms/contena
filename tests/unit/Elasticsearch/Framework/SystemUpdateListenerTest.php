<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Elasticsearch\Framework;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Adapter\Storage\AbstractKeyValueStorage;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Update\Event\UpdatePostFinishEvent;
use Contena\Core\Test\Stub\MessageBus\CollectingMessageBus;
use Contena\Elasticsearch\Framework\Indexing\ElasticsearchIndexer;
use Contena\Elasticsearch\Framework\Indexing\ElasticsearchIndexingMessage;
use Contena\Elasticsearch\Framework\Indexing\IndexerOffset;
use Contena\Elasticsearch\Framework\Indexing\IndexingDto;
use Contena\Elasticsearch\Framework\Indexing\IndexMappingUpdater;
use Contena\Elasticsearch\Framework\SystemUpdateListener;
use Symfony\Component\Messenger\Envelope;

/**
 * @internal
 */
#[CoversClass(SystemUpdateListener::class)]
class SystemUpdateListenerTest extends TestCase
{
    public function testShouldDoNothingWhenNotSet(): void
    {
        $messageBus = new CollectingMessageBus();

        $mappingUpdater = $this->createMock(IndexMappingUpdater::class);
        $mappingUpdater
            ->expects($this->once())
            ->method('update');

        $listener = new SystemUpdateListener(
            static::createStub(AbstractKeyValueStorage::class),
            static::createStub(ElasticsearchIndexer::class),
            $messageBus,
            $mappingUpdater
        );

        $listener(static::createStub(UpdatePostFinishEvent::class));

        static::assertCount(0, $messageBus->getMessages());
    }

    public function testShouldScheduleWithValues(): void
    {
        $messageBus = new CollectingMessageBus();

        $mappingUpdater = $this->createMock(IndexMappingUpdater::class);
        $mappingUpdater
            ->expects($this->once())
            ->method('update');

        $storage = $this->createMock(AbstractKeyValueStorage::class);
        $storage
            ->method('get')
            ->willReturn(['*']);
        $storage->expects($this->once())->method('remove')->with(SystemUpdateListener::CONFIG_KEY);

        $messages = [
            new ElasticsearchIndexingMessage(
                new IndexingDto(['first'], 'blog', 'blog'),
                static::createStub(IndexerOffset::class),
                Context::createGlobalContext(),
            ),
            new ElasticsearchIndexingMessage(
                new IndexingDto(['second'], 'blog', 'blog'),
                static::createStub(IndexerOffset::class),
                Context::createGlobalContext(),
            ),
        ];
        $iteration = 0;

        $indexer = static::createStub(ElasticsearchIndexer::class);
        $indexer
            ->method('iterate')
            ->willReturnCallback(
                static function () use (&$iteration, $messageBus, $messages): ?ElasticsearchIndexingMessage {
                    $message = $messages[$iteration++] ?? null;
                    if ($message === null) {
                        static::assertSame(
                            [$messages[0]],
                            array_map(static fn (Envelope $envelope): object => $envelope->getMessage(), $messageBus->getMessages()),
                        );

                        return null;
                    }

                    return $message;
                },
            );

        $listener = new SystemUpdateListener(
            $storage,
            $indexer,
            $messageBus,
            $mappingUpdater
        );

        $listener(static::createStub(UpdatePostFinishEvent::class));

        static::assertSame(
            $messages,
            array_map(static fn (Envelope $envelope): object => $envelope->getMessage(), $messageBus->getMessages()),
        );
        static::assertFalse($messages[0]->isLastMessage());
        static::assertTrue($messages[1]->isLastMessage());
    }
}
