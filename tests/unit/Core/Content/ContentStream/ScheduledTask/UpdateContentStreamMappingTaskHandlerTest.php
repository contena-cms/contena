<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\ContentStream\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Contena\Core\Content\Blog\DataAbstractionLayer\ContentStreamMappingIndexingMessage;
use Contena\Core\Content\Blog\DataAbstractionLayer\ContentStreamUpdater;
use Contena\Core\Content\ContentStream\ContentStreamCollection;
use Contena\Core\Content\ContentStream\ScheduledTask\UpdateBlogStreamMappingTaskHandler;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[CoversClass(UpdateBlogStreamMappingTaskHandler::class)]
class UpdateBlogStreamMappingTaskHandlerTest extends TestCase
{
    public function testRunTouchesStreamsAndDispatchesIndexingMessages(): void
    {
        $streamIds = ['stream-until', 'stream-since'];

        /** @var StaticEntityRepository<ContentStreamCollection> $contentStreamRepository */
        $contentStreamRepository = new StaticEntityRepository([$streamIds]);

        $dispatched = [];
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static function (object $message) use (&$dispatched): Envelope {
                $dispatched[] = $message;

                return new Envelope($message);
            });

        $handler = new UpdateBlogStreamMappingTaskHandler(
            static::createStub(EntityRepository::class),
            static::createStub(LoggerInterface::class),
            $contentStreamRepository,
            $messageBus,
        );

        $handler->run();

        static::assertSame(
            [[['id' => 'stream-until'], ['id' => 'stream-since']]],
            $contentStreamRepository->updates,
            'expected scheduled task to touch stream rows so cache invalidation subscribers fire',
        );

        static::assertCount(2, $dispatched);
        foreach ($dispatched as $index => $message) {
            static::assertInstanceOf(ContentStreamMappingIndexingMessage::class, $message);
            static::assertSame($streamIds[$index], $message->getData());
            static::assertSame(ContentStreamUpdater::INDEXER_NAME, $message->getIndexer());
        }
    }

    public function testRunDoesNothingWhenNoStreamsMatch(): void
    {
        /** @var StaticEntityRepository<ContentStreamCollection> $contentStreamRepository */
        $contentStreamRepository = new StaticEntityRepository([[]]);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->never())->method('dispatch');

        $handler = new UpdateBlogStreamMappingTaskHandler(
            static::createStub(EntityRepository::class),
            static::createStub(LoggerInterface::class),
            $contentStreamRepository,
            $messageBus,
        );

        $handler->run();

        static::assertSame([], $contentStreamRepository->updates);
    }
}
