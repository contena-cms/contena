<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\UserDefinition;
use Contena\Core\System\User\Subscriber\UserLanguageChannelSubscriber;
use Contena\Core\Framework\Api\Context\ChannelApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Contena\Core\Framework\DataAbstractionLayer\PartialEntity;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Contena\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\WriteConstraintViolationException;
use Contena\Core\System\Tax\TaxDefinition;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(UserLanguageChannelSubscriber::class)]
class UserLanguageChannelSubscriberTest extends TestCase
{
    private StaticDefinitionInstanceRegistry $definitionRegistry;

    /**
     * @var Stub&EntityRepository<EntityCollection<PartialEntity>>
     */
    private Stub $channelRepository;

    protected function setUp(): void
    {
        $this->definitionRegistry = new StaticDefinitionInstanceRegistry(
            [UserDefinition::class, TaxDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );
        $this->channelRepository = static::createStub(EntityRepository::class);
    }

    public function testGetSubscribedEvents(): void
    {
        static::assertSame(
            [PreWriteValidationEvent::class => 'validate'],
            UserLanguageChannelSubscriber::getSubscribedEvents()
        );
    }

    public function testValidateSkipsWhenChannelApiSource(): void
    {
        $context = new Context(new ChannelApiSource(Uuid::randomHex()));
        $event = new PreWriteValidationEvent(WriteContext::createFromContext($context), []);

        $channelRepository = $this->createMock(EntityRepository::class);
        $channelRepository->expects($this->never())->method('search');

        $this->createSubscriber($channelRepository)->validate($event);
    }

    public function testValidateSkipsWhenNoUserCommands(): void
    {
        $context = Context::createDefaultContext();
        $event = new PreWriteValidationEvent(WriteContext::createFromContext($context), []);

        $channelRepository = $this->createMock(EntityRepository::class);
        $channelRepository->expects($this->never())->method('search');

        $this->createSubscriber($channelRepository)->validate($event);
    }

    public function testValidateSkipsWhenUserCommandHasNoLanguageId(): void
    {
        $context = Context::createDefaultContext();
        $userDef = $this->definitionRegistry->get(UserDefinition::class);
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext($context),
            [
                new InsertCommand(
                    $userDef,
                    ['channel_id' => Uuid::randomBytes()],
                    ['id' => Uuid::randomBytes()],
                    static::createStub(EntityExistence::class),
                    '/0/'
                ),
            ]
        );

        $channelRepository = $this->createMock(EntityRepository::class);
        $channelRepository->expects($this->never())->method('search');

        $this->createSubscriber($channelRepository)->validate($event);
    }

    public function testValidateSkipsWhenInsertHasNoChannelIdAndNoUserId(): void
    {
        $languageIdBytes = Uuid::fromHexToBytes(Uuid::randomHex());
        $context = Context::createDefaultContext();
        $userDef = $this->definitionRegistry->get(UserDefinition::class);
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext($context),
            [
                new InsertCommand(
                    $userDef,
                    ['language_id' => $languageIdBytes],
                    ['id' => Uuid::randomBytes()],
                    static::createStub(EntityExistence::class),
                    '/0/'
                ),
            ]
        );

        $channelRepository = $this->createMock(EntityRepository::class);
        $channelRepository->expects($this->never())->method('search');

        $this->createSubscriber($channelRepository)->validate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testValidateSkipsWhenLanguageInChannel(): void
    {
        $channelId = Uuid::randomHex();
        $languageId = Uuid::randomHex();
        $channelIdBytes = Uuid::fromHexToBytes($channelId);
        $languageIdBytes = Uuid::fromHexToBytes($languageId);
        $context = Context::createDefaultContext();
        $userDef = $this->definitionRegistry->get(UserDefinition::class);
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext($context),
            [
                new InsertCommand(
                    $userDef,
                    [
                        'language_id' => $languageIdBytes,
                        'channel_id' => $channelIdBytes,
                    ],
                    ['id' => Uuid::randomBytes()],
                    static::createStub(EntityExistence::class),
                    '/0/'
                ),
            ]
        );

        $language = new PartialEntity(['id' => $languageId]);
        $channel = new PartialEntity([
            'id' => $channelId,
            'languages' => new EntityCollection([$language]),
        ]);
        $channel->internalSetEntityData('channel', new FieldVisibility([]));
        $channels = new EntityCollection([$channel]);
        $channelRepository = $this->createMock(EntityRepository::class);
        $channelRepository->expects($this->once())
            ->method('search')
            ->with(static::isInstanceOf(Criteria::class), $context)
            ->willReturn(new EntitySearchResult('channel', 1, $channels, null, new Criteria(), $context));

        $this->createSubscriber($channelRepository)->validate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testValidateAddsViolationWhenLanguageNotInChannel(): void
    {
        $channelId = Uuid::randomHex();
        $languageId = Uuid::randomHex();
        $channelIdBytes = Uuid::fromHexToBytes($channelId);
        $languageIdBytes = Uuid::fromHexToBytes($languageId);
        $context = Context::createDefaultContext();
        $userDef = $this->definitionRegistry->get(UserDefinition::class);
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext($context),
            [
                new InsertCommand(
                    $userDef,
                    [
                        'language_id' => $languageIdBytes,
                        'channel_id' => $channelIdBytes,
                    ],
                    ['id' => Uuid::randomBytes()],
                    static::createStub(EntityExistence::class),
                    '/0/'
                ),
            ]
        );

        $channel = new PartialEntity([
            'id' => $channelId,
            'languages' => new EntityCollection([]),
        ]);
        $channel->internalSetEntityData('channel', new FieldVisibility([]));
        $channels = new EntityCollection([$channel]);
        $channelRepository = $this->createMock(EntityRepository::class);
        $channelRepository->expects($this->once())
            ->method('search')
            ->with(static::isInstanceOf(Criteria::class), $context)
            ->willReturn(new EntitySearchResult('channel', 1, $channels, null, new Criteria(), $context));

        $this->createSubscriber($channelRepository)->validate($event);

        static::assertCount(1, $event->getExceptions()->getExceptions());
        $exception = $event->getExceptions()->getExceptions()[0];
        static::assertInstanceOf(WriteConstraintViolationException::class, $exception);
        static::assertCount(1, $exception->getViolations());
        static::assertSame(
            UserLanguageChannelSubscriber::VIOLATION_LANGUAGE_NOT_IN_CHANNEL,
            $exception->getViolations()->get(0)->getCode()
        );
    }

    public function testValidateAddsViolationWhenChannelNotInSearchResult(): void
    {
        $channelId = Uuid::randomHex();
        $languageId = Uuid::randomHex();
        $channelIdBytes = Uuid::fromHexToBytes($channelId);
        $languageIdBytes = Uuid::fromHexToBytes($languageId);
        $context = Context::createDefaultContext();
        $userDef = $this->definitionRegistry->get(UserDefinition::class);
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext($context),
            [
                new InsertCommand(
                    $userDef,
                    [
                        'language_id' => $languageIdBytes,
                        'channel_id' => $channelIdBytes,
                    ],
                    ['id' => Uuid::randomBytes()],
                    static::createStub(EntityExistence::class),
                    '/0/'
                ),
            ]
        );

        $channelRepository = $this->createMock(EntityRepository::class);
        $channelRepository->expects($this->once())
            ->method('search')
            ->with(static::isInstanceOf(Criteria::class), $context)
            ->willReturn(new EntitySearchResult('channel', 0, new EntityCollection(), null, new Criteria(), $context));

        $this->createSubscriber($channelRepository)->validate($event);

        $exceptions = $event->getExceptions()->getExceptions();
        static::assertCount(1, $exceptions);
        $exception = $exceptions[0];
        static::assertInstanceOf(WriteConstraintViolationException::class, $exception);
        static::assertSame(
            UserLanguageChannelSubscriber::VIOLATION_LANGUAGE_NOT_IN_CHANNEL,
            $exception->getViolations()->get(0)->getCode()
        );
    }

    public function testValidateUpdateFindsChannelByUserIdWhenChannelIdNull(): void
    {
        $userId = Uuid::randomHex();
        $channelId = Uuid::randomHex();
        $languageId = Uuid::randomHex();
        $userIdBytes = Uuid::fromHexToBytes($userId);
        $languageIdBytes = Uuid::fromHexToBytes($languageId);
        $context = Context::createDefaultContext();
        $userDef = $this->definitionRegistry->get(UserDefinition::class);
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext($context),
            [
                new UpdateCommand(
                    $userDef,
                    ['language_id' => $languageIdBytes],
                    ['id' => $userIdBytes],
                    static::createStub(EntityExistence::class),
                    '/0/'
                ),
            ]
        );

        $userRef = new PartialEntity(['id' => $userId]);
        $language = new PartialEntity(['id' => $languageId]);
        $channel = new PartialEntity([
            'id' => $channelId,
            'languages' => new EntityCollection([$language]),
            'users' => new EntityCollection([$userRef]),
        ]);
        $channel->internalSetEntityData('channel', new FieldVisibility([]));
        $channels = new EntityCollection([$channel]);
        $channelRepository = $this->createMock(EntityRepository::class);
        $channelRepository->expects($this->once())
            ->method('search')
            ->with(static::isInstanceOf(Criteria::class), $context)
            ->willReturn(new EntitySearchResult('channel', 1, $channels, null, new Criteria(), $context));

        $this->createSubscriber($channelRepository)->validate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testValidateUpdateAddsViolationWhenLanguageNotInChannelResolvedByUser(): void
    {
        $userId = Uuid::randomHex();
        $channelId = Uuid::randomHex();
        $languageId = Uuid::randomHex();
        $userIdBytes = Uuid::fromHexToBytes($userId);
        $languageIdBytes = Uuid::fromHexToBytes($languageId);
        $context = Context::createDefaultContext();
        $userDef = $this->definitionRegistry->get(UserDefinition::class);
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext($context),
            [
                new UpdateCommand(
                    $userDef,
                    ['language_id' => $languageIdBytes],
                    ['id' => $userIdBytes],
                    static::createStub(EntityExistence::class),
                    '/0/'
                ),
            ]
        );

        $userRef = new PartialEntity(['id' => $userId]);
        $channel = new PartialEntity([
            'id' => $channelId,
            'languages' => new EntityCollection([]),
            'users' => new EntityCollection([$userRef]),
        ]);
        $channel->internalSetEntityData('channel', new FieldVisibility([]));
        $channels = new EntityCollection([$channel]);
        $channelRepository = $this->createMock(EntityRepository::class);
        $channelRepository->expects($this->once())
            ->method('search')
            ->with(static::isInstanceOf(Criteria::class), $context)
            ->willReturn(new EntitySearchResult('channel', 1, $channels, null, new Criteria(), $context));

        $this->createSubscriber($channelRepository)->validate($event);

        static::assertCount(1, $event->getExceptions()->getExceptions());
        $exception = $event->getExceptions()->getExceptions()[0];
        static::assertInstanceOf(WriteConstraintViolationException::class, $exception);
        static::assertSame(
            UserLanguageChannelSubscriber::VIOLATION_LANGUAGE_NOT_IN_CHANNEL,
            $exception->getViolations()->get(0)->getCode()
        );
    }

    public function testValidateSkipsCommandsForOtherEntities(): void
    {
        $channelId = Uuid::randomHex();
        $languageId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $userDef = $this->definitionRegistry->get(UserDefinition::class);
        $taxDef = $this->definitionRegistry->get(TaxDefinition::class);
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext($context),
            [
                new InsertCommand(
                    $taxDef,
                    ['name' => 'test', 'tax_rate' => 19.0, 'position' => 1],
                    ['id' => Uuid::randomBytes()],
                    static::createStub(EntityExistence::class),
                    '/0/'
                ),
                new InsertCommand(
                    $userDef,
                    [
                        'language_id' => Uuid::fromHexToBytes($languageId),
                        'channel_id' => Uuid::fromHexToBytes($channelId),
                    ],
                    ['id' => Uuid::randomBytes()],
                    static::createStub(EntityExistence::class),
                    '/1/'
                ),
            ]
        );

        $language = new PartialEntity(['id' => $languageId]);
        $channel = new PartialEntity([
            'id' => $channelId,
            'languages' => new EntityCollection([$language]),
        ]);
        $channel->internalSetEntityData('channel', new FieldVisibility([]));
        $channelRepository = $this->createMock(EntityRepository::class);
        $channelRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult('channel', 1, new EntityCollection([$channel]), null, new Criteria(), $context));

        $this->createSubscriber($channelRepository)->validate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testValidateSkipsDeleteCommandForUser(): void
    {
        $channelId = Uuid::randomHex();
        $languageId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $userDef = $this->definitionRegistry->get(UserDefinition::class);
        $userIdBytes = Uuid::randomBytes();
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext($context),
            [
                new DeleteCommand(
                    $userDef,
                    ['id' => $userIdBytes],
                    EntityExistence::createForEntity(UserDefinition::ENTITY_NAME, ['id' => $userIdBytes])
                ),
                new InsertCommand(
                    $userDef,
                    [
                        'language_id' => Uuid::fromHexToBytes($languageId),
                        'channel_id' => Uuid::fromHexToBytes($channelId),
                    ],
                    ['id' => Uuid::randomBytes()],
                    static::createStub(EntityExistence::class),
                    '/1/'
                ),
            ]
        );

        $language = new PartialEntity(['id' => $languageId]);
        $channel = new PartialEntity([
            'id' => $channelId,
            'languages' => new EntityCollection([$language]),
        ]);
        $channel->internalSetEntityData('channel', new FieldVisibility([]));
        $channelRepository = $this->createMock(EntityRepository::class);
        $channelRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult('channel', 1, new EntityCollection([$channel]), null, new Criteria(), $context));

        $this->createSubscriber($channelRepository)->validate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testValidateSkipsWhenUserNotInAnyFetchedChannel(): void
    {
        $userId = Uuid::randomHex();
        $languageId = Uuid::randomHex();
        $userIdBytes = Uuid::fromHexToBytes($userId);
        $languageIdBytes = Uuid::fromHexToBytes($languageId);
        $context = Context::createDefaultContext();
        $userDef = $this->definitionRegistry->get(UserDefinition::class);
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext($context),
            [
                new UpdateCommand(
                    $userDef,
                    ['language_id' => $languageIdBytes],
                    ['id' => $userIdBytes],
                    static::createStub(EntityExistence::class),
                    '/0/'
                ),
            ]
        );

        $otherUserId = Uuid::randomHex();
        $channel = new PartialEntity([
            'id' => Uuid::randomHex(),
            'languages' => new EntityCollection([new PartialEntity(['id' => $languageId])]),
            'users' => new EntityCollection([new PartialEntity(['id' => $otherUserId])]),
        ]);
        $channel->internalSetEntityData('channel', new FieldVisibility([]));
        $channelRepository = $this->createMock(EntityRepository::class);
        $channelRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult('channel', 1, new EntityCollection([$channel]), null, new Criteria(), $context));

        $this->createSubscriber($channelRepository)->validate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    /**
     * @param (MockObject&EntityRepository<EntityCollection<PartialEntity>>)|null $channelRepository
     */
    private function createSubscriber(?MockObject $channelRepository = null): UserLanguageChannelSubscriber
    {
        return new UserLanguageChannelSubscriber($channelRepository ?? $this->channelRepository);
    }
}
