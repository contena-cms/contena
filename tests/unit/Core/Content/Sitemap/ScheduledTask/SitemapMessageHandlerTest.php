<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Sitemap\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Contena\Core\Content\Sitemap\ScheduledTask\SitemapMessage;
use Contena\Core\Content\Sitemap\ScheduledTask\SitemapMessageHandler;
use Contena\Core\Content\Sitemap\Service\SitemapExporterInterface;
use Contena\Core\Content\Sitemap\Struct\SitemapGenerationResult;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\AbstractChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
#[CoversClass(SitemapMessageHandler::class)]
class SitemapMessageHandlerTest extends TestCase
{
    public function testUsesThePreservedTenantContextForConfigAndGeneration(): void
    {
        $tenantId = Uuid::randomHex();
        $channelId = Uuid::randomHex();
        $languageId = Uuid::randomHex();
        $tenantContext = Context::createTenantContext($tenantId);
        $channelContext = static::createStub(ChannelContext::class);
        $channelContext->method('getContext')->willReturn($tenantContext);

        $channelContextFactory = $this->createMock(AbstractChannelContextFactory::class);
        $channelContextFactory->expects($this->once())
            ->method('create')
            ->with('', $channelId, [ChannelContextService::LANGUAGE_ID => $languageId])
            ->willReturn($channelContext);

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects($this->once())
            ->method('getInt')
            ->with('core.sitemap.sitemapRefreshStrategy', null, $tenantContext)
            ->willReturn(SitemapExporterInterface::STRATEGY_SCHEDULED_TASK);

        $sitemapExporter = $this->createMock(SitemapExporterInterface::class);
        $sitemapExporter->expects($this->once())
            ->method('generate')
            ->with($channelContext, true, null, null)
            ->willReturn(new SitemapGenerationResult(true, null, null, $channelId, $languageId));

        $handler = new SitemapMessageHandler(
            $channelContextFactory,
            $sitemapExporter,
            static::createStub(LoggerInterface::class),
            $systemConfigService,
        );

        $handler(new SitemapMessage($channelId, $languageId, null, null, false, $tenantId));
    }

    public function testRejectsAMessageFromAnotherTenant(): void
    {
        $channelId = Uuid::randomHex();
        $languageId = Uuid::randomHex();
        $channelContext = static::createStub(ChannelContext::class);
        $channelContext->method('getContext')->willReturn(Context::createTenantContext(Uuid::randomHex()));

        $channelContextFactory = static::createStub(AbstractChannelContextFactory::class);
        $channelContextFactory->method('create')->willReturn($channelContext);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with('Sitemap message tenant does not match the channel tenant.');

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects($this->never())->method('getInt');
        $sitemapExporter = $this->createMock(SitemapExporterInterface::class);
        $sitemapExporter->expects($this->never())->method('generate');

        $handler = new SitemapMessageHandler(
            $channelContextFactory,
            $sitemapExporter,
            $logger,
            $systemConfigService,
        );

        $handler(new SitemapMessage($channelId, $languageId, null, null, false, Uuid::randomHex()));
    }
}
