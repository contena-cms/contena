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
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Contena\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\WriteConstraintViolationException;
use Contena\Core\System\Channel\Aggregate\ChannelLanguage\ChannelLanguageDefinition;
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

    protected function setUp(): void
    {
        $this->definitionRegistry = new StaticDefinitionInstanceRegistry(
            [ChannelDefinition::class, ChannelLanguageDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class),
        );
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
                ['ids' => ArrayParameterType::BINARY],
            )
            ->willReturn([]);

        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [new InsertCommand(
                $this->definitionRegistry->getByEntityName(ChannelDefinition::ENTITY_NAME),
                [
                    'type_id' => Uuid::fromHexToBytes($typeId),
                    'language_id' => Uuid::fromHexToBytes($languageId),
                ],
                ['id' => Uuid::fromHexToBytes($channelId)],
                static::createStub(EntityExistence::class),
                '/0',
            )],
        );

        new ChannelValidator($connection)->handleChannelLanguageIds($event);

        static::assertCount(1, $event->getExceptions()->getExceptions());
        $exception = $event->getExceptions()->getExceptions()[0];
        static::assertInstanceOf(WriteConstraintViolationException::class, $exception);
        static::assertSame('SYSTEM__NO_GIVEN_DEFAULT_LANGUAGE_ID', $exception->getViolations()->get(0)->getCode());
    }

    public function testUnsupportedChannelTypeDoesNotRequireDefaultLanguageInLanguageList(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('fetchAllAssociative')->willReturn([]);

        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [new InsertCommand(
                $this->definitionRegistry->getByEntityName(ChannelDefinition::ENTITY_NAME),
                [
                    'type_id' => Uuid::randomBytes(),
                    'language_id' => Uuid::randomBytes(),
                ],
                ['id' => Uuid::randomBytes()],
                static::createStub(EntityExistence::class),
                '/0',
            )],
        );

        new ChannelValidator($connection)->handleChannelLanguageIds($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testWebChannelSucceedsWithDefaultLanguageInLanguageList(): void
    {
        $channelId = Uuid::randomHex();
        $languageId = Uuid::randomHex();
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('fetchAllAssociative')->willReturn([]);

        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                new InsertCommand(
                    $this->definitionRegistry->getByEntityName(ChannelDefinition::ENTITY_NAME),
                    [
                        'type_id' => Uuid::fromHexToBytes(Defaults::CHANNEL_TYPE_WEB),
                        'language_id' => Uuid::fromHexToBytes($languageId),
                    ],
                    ['id' => Uuid::fromHexToBytes($channelId)],
                    static::createStub(EntityExistence::class),
                    '/0',
                ),
                new InsertCommand(
                    $this->definitionRegistry->getByEntityName(ChannelLanguageDefinition::ENTITY_NAME),
                    [],
                    [
                        'channel_id' => Uuid::fromHexToBytes($channelId),
                        'language_id' => Uuid::fromHexToBytes($languageId),
                    ],
                    static::createStub(EntityExistence::class),
                    '/0/languages/0',
                ),
            ],
        );

        new ChannelValidator($connection)->handleChannelLanguageIds($event);

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

        new ChannelValidator($connection)->handleChannelLanguageIds($event);

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

        new ChannelValidator($connection)->handleChannelLanguageIds($event);

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

        new ChannelValidator($connection)->handleChannelLanguageIds($event);

        static::assertCount(1, $event->getExceptions()->getExceptions());
        $exception = $event->getExceptions()->getExceptions()[0];
        static::assertInstanceOf(WriteConstraintViolationException::class, $exception);
        static::assertSame('SYSTEM__CANNOT_DELETE_DEFAULT_LANGUAGE_ID', $exception->getViolations()->get(0)->getCode());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function supportedChannelTypeProvider(): iterable
    {
        yield 'web' => [Defaults::CHANNEL_TYPE_WEB];
        yield 'api' => [Defaults::CHANNEL_TYPE_API];
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
                'current_default' => $currentDefaultId,
                'language_id' => $languageId,
            ];
        }

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn($states);

        return $connection;
    }
}
