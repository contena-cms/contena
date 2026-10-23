<?php declare(strict_types=1);

namespace Contena\Core\Content\Sitemap\Service;

use Contena\Core\Content\Sitemap\Event\SitemapChannelCriteriaEvent;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainEntity;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Channel\ChannelEntity;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Determines which sales channels and languages sitemaps are generated for.
 *
 * @internal
 */
class SitemapChannelLoader
{
    /**
     * @param EntityRepository<ChannelCollection> $channelRepository
     */
    public function __construct(
        private readonly EntityRepository $channelRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * Sitemaps are generated for frontend sales channels and for headless (API type) sales channels
     * with a domain flagged as external frontend.
     */
    public function loadChannels(Context $context, ?string $channelId = null): ChannelCollection
    {
        $criteria = $channelId ? new Criteria([$channelId]) : new Criteria();
        $criteria->addAssociation('domains');
        $criteria->addFilter(new NotEqualsFilter('domains.id', null));

        $criteria->addAssociation('type');
        $criteria->addFilter(new OrFilter([
            new EqualsFilter('type.id', Defaults::CHANNEL_TYPE_FRONTEND),
            new AndFilter([
                new EqualsFilter('type.id', Defaults::CHANNEL_TYPE_API),
                new EqualsFilter('domains.isExternalFrontend', true),
            ]),
        ]));

        $this->eventDispatcher->dispatch(new SitemapChannelCriteriaEvent($criteria, $context));

        return $this->channelRepository->search($criteria, $context)->getEntities();
    }

    /**
     * For headless sales channels only languages with an external frontend domain generate sitemap entries.
     *
     * @return list<string>
     */
    public function getLanguageIds(ChannelEntity $channel): array
    {
        $domains = $channel->getDomains();
        if ($domains === null) {
            return [];
        }

        if ($channel->getTypeId() === Defaults::CHANNEL_TYPE_API) {
            $domains = $domains->filter(static fn (ChannelDomainEntity $domain) => $domain->getIsExternalFrontend());
        }

        return array_values(array_unique($domains->map(static fn (ChannelDomainEntity $domain) => $domain->getLanguageId())));
    }
}
