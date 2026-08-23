<?php declare(strict_types=1);

namespace Contena\Core\Content\Sitemap\ScheduledTask;

use Psr\Log\LoggerInterface;
use Contena\Core\Content\Sitemap\Exception\AlreadyLockedException;
use Contena\Core\Content\Sitemap\Service\SitemapExporterInterface;
use Contena\Core\System\Channel\Context\AbstractChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[AsMessageHandler]
final readonly class SitemapMessageHandler
{
    /**
     * @internal
     */
    public function __construct(
        private AbstractChannelContextFactory $channelContextFactory,
        private SitemapExporterInterface $sitemapExporter,
        private LoggerInterface $logger,
        private SystemConfigService $systemConfigService,
    ) {
    }

    public function __invoke(SitemapMessage $message): void
    {
        $this->generate($message);
    }

    private function generate(SitemapMessage $message): void
    {
        if ($message->getLastChannelId() === null || $message->getLastLanguageId() === null) {
            return;
        }

        $channelContext = $this->channelContextFactory->create('', $message->getLastChannelId(), [ChannelContextService::LANGUAGE_ID => $message->getLastLanguageId()]);
        $context = $channelContext->getContext();
        if ($context->getTenantId() !== $message->getTenantId()) {
            $this->logger->error('Sitemap message tenant does not match the channel tenant.');

            return;
        }

        $sitemapRefreshStrategy = $this->systemConfigService->getInt('core.sitemap.sitemapRefreshStrategy', context: $context);
        if ($sitemapRefreshStrategy !== SitemapExporterInterface::STRATEGY_SCHEDULED_TASK) {
            return;
        }

        try {
            $this->sitemapExporter->generate($channelContext, true, $message->getLastProvider(), $message->getNextOffset());
        } catch (AlreadyLockedException $exception) {
            $this->logger->error(\sprintf('ERROR: %s', $exception->getMessage()));
        }
    }
}
