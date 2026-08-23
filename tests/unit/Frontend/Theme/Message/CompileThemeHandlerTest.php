<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Theme\Message;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Notification\NotificationService;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Contena\Core\Test\TestDefaults;
use Contena\Frontend\Theme\ConfigLoader\AbstractConfigLoader;
use Contena\Frontend\Theme\Event\ThemeAssignedEvent;
use Contena\Frontend\Theme\FrontendPluginRegistry;
use Contena\Frontend\Theme\Message\CompileThemeHandler;
use Contena\Frontend\Theme\Message\CompileThemeMessage;
use Contena\Frontend\Theme\ThemeCompiler;
use Contena\Frontend\Theme\ThemeRuntimeConfigService;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(CompileThemeHandler::class)]
class CompileThemeHandlerTest extends TestCase
{
    public function testHandleMessageCompile(): void
    {
        $themeCompilerMock = $this->createMock(ThemeCompiler::class);
        $notificationServiceMock = static::createStub(NotificationService::class);
        $themeId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $message = new CompileThemeMessage(TestDefaults::CHANNEL, $themeId, true, $context);

        $themeCompilerMock->expects($this->once())->method('compileTheme');

        $scEntity = new ChannelEntity();
        $scEntity->setUniqueIdentifier(Uuid::randomHex());
        $scEntity->setName('Test Channel');

        $channelRep = StaticEntityRepository::of(ChannelCollection::class, [new EntityCollection([$scEntity])]);

        // without the assign flag the relation must not be touched and no event dispatched
        $themeChannelRep = $this->createMock(EntityRepository::class);
        $themeChannelRep->expects($this->never())->method('upsert');

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->never())->method('dispatch');

        $handler = new CompileThemeHandler(
            $themeCompilerMock,
            static::createStub(AbstractConfigLoader::class),
            static::createStub(FrontendPluginRegistry::class),
            $notificationServiceMock,
            $channelRep,
            static::createStub(ThemeRuntimeConfigService::class),
            $themeChannelRep,
            $eventDispatcher,
            static::createStub(SystemConfigService::class),
        );

        $handler($message);
    }

    public function testHandleMessageAssignsThemeAfterCompile(): void
    {
        $themeCompilerMock = $this->createMock(ThemeCompiler::class);
        $themeId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $message = new CompileThemeMessage(TestDefaults::CHANNEL, $themeId, true, $context, true);

        $themeCompilerMock->expects($this->once())->method('compileTheme');

        /** @var StaticEntityRepository<ChannelCollection> $channelRep */
        $channelRep = new StaticEntityRepository([]);

        // the theme is still the latest requested one for the channel
        $systemConfigService = static::createStub(SystemConfigService::class);
        $systemConfigService->method('getString')->willReturn($themeId);

        // with the assign flag set, the relation is upserted after compilation ...
        $themeChannelRep = $this->createMock(EntityRepository::class);
        $themeChannelRep->expects($this->once())->method('upsert')->with(
            [[
                'themeId' => $themeId,
                'channelId' => TestDefaults::CHANNEL,
            ]],
            $context
        );

        // ... and the assignment event is dispatched so caches are invalidated
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())->method('dispatch')->with(
            new ThemeAssignedEvent($themeId, TestDefaults::CHANNEL, $context)
        );

        $handler = new CompileThemeHandler(
            $themeCompilerMock,
            static::createStub(AbstractConfigLoader::class),
            static::createStub(FrontendPluginRegistry::class),
            static::createStub(NotificationService::class),
            $channelRep,
            static::createStub(ThemeRuntimeConfigService::class),
            $themeChannelRep,
            $eventDispatcher,
            $systemConfigService,
        );

        $handler($message);
    }

    public function testHandleMessageRethrowsWhenCompilationFailsWithoutNotifying(): void
    {
        $themeId = Uuid::randomHex();
        // AdminApiSource -> USER_SCOPE, yet the handler itself must not notify: that happens once on
        // the terminal failure via CompileThemeFailedSubscriber, not on every retried attempt
        $context = Context::createDefaultContext(new AdminApiSource(Uuid::randomHex()));
        $message = new CompileThemeMessage(TestDefaults::CHANNEL, $themeId, true, $context, true);

        $themeCompiler = static::createStub(ThemeCompiler::class);
        $themeCompiler->method('compileTheme')->willThrowException(new \RuntimeException('compile failed'));

        // no notification is emitted from the handler on a failed compile ...
        $notificationService = $this->createMock(NotificationService::class);
        $notificationService->expects($this->never())->method('createNotification');

        // ... the assignment must not be applied when the compile failed ...
        $themeChannelRep = $this->createMock(EntityRepository::class);
        $themeChannelRep->expects($this->never())->method('upsert');

        /** @var StaticEntityRepository<ChannelCollection> $channelRep */
        $channelRep = new StaticEntityRepository([]);

        $handler = new CompileThemeHandler(
            $themeCompiler,
            static::createStub(AbstractConfigLoader::class),
            static::createStub(FrontendPluginRegistry::class),
            $notificationService,
            $channelRep,
            static::createStub(ThemeRuntimeConfigService::class),
            $themeChannelRep,
            static::createStub(EventDispatcherInterface::class),
            static::createStub(SystemConfigService::class),
        );

        // ... and the exception propagates so the messenger can retry / dead-letter the message
        $this->expectException(\RuntimeException::class);
        $handler($message);
    }

    public function testHandleMessageSkipsAssignmentWhenSupersededByNewerSwitch(): void
    {
        $themeId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $message = new CompileThemeMessage(TestDefaults::CHANNEL, $themeId, true, $context, true);

        // a newer switch to a different theme has been requested in the meantime ...
        $systemConfigService = static::createStub(SystemConfigService::class);
        $systemConfigService->method('getString')->willReturn(Uuid::randomHex());

        // ... so this stale message must be skipped BEFORE compiling: compiling would rotate the
        // shared per-channel seed and break the currently assigned theme's CSS path
        $themeCompilerMock = $this->createMock(ThemeCompiler::class);
        $themeCompilerMock->expects($this->never())->method('compileTheme');

        // and it must not be applied nor dispatch the event
        $themeChannelRep = $this->createMock(EntityRepository::class);
        $themeChannelRep->expects($this->never())->method('upsert');

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->never())->method('dispatch');

        /** @var StaticEntityRepository<ChannelCollection> $channelRep */
        $channelRep = new StaticEntityRepository([]);

        $handler = new CompileThemeHandler(
            $themeCompilerMock,
            static::createStub(AbstractConfigLoader::class),
            static::createStub(FrontendPluginRegistry::class),
            static::createStub(NotificationService::class),
            $channelRep,
            static::createStub(ThemeRuntimeConfigService::class),
            $themeChannelRep,
            $eventDispatcher,
            $systemConfigService,
        );

        $handler($message);
    }

    public function testHandleMessageSkipsAssignmentWhenSupersededDuringCompile(): void
    {
        $themeId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $message = new CompileThemeMessage(TestDefaults::CHANNEL, $themeId, true, $context, true);

        // still the latest requested theme when the handler starts, but a newer switch arrives
        // while compiling: first check passes, the re-check after compiling fails
        $systemConfigService = static::createStub(SystemConfigService::class);
        $systemConfigService->method('getString')->willReturnOnConsecutiveCalls($themeId, Uuid::randomHex());

        // so the theme is compiled, but the now-stale assignment is not applied
        $themeCompilerMock = $this->createMock(ThemeCompiler::class);
        $themeCompilerMock->expects($this->once())->method('compileTheme');

        $themeChannelRep = $this->createMock(EntityRepository::class);
        $themeChannelRep->expects($this->never())->method('upsert');

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->never())->method('dispatch');

        /** @var StaticEntityRepository<ChannelCollection> $channelRep */
        $channelRep = new StaticEntityRepository([]);

        $handler = new CompileThemeHandler(
            $themeCompilerMock,
            static::createStub(AbstractConfigLoader::class),
            static::createStub(FrontendPluginRegistry::class),
            static::createStub(NotificationService::class),
            $channelRep,
            static::createStub(ThemeRuntimeConfigService::class),
            $themeChannelRep,
            $eventDispatcher,
            $systemConfigService,
        );

        $handler($message);
    }
}
