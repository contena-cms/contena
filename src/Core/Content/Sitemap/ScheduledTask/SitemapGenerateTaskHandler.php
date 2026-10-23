<?php declare(strict_types=1);

namespace Contena\Core\Content\Sitemap\ScheduledTask;

use Psr\Log\LoggerInterface;
use Contena\Core\Content\Sitemap\Service\SitemapChannelLoader;
use Contena\Core\Content\Sitemap\Service\SitemapExporterInterface;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[AsMessageHandler(handles: SitemapGenerateTask::class)]
final class SitemapGenerateTaskHandler extends ScheduledTaskHandler
{
    /**
     * @internal
     *
     * @param EntityRepository<ScheduledTaskCollection> $scheduledTaskRepository
     */
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        private readonly SitemapChannelLoader $channelLoader,
        private readonly SystemConfigService $systemConfigService,
        private readonly MessageBusInterface $messageBus
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        $sitemapRefreshStrategy = $this->systemConfigService->getInt('core.sitemap.sitemapRefreshStrategy');
        if ($sitemapRefreshStrategy !== SitemapExporterInterface::STRATEGY_SCHEDULED_TASK) {
            return;
        }

        $channels = $this->channelLoader->loadChannels(Context::createCLIContext());

        foreach ($channels as $channel) {
            foreach ($this->channelLoader->getLanguageIds($channel) as $languageId) {
                $this->messageBus->dispatch(new SitemapMessage($channel->getId(), $languageId, null, null, false));
            }
        }
    }
}
