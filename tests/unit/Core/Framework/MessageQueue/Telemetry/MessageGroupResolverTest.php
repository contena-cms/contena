<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\MessageQueue\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Cleanup\CleanupCartTask;
use Contena\Core\Content\Flow\Indexing\FlowIndexingMessage;
use Contena\Core\Content\ImportExport\Message\ImportExportMessage;
use Contena\Core\Content\Mail\Message\SendMailMessage;
use Contena\Core\Content\Media\Message\GenerateThumbnailsMessage;
use Contena\Core\Content\Product\DataAbstractionLayer\ProductIndexingMessage;
use Contena\Core\Framework\Adapter\Cache\Message\RefreshHttpCacheMessage;
use Contena\Core\Framework\App\Message\RotateAppSecretMessage;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\MessageQueue\IterateEntityIndexerMessage;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\MessageQueue\ScheduledTask\RegisterScheduledTaskMessage;
use Contena\Core\Framework\MessageQueue\Telemetry\MessageGroupResolver;
use Contena\Core\Framework\Webhook\Message\WebhookEventMessage;
use Contena\Core\Framework\Webhook\ScheduledTask\CleanupWebhookEventLogTask;
use Contena\Core\System\UsageData\EntitySync\DispatchEntityMessage;
use Contena\Storefront\Theme\Message\CompileThemeMessage;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MessageGroupResolver::class)]
class MessageGroupResolverTest extends TestCase
{
    /**
     * Should correspond to metric definition in the tememetry.yaml
     */
    private const KNOWN_GROUPS = ['indexer', 'webhook', 'scheduled-task', 'mail', 'business', 'system', 'other'];

    #[DataProvider('messageProvider')]
    public function testResolve(string $messageClass, string $expected): void
    {
        $resolved = (new MessageGroupResolver())->resolve($messageClass);
        static::assertSame($expected, $resolved);
        // Hard guard of the documented output set
        static::assertContains($resolved, self::KNOWN_GROUPS);
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function messageProvider(): \Generator
    {
        // scheduled tasks — resolved via the ScheduledTask base class, so plugin tasks group too
        yield 'scheduled task' => [CleanupCartTask::class, 'scheduled-task'];
        // a task living under Framework\Webhook must stay a scheduled-task, not fall to the webhook fragment
        yield 'webhook cleanup task is scheduled-task, not webhook' => [CleanupWebhookEventLogTask::class, 'scheduled-task'];

        // indexer — EntityIndexingMessage subclasses + indexer control messages
        yield 'entity indexing message' => [ProductIndexingMessage::class, 'indexer'];
        yield 'flow index rebuild is indexer, not flow' => [FlowIndexingMessage::class, 'indexer'];
        yield 'indexer iterate control message' => [IterateEntityIndexerMessage::class, 'indexer'];

        yield 'webhook delivery' => [WebhookEventMessage::class, 'webhook'];

        // mail — Contena rendering message and the Symfony mailer transport send
        yield 'mail render message' => [SendMailMessage::class, 'mail'];
        yield 'symfony mailer transport send' => [SendEmailMessage::class, 'mail'];

        // business — customer-/catalog-facing async work
        yield 'thumbnail generation' => [GenerateThumbnailsMessage::class, 'business'];
        yield 'import/export' => [ImportExportMessage::class, 'business'];

        // system — framework infrastructure & housekeeping
        yield 'http cache refresh' => [RefreshHttpCacheMessage::class, 'system'];
        yield 'app secret rotation' => [RotateAppSecretMessage::class, 'system'];
        yield 'theme compile' => [CompileThemeMessage::class, 'system'];
        yield 'scheduled task registration' => [RegisterScheduledTaskMessage::class, 'system'];
        yield 'usage data sync' => [DispatchEntityMessage::class, 'system'];

        // unknown/plugin messages fall through to other
        yield 'unknown plugin message is other' => ['Swag\\Example\\Message\\DoThingMessage', 'other'];
    }
}
