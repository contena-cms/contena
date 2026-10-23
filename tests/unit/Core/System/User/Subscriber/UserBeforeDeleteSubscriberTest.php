<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserDefinition;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\Event\UserDeletedEvent;
use Contena\Core\System\User\Subscriber\UserBeforeDeleteSubscriber;
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
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\Serializer\StructNormalizer;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Language\LanguageCollection;
use Contena\Core\System\Language\LanguageEntity;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Serializer\Serializer;

/**
 * @internal
 */
#[CoversClass(UserBeforeDeleteSubscriber::class)]
class UserBeforeDeleteSubscriberTest extends TestCase
{
    public function testEventsDispatched(): void
    {
        $userId = Uuid::randomBytes();
        $user = (new UserEntity())
            ->assign([
                'id' => Uuid::fromBytesToHex($userId),
                'channelId' => Uuid::randomHex(),
                'languageId' => Uuid::randomHex(),
                'userNumber' => 'SW1000',
                'email' => 'foo@bar.com',
                'firstName' => 'foo',
                'lastName' => 'bar',
            ]);

        $definitionInstanceRegistry = static::createStub(DefinitionInstanceRegistry::class);

        $userDefinition = new UserDefinition();
        $userDefinition->compile($definitionInstanceRegistry);

        $userRepository = new StaticEntityRepository([
            new EntitySearchResult(
                'user',
                1,
                new UserCollection([$user]),
                null,
                new Criteria([$userId]),
                Context::createDefaultContext()
            ),
        ], $userDefinition);

        $channelId = $user->getChannelId();
        $languageId = $user->getLanguageId();
        $language = (new LanguageEntity())->assign(['id' => $languageId]);
        $channel = (new ChannelEntity())->assign([
            'id' => $channelId,
            'languages' => new LanguageCollection([$language]),
        ]);
        $channelRepository = new StaticEntityRepository([
            new ChannelCollection([$channel]),
        ]);

        $channelContextService = static::createStub(ChannelContextService::class);
        $channelContextService->method('get')->willReturn(Generator::generateChannelContext());

        $eventDispatcher = new EventDispatcher();

        $structNormalizer = new StructNormalizer();

        $jsonEntityEncoder = new JsonEntityEncoder(new Serializer([$structNormalizer], []));

        $subscriber = new UserBeforeDeleteSubscriber(
            $userRepository,
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
                    $userDefinition,
                    ['id' => $userId],
                    new EntityExistence(
                        'user',
                        ['id' => $userId],
                        true,
                        false,
                        false,
                        [
                            'exists' => true,
                            'id' => $userId,
                        ]
                    )
                ),
            ]
        );

        $userDeletedEventCount = 0;

        $serializedUser = $jsonEntityEncoder->encode(
            new Criteria(),
            $userDefinition,
            $user,
            '/api/user'
        );

        $eventDispatcher->addListener(
            UserDeletedEvent::class,
            static function (UserDeletedEvent $event) use (&$userDeletedEventCount, $user, $serializedUser): void {
                ++$userDeletedEventCount;
                static::assertSame($user, $event->getUser());
                $values = $event->getValues();
                static::assertArrayHasKey('user', $values);
                static::assertSame($serializedUser, $values['user']);
            }
        );

        $eventDispatcher->dispatch($entityDeleteEvent);
        $entityDeleteEvent->success();

        static::assertSame(1, $userDeletedEventCount);
    }

    public function testBeforeDeleteWithEmptyUserIdsDoesNotDispatch(): void
    {
        $definitionInstanceRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $userDefinition = new UserDefinition();
        $userDefinition->compile($definitionInstanceRegistry);

        $userRepository = new StaticEntityRepository([], $userDefinition);
        $channelRepository = new StaticEntityRepository([]);
        $channelContextService = static::createStub(ChannelContextService::class);
        $eventDispatcher = new EventDispatcher();
        $jsonEntityEncoder = static::createStub(JsonEntityEncoder::class);

        $subscriber = new UserBeforeDeleteSubscriber(
            $userRepository,
            $channelRepository,
            $channelContextService,
            $eventDispatcher,
            $jsonEntityEncoder
        );
        $eventDispatcher->addSubscriber($subscriber);

        $caughtEvents = 0;
        $eventDispatcher->addListener(
            UserDeletedEvent::class,
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
        $userId = Uuid::randomBytes();
        $channelIdFromSource = Uuid::randomHex();
        $languageId = Uuid::randomHex();
        $user = (new UserEntity())
            ->assign([
                'id' => Uuid::fromBytesToHex($userId),
                'channelId' => Uuid::randomHex(),
                'languageId' => $languageId,
                'userNumber' => 'SW1001',
                'email' => 'bar@baz.com',
                'firstName' => 'bar',
                'lastName' => 'baz',
            ]);

        $definitionInstanceRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $userDefinition = new UserDefinition();
        $userDefinition->compile($definitionInstanceRegistry);

        $userRepository = new StaticEntityRepository([
            new EntitySearchResult(
                'user',
                1,
                new UserCollection([$user]),
                null,
                new Criteria([$userId]),
                Context::createDefaultContext()
            ),
        ], $userDefinition);

        $language = (new LanguageEntity())->assign(['id' => $languageId]);
        $channel = (new ChannelEntity())->assign([
            'id' => $channelIdFromSource,
            'languages' => new LanguageCollection([$language]),
        ]);
        $channelRepository = new StaticEntityRepository([
            new ChannelCollection([$channel]),
        ]);

        $channelContextService = static::createStub(ChannelContextService::class);
        $channelContextService->method('get')->willReturn(Generator::generateChannelContext());
        $eventDispatcher = new EventDispatcher();
        $jsonEntityEncoder = new JsonEntityEncoder(new Serializer([new StructNormalizer()], []));

        $subscriber = new UserBeforeDeleteSubscriber(
            $userRepository,
            $channelRepository,
            $channelContextService,
            $eventDispatcher,
            $jsonEntityEncoder
        );
        $eventDispatcher->addSubscriber($subscriber);

        $dispatchedCount = 0;
        $eventDispatcher->addListener(
            UserDeletedEvent::class,
            static function () use (&$dispatchedCount): void {
                ++$dispatchedCount;
            }
        );

        $context = Context::createDefaultContext(new ChannelApiSource($channelIdFromSource));
        $entityDeleteEvent = EntityDeleteEvent::create(
            WriteContext::createFromContext($context),
            [
                new DeleteCommand(
                    $userDefinition,
                    ['id' => $userId],
                    new EntityExistence(
                        'user',
                        ['id' => $userId],
                        true,
                        false,
                        false,
                        ['exists' => true, 'id' => $userId]
                    )
                ),
            ]
        );
        $eventDispatcher->dispatch($entityDeleteEvent);
        $entityDeleteEvent->success();

        static::assertSame(1, $dispatchedCount);
    }

    public function testBeforeDeleteWhenChannelDoesNotHaveUserLanguageUsesNullLanguageId(): void
    {
        $userId = Uuid::randomBytes();
        $channelId = Uuid::randomHex();
        $userLanguageId = Uuid::randomHex();
        $otherLanguageId = Uuid::randomHex();
        $user = (new UserEntity())
            ->assign([
                'id' => Uuid::fromBytesToHex($userId),
                'channelId' => $channelId,
                'languageId' => $userLanguageId,
                'userNumber' => 'SW1002',
                'email' => 'nolang@test.com',
                'firstName' => 'No',
                'lastName' => 'Lang',
            ]);

        $definitionInstanceRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $userDefinition = new UserDefinition();
        $userDefinition->compile($definitionInstanceRegistry);

        $userRepository = new StaticEntityRepository([
            new EntitySearchResult(
                'user',
                1,
                new UserCollection([$user]),
                null,
                new Criteria([$userId]),
                Context::createDefaultContext()
            ),
        ], $userDefinition);

        $channelHasOnlyOtherLanguage = (new LanguageEntity())->assign(['id' => $otherLanguageId]);
        $channel = (new ChannelEntity())->assign([
            'id' => $channelId,
            'languages' => new LanguageCollection([$channelHasOnlyOtherLanguage]),
        ]);
        $channelRepository = new StaticEntityRepository([
            new ChannelCollection([$channel]),
        ]);

        $channelContextService = static::createStub(ChannelContextService::class);
        $channelContextService->method('get')->willReturn(Generator::generateChannelContext());
        $eventDispatcher = new EventDispatcher();
        $jsonEntityEncoder = new JsonEntityEncoder(new Serializer([new StructNormalizer()], []));

        $subscriber = new UserBeforeDeleteSubscriber(
            $userRepository,
            $channelRepository,
            $channelContextService,
            $eventDispatcher,
            $jsonEntityEncoder
        );
        $eventDispatcher->addSubscriber($subscriber);

        $dispatchedCount = 0;
        $eventDispatcher->addListener(
            UserDeletedEvent::class,
            static function () use (&$dispatchedCount): void {
                ++$dispatchedCount;
            }
        );

        $entityDeleteEvent = EntityDeleteEvent::create(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                new DeleteCommand(
                    $userDefinition,
                    ['id' => $userId],
                    new EntityExistence(
                        'user',
                        ['id' => $userId],
                        true,
                        false,
                        false,
                        ['exists' => true, 'id' => $userId]
                    )
                ),
            ]
        );
        $eventDispatcher->dispatch($entityDeleteEvent);
        $entityDeleteEvent->success();

        static::assertSame(1, $dispatchedCount);
    }
}
