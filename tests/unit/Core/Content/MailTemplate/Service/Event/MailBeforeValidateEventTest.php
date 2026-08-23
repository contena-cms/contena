<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\MailTemplate\Service\Event;

use Monolog\Level;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Content\Flow\Dispatching\Storer\ScalarValuesStorer;
use Contena\Core\Content\MailTemplate\Service\Event\MailBeforeValidateEvent;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(MailBeforeValidateEvent::class)]
class MailBeforeValidateEventTest extends TestCase
{
    public function testScalarValuesCorrectly(): void
    {
        $event = new MailBeforeValidateEvent(
            ['foo' => 'bar'],
            Context::createDefaultContext(),
            ['template' => 'data'],
        );

        $storer = new ScalarValuesStorer();

        $stored = $storer->store($event, []);

        $flow = new StorableFlow('foo', Context::createDefaultContext(), $stored);

        $storer->restore($flow);

        static::assertArrayHasKey('data', $flow->data());
        static::assertArrayHasKey('templateData', $flow->data());
        static::assertSame(['foo' => 'bar'], $flow->data()['data']);
        static::assertSame(['template' => 'data'], $flow->data()['templateData']);
    }

    public function testInstantiate(): void
    {
        $context = Context::createDefaultContext();
        $memberId = Uuid::randomHex();

        $event = new MailBeforeValidateEvent(
            [
                'memberId' => $memberId,
            ],
            $context,
            [
                'user' => 'admin',
                'recoveryUrl' => 'http://some-url.com',
                'resetUrl' => 'http://some-url.com',
                'eventName' => 'content.item.updated',
            ]
        );

        static::assertSame(Level::Info, $event->getLogLevel());
        static::assertSame('mail.before.send', $event->getName());
        static::assertSame($context, $event->getContext());
        static::assertSame([
            'memberId' => $memberId,
        ], $event->getData());
        static::assertSame([
            'user' => 'admin',
            'recoveryUrl' => 'http://some-url.com',
            'resetUrl' => 'http://some-url.com',
            'eventName' => 'content.item.updated',
        ], $event->getTemplateData());
        static::assertSame([
            'data' => [
                'memberId' => $memberId,
            ],
            'eventName' => 'content.item.updated',
            'templateData' => [
                'user' => 'admin',
                'recoveryUrl' => 'http://some-url.com',
                'resetUrl' => 'http://some-url.com',
                'eventName' => 'content.item.updated',
            ],
        ], $event->getLogData());
    }
}
