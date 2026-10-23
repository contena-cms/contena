<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo;

use Doctrine\DBAL\Connection;
use Contena\Core\Content\Seo\SeoUrlRoute\EntitySeoUrlRouteInterface;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Context\SystemSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Language\LanguageCollection;
use Contena\Core\System\Channel\ChannelCollection;

/**
 * This class can be used to regenerate the seo urls for a route and an offset at ids.
 */
class SeoUrlUpdater
{
    /**
     * @internal
     *
     * @param EntityRepository<LanguageCollection> $languageRepository
     * @param EntityRepository<ChannelCollection> $channelRepository
     * @param iterable<EntitySeoUrlRouteInterface> $entitySeoUrlRoutes
     */
    public function __construct(
        private readonly EntityRepository $languageRepository,
        private readonly SeoUrlRouteRegistry $seoUrlRouteRegistry,
        private readonly SeoUrlGenerator $seoUrlGenerator,
        private readonly SeoUrlPersister $seoUrlPersister,
        private readonly Connection $connection,
        private readonly EntityRepository $channelRepository,
        private readonly iterable $entitySeoUrlRoutes = []
    ) {
    }

    /**
     * @param list<string> $ids
     */
    public function update(string $routeName, array $ids): void
    {
        if ($routeName === '') {
            return;
        }

        $route = $this->seoUrlRouteRegistry->findByRouteName($routeName);

        if ($route !== null) {
            $templates = $this->loadUrlTemplate($routeName, false);
            if ($templates !== []) {
                $this->generateAndPersist($route, $routeName, $templates, $ids);
            }

            return;
        }

        // headless route (store-api): not registered as a full route, but configurable per headless sales channel
        $entityRoute = $this->findEntitySeoUrlRoute($routeName);
        if ($entityRoute === null) {
            throw SeoException::seoUrlRouteNotFound($routeName);
        }

        $templates = $this->loadUrlTemplate($routeName, true);
        if ($templates === []) {
            return;
        }

        $this->generateAndPersist(
            new ConfiguredEntitySeoUrlRoute($entityRoute),
            $routeName,
            $templates,
            $ids
        );
    }

    /**
     * @param list<string> $ids
     * @param list<array{channelId: string, languageId: string, template: string}> $templates
     */
    private function generateAndPersist(SeoUrlRouteInterface $route, string $routeName, array $templates, array $ids): void
    {
        $context = Context::createDefaultContext();

        $languageChains = $this->fetchLanguageChains($context);

        $channelIds = array_values(array_unique(array_column($templates, 'channelId')));
        $criteria = new Criteria($channelIds);
        $criteria->addAssociation('domains');
        $channels = $this->channelRepository->search($criteria, $context)->getEntities();

        foreach ($templates as $config) {
            $template = $config['template'];
            $channel = $channels->get($config['channelId']);
            if ($template === '' || !$channel) {
                continue;
            }

            $chain = $languageChains[$config['languageId']] ?? null;
            if (!$chain) {
                continue;
            }

            $languageContext = new Context(new SystemSource(), [], Defaults::CURRENCY, $chain);
            $languageContext->setConsiderInheritance(true);

            // generate new seo urls
            $urls = $this->seoUrlGenerator->generate($ids, $template, $route, $languageContext, $channel);

            // persist seo urls to storage
            $this->seoUrlPersister->updateSeoUrls($languageContext, $routeName, $ids, $urls, $channel);
        }
    }

    private function findEntitySeoUrlRoute(string $routeName): ?EntitySeoUrlRouteInterface
    {
        foreach ($this->entitySeoUrlRoutes as $entitySeoUrlRoute) {
            if ($entitySeoUrlRoute->getConfig()->getRouteName() === $routeName) {
                return $entitySeoUrlRoute;
            }
        }

        return null;
    }

    /**
     * @param non-empty-string $routeName
     *
     * @return list<array{channelId: string, languageId: string, template: string}>
     */
    private function loadUrlTemplate(string $routeName, bool $isHeadless): array
    {
        $query = 'SELECT DISTINCT
               LOWER(HEX(channel.id)) as channelId,
               LOWER(HEX(domains.language_id)) as languageId
             FROM channel_domain as domains
             INNER JOIN channel
               ON domains.channel_id = channel.id
               AND channel.active = 1';

        $query .= $isHeadless
            ? ' AND channel.type_id = :apiTypeId AND domains.is_external_frontend = 1'
            : ' AND channel.type_id != :apiTypeId';
        $parameters = ['apiTypeId' => Uuid::fromHexToBytes(Defaults::CHANNEL_TYPE_API)];

        $domains = $this->connection->fetchAllAssociative($query, $parameters);

        if ($domains === []) {
            return [];
        }

        $channelTemplates = $this->connection->fetchAllKeyValue(
            'SELECT LOWER(HEX(channel_id)) as channel_id, template
             FROM seo_url_template
             WHERE route_name LIKE :route
               AND is_headless = :isHeadless',
            ['route' => $routeName, 'isHeadless' => (int) $isHeadless]
        );

        $hasDefaultTemplate = \array_key_exists('', $channelTemplates);

        if (!$isHeadless && !$hasDefaultTemplate) {
            throw SeoException::invalidTemplate('Default templates not configured');
        }

        $default = $hasDefaultTemplate ? (string) $channelTemplates[''] : null;

        $result = [];
        foreach ($domains as $domain) {
            $template = $channelTemplates[$domain['channelId']] ?? $default;

            if ($template === null) {
                continue;
            }

            $result[] = [
                'channelId' => $domain['channelId'],
                'languageId' => $domain['languageId'],
                'template' => (string) $template,
            ];
        }

        return $result;
    }

    /**
     * @return array<string, non-empty-list<string>>
     */
    private function fetchLanguageChains(Context $context): array
    {
        $languages = $this->languageRepository->search(new Criteria(), $context)->getEntities()->getElements();

        $languageChains = [];
        foreach ($languages as $language) {
            $languageId = $language->getId();
            $languageChains[$languageId] = array_values(array_filter([
                $languageId,
                $language->getParentId(),
                Defaults::LANGUAGE_SYSTEM,
            ]));
        }

        return $languageChains;
    }
}
