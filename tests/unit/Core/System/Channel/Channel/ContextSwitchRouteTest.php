<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Channel\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\System\Channel\Channel\ContextSwitchRoute;
use Contena\Core\System\Channel\Context\ChannelContextPersister;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Channel\Context\ChannelContextServiceInterface;
use Contena\Core\System\Channel\Context\ChannelContextServiceParameters;
use Contena\Core\System\Channel\Event\ChannelContextSwitchEvent;
use Contena\Core\System\Channel\Event\SwitchContextEvent;
use Contena\Core\Test\Generator;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(ContextSwitchRoute::class)]
class ContextSwitchRouteTest extends TestCase
{
    public function testSwitchContextPersistsGenericParametersAndDispatchesEvents(): void
    {
        $context = Generator::generateChannelContext();
        $parameters = [
            ChannelContextService::COUNTRY_ID => $context->getCountryId(),
            ChannelContextService::LANGUAGE_ID => $context->getLanguageId(),
        ];

        $validator = $this->createMock(DataValidator::class);
        $validator
            ->expects($this->exactly(2))
            ->method('validate')
            ->with($parameters);

        $contextPersister = $this->createMock(ChannelContextPersister::class);
        $contextPersister
            ->expects($this->once())
            ->method('save')
            ->with($context->getToken(), $parameters, $context->getChannelId(), null);

        $contextService = $this->createMock(ChannelContextServiceInterface::class);
        $contextService
            ->expects($this->once())
            ->method('get')
            ->with(static::equalTo(new ChannelContextServiceParameters($context->getChannelId(), $context->getToken())))
            ->willReturn($context);

        $dispatchedEvents = [];
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects($this->exactly(3))
            ->method('dispatch')
            ->willReturnCallback(static function (object $event) use (&$dispatchedEvents): object {
                $dispatchedEvents[] = $event;

                return $event;
            });

        $response = new ContextSwitchRoute(
            $validator,
            $contextPersister,
            $eventDispatcher,
            $contextService,
        )->switchContext(new RequestDataBag($parameters), $context);

        static::assertSame($context->getToken(), $response->getToken());
        static::assertNull($response->getRedirectUrl());
        static::assertInstanceOf(SwitchContextEvent::class, $dispatchedEvents[0]);
        static::assertInstanceOf(SwitchContextEvent::class, $dispatchedEvents[1]);
        static::assertInstanceOf(ChannelContextSwitchEvent::class, $dispatchedEvents[2]);
    }
}
