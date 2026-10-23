<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\MailTemplate\Service\Event;

use Monolog\Level;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Content\Flow\Dispatching\Storer\ScalarValuesStorer;
use Contena\Core\Content\MailTemplate\Service\Event\MailBeforeValidateEvent;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
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
        $userId = Uuid::randomHex();

        $event = new MailBeforeValidateEvent(
            [
                'userId' => $userId,
            ],
            $context,
            [
                'admin_user' => 'admin',
                'recoveryUrl' => 'http://some-url.com',
                'resetUrl' => 'http://some-url.com',
                'eventName' => CheckoutOrderPlacedEvent::EVENT_NAME,
            ]
        );

        static::assertSame(Level::Info, $event->getLogLevel());
        static::assertSame('mail.before.send', $event->getName());
        static::assertSame($context, $event->getContext());
        static::assertSame([
            'userId' => $userId,
        ], $event->getData());
        static::assertSame([
            'admin_user' => 'admin',
            'recoveryUrl' => 'http://some-url.com',
            'resetUrl' => 'http://some-url.com',
            'eventName' => CheckoutOrderPlacedEvent::EVENT_NAME,
        ], $event->getTemplateData());
        static::assertSame([
            'data' => [
                'userId' => $userId,
            ],
            'eventName' => CheckoutOrderPlacedEvent::EVENT_NAME,
            'templateData' => [
                'admin_user' => 'admin',
                'recoveryUrl' => 'http://some-url.com',
                'resetUrl' => 'http://some-url.com',
                'eventName' => CheckoutOrderPlacedEvent::EVENT_NAME,
            ],
        ], $event->getLogData());
    }

    public function testDataAndTemplateDataCanBeAmended(): void
    {
        $event = new MailBeforeValidateEvent(['foo' => 'bar'], Context::createDefaultContext());

        $event->addData('baz', 'qux');
        $event->setTemplateData(['template' => 'data']);
        $event->addTemplateData('extra', 'value');

        static::assertSame(['foo' => 'bar', 'baz' => 'qux'], $event->getData());
        static::assertSame(['template' => 'data', 'extra' => 'value'], $event->getTemplateData());

        $event->setData(['replaced' => true]);

        static::assertSame(['replaced' => true], $event->getData());
    }

    public function testAvailableDataDescribesTheFlowPayload(): void
    {
        static::assertSame(['data', 'templateData'], array_keys(MailBeforeValidateEvent::getAvailableData()->toArray()));
    }
}
