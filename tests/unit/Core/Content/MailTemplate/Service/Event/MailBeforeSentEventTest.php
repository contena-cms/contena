<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\MailTemplate\Service\Event;

use Monolog\Level;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Content\Flow\Dispatching\Storer\ScalarValuesStorer;
use Contena\Core\Content\MailTemplate\Service\Event\MailBeforeSentEvent;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * @internal
 */
#[CoversClass(MailBeforeSentEvent::class)]
class MailBeforeSentEventTest extends TestCase
{
    public function testScalarValuesCorrectly(): void
    {
        $event = new MailBeforeSentEvent(
            ['foo' => 'bar'],
            new Email(),
            Context::createDefaultContext()
        );

        $storer = new ScalarValuesStorer();

        $stored = $storer->store($event, []);

        $flow = new StorableFlow('foo', Context::createDefaultContext(), $stored);

        $storer->restore($flow);

        static::assertArrayHasKey('data', $flow->data());
        static::assertSame(['foo' => 'bar'], $flow->data()['data']);
    }

    public function testInstantiate(): void
    {
        $context = Context::createDefaultContext();
        $userId = Uuid::randomHex();
        $email = (new Email())->subject('test subject')
            ->html('content html')
            ->text('content plain')
            ->to('test@contena.cn')
            ->from(new Address('test@contena.cn'));

        $event = new MailBeforeSentEvent(
            [
                'userId' => $userId,
            ],
            $email,
            $context,
            CheckoutOrderPlacedEvent::EVENT_NAME
        );

        static::assertSame(Level::Info, $event->getLogLevel());
        static::assertSame('mail.after.create.message', $event->getName());
        static::assertSame($context, $event->getContext());
        static::assertSame([
            'userId' => $userId,
        ], $event->getData());
        static::assertSame([
            'data' => [
                'userId' => $userId,
            ],
            'eventName' => CheckoutOrderPlacedEvent::EVENT_NAME,
            'message' => $email,
        ], $event->getLogData());
        static::assertSame($email, $event->getMessage());
    }

    public function testAvailableDataDescribesTheFlowPayload(): void
    {
        static::assertSame(['data', 'message'], array_keys(MailBeforeSentEvent::getAvailableData()->toArray()));
    }
}
