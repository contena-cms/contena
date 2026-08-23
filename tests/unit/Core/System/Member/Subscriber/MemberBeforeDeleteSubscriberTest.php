<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Member\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\Context\ChannelApiSource;
use Contena\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Contena\Core\Framework\Struct\Serializer\StructNormalizer;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\Channel\Context\ChannelContextServiceInterface;
use Contena\Core\System\Language\LanguageCollection;
use Contena\Core\System\Language\LanguageEntity;
use Contena\Core\System\Member\Event\MemberDeletedEvent;
use Contena\Core\System\Member\MemberCollection;
use Contena\Core\System\Member\MemberDefinition;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\System\Member\Subscriber\MemberBeforeDeleteSubscriber;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Serializer\Serializer;

/**
 * @internal
 */
#[CoversClass(MemberBeforeDeleteSubscriber::class)]
class MemberBeforeDeleteSubscriberTest extends TestCase
{
    public function testEventsDispatched(): void
    {
        $memberId = Uuid::randomBytes();
        $member = new MemberEntity()
            ->assign([
                'id' => Uuid::fromBytesToHex($memberId),
                'channelId' => Uuid::randomHex(),
                'languageId' => Uuid::randomHex(),
                'memberNumber' => 'MEM1000',
                'email' => 'foo@bar.com',
                'name' => 'foo bar',
            ]);

        $definitionInstanceRegistry = static::createStub(DefinitionInstanceRegistry::class);

        $memberDefinition = new MemberDefinition();
        $memberDefinition->compile($definitionInstanceRegistry);

        /** @var StaticEntityRepository<MemberCollection> $memberRepository */
        $memberRepository = new StaticEntityRepository([
            new EntitySearchResult(
                1,
                new MemberCollection([$member]),
                null,
                new Criteria([$memberId]),
                Context::createDefaultContext()
            ),
        ], $memberDefinition);

        $channelId = $member->getChannelId();
        $languageId = $member->getLanguageId();
        $language = new LanguageEntity()->assign(['id' => $languageId]);
        $channel = new ChannelEntity()->assign([
            'id' => $channelId,
            'languages' => new LanguageCollection([$language]),
        ]);
        /** @var StaticEntityRepository<ChannelCollection> $channelRepository */
        $channelRepository = new StaticEntityRepository([
            new ChannelCollection([$channel]),
        ]);

        $channelContextService = static::createStub(ChannelContextServiceInterface::class);
        $channelContextService->method('get')->willReturn(Generator::generateChannelContext());

        $eventDispatcher = new EventDispatcher();

        $structNormalizer = new StructNormalizer();

        $jsonEntityEncoder = new JsonEntityEncoder(new Serializer([$structNormalizer], []));

        $subscriber = new MemberBeforeDeleteSubscriber(
            $memberRepository,
            $channelRepository,
            $channelContextService,
            $eventDispatcher,
            $jsonEntityEncoder
        );
        $eventDispatcher->addSubscriber($subscriber);

        $entityDeleteEvent = EntityDeleteEvent::create(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                new DeleteCommand(
                    $memberDefinition,
                    ['id' => $memberId],
                    new EntityExistence(
                        'member',
                        ['id' => $memberId],
                        true,
                        false,
                        false,
                        [
                            'exists' => true,
                            'id' => $memberId,
                        ]
                    )
                ),
            ]
        );

        $memberDeletedEventCount = 0;

        $serializedMember = $jsonEntityEncoder->encode(
            new Criteria(),
            $memberDefinition,
            $member,
            '/api/member'
        );

        $eventDispatcher->addListener(
            MemberDeletedEvent::class,
            static function (MemberDeletedEvent $event) use (&$memberDeletedEventCount, $member, $serializedMember): void {
                ++$memberDeletedEventCount;
                static::assertSame($member, $event->getMember());
                $values = $event->getValues();
                static::assertArrayHasKey('member', $values);
                static::assertSame($serializedMember, $values['member']);
            }
        );

        $eventDispatcher->dispatch($entityDeleteEvent);
        $entityDeleteEvent->success();

        static::assertSame(1, $memberDeletedEventCount);
    }

    public function testBeforeDeleteWithEmptyMemberIdsDoesNotDispatch(): void
    {
        $definitionInstanceRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $memberDefinition = new MemberDefinition();
        $memberDefinition->compile($definitionInstanceRegistry);

        /** @var StaticEntityRepository<MemberCollection> $memberRepository */
        $memberRepository = new StaticEntityRepository([], $memberDefinition);
        /** @var StaticEntityRepository<ChannelCollection> $channelRepository */
        $channelRepository = new StaticEntityRepository([]);
        $channelContextService = static::createStub(ChannelContextServiceInterface::class);
        $eventDispatcher = new EventDispatcher();
        $jsonEntityEncoder = static::createStub(JsonEntityEncoder::class);

        $subscriber = new MemberBeforeDeleteSubscriber(
            $memberRepository,
            $channelRepository,
            $channelContextService,
            $eventDispatcher,
            $jsonEntityEncoder
        );
        $eventDispatcher->addSubscriber($subscriber);

        $caughtEvents = 0;
        $eventDispatcher->addListener(
            MemberDeletedEvent::class,
            static function () use (&$caughtEvents): void {
                ++$caughtEvents;
            }
        );

        $entityDeleteEvent = EntityDeleteEvent::create(
            WriteContext::createFromContext(Context::createDefaultContext()),
            []
        );
        $eventDispatcher->dispatch($entityDeleteEvent);
        $entityDeleteEvent->success();

        static::assertSame(0, $caughtEvents);
    }

    public function testBeforeDeleteWithChannelApiSourceUsesSourceChannelId(): void
    {
        $memberId = Uuid::randomBytes();
        $channelIdFromSource = Uuid::randomHex();
        $languageId = Uuid::randomHex();
        $member = new MemberEntity()
            ->assign([
                'id' => Uuid::fromBytesToHex($memberId),
                'channelId' => Uuid::randomHex(),
                'languageId' => $languageId,
                'memberNumber' => 'MEM1001',
                'email' => 'bar@baz.com',
                'name' => 'bar baz',
            ]);

        $definitionInstanceRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $memberDefinition = new MemberDefinition();
        $memberDefinition->compile($definitionInstanceRegistry);

        /** @var StaticEntityRepository<MemberCollection> $memberRepository */
        $memberRepository = new StaticEntityRepository([
            new EntitySearchResult(
                1,
                new MemberCollection([$member]),
                null,
                new Criteria([$memberId]),
                Context::createDefaultContext()
            ),
        ], $memberDefinition);

        $language = new LanguageEntity()->assign(['id' => $languageId]);
        $channel = new ChannelEntity()->assign([
            'id' => $channelIdFromSource,
            'languages' => new LanguageCollection([$language]),
        ]);
        /** @var StaticEntityRepository<ChannelCollection> $channelRepository */
        $channelRepository = new StaticEntityRepository([
            new ChannelCollection([$channel]),
        ]);

        $channelContextService = static::createStub(ChannelContextServiceInterface::class);
        $channelContextService->method('get')->willReturn(Generator::generateChannelContext());
        $eventDispatcher = new EventDispatcher();
        $jsonEntityEncoder = new JsonEntityEncoder(new Serializer([new StructNormalizer()], []));

        $subscriber = new MemberBeforeDeleteSubscriber(
            $memberRepository,
            $channelRepository,
            $channelContextService,
            $eventDispatcher,
            $jsonEntityEncoder
        );
        $eventDispatcher->addSubscriber($subscriber);

        $dispatchedCount = 0;
        $eventDispatcher->addListener(
            MemberDeletedEvent::class,
            static function () use (&$dispatchedCount): void {
                ++$dispatchedCount;
            }
        );

        $context = Context::createDefaultContext(new ChannelApiSource($channelIdFromSource));
        $entityDeleteEvent = EntityDeleteEvent::create(
            WriteContext::createFromContext($context),
            [
                new DeleteCommand(
                    $memberDefinition,
                    ['id' => $memberId],
                    new EntityExistence(
                        'member',
                        ['id' => $memberId],
                        true,
                        false,
                        false,
                        ['exists' => true, 'id' => $memberId]
                    )
                ),
            ]
        );
        $eventDispatcher->dispatch($entityDeleteEvent);
        $entityDeleteEvent->success();

        static::assertSame(1, $dispatchedCount);
    }

    public function testBeforeDeleteWhenChannelDoesNotHaveMemberLanguageUsesNullLanguageId(): void
    {
        $memberId = Uuid::randomBytes();
        $channelId = Uuid::randomHex();
        $memberLanguageId = Uuid::randomHex();
        $otherLanguageId = Uuid::randomHex();
        $member = new MemberEntity()
            ->assign([
                'id' => Uuid::fromBytesToHex($memberId),
                'channelId' => $channelId,
                'languageId' => $memberLanguageId,
                'memberNumber' => 'MEM1002',
                'email' => 'nolang@test.com',
                'name' => 'No Lang',
            ]);

        $definitionInstanceRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $memberDefinition = new MemberDefinition();
        $memberDefinition->compile($definitionInstanceRegistry);

        /** @var StaticEntityRepository<MemberCollection> $memberRepository */
        $memberRepository = new StaticEntityRepository([
            new EntitySearchResult(
                1,
                new MemberCollection([$member]),
                null,
                new Criteria([$memberId]),
                Context::createDefaultContext()
            ),
        ], $memberDefinition);

        $channelHasOnlyOtherLanguage = new LanguageEntity()->assign(['id' => $otherLanguageId]);
        $channel = new ChannelEntity()->assign([
            'id' => $channelId,
            'languages' => new LanguageCollection([$channelHasOnlyOtherLanguage]),
        ]);
        /** @var StaticEntityRepository<ChannelCollection> $channelRepository */
        $channelRepository = new StaticEntityRepository([
            new ChannelCollection([$channel]),
        ]);

        $channelContextService = static::createStub(ChannelContextServiceInterface::class);
        $channelContextService->method('get')->willReturn(Generator::generateChannelContext());
        $eventDispatcher = new EventDispatcher();
        $jsonEntityEncoder = new JsonEntityEncoder(new Serializer([new StructNormalizer()], []));

        $subscriber = new MemberBeforeDeleteSubscriber(
            $memberRepository,
            $channelRepository,
            $channelContextService,
            $eventDispatcher,
            $jsonEntityEncoder
        );
        $eventDispatcher->addSubscriber($subscriber);

        $dispatchedCount = 0;
        $eventDispatcher->addListener(
            MemberDeletedEvent::class,
            static function () use (&$dispatchedCount): void {
                ++$dispatchedCount;
            }
        );

        $entityDeleteEvent = EntityDeleteEvent::create(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                new DeleteCommand(
                    $memberDefinition,
                    ['id' => $memberId],
                    new EntityExistence(
                        'member',
                        ['id' => $memberId],
                        true,
                        false,
                        false,
                        ['exists' => true, 'id' => $memberId]
                    )
                ),
            ]
        );
        $eventDispatcher->dispatch($entityDeleteEvent);
        $entityDeleteEvent->success();

        static::assertSame(1, $dispatchedCount);
    }
}
