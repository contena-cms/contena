<?php declare(strict_types=1);

namespace Contena\Core\Content\Sitemap\ScheduledTask;

use Psr\Log\LoggerInterface;
use Contena\Core\Content\Sitemap\Event\SitemapChannelCriteriaEvent;
use Contena\Core\Content\Sitemap\Service\SitemapChannelProvider;
use Contena\Core\Content\Sitemap\Service\SitemapExporterInterface;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainEntity;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

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
        private readonly SitemapChannelProvider $channelProvider,
        private readonly SystemConfigService $systemConfigService,
        private readonly MessageBusInterface $messageBus,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        $criteria = new Criteria();
        $criteria->addAssociation('domains');
        $criteria->addFilter(new NotEqualsFilter('domains.id', null));

        $criteria->addAssociation('type');
        $criteria->addFilter(new EqualsFilter('type.id', Defaults::CHANNEL_TYPE_WEB));

        $context = Context::createCLIContext();

        $this->eventDispatcher->dispatch(
            new SitemapChannelCriteriaEvent($criteria, $context)
        );

        foreach ($this->channelProvider->getChannels($criteria) as $channel) {
            $tenantId = $channel->getTenantId();
            $context = $tenantId === null ? Context::createCLIContext() : Context::createTenantContext($tenantId);
            if (!$this->usesScheduledGeneration($context)) {
                continue;
            }

            if ($channel->getDomains() === null) {
                continue;
            }

            $languageIds = $channel->getDomains()->map(static fn (ChannelDomainEntity $channelDomain) => $channelDomain->getLanguageId());

            $languageIds = array_unique($languageIds);

            foreach ($languageIds as $languageId) {
                $this->messageBus->dispatch(new SitemapMessage($channel->getId(), $languageId, null, null, false, $tenantId));
            }
        }
    }

    private function usesScheduledGeneration(Context $context): bool
    {
        return $this->systemConfigService->getInt('core.sitemap.sitemapRefreshStrategy', context: $context)
            === SitemapExporterInterface::STRATEGY_SCHEDULED_TASK;
    }
}
