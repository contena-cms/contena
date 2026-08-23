<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\MessageQueue\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\DataAbstractionLayer\BlogIndexingMessage;
use Contena\Core\Content\Flow\Indexing\FlowIndexingMessage;
use Contena\Core\Content\Mail\Message\SendMailMessage;
use Contena\Core\Content\Media\Message\GenerateThumbnailsMessage;
use Contena\Core\Framework\Adapter\Cache\Message\RefreshHttpCacheMessage;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\MessageQueue\IterateEntityIndexerMessage;
use Contena\Core\Framework\MessageQueue\Telemetry\MessageGroupResolver;
use Contena\Core\Framework\Telemetry\Metrics\ScheduledTask\CollectPeriodicMetricsTask;
use Contena\Frontend\Theme\Message\CompileThemeMessage;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;

/**
 * @internal
 */
#[CoversClass(MessageGroupResolver::class)]
class MessageGroupResolverTest extends TestCase
{
    /**
     * Should correspond to metric definition in the telemetry.yaml
     */
    private const KNOWN_GROUPS = ['indexer', 'webhook', 'scheduled-task', 'mail', 'business', 'system', 'other'];

    #[DataProvider('messageProvider')]
    public function testResolve(string $messageClass, string $expected): void
    {
        $resolved = new MessageGroupResolver()->resolve($messageClass);
        static::assertSame($expected, $resolved);
        // Hard guard of the documented output set
        static::assertContains($resolved, self::KNOWN_GROUPS);
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function messageProvider(): \Generator
    {
        // scheduled tasks are resolved via the base class, so plugin tasks group too
        yield 'scheduled task' => [CollectPeriodicMetricsTask::class, 'scheduled-task'];

        // indexer - EntityIndexingMessage subclasses + indexer control messages
        yield 'blog indexing message' => [BlogIndexingMessage::class, 'indexer'];
        yield 'flow index rebuild is indexer, not flow' => [FlowIndexingMessage::class, 'indexer'];
        yield 'indexer iterate control message' => [IterateEntityIndexerMessage::class, 'indexer'];

        yield 'webhook delivery' => ['Contena\\Core\\Framework\\Webhook\\Message\\WebhookEventMessage', 'webhook'];

        // mail - Contena rendering message and the Symfony mailer transport send
        yield 'mail render message' => [SendMailMessage::class, 'mail'];
        yield 'symfony mailer transport send' => [SendEmailMessage::class, 'mail'];

        // general business-facing asynchronous work
        yield 'thumbnail generation' => [GenerateThumbnailsMessage::class, 'business'];
        yield 'import/export' => ['Contena\\Core\\Content\\ImportExport\\Message\\ImportExportMessage', 'business'];

        // system - framework infrastructure and housekeeping
        yield 'http cache refresh' => [RefreshHttpCacheMessage::class, 'system'];
        yield 'theme compile' => [CompileThemeMessage::class, 'system'];
        yield 'scheduled task registration' => ['Contena\\Core\\Framework\\MessageQueue\\ScheduledTask\\RegisterScheduledTaskMessage', 'system'];

        // unknown/plugin messages fall through to other
        yield 'unknown plugin message is other' => ['Ct\\Example\\Message\\DoThingMessage', 'other'];
    }
}
