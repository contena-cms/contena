<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Gateway\Context\Command\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Contena\Core\Framework\Gateway\Context\Command\ChangeShippingLocationCommand;
use Contena\Core\Framework\Gateway\Context\Command\Handler\ChangeShippingLocationCommandHandler;
use Contena\Core\Framework\Gateway\GatewayException;
use Contena\Core\Test\Generator;

/**
 * @internal
 */
#[CoversClass(ChangeShippingLocationCommandHandler::class)]
class ChangeShippingLocationCommandHandlerTest extends TestCase
{
    public function testHandleWithNoData(): void
    {
        $command = ChangeShippingLocationCommand::createFromPayload();
        $context = Generator::generateChannelContext();
        $parameters = [];

        $handler = new ChangeShippingLocationCommandHandler(
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
        );

        $handler->handle($command, $context, $parameters);

        static::assertSame([], $parameters);
    }

    public function testHandleWithCountryIso(): void
    {
        $command = ChangeShippingLocationCommand::createFromPayload(['countryIso' => 'DE']);
        $context = Generator::generateChannelContext();
        $parameters = [];

        $expectedCriteria = new Criteria();
        $expectedCriteria->addFilter(
            new OrFilter(
                [
                    new EqualsFilter('country.iso', $command->countryIso),
                    new EqualsFilter('country.iso3', $command->countryIso),
                ]
            )
        );

        $countryResult = new IdSearchResult(
            1,
            ['countryId' => ['primaryKey' => 'countryId', 'data' => []]],
            $expectedCriteria,
            $context->getContext()
        );

        $countryRepo = $this->createMock(EntityRepository::class);
        $countryRepo
            ->expects($this->once())
            ->method('searchIds')
            ->with(static::equalTo($expectedCriteria), $context->getContext())
            ->willReturn($countryResult);

        $handler = new ChangeShippingLocationCommandHandler($countryRepo, static::createStub(EntityRepository::class));
        $handler->handle($command, $context, $parameters);

        static::assertSame(['countryId' => 'countryId'], $parameters);
    }

    public function testHandleCountryWithCountryNotFound(): void
    {
        $command = ChangeShippingLocationCommand::createFromPayload(['countryIso' => 'DE']);
        $context = Generator::generateChannelContext();
        $parameters = [];

        $expectedCriteria = new Criteria();
        $expectedCriteria->addFilter(
            new OrFilter(
                [
                    new EqualsFilter('country.iso', $command->countryIso),
                    new EqualsFilter('country.iso3', $command->countryIso),
                ]
            )
        );

        $countryResult = new IdSearchResult(
            0,
            [],
            $expectedCriteria,
            $context->getContext()
        );

        $countryRepo = $this->createMock(EntityRepository::class);
        $countryRepo
            ->expects($this->once())
            ->method('searchIds')
            ->with(static::equalTo($expectedCriteria), $context->getContext())
            ->willReturn($countryResult);

        $this->expectExceptionObject(GatewayException::handlerException('Country with iso code {{ isoCode }} not found', ['isoCode' => 'DE']));

        $handler = new ChangeShippingLocationCommandHandler($countryRepo, static::createStub(EntityRepository::class));

        try {
            $handler->handle($command, $context, $parameters);
        } finally {
            static::assertSame([], $parameters);
        }
    }

    public function testHandleWithCountryStateIso(): void
    {
        $command = ChangeShippingLocationCommand::createFromPayload(['countryStateIso' => 'DE-BY']);
        $context = Generator::generateChannelContext();
        $parameters = [];

        $expectedCriteria = new Criteria();
        $expectedCriteria->addFilter(new EqualsFilter('shortCode', $command->countryStateIso));

        $countryStateResult = new IdSearchResult(
            1,
            ['countryStateId' => ['primaryKey' => 'countryStateId', 'data' => []]],
            $expectedCriteria,
            $context->getContext()
        );

        $countryStateRepo = $this->createMock(EntityRepository::class);
        $countryStateRepo
            ->expects($this->once())
            ->method('searchIds')
            ->with(static::equalTo($expectedCriteria), $context->getContext())
            ->willReturn($countryStateResult);

        $handler = new ChangeShippingLocationCommandHandler(static::createStub(EntityRepository::class), $countryStateRepo);
        $handler->handle($command, $context, $parameters);

        static::assertSame(['countryStateId' => 'countryStateId'], $parameters);
    }

    public function testHandleCountryStateWithCountryStateNotFound(): void
    {
        $command = ChangeShippingLocationCommand::createFromPayload(['countryStateIso' => 'DE-BY']);
        $context = Generator::generateChannelContext();
        $parameters = [];

        $expectedCriteria = new Criteria();
        $expectedCriteria->addFilter(new EqualsFilter('shortCode', $command->countryStateIso));

        $countryStateResult = new IdSearchResult(
            0,
            [],
            $expectedCriteria,
            $context->getContext()
        );

        $countryStateRepo = $this->createMock(EntityRepository::class);
        $countryStateRepo
            ->expects($this->once())
            ->method('searchIds')
            ->with(static::equalTo($expectedCriteria), $context->getContext())
            ->willReturn($countryStateResult);

        $this->expectExceptionObject(GatewayException::handlerException('Country state with short code {{ shortCode }} not found', ['shortCode' => 'DE-BY']));

        $handler = new ChangeShippingLocationCommandHandler(static::createStub(EntityRepository::class), $countryStateRepo);

        try {
            $handler->handle($command, $context, $parameters);
        } finally {
            static::assertSame([], $parameters);
        }
    }

    public function testHandleWithCountryAndState(): void
    {
        $command = ChangeShippingLocationCommand::createFromPayload(['countryIso' => 'DEU', 'countryStateIso' => 'DE-BY']);
        $context = Generator::generateChannelContext();
        $parameters = [];

        $expectedCountryIso = new Criteria();
        $expectedCountryIso->addFilter(
            new OrFilter(
                [
                    new EqualsFilter('country.iso', $command->countryIso),
                    new EqualsFilter('country.iso3', $command->countryIso),
                ]
            )
        );

        $countryResult = new IdSearchResult(
            1,
            ['countryId' => ['primaryKey' => 'countryId', 'data' => []]],
            $expectedCountryIso,
            $context->getContext()
        );

        $countryRepo = $this->createMock(EntityRepository::class);
        $countryRepo
            ->expects($this->once())
            ->method('searchIds')
            ->with(static::equalTo($expectedCountryIso), $context->getContext())
            ->willReturn($countryResult);

        $expectedStateCriteria = new Criteria();
        $expectedStateCriteria->addFilter(new EqualsFilter('shortCode', $command->countryStateIso));

        $countryStateResult = new IdSearchResult(
            1,
            ['countryStateId' => ['primaryKey' => 'countryStateId', 'data' => []]],
            $expectedStateCriteria,
            $context->getContext()
        );

        $countryStateRepo = $this->createMock(EntityRepository::class);
        $countryStateRepo
            ->expects($this->once())
            ->method('searchIds')
            ->with(static::equalTo($expectedStateCriteria), $context->getContext())
            ->willReturn($countryStateResult);

        $handler = new ChangeShippingLocationCommandHandler($countryRepo, $countryStateRepo);
        $handler->handle($command, $context, $parameters);

        static::assertSame(['countryId' => 'countryId', 'countryStateId' => 'countryStateId'], $parameters);
    }

    public function testSupportedCommands(): void
    {
        static::assertSame([ChangeShippingLocationCommand::class], ChangeShippingLocationCommandHandler::supportedCommands());
    }
}
