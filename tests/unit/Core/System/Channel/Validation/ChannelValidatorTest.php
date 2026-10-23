<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Channel\Validation;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Contena\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\WriteConstraintViolationException;
use Contena\Core\System\Channel\Aggregate\ChannelCurrency\ChannelCurrencyDefinition;
use Contena\Core\System\Channel\Aggregate\ChannelLanguage\ChannelLanguageDefinition;
use Contena\Core\System\Channel\Capability\AbstractChannelTypeCapabilities;
use Contena\Core\System\Channel\Capability\ChannelTypeCapabilityRegistry;
use Contena\Core\System\Channel\ChannelDefinition;
use Contena\Core\System\Channel\Validation\ChannelValidator;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(ChannelValidator::class)]
class ChannelValidatorTest extends TestCase
{
    private StaticDefinitionInstanceRegistry $definitionRegistry;

    private ChannelTypeCapabilityRegistry $capabilityRegistry;

    protected function setUp(): void
    {
        $this->definitionRegistry = new StaticDefinitionInstanceRegistry(
            [ChannelDefinition::class, ChannelLanguageDefinition::class, ChannelCurrencyDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        $this->capabilityRegistry = new ChannelTypeCapabilityRegistry([]);
    }

    #[DataProvider('supportedChannelTypeProvider')]
    public function testSupportedChannelTypesRequireDefaultLanguageInLanguageList(string $typeId): void
    {
        $channelId = Uuid::randomHex();
        $languageId = Uuid::randomHex();
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->with(
                static::isString(),
                ['ids' => [Uuid::fromHexToBytes($channelId)]],
                ['ids' => ArrayParameterType::BINARY]
            )
            ->willReturn([]);

        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
            ]
        );

        (new ChannelValidator($connection, $this->capabilityRegistry))->handleChannelLanguageIds($event);

        static::assertCount(1, $event->getExceptions()->getExceptions());
        $exception = $event->getExceptions()->getExceptions()[0];
        static::assertInstanceOf(WriteConstraintViolationException::class, $exception);
        static::assertSame('SYSTEM__NO_GIVEN_DEFAULT_LANGUAGE_ID', $exception->getViolations()->get(0)->getCode());
    }

    public function testEveryChannelTypeRequiresDefaultLanguageInLanguageList(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([]);

        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
            ]
        );

        (new ChannelValidator($connection, $this->capabilityRegistry))->handleChannelLanguageIds($event);

        static::assertCount(1, $event->getExceptions()->getExceptions());
        $exception = $event->getExceptions()->getExceptions()[0];
        static::assertInstanceOf(WriteConstraintViolationException::class, $exception);
        static::assertSame('SYSTEM__NO_GIVEN_DEFAULT_LANGUAGE_ID', $exception->getViolations()->get(0)->getCode());
    }

    public function testBlogComparisonChannelSucceedsWithDefaultLanguageInLanguageList(): void
    {
        $channelId = Uuid::randomHex();
        $languageId = Uuid::randomHex();
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([]);

        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
            ]
        );

        (new ChannelValidator($connection, $this->capabilityRegistry))->handleChannelLanguageIds($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testDeletingThePreviousDefaultLanguageSucceedsWhenTheSameWriteSetsANewDefault(): void
    {
        $channelId = Uuid::randomHex();
        $previousDefaultId = Uuid::randomHex();
        $newDefaultId = Uuid::randomHex();

        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                $this->updateDefaultLanguageCommand($channelId, $newDefaultId),
                $this->deleteLanguageCommand($channelId, $previousDefaultId),
            ]
        );

        $connection = $this->connectionWithLanguageState($channelId, $previousDefaultId, [$previousDefaultId, $newDefaultId]);

        (new ChannelValidator($connection, $this->capabilityRegistry))->handleChannelLanguageIds($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testDeletingTheNewDefaultLanguageFails(): void
    {
        $channelId = Uuid::randomHex();
        $previousDefaultId = Uuid::randomHex();
        $newDefaultId = Uuid::randomHex();

        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                $this->updateDefaultLanguageCommand($channelId, $newDefaultId),
                $this->deleteLanguageCommand($channelId, $newDefaultId),
            ]
        );

        $connection = $this->connectionWithLanguageState($channelId, $previousDefaultId, [$previousDefaultId, $newDefaultId]);

        (new ChannelValidator($connection, $this->capabilityRegistry))->handleChannelLanguageIds($event);

        static::assertCount(1, $event->getExceptions()->getExceptions());
        $exception = $event->getExceptions()->getExceptions()[0];
        static::assertInstanceOf(WriteConstraintViolationException::class, $exception);
        static::assertSame('SYSTEM__CANNOT_DELETE_DEFAULT_LANGUAGE_ID', $exception->getViolations()->get(0)->getCode());
    }

    public function testDeletingTheDefaultLanguageFailsWhenTheWriteKeepsThatDefault(): void
    {
        $channelId = Uuid::randomHex();
        $defaultId = Uuid::randomHex();
        $secondLanguageId = Uuid::randomHex();

        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                $this->deleteLanguageCommand($channelId, $defaultId),
            ]
        );

        $connection = $this->connectionWithLanguageState($channelId, $defaultId, [$defaultId, $secondLanguageId]);

        (new ChannelValidator($connection, $this->capabilityRegistry))->handleChannelLanguageIds($event);

        static::assertCount(1, $event->getExceptions()->getExceptions());
        $exception = $event->getExceptions()->getExceptions()[0];
        static::assertInstanceOf(WriteConstraintViolationException::class, $exception);
        static::assertSame('SYSTEM__CANNOT_DELETE_DEFAULT_LANGUAGE_ID', $exception->getViolations()->get(0)->getCode());
    }

    public function testChannelRequiresDefaultCurrencyInCurrencyList(): void
    {
        $channelId = Uuid::randomHex();
        $currencyId = Uuid::randomHex();
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([]);

        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
            ]
        );

        (new ChannelValidator($connection, $this->capabilityRegistry))->handleChannelLanguageIds($event);

        static::assertCount(1, $event->getExceptions()->getExceptions());
        $exception = $event->getExceptions()->getExceptions()[0];
        static::assertInstanceOf(WriteConstraintViolationException::class, $exception);
        static::assertSame('SYSTEM__NO_GIVEN_DEFAULT_CURRENCY_ID', $exception->getViolations()->get(0)->getCode());
    }

    #[DataProvider('transactionalChannelTypeProvider')]
    public function testTransactionalChannelTypesRequireDefaultCurrencyInCurrencyList(string $typeId): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([]);

        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
            ]
        );

        (new ChannelValidator($connection, $this->capabilityRegistry))->handleChannelLanguageIds($event);

        static::assertCount(1, $event->getExceptions()->getExceptions());
        $exception = $event->getExceptions()->getExceptions()[0];
        static::assertInstanceOf(WriteConstraintViolationException::class, $exception);
        static::assertSame('SYSTEM__NO_GIVEN_DEFAULT_CURRENCY_ID', $exception->getViolations()->get(0)->getCode());
    }

    #[DataProvider('nonTransactionalChannelTypeProvider')]
    public function testNonTransactionalChannelTypesDoNotRequireDefaultCurrencyInCurrencyList(string $typeId): void
    {
        $channelId = Uuid::randomHex();
        $currencyId = Uuid::randomHex();
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([]);

        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
            ]
        );

        (new ChannelValidator($connection, $this->capabilityRegistry))->handleChannelLanguageIds($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testPluginTypeRegisteredAsTransactionalRequiresDefaultCurrencyInCurrencyList(): void
    {
        $pluginTypeId = Uuid::randomHex();
        $capabilityRegistry = new ChannelTypeCapabilityRegistry([
            new class($pluginTypeId) extends AbstractChannelTypeCapabilities {
                public function __construct(private readonly string $channelTypeId)
                {
                }

                public function getChannelTypeId(): string
                {
                    return $this->channelTypeId;
                }

                public function isTransactional(): bool
                {
                    return true;
                }
            },
        ]);
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([]);

        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
            ]
        );

        (new ChannelValidator($connection, $capabilityRegistry))->handleChannelLanguageIds($event);

        static::assertCount(1, $event->getExceptions()->getExceptions());
        $exception = $event->getExceptions()->getExceptions()[0];
        static::assertInstanceOf(WriteConstraintViolationException::class, $exception);
        static::assertSame('SYSTEM__NO_GIVEN_DEFAULT_CURRENCY_ID', $exception->getViolations()->get(0)->getCode());
    }

    #[DataProvider('nonTransactionalChannelTypeProvider')]
    public function testDeletingDefaultCurrencyOfNonTransactionalChannelTypeIsSkipped(string $typeId): void
    {
        $channelId = Uuid::randomHex();
        $defaultId = Uuid::randomHex();

        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
            ]
        );

        $connection = $this->connectionWithCurrencyState($channelId, $defaultId, [$defaultId], $typeId);

        (new ChannelValidator($connection, $this->capabilityRegistry))->handleChannelLanguageIds($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testUpdatingDefaultCurrencyToUnassignedCurrencyFails(): void
    {
        $channelId = Uuid::randomHex();
        $currentDefaultId = Uuid::randomHex();
        $newDefaultId = Uuid::randomHex();

        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
            ]
        );

        $connection = $this->connectionWithCurrencyState($channelId, $currentDefaultId, [$currentDefaultId]);

        (new ChannelValidator($connection, $this->capabilityRegistry))->handleChannelLanguageIds($event);

        static::assertCount(1, $event->getExceptions()->getExceptions());
        $exception = $event->getExceptions()->getExceptions()[0];
        static::assertInstanceOf(WriteConstraintViolationException::class, $exception);
        static::assertSame('SYSTEM__CANNOT_UPDATE_DEFAULT_CURRENCY_ID', $exception->getViolations()->get(0)->getCode());
    }

    #[DataProvider('nonTransactionalChannelTypeProvider')]
    public function testUpdatingDefaultCurrencyOfNonTransactionalChannelTypeIsSkipped(string $typeId): void
    {
        $channelId = Uuid::randomHex();
        $currentDefaultId = Uuid::randomHex();

        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
            ]
        );

        $connection = $this->connectionWithCurrencyState($channelId, $currentDefaultId, [$currentDefaultId], $typeId);

        (new ChannelValidator($connection, $this->capabilityRegistry))->handleChannelLanguageIds($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testDeletingDefaultCurrencyFails(): void
    {
        $channelId = Uuid::randomHex();
        $defaultId = Uuid::randomHex();

        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
            ]
        );

        $connection = $this->connectionWithCurrencyState($channelId, $defaultId, [$defaultId]);

        (new ChannelValidator($connection, $this->capabilityRegistry))->handleChannelLanguageIds($event);

        static::assertCount(1, $event->getExceptions()->getExceptions());
        $exception = $event->getExceptions()->getExceptions()[0];
        static::assertInstanceOf(WriteConstraintViolationException::class, $exception);
        static::assertSame('SYSTEM__CANNOT_DELETE_DEFAULT_CURRENCY_ID', $exception->getViolations()->get(0)->getCode());
    }

    public function testReaddingAssignedCurrencyIsIgnored(): void
    {
        $channelId = Uuid::randomHex();
        $currencyId = Uuid::randomHex();

        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
            ]
        );

        $connection = $this->connectionWithCurrencyState($channelId, $currencyId, [$currencyId]);

        (new ChannelValidator($connection, $this->capabilityRegistry))->handleChannelLanguageIds($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function supportedChannelTypeProvider(): iterable
    {
        yield 'frontend' => [Defaults::CHANNEL_TYPE_FRONTEND];
        yield 'api' => [Defaults::CHANNEL_TYPE_API];
        yield 'blog comparison' => [Defaults::CHANNEL_TYPE_BLOG_COMPARISON];
        yield 'agentic commerce' => [Defaults::CHANNEL_TYPE_AGENTIC_COMMERCE];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function transactionalChannelTypeProvider(): iterable
    {
        yield 'frontend' => [Defaults::CHANNEL_TYPE_FRONTEND];
        yield 'headless' => [Defaults::CHANNEL_TYPE_API];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function nonTransactionalChannelTypeProvider(): iterable
    {
        yield 'blog comparison' => [Defaults::CHANNEL_TYPE_BLOG_COMPARISON];
        yield 'agentic commerce' => [Defaults::CHANNEL_TYPE_AGENTIC_COMMERCE];
        yield 'plugin type without registration' => [Uuid::randomHex()];
    }

    private function updateDefaultLanguageCommand(string $channelId, string $languageId): UpdateCommand
    {
        return new UpdateCommand(
            $this->definitionRegistry->getByEntityName(ChannelDefinition::ENTITY_NAME),
            ['language_id' => Uuid::fromHexToBytes($languageId)],
            ['id' => Uuid::fromHexToBytes($channelId)],
            static::createStub(EntityExistence::class),
            '/0'
        );
    }

    private function deleteLanguageCommand(string $channelId, string $languageId): DeleteCommand
    {
        return new DeleteCommand(
            $this->definitionRegistry->getByEntityName(ChannelLanguageDefinition::ENTITY_NAME),
            [
                'channel_id' => Uuid::fromHexToBytes($channelId),
                'language_id' => Uuid::fromHexToBytes($languageId),
            ],
            static::createStub(EntityExistence::class)
        );
    }

    /**
     * @param list<string> $assignedLanguageIds
     */
    private function connectionWithLanguageState(string $channelId, string $currentDefaultId, array $assignedLanguageIds): Connection
    {
        $states = [];
        foreach ($assignedLanguageIds as $languageId) {
            $states[] = [
                'channel_id' => $channelId,
                'type_id' => Defaults::CHANNEL_TYPE_FRONTEND,
                'current_default' => $currentDefaultId,
                'language_id' => $languageId,
            ];
        }

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn($states);

        return $connection;
    }

    /**
     * @param list<string> $assignedCurrencyIds
     */
    private function connectionWithCurrencyState(string $channelId, string $currentDefaultId, array $assignedCurrencyIds, string $typeId = Defaults::CHANNEL_TYPE_FRONTEND): Connection
    {
        $states = [];
        foreach ($assignedCurrencyIds as $currencyId) {
            $states[] = [
                'channel_id' => $channelId,
                'type_id' => $typeId,
                'current_default' => $currentDefaultId,
                'currency_id' => $currencyId,
            ];
        }

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn($states);

        return $connection;
    }
}
