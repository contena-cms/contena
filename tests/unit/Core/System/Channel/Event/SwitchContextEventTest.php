<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Channel\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\System\Channel\Event\SwitchContextEvent;
use Contena\Core\Test\Generator;

/**
 * @internal
 */
#[CoversClass(SwitchContextEvent::class)]
class SwitchContextEventTest extends TestCase
{
    public function testExposesTheSwitchRequest(): void
    {
        $requestData = new RequestDataBag(['currencyId' => 'currency-id']);
        $context = Generator::generateChannelContext();
        $definition = new DataValidationDefinition('context_switch');

        $event = new SwitchContextEvent($requestData, $context, $definition, ['currencyId' => 'currency-id']);

        static::assertSame($requestData, $event->getRequestData());
        static::assertSame($context, $event->getChannelContext());
        static::assertSame($context->getContext(), $event->getContext());
        static::assertSame($definition, $event->getDataValidationDefinition());
        static::assertSame(['currencyId' => 'currency-id'], $event->getParameters());
    }

    public function testParametersCanBeAddedAndDeleted(): void
    {
        $event = new SwitchContextEvent(new RequestDataBag(), Generator::generateChannelContext(), new DataValidationDefinition(), ['currencyId' => 'currency-id']);

        $event->addParameter('languageId', 'language-id');
        static::assertSame(['currencyId' => 'currency-id', 'languageId' => 'language-id'], $event->getParameters());

        $event->deleteParameter('currencyId');
        static::assertSame(['languageId' => 'language-id'], $event->getParameters());

        $event->deleteParameter('unknown');
        static::assertSame(['languageId' => 'language-id'], $event->getParameters());
    }
}
