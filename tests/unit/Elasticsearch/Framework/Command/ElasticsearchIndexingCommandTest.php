<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Elasticsearch\Framework\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Test\Stub\MessageBus\CollectingMessageBus;
use Contena\Elasticsearch\Framework\Command\ElasticsearchIndexingCommand;
use Contena\Elasticsearch\Framework\Indexing\CreateAliasTaskHandler;
use Contena\Elasticsearch\Framework\Indexing\ElasticsearchIndexer;
use Contena\Elasticsearch\Framework\Indexing\ElasticsearchIndexingMessage;
use Contena\Elasticsearch\Framework\Indexing\IndexerOffset;
use Contena\Elasticsearch\Framework\Indexing\IndexingDto;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[CoversClass(ElasticsearchIndexingCommand::class)]
class ElasticsearchIndexingCommandTest extends TestCase
{
    public function testExecute(): void
    {
        $oldIndexer = static::createStub(ElasticsearchIndexer::class);

        $bus = static::createStub(MessageBusInterface::class);
        $aliasHandler = $this->createMock(CreateAliasTaskHandler::class);
        $aliasHandler->expects($this->never())->method('run');

        $commandTester = new CommandTester(new ElasticsearchIndexingCommand($oldIndexer, $bus, $aliasHandler, true));
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();
    }

    public function testExecuteQueue(): void
    {
        $oldIndexer = static::createStub(ElasticsearchIndexer::class);

        $message = new ElasticsearchIndexingMessage(
            new IndexingDto([], 'blog', 'blog'),
            null,
            Context::createDefaultContext(),
            false
        );

        static::assertFalse($message->isLastMessage());
        $oldIndexer->method('iterate')->willReturnOnConsecutiveCalls(
            $message,
            null
        );

        $bus = static::createStub(MessageBusInterface::class);
        $aliasHandler = $this->createMock(CreateAliasTaskHandler::class);
        $aliasHandler->expects($this->once())->method('run');

        $commandTester = new CommandTester(new ElasticsearchIndexingCommand($oldIndexer, $bus, $aliasHandler, true));
        $commandTester->execute(['--no-queue' => true]);

        static::assertTrue($message->isLastMessage());
        $commandTester->assertCommandIsSuccessful();
    }

    public function testExecuteStreamsQueuedMessagesAndMarksOnlyTheLastMessage(): void
    {
        $messageBus = new CollectingMessageBus();
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
        $indexer->method('iterate')->willReturnCallback(
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

        $aliasHandler = $this->createMock(CreateAliasTaskHandler::class);
        $aliasHandler->expects($this->never())->method('run');

        $commandTester = new CommandTester(new ElasticsearchIndexingCommand($indexer, $messageBus, $aliasHandler, true));
        $commandTester->execute([]);

        static::assertSame(
            $messages,
            array_map(static fn (Envelope $envelope): object => $envelope->getMessage(), $messageBus->getMessages()),
        );
        static::assertFalse($messages[0]->isLastMessage());
        static::assertTrue($messages[1]->isLastMessage());
        $commandTester->assertCommandIsSuccessful();
    }

    public function testEsDisabled(): void
    {
        $oldIndexer = static::createStub(ElasticsearchIndexer::class);

        $bus = static::createStub(MessageBusInterface::class);
        $aliasHandler = $this->createMock(CreateAliasTaskHandler::class);
        $aliasHandler->expects($this->never())->method('run');

        $commandTester = new CommandTester(new ElasticsearchIndexingCommand($oldIndexer, $bus, $aliasHandler, false));
        $commandTester->execute(['--no-queue' => true], ['capture_stderr_separately' => true]);

        $output = $commandTester->getDisplay();

        static::assertStringContainsString('[ERROR] Elasticsearch indexing is disabled', $output);
    }

    public function testExecuteOnly(): void
    {
        $oldIndexer = static::createStub(ElasticsearchIndexer::class);

        $bus = static::createStub(MessageBusInterface::class);
        $aliasHandler = $this->createMock(CreateAliasTaskHandler::class);
        $aliasHandler->expects($this->never())->method('run');

        $commandTester = new CommandTester(new ElasticsearchIndexingCommand($oldIndexer, $bus, $aliasHandler, true));
        $commandTester->execute(['--only' => 'blog,category']);

        $commandTester->assertCommandIsSuccessful();
    }
}
