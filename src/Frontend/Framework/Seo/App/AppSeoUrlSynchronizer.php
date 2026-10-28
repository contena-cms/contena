<?php declare(strict_types=1);

namespace Contena\Storefront\Framework\Seo\App;

use Contena\Core\Content\Seo\SeoUrlPersister;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Context\SystemSource;
use Contena\Core\Framework\App\Feature\AppFeatureStorage;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Locale\LanguageLocaleCodeProvider;
use Contena\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Contena\Core\System\SalesChannel\SalesChannelCollection;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[Package('inventory')]
class AppSeoUrlSynchronizer
{
    /**
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     */
    public function __construct(
        private readonly AppFeatureStorage $storage,
        private readonly EntityRepository $salesChannelRepository,
        private readonly SeoUrlPersister $seoUrlPersister,
        private readonly LanguageLocaleCodeProvider $languageLocaleProvider,
        private readonly RouterInterface $router,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function syncStaticRoutes(?string $appId = null): void
    {
        $seoUrls = $this->fetchSeoUrls($appId);

        if ($seoUrls === []) {
            return;
        }

        $pathInfos = [];
        foreach ($seoUrls as $seoUrl) {
            $pathInfos[$seoUrl->getRouteName()] = $this->generatePathInfo($seoUrl);
        }

        foreach ($this->fetchSalesChannels() as $salesChannel) {
            $languageIds = array_unique($salesChannel->getDomains()?->map(
                static fn (SalesChannelDomainEntity $domain): string => $domain->getLanguageId()
            ) ?? []);

            foreach ($languageIds as $languageId) {
                $context = new Context(new SystemSource(), [], Defaults::CURRENCY, array_values(array_unique([$languageId, Defaults::LANGUAGE_SYSTEM])));

                foreach ($seoUrls as $seoUrl) {
                    $this->write($seoUrl, $pathInfos[$seoUrl->getRouteName()], $salesChannel, $context);
                }
            }
        }
    }

    /**
     * @return list<AppSeoUrlConfig>
     */
    private function fetchSeoUrls(?string $appId): array
    {
        $seoUrls = [];

        foreach ($this->storage->forActiveApps(AppSeoUrlConfig::class) as $feature) {
            if ($appId === null || $feature->appId === $appId) {
                $seoUrls[] = $feature->config;
            }
        }

        return $seoUrls;
    }




    private function fetchSalesChannels(): SalesChannelCollection
    {
        $criteria = new Criteria();
        $criteria->setTitle('app-seo-url::static-sync');
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [
        ]));
        $criteria->addAssociation('domains');

        return $this->salesChannelRepository->search($criteria, Context::createDefaultContext())->getEntities();
    }
}
