<?php declare(strict_types=1);

namespace Contena\Core\Content\Sitemap\Service;

use Contena\Core\Content\Sitemap\Event\SitemapSalesChannelCriteriaEvent;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Contena\Core\System\SalesChannel\SalesChannelCollection;
use Contena\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Determines which sales channels and languages sitemaps are generated for.
 *
 * @internal
 */
#[Package('discovery')]
class SitemapSalesChannelLoader
{
    /**
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     */
    public function __construct(
        private readonly EntityRepository $salesChannelRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * Sitemaps are generated for storefront sales channels and for headless (API type) sales channels
     * with a domain flagged as external storefront.
     */
    public function loadSalesChannels(Context $context, ?string $salesChannelId = null): SalesChannelCollection
    {
        $criteria = $salesChannelId ? new Criteria([$salesChannelId]) : new Criteria();
        $criteria->addAssociation('domains');
        $criteria->addFilter(new NotEqualsFilter('domains.id', null));

        $criteria->addAssociation('type');
        $criteria->addFilter(new OrFilter([
        ]));

        $this->eventDispatcher->dispatch(new SitemapSalesChannelCriteriaEvent($criteria, $context));

        return $this->salesChannelRepository->search($criteria, $context)->getEntities();
    }

    /**
     * For headless sales channels only languages with an external storefront domain generate sitemap entries.
     *
     * @return list<string>
     */
    public function getLanguageIds(SalesChannelEntity $salesChannel): array
    {
        $domains = $salesChannel->getDomains();
        if ($domains === null) {
            return [];
        }

        if ($salesChannel->getTypeId() === Defaults::SALES_CHANNEL_TYPE_API) {
            $domains = $domains->filter(static fn (SalesChannelDomainEntity $domain) => $domain->getIsExternalStorefront());
        }

        return array_values(array_unique($domains->map(static fn (SalesChannelDomainEntity $domain) => $domain->getLanguageId())));
    }
}
