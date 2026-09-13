<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Seo\App;

use Contena\Core\Content\Seo\SeoUrlPersister;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Context\SystemSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Channel\ChannelEntity;

/**
 * @internal
 */
class AppStaticSeoUrlSynchronizer
{
    private const FALLBACK_LOCALE = 'en-GB';

    /**
     * @param EntityRepository<ChannelCollection> $channelRepository
     */
    public function __construct(
        private readonly AppSeoUrlRouteLoader $routeLoader,
        private readonly EntityRepository $channelRepository,
        private readonly SeoUrlPersister $seoUrlPersister,
    ) {
    }

    public function sync(?string $appId = null): void
    {
        $routes = $this->routeLoader->getStaticRoutes($appId);

        if ($routes === []) {
            return;
        }

        foreach ($this->fetchChannels() as $channel) {
            foreach ($this->localesByLanguage($channel) as $languageId => $localeCode) {
                $context = new Context(new SystemSource(), [$languageId, Defaults::LANGUAGE_SYSTEM]);

                foreach ($routes as $route) {
                    $path = $this->resolvePath($route['paths'], $localeCode);
                    $foreignKey = Uuid::fromStringToHex($route['routeName']);

                    $this->seoUrlPersister->forceUpdateSeoUrls(
                        $context,
                        $route['routeName'],
                        [$foreignKey],
                        [[
                            'foreignKey' => $foreignKey,
                            'pathInfo' => AppSeoUrlRoute::PATH_PREFIX . $route['hook'],
                            'seoPathInfo' => $path,
                            'channelId' => $channel->getId(),
                            'isCanonical' => true,
                            'isModified' => true,
                            'isDeleted' => false,
                        ]],
                        $channel
                    );
                }
            }
        }
    }

    private function fetchChannels(): ChannelCollection
    {
        $criteria = new Criteria();
        $criteria->setTitle('app-seo-url-routes::static-sync');
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [
            new EqualsFilter('typeId', Defaults::CHANNEL_TYPE_API),
        ]));
        $criteria->addAssociation('domains.language.locale');

        return $this->channelRepository->search($criteria, Context::createDefaultContext())->getEntities();
    }

    /**
     * @return array<string, string|null>
     */
    private function localesByLanguage(ChannelEntity $channel): array
    {
        $locales = [];

        foreach ($channel->getDomains() ?? [] as $domain) {
            $languageId = $domain->getLanguageId();

            if (\array_key_exists($languageId, $locales)) {
                continue;
            }

            $locales[$languageId] = $domain->getLanguage()?->getLocale()?->getCode();
        }

        return $locales;
    }

    /**
     * @param non-empty-array<string, string> $paths
     */
    private function resolvePath(array $paths, ?string $localeCode): string
    {
        if ($localeCode !== null && isset($paths[$localeCode])) {
            return $paths[$localeCode];
        }

        return $paths[self::FALLBACK_LOCALE] ?? array_first($paths);
    }
}
