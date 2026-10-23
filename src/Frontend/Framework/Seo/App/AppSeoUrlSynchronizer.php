<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Seo\App;

use Contena\Core\Content\Seo\SeoUrlPersister;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Context\SystemSource;
use Contena\Core\Framework\App\Feature\AppFeatureStorage;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainEntity;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\Locale\LanguageLocaleCodeProvider;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
class AppSeoUrlSynchronizer
{
    /**
     * @param EntityRepository<ChannelCollection> $channelRepository
     */
    public function __construct(
        private readonly AppFeatureStorage $storage,
        private readonly EntityRepository $channelRepository,
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

        foreach ($this->fetchChannels() as $channel) {
            $languageIds = array_unique($channel->getDomains()?->map(
                static fn (ChannelDomainEntity $domain): string => $domain->getLanguageId()
            ) ?? []);

            foreach ($languageIds as $languageId) {
                $context = new Context(new SystemSource(), [], Defaults::CURRENCY, array_values(array_unique([$languageId, Defaults::LANGUAGE_SYSTEM])));

                foreach ($seoUrls as $seoUrl) {
                    $this->write($seoUrl, $pathInfos[$seoUrl->getRouteName()], $channel, $context);
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

    private function write(AppSeoUrlConfig $seoUrl, string $pathInfo, ChannelEntity $channel, Context $context): void
    {
        $foreignKey = Uuid::fromStringToHex($seoUrl->getRouteName());

        $this->seoUrlPersister->forceUpdateSeoUrls(
            $context,
            $seoUrl->getRouteName(),
            [$foreignKey],
            [[
                'foreignKey' => $foreignKey,
                'pathInfo' => $pathInfo,
                'seoPathInfo' => $this->resolvePath($seoUrl, $context->getLanguageId()),
                'channelId' => $channel->getId(),
                'isCanonical' => true,
                'isModified' => true,
                'isDeleted' => false,
            ]],
            $channel
        );
    }

    private function generatePathInfo(AppSeoUrlConfig $seoUrl): string
    {
        $pathInfo = $this->router->generate(AppSeoUrlRoute::TARGET_ROUTE, ['hook' => $seoUrl->getHook()]);
        $basePath = $this->requestStack->getMainRequest()?->getBasePath() ?? '';

        if ($basePath === '' || !str_starts_with($pathInfo, $basePath)) {
            return $pathInfo;
        }

        return substr($pathInfo, \strlen($basePath));
    }

    private function resolvePath(AppSeoUrlConfig $seoUrl, string $languageId): string
    {
        $paths = $seoUrl->getPaths();
        $locale = $this->languageLocaleProvider->getLocaleForLanguageId(...);

        return $paths[$locale($languageId)] ?? $paths[$locale(Defaults::LANGUAGE_SYSTEM)] ?? (string) array_first($paths);
    }

    private function fetchChannels(): ChannelCollection
    {
        $criteria = new Criteria();
        $criteria->setTitle('app-seo-url::static-sync');
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [
        ]));
        $criteria->addAssociation('domains');

        return $this->channelRepository->search($criteria, Context::createDefaultContext())->getEntities();
    }
}
