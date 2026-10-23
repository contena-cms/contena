<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\AdminUser\Recovery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Context\SystemSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\AdminUser\AdminUserCollection;
use Contena\Core\System\AdminUser\AdminUserDefinition;
use Contena\Core\System\AdminUser\AdminUserEntity;
use Contena\Core\System\AdminUser\AdminUserException;
use Contena\Core\System\AdminUser\Aggregate\AdminUserRecovery\AdminUserRecoveryCollection;
use Contena\Core\System\AdminUser\Aggregate\AdminUserRecovery\AdminUserRecoveryDefinition;
use Contena\Core\System\AdminUser\Aggregate\AdminUserRecovery\AdminUserRecoveryEntity;
use Contena\Core\System\AdminUser\Recovery\AdminUserRecoveryRequestEvent;
use Contena\Core\System\AdminUser\Recovery\AdminUserRecoveryService;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelDefinition;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\RouterInterface;
use Contena\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;

/**
 * @internal
 */
#[CoversClass(AdminUserRecoveryService::class)]
class AdminUserRecoveryServiceTest extends TestCase
{
    use EnvTestBehaviour;

    private const HASH = 'Ynp1oKlXNlLRnjTHVCXBSLnFmQCLLbNe';

    protected function setUp(): void
    {
        Request::setTrustedHosts([]);
        $this->setEnvVars([
            'APP_URL' => 'https://shop.example.com',
            'CONTENA_ADMINISTRATION_PATH_NAME' => null,
        ]);
    }

    protected function tearDown(): void
    {
        Request::setTrustedHosts([]);
    }

    public function testGenerateUserRecoveryUserNotFound(): void
    {
        $router = static::createStub(RouterInterface::class);
        $channelContextService = static::createStub(ChannelContextService::class);

        $adminUserEmail = 'nonexistent@example.com';
        $context = new Context(new SystemSource(), [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);
        $recoveryEntity = new AdminUserRecoveryEntity();
        $recoveryEntity->setUniqueIdentifier(Uuid::randomHex());

        $adminUserRepository = new StaticEntityRepository([
            new AdminUserCollection([]),
        ], new AdminUserDefinition());

        $recoveryRepository = new StaticEntityRepository([
            new AdminUserRecoveryCollection([$recoveryEntity]),
            new AdminUserRecoveryCollection([$recoveryEntity]),
        ], new AdminUserRecoveryDefinition());

        $channelRepository = new StaticEntityRepository([
            new ChannelCollection([]),
        ], new ChannelDefinition());

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects($this->never())
            ->method('dispatch');

        $service = new AdminUserRecoveryService(
            $recoveryRepository,
            $adminUserRepository,
            $router,
            $dispatcher,
            $channelContextService,
            $channelRepository,
            new NativeClock()
        );

        $service->generateUserRecovery($adminUserEmail, $context);
        static::assertCount(0, $recoveryRepository->creates);
        static::assertCount(0, $recoveryRepository->deletes);
    }

    public function testGenerateUserRecoveryWithNoChannel(): void
    {
        $router = static::createStub(RouterInterface::class);
        $channelContextService = static::createStub(ChannelContextService::class);

        $this->expectExceptionObject(AdminUserException::channelNotFound());

        $adminUserEmail = 'existing@example.com';
        $context = new Context(new SystemSource(), [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);
        $adminUser = new AdminUserEntity();
        $adminUser->setUniqueIdentifier(Uuid::randomHex());
        $adminUser->setId(Uuid::randomHex());

        $recoveryEntity = new AdminUserRecoveryEntity();
        $recoveryEntity->setUniqueIdentifier(Uuid::randomHex());
        $recoveryEntity->setId(Uuid::randomHex());
        $recoveryEntity->setHash(Uuid::randomHex());

        $adminUserRepository = new StaticEntityRepository([
            new AdminUserCollection([$adminUser]),
        ], new AdminUserDefinition());

        $recoveryRepository = new StaticEntityRepository([
            new AdminUserRecoveryCollection([$recoveryEntity]),
            new AdminUserRecoveryCollection([$recoveryEntity]),
        ], new AdminUserRecoveryDefinition());

        $channelRepository = new StaticEntityRepository([
            new ChannelCollection([]),
        ], new ChannelDefinition());

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects($this->never())
            ->method('dispatch');

        $service = new AdminUserRecoveryService(
            $recoveryRepository,
            $adminUserRepository,
            $router,
            $dispatcher,
            $channelContextService,
            $channelRepository,
            new NativeClock()
        );

        try {
            $service->generateUserRecovery($adminUserEmail, $context);
        } finally {
            static::assertCount(1, $recoveryRepository->creates);
            static::assertCount(1, $recoveryRepository->deletes);
        }
    }

    public function testGenerateUserRecoveryWithExistingRecovery(): void
    {
        $router = static::createStub(RouterInterface::class);
        $channelContextService = $this->createMock(ChannelContextService::class);

        $adminUserEmail = 'existing@example.com';
        $context = new Context(new SystemSource(), [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);
        $adminUser = new AdminUserEntity();
        $recoveryEntity = new AdminUserRecoveryEntity();
        $adminUser->setUniqueIdentifier(Uuid::randomHex());
        $adminUser->setId(Uuid::randomHex());
        $recoveryEntity->setUniqueIdentifier(Uuid::randomHex());
        $recoveryEntity->setId(Uuid::randomHex());
        $recoveryEntity->setHash(Uuid::randomHex());
        $channelEntity = new ChannelEntity();
        $channelEntity->setUniqueIdentifier(Uuid::randomHex());
        $channelEntity->setId(Uuid::randomHex());
        $channelEntity->setLanguageId(Uuid::randomHex());
        $channelEntity->setCurrencyId(Uuid::randomHex());

        $adminUserRepository = new StaticEntityRepository([
            new AdminUserCollection([$adminUser]),
        ], new AdminUserDefinition());

        $recoveryRepository = new StaticEntityRepository([
            new AdminUserRecoveryCollection([$recoveryEntity]),
            new AdminUserRecoveryCollection([$recoveryEntity]),
        ], new AdminUserRecoveryDefinition());

        $channelRepository = new StaticEntityRepository([
            new ChannelCollection([$channelEntity]),
        ], new ChannelDefinition());

        $channelContextService
            ->expects($this->once())
            ->method('get')
            ->willReturn(static::createStub(ChannelContext::class));

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                static::isInstanceOf(AdminUserRecoveryRequestEvent::class),
                AdminUserRecoveryRequestEvent::EVENT_NAME
            );

        $service = new AdminUserRecoveryService(
            $recoveryRepository,
            $adminUserRepository,
            $router,
            $dispatcher,
            $channelContextService,
            $channelRepository,
            new NativeClock()
        );

        $service->generateUserRecovery($adminUserEmail, $context);
        static::assertCount(1, $recoveryRepository->deletes);
        static::assertCount(1, $recoveryRepository->creates);
    }

    public function testGenerateUserRecoveryWithoutExistingRecovery(): void
    {
        $router = static::createStub(RouterInterface::class);
        $channelContextService = $this->createMock(ChannelContextService::class);

        $adminUserEmail = 'existing@example.com';
        $context = new Context(new SystemSource(), [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);
        $adminUser = new AdminUserEntity();
        $recoveryEntity = new AdminUserRecoveryEntity();
        $channelEntity = new ChannelEntity();
        $channelEntity->setUniqueIdentifier(Uuid::randomHex());
        $channelEntity->setId(Uuid::randomHex());
        $channelEntity->setLanguageId(Uuid::randomHex());
        $channelEntity->setCurrencyId(Uuid::randomHex());
        $adminUser->setUniqueIdentifier(Uuid::randomHex());
        $adminUser->setId(Uuid::randomHex());
        $recoveryEntity->setUniqueIdentifier(Uuid::randomHex());
        $recoveryEntity->setHash(Uuid::randomHex());

        $adminUserRepository = new StaticEntityRepository([
            new AdminUserCollection([$adminUser]),
        ], new AdminUserDefinition());

        $recoveryRepository = new StaticEntityRepository([
            new AdminUserRecoveryCollection([]),
            new AdminUserRecoveryCollection([$recoveryEntity]),
        ], new AdminUserRecoveryDefinition());

        $channelRepository = new StaticEntityRepository([
            static function (Criteria $criteria, Context $context) use ($channelEntity) {
                static::assertCount(1, $criteria->getFilters());
                static::assertEquals([
                ], $criteria->getFilters());

                return new ChannelCollection([$channelEntity]);
            },
        ], new ChannelDefinition());

        $channelContextService
            ->expects($this->once())
            ->method('get')
            ->willReturn(static::createStub(ChannelContext::class));

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                static::isInstanceOf(AdminUserRecoveryRequestEvent::class),
                AdminUserRecoveryRequestEvent::EVENT_NAME
            );

        $service = new AdminUserRecoveryService(
            $recoveryRepository,
            $adminUserRepository,
            $router,
            $dispatcher,
            $channelContextService,
            $channelRepository,
            new NativeClock()
        );

        $service->generateUserRecovery($adminUserEmail, $context);
        static::assertCount(0, $recoveryRepository->deletes);
        static::assertCount(1, $recoveryRepository->creates);
    }

    public function testRecoveryUrlIsGeneratedByRouterWhenTrustedHostsAreConfigured(): void
    {
        Request::setTrustedHosts(['shop.example.com']);

        $router = static::createStub(RouterInterface::class);
        $router->method('generate')->willReturn('https://shop.example.com/admin');

        static::assertSame(
            'https://shop.example.com/admin#/login/admin_user-recovery/' . self::HASH,
            $this->getGeneratedRecoveryUrl($router)
        );
    }

    public function testRecoveryUrlFallsBackToAppUrlWhenAdministrationRouteIsNotRegistered(): void
    {
        Request::setTrustedHosts(['shop.example.com']);

        $router = static::createStub(RouterInterface::class);
        $router->method('generate')->willThrowException(new RouteNotFoundException());

        static::assertSame(
            'https://shop.example.com/admin#/login/admin_user-recovery/' . self::HASH,
            $this->getGeneratedRecoveryUrl($router)
        );
    }

    public function testRouterIsNeverCalledWhenNoTrustedHostsAreConfigured(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->expects($this->never())->method('generate');

        static::assertSame(
            'https://shop.example.com/admin#/login/admin_user-recovery/' . self::HASH,
            $this->getGeneratedRecoveryUrl($router)
        );
    }

    public function testAppUrlIsNotValidatedWhileTheRouterProvidesTheUrl(): void
    {
        Request::setTrustedHosts(['shop.example.com']);
        $this->setEnvVars(['APP_URL' => 'not-a-url']);

        $router = static::createStub(RouterInterface::class);
        $router->method('generate')->willReturn('https://shop.example.com/admin');

        static::assertSame(
            'https://shop.example.com/admin#/login/admin_user-recovery/' . self::HASH,
            $this->getGeneratedRecoveryUrl($router)
        );
    }

    public function testRecoveryUrlThrowsWhenAppUrlIsInvalidAndAdministrationRouteIsNotRegistered(): void
    {
        Request::setTrustedHosts(['shop.example.com']);
        $this->setEnvVars(['APP_URL' => 'not-a-url']);

        $router = static::createStub(RouterInterface::class);
        $router->method('generate')->willThrowException(new RouteNotFoundException());

        $this->expectExceptionObject(AdminUserException::invalidAppUrl('not-a-url'));

        $this->getGeneratedRecoveryUrl($router);
    }

    public function testRecoveryUrlUsesConfiguredAdministrationPathName(): void
    {
        $this->setEnvVars([
            'APP_URL' => 'https://shop.example.com/',
            'CONTENA_ADMINISTRATION_PATH_NAME' => '/backoffice/',
        ]);

        static::assertSame(
            'https://shop.example.com/backoffice#/login/admin_user-recovery/' . self::HASH,
            $this->getGeneratedRecoveryUrl(static::createStub(RouterInterface::class))
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidAppUrlProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'no scheme' => ['shop.example.com'];
        yield 'unsupported scheme' => ['ftp://shop.example.com'];
        yield 'javascript scheme' => ['javascript://shop.example.com/%0aalert(1)'];
    }

    #[DataProvider('invalidAppUrlProvider')]
    public function testRecoveryUrlThrowsWhenAppUrlIsNotAValidHttpUrl(string $appUrl): void
    {
        $this->setEnvVars(['APP_URL' => $appUrl]);

        $this->expectExceptionObject(AdminUserException::invalidAppUrl(rtrim($appUrl, '/')));

        $this->getGeneratedRecoveryUrl(static::createStub(RouterInterface::class));
    }

    private function getGeneratedRecoveryUrl(RouterInterface $router): string
    {
        $adminUser = new AdminUserEntity();
        $adminUser->setUniqueIdentifier(Uuid::randomHex());
        $adminUser->setId(Uuid::randomHex());

        $recoveryEntity = new AdminUserRecoveryEntity();
        $recoveryEntity->setUniqueIdentifier(Uuid::randomHex());
        $recoveryEntity->setId(Uuid::randomHex());
        $recoveryEntity->setHash(self::HASH);

        $channelEntity = new ChannelEntity();
        $channelEntity->setUniqueIdentifier(Uuid::randomHex());
        $channelEntity->setId(Uuid::randomHex());
        $channelEntity->setLanguageId(Uuid::randomHex());
        $channelEntity->setCurrencyId(Uuid::randomHex());

        $channelContextService = static::createStub(ChannelContextService::class);
        $channelContextService
            ->method('get')
            ->willReturn(static::createStub(ChannelContext::class));

        $recoveryUrl = null;
        $dispatcher = static::createStub(EventDispatcherInterface::class);
        $dispatcher
            ->method('dispatch')
            ->willReturnCallback(function (AdminUserRecoveryRequestEvent $event) use (&$recoveryUrl) {
                $recoveryUrl = $event->getResetUrl();

                return $event;
            });

        /** @var StaticEntityRepository<AdminUserRecoveryCollection> $recoveryRepository */
        $recoveryRepository = new StaticEntityRepository([
            new AdminUserRecoveryCollection([]),
            new AdminUserRecoveryCollection([$recoveryEntity]),
        ], new AdminUserRecoveryDefinition());

        /** @var StaticEntityRepository<AdminUserCollection> $adminUserRepository */
        $adminUserRepository = new StaticEntityRepository([
            new AdminUserCollection([$adminUser]),
        ], new AdminUserDefinition());

        /** @var StaticEntityRepository<ChannelCollection> $channelRepository */
        $channelRepository = new StaticEntityRepository([
            new ChannelCollection([$channelEntity]),
        ], new ChannelDefinition());

        $service = new AdminUserRecoveryService(
            $recoveryRepository,
            $adminUserRepository,
            $router,
            $dispatcher,
            $channelContextService,
            $channelRepository,
            new NativeClock()
        );

        $service->generateUserRecovery('existing@example.com', new Context(new SystemSource(), [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]));

        static::assertIsString($recoveryUrl);

        return $recoveryUrl;
    }
}
