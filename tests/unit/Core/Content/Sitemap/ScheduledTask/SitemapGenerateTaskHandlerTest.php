<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Sitemap\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Contena\Core\Content\Sitemap\Event\SitemapChannelCriteriaEvent;
use Contena\Core\Content\Sitemap\ScheduledTask\SitemapGenerateTaskHandler;
use Contena\Core\Content\Sitemap\ScheduledTask\SitemapMessage;
use Contena\Core\Content\Sitemap\Service\SitemapChannelProvider;
use Contena\Core\Content\Sitemap\Service\SitemapExporterInterface;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainCollection;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainEntity;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(SitemapGenerateTaskHandler::class)]
class SitemapGenerateTaskHandlerTest extends TestCase
{
    public function testPlatformTaskScansChannelsWithGlobalContext(): void
    {
        $channelProvider = $this->createMock(SitemapChannelProvider::class);
        $channelProvider->expects($this->once())
            ->method('getChannels')
            ->with(static::isInstanceOf(Criteria::class))
            ->willReturn((static function (): \Generator {
                yield from [];
            })());

        $systemConfigService = static::createStub(SystemConfigService::class);
        $systemConfigService->method('getInt')->willReturn(SitemapExporterInterface::STRATEGY_SCHEDULED_TASK);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::callback(static function (object $event): bool {
                static::assertInstanceOf(SitemapChannelCriteriaEvent::class, $event);

                return $event->getContext()->hasGlobalTenantAccess();
            }))
            ->willReturnArgument(0);

        $handler = new SitemapGenerateTaskHandler(
            static::createStub(EntityRepository::class),
            static::createStub(LoggerInterface::class),
            $channelProvider,
            $systemConfigService,
            static::createStub(MessageBusInterface::class),
            $eventDispatcher,
        );

        $handler->run();
    }

    public function testTenantChannelUsesTenantConfigAndPreservesTenantInMessage(): void
    {
        $tenantId = Uuid::randomHex();
        $channelId = Uuid::randomHex();
        $languageId = Uuid::randomHex();

        $domain = new ChannelDomainEntity();
        $domain->setId(Uuid::randomHex());
        $domain->setLanguageId($languageId);

        $channel = new ChannelEntity();
        $channel->setId($channelId);
        $channel->setTenantId($tenantId);
        $channel->setDomains(new ChannelDomainCollection([$domain]));

        $channelProvider = static::createStub(SitemapChannelProvider::class);
        $channelProvider->method('getChannels')->willReturn((static function () use ($channel): \Generator {
            yield $channel;
        })());

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects($this->once())
            ->method('getInt')
            ->with(
                'core.sitemap.sitemapRefreshStrategy',
                null,
                static::callback(static fn (Context $context): bool => $context->getTenantId() === $tenantId),
            )
            ->willReturn(SitemapExporterInterface::STRATEGY_SCHEDULED_TASK);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->once())
            ->method('dispatch')
            ->with(static::callback(static function (object $message) use ($channelId, $languageId, $tenantId): bool {
                static::assertInstanceOf(SitemapMessage::class, $message);
                static::assertSame($channelId, $message->getLastChannelId());
                static::assertSame($languageId, $message->getLastLanguageId());
                static::assertSame($tenantId, $message->getTenantId());

                return true;
            }))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));

        $handler = new SitemapGenerateTaskHandler(
            static::createStub(EntityRepository::class),
            static::createStub(LoggerInterface::class),
            $channelProvider,
            $systemConfigService,
            $messageBus,
            static::createStub(EventDispatcherInterface::class),
        );

        $handler->run();
    }
}
