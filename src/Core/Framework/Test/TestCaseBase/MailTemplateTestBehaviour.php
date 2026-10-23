<?php declare(strict_types=1);

namespace Contena\Core\Framework\Test\TestCaseBase;

use Contena\Core\Framework\Event\ContenaEvent;
use Contena\Core\Framework\Event\EventData\MailRecipientStruct;
use Contena\Core\Framework\Event\MailAware;
use Contena\Core\Framework\Test\TestCaseBase\EventDispatcherBehaviour;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\Event;

trait MailTemplateTestBehaviour
{
    use EventDispatcherBehaviour;

    /**
     * @param class-string<object> $expectedClass
     */
    public static function assertMailEvent(
        string $expectedClass,
        ContenaEvent $event,
        ChannelContext $channelContext
    ): void {
        TestCase::assertInstanceOf($expectedClass, $event);
        TestCase::assertSame($channelContext->getContext(), $event->getContext());
    }

    public static function assertMailRecipientStructEvent(MailRecipientStruct $expectedStruct, MailAware $event): void
    {
        TestCase::assertSame($expectedStruct->getRecipients(), $event->getMailStruct()->getRecipients());
    }

    /**
     * @template TEvent of Event
     *
     * @param class-string<TEvent> $eventName
     * @param TEvent|null $eventResult
     */
    protected function catchEvent(string $eventName, ?object &$eventResult): void
    {
        EventHookDispatcher::fromContainer(static::getContainer())->on($eventName, static function (object $event) use (&$eventResult): void {
            $eventResult = $event;
        });
    }
}
