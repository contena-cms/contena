<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\MailTemplate\Service\Event;

use Monolog\Level;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\MailTemplate\Service\Event\MailErrorEvent;
use Contena\Core\Framework\Context;

/**
 * @internal
 */
#[CoversClass(MailErrorEvent::class)]
class MailErrorEventTest extends TestCase
{
    public function testInstantiate(): void
    {
        $exception = new \Exception('exception');
        $context = Context::createDefaultContext();

        $event = new MailErrorEvent(
            $context,
            Level::Error,
            $exception,
            'Test',
            '{{ subject }}',
            [
                'eventName' => 'content.item.updated',
                'platformName' => 'Contena',
            ],
        );

        static::assertSame('Test', $event->getMessage());
        static::assertSame(Level::Error, $event->getLogLevel());
        static::assertSame([
            'exception' => (string) $exception,
            'message' => 'Test',
            'template' => '{{ subject }}',
            'eventName' => 'content.item.updated',
            'templateData' => [
                'eventName' => 'content.item.updated',
                'platformName' => 'Contena',
            ],
        ], $event->getLogData());
        static::assertSame('mail.sent.error', $event->getName());
        static::assertSame($context, $event->getContext());
        static::assertSame($exception, $event->getThrowable());
    }
}
