<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Member\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\Context\ChannelApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
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
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\WriteConstraintViolationException;
use Contena\Core\System\Country\CountryDefinition;
use Contena\Core\System\Member\MemberDefinition;
use Contena\Core\System\Member\Subscriber\MemberLanguageChannelSubscriber;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(MemberLanguageChannelSubscriber::class)]
class MemberLanguageChannelSubscriberTest extends TestCase
{
    private StaticDefinitionInstanceRegistry $definitionRegistry;

    /**
     * @var Stub&EntityRepository<EntityCollection<PartialEntity>>
     */
    private Stub $channelRepository;

    protected function setUp(): void
    {
        $this->definitionRegistry = new StaticDefinitionInstanceRegistry(
            [MemberDefinition::class, CountryDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );
        $this->channelRepository = static::createStub(EntityRepository::class);
    }

    public function testGetSubscribedEvents(): void
    {
        static::assertSame(
            [PreWriteValidationEvent::class => 'validate'],
            MemberLanguageChannelSubscriber::getSubscribedEvents()
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

    public function testValidateSkipsWhenNoMemberCommands(): void
    {
        $context = Context::createDefaultContext();
        $event = new PreWriteValidationEvent(WriteContext::createFromContext($context), []);

        $channelRepository = $this->createMock(EntityRepository::class);
        $channelRepository->expects($this->never())->method('search');

        $this->createSubscriber($channelRepository)->validate($event);
    }

    public function testValidateSkipsWhenMemberCommandHasNoLanguageId(): void
    {
        $context = Context::createDefaultContext();
        $memberDef = $this->definitionRegistry->get(MemberDefinition::class);
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext($context),
            [
                new InsertCommand(
                    $memberDef,
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

    public function testValidateSkipsWhenInsertHasNoChannelIdAndNoMemberId(): void
    {
        $languageIdBytes = Uuid::fromHexToBytes(Uuid::randomHex());
        $context = Context::createDefaultContext();
        $memberDef = $this->definitionRegistry->get(MemberDefinition::class);
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext($context),
            [
                new InsertCommand(
                    $memberDef,
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
        $memberDef = $this->definitionRegistry->get(MemberDefinition::class);
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext($context),
            [
                new InsertCommand(
                    $memberDef,
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
        $channels = new EntityCollection([$channel]);
        $channelRepository = $this->createMock(EntityRepository::class);
        $channelRepository->expects($this->once())
            ->method('search')
            ->with(static::isInstanceOf(Criteria::class), $context)
            ->willReturn(new EntitySearchResult(1, $channels, null, new Criteria(), $context));

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
        $memberDef = $this->definitionRegistry->get(MemberDefinition::class);
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext($context),
            [
                new InsertCommand(
                    $memberDef,
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
        $channels = new EntityCollection([$channel]);
        $channelRepository = $this->createMock(EntityRepository::class);
        $channelRepository->expects($this->once())
            ->method('search')
            ->with(static::isInstanceOf(Criteria::class), $context)
            ->willReturn(new EntitySearchResult(1, $channels, null, new Criteria(), $context));

        $this->createSubscriber($channelRepository)->validate($event);

        static::assertCount(1, $event->getExceptions()->getExceptions());
        $exception = $event->getExceptions()->getExceptions()[0];
        static::assertInstanceOf(WriteConstraintViolationException::class, $exception);
        static::assertCount(1, $exception->getViolations());
        static::assertSame(
            MemberLanguageChannelSubscriber::VIOLATION_LANGUAGE_NOT_IN_CHANNEL,
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
        $memberDef = $this->definitionRegistry->get(MemberDefinition::class);
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext($context),
            [
                new InsertCommand(
                    $memberDef,
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
            ->willReturn(new EntitySearchResult(0, new EntityCollection(), null, new Criteria(), $context));

        $this->createSubscriber($channelRepository)->validate($event);

        $exceptions = $event->getExceptions()->getExceptions();
        static::assertCount(1, $exceptions);
        $exception = $exceptions[0];
        static::assertInstanceOf(WriteConstraintViolationException::class, $exception);
        static::assertSame(
            MemberLanguageChannelSubscriber::VIOLATION_LANGUAGE_NOT_IN_CHANNEL,
            $exception->getViolations()->get(0)->getCode()
        );
    }

    public function testValidateUpdateFindsChannelByMemberIdWhenChannelIdNull(): void
    {
        $memberId = Uuid::randomHex();
        $channelId = Uuid::randomHex();
        $languageId = Uuid::randomHex();
        $memberIdBytes = Uuid::fromHexToBytes($memberId);
        $languageIdBytes = Uuid::fromHexToBytes($languageId);
        $context = Context::createDefaultContext();
        $memberDef = $this->definitionRegistry->get(MemberDefinition::class);
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext($context),
            [
                new UpdateCommand(
                    $memberDef,
                    ['language_id' => $languageIdBytes],
                    ['id' => $memberIdBytes],
                    static::createStub(EntityExistence::class),
                    '/0/'
                ),
            ]
        );

        $memberRef = new PartialEntity(['id' => $memberId]);
        $language = new PartialEntity(['id' => $languageId]);
        $channel = new PartialEntity([
            'id' => $channelId,
            'languages' => new EntityCollection([$language]),
            'members' => new EntityCollection([$memberRef]),
        ]);
        $channels = new EntityCollection([$channel]);
        $channelRepository = $this->createMock(EntityRepository::class);
        $channelRepository->expects($this->once())
            ->method('search')
            ->with(static::isInstanceOf(Criteria::class), $context)
            ->willReturn(new EntitySearchResult(1, $channels, null, new Criteria(), $context));

        $this->createSubscriber($channelRepository)->validate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testValidateUpdateAddsViolationWhenLanguageNotInChannelResolvedByMember(): void
    {
        $memberId = Uuid::randomHex();
        $channelId = Uuid::randomHex();
        $languageId = Uuid::randomHex();
        $memberIdBytes = Uuid::fromHexToBytes($memberId);
        $languageIdBytes = Uuid::fromHexToBytes($languageId);
        $context = Context::createDefaultContext();
        $memberDef = $this->definitionRegistry->get(MemberDefinition::class);
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext($context),
            [
                new UpdateCommand(
                    $memberDef,
                    ['language_id' => $languageIdBytes],
                    ['id' => $memberIdBytes],
                    static::createStub(EntityExistence::class),
                    '/0/'
                ),
            ]
        );

        $memberRef = new PartialEntity(['id' => $memberId]);
        $channel = new PartialEntity([
            'id' => $channelId,
            'languages' => new EntityCollection([]),
            'members' => new EntityCollection([$memberRef]),
        ]);
        $channels = new EntityCollection([$channel]);
        $channelRepository = $this->createMock(EntityRepository::class);
        $channelRepository->expects($this->once())
            ->method('search')
            ->with(static::isInstanceOf(Criteria::class), $context)
            ->willReturn(new EntitySearchResult(1, $channels, null, new Criteria(), $context));

        $this->createSubscriber($channelRepository)->validate($event);

        static::assertCount(1, $event->getExceptions()->getExceptions());
        $exception = $event->getExceptions()->getExceptions()[0];
        static::assertInstanceOf(WriteConstraintViolationException::class, $exception);
        static::assertSame(
            MemberLanguageChannelSubscriber::VIOLATION_LANGUAGE_NOT_IN_CHANNEL,
            $exception->getViolations()->get(0)->getCode()
        );
    }

    public function testValidateSkipsCommandsForOtherEntities(): void
    {
        $channelId = Uuid::randomHex();
        $languageId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $memberDef = $this->definitionRegistry->get(MemberDefinition::class);
        $countryDefinition = $this->definitionRegistry->get(CountryDefinition::class);
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext($context),
            [
                new InsertCommand(
                    $countryDefinition,
                    ['name' => 'test'],
                    ['id' => Uuid::randomBytes()],
                    static::createStub(EntityExistence::class),
                    '/0/'
                ),
                new InsertCommand(
                    $memberDef,
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
        $channelRepository = $this->createMock(EntityRepository::class);
        $channelRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(1, new EntityCollection([$channel]), null, new Criteria(), $context));

        $this->createSubscriber($channelRepository)->validate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testValidateSkipsDeleteCommandForMember(): void
    {
        $channelId = Uuid::randomHex();
        $languageId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $memberDef = $this->definitionRegistry->get(MemberDefinition::class);
        $memberIdBytes = Uuid::randomBytes();
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext($context),
            [
                new DeleteCommand(
                    $memberDef,
                    ['id' => $memberIdBytes],
                    EntityExistence::createForEntity(MemberDefinition::ENTITY_NAME, ['id' => $memberIdBytes])
                ),
                new InsertCommand(
                    $memberDef,
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
        $channelRepository = $this->createMock(EntityRepository::class);
        $channelRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(1, new EntityCollection([$channel]), null, new Criteria(), $context));

        $this->createSubscriber($channelRepository)->validate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testValidateSkipsWhenMemberNotInAnyFetchedChannel(): void
    {
        $memberId = Uuid::randomHex();
        $languageId = Uuid::randomHex();
        $memberIdBytes = Uuid::fromHexToBytes($memberId);
        $languageIdBytes = Uuid::fromHexToBytes($languageId);
        $context = Context::createDefaultContext();
        $memberDef = $this->definitionRegistry->get(MemberDefinition::class);
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext($context),
            [
                new UpdateCommand(
                    $memberDef,
                    ['language_id' => $languageIdBytes],
                    ['id' => $memberIdBytes],
                    static::createStub(EntityExistence::class),
                    '/0/'
                ),
            ]
        );

        $otherMemberId = Uuid::randomHex();
        $channel = new PartialEntity([
            'id' => Uuid::randomHex(),
            'languages' => new EntityCollection([new PartialEntity(['id' => $languageId])]),
            'members' => new EntityCollection([new PartialEntity(['id' => $otherMemberId])]),
        ]);
        $channelRepository = $this->createMock(EntityRepository::class);
        $channelRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(1, new EntityCollection([$channel]), null, new Criteria(), $context));

        $this->createSubscriber($channelRepository)->validate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    /**
     * @param (MockObject&EntityRepository<EntityCollection<PartialEntity>>)|null $channelRepository
     */
    private function createSubscriber(?MockObject $channelRepository = null): MemberLanguageChannelSubscriber
    {
        return new MemberLanguageChannelSubscriber($channelRepository ?? $this->channelRepository);
    }
}
