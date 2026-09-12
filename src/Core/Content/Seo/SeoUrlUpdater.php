<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo;

use Contena\Core\Content\Seo\SeoUrlRoute\EntitySeoUrlRouteInterface;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DataScope;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Language\LanguageCollection;
use Doctrine\DBAL\Connection;

/**
 * This class can be used to regenerate the SEO URLs for a route and a set of ids.
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
        private readonly iterable $entitySeoUrlRoutes = [],
    ) {
    }

    /**
     * @param list<string> $ids
     */
    public function update(string $routeName, array $ids, ?Context $context = null): void
    {
        if ($routeName === '') {
            return;
        }

        $context ??= Context::createDefaultContext();
        $route = $this->seoUrlRouteRegistry->findByRouteName($routeName);

        if ($route !== null) {
            $templates = $this->loadUrlTemplate($routeName, false, $context);
            if ($templates !== []) {
                $this->generateAndPersist($route, $routeName, $templates, $ids, $context);
            }

            return;
        }

        $entityRoute = $this->findEntitySeoUrlRoute($routeName);
        if ($entityRoute === null) {
            throw SeoException::seoUrlRouteNotFound($routeName);
        }

        $templates = $this->loadUrlTemplate($routeName, true, $context);
        if ($templates === []) {
            return;
        }

        $this->generateAndPersist(
            new ConfiguredEntitySeoUrlRoute($entityRoute),
            $routeName,
            $templates,
            $ids,
            $context,
        );
    }

    /**
     * @param list<array{channelId: string, languageId: string, dataScopeId: string, template: string}> $templates
     * @param list<string> $ids
     */
    private function generateAndPersist(
        SeoUrlRouteInterface $route,
        string $routeName,
        array $templates,
        array $ids,
        Context $context,
    ): void {
        $languageChains = $this->fetchLanguageChains($context);

        $channelIds = array_values(array_unique(array_column($templates, 'channelId')));
        $criteria = new Criteria($channelIds);
        $criteria->addAssociation('domains');
        $channels = $this->channelRepository->search($criteria, $context)->getEntities();

        foreach ($templates as $config) {
            $template = $config['template'];
            $channel = $channels->get($config['channelId']);
            if ($template === '' || $channel === null) {
                continue;
            }

            $chain = $languageChains[$config['languageId']] ?? null;
            if ($chain === null) {
                continue;
            }

            $dataScopeId = $config['dataScopeId'];
            if ($channel->getDataScopeId() !== $dataScopeId) {
                continue;
            }

            $languageContext = new Context(
                $context->getSource(),
                $chain,
                Defaults::LIVE_VERSION,
                true,
                $context->getRuleIds(),
                DataScope::platform()->getId() === $dataScopeId ? DataScope::platform() : DataScope::tenant($dataScopeId),
            );
            $languageContext->setConsiderInheritance(true);

            $urls = $this->seoUrlGenerator->generate($ids, $template, $route, $languageContext, $channel);
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
     * @return list<array{channelId: string, languageId: string, dataScopeId: string, template: string}>
     */
    private function loadUrlTemplate(string $routeName, bool $isHeadless, Context $context): array
    {
        $query = 'SELECT DISTINCT
               LOWER(HEX(channel.id)) as channelId,
               LOWER(HEX(domains.language_id)) as languageId,
               LOWER(HEX(channel.data_scope_id)) as dataScopeId
             FROM channel_domain as domains
             INNER JOIN channel
               ON domains.channel_id = channel.id
               AND channel.active = 1';

        $query .= $isHeadless
            ? ' AND channel.type_id = :apiTypeId AND domains.is_external_frontend = 1'
            : ' AND channel.type_id != :apiTypeId';
        $parameters = ['apiTypeId' => Uuid::fromHexToBytes(Defaults::CHANNEL_TYPE_API)];

        if (!$context->allowsCrossScopeReads()) {
            $query .= ' AND channel.data_scope_id = :dataScopeId';
            $parameters['dataScopeId'] = Uuid::fromHexToBytes($context->getDataScopeId());
        }

        $domains = $this->connection->fetchAllAssociative($query, $parameters);
        if ($domains === []) {
            return [];
        }

        $templateQuery = 'SELECT LOWER(HEX(channel_id)) as channelId,
                    LOWER(HEX(data_scope_id)) as dataScopeId,
                    template
             FROM seo_url_template
             WHERE route_name LIKE :route
               AND is_headless = :isHeadless';
        $templateParameters = ['route' => $routeName, 'isHeadless' => (int) $isHeadless];

        if (!$context->allowsCrossScopeReads()) {
            $templateQuery .= ' AND data_scope_id = :dataScopeId';
            $templateParameters['dataScopeId'] = Uuid::fromHexToBytes($context->getDataScopeId());
        }

        $templateRows = $this->connection->fetchAllAssociative($templateQuery, $templateParameters);

        /** @var array<string, array<string, array<string, string|null>>> $channelTemplates */
        $channelTemplates = [];
        foreach ($templateRows as $row) {
            $scopeKey = (string) ($row['dataScopeId'] ?? '');
            $channelKey = (string) ($row['channelId'] ?? '');
            $channelTemplates[$scopeKey][$channelKey] = $row['template'] !== null ? (string) $row['template'] : null;
        }

        $result = [];
        foreach ($domains as $domain) {
            $channelId = (string) $domain['channelId'];
            $scopeKey = (string) ($domain['dataScopeId'] ?? '');
            $templates = [
                ...($channelTemplates[$scopeKey] ?? []),
            ];

            if (!$isHeadless && !\array_key_exists('', $templates)) {
                throw SeoException::invalidTemplate('Default templates not configured');
            }

            $template = $templates[$channelId] ?? $templates[''] ?? null;

            if (!\is_string($template) || $template === '') {
                continue;
            }

            $result[] = [
                'channelId' => $channelId,
                'languageId' => (string) $domain['languageId'],
                'dataScopeId' => $scopeKey,
                'template' => $template,
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
