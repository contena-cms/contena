<?php declare(strict_types=1);

namespace Contena\Core\Content\Sitemap\Provider;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Contena\Core\Content\LandingPage\LandingPageDefinition;
use Contena\Core\Content\LandingPage\LandingPageEntity;
use Contena\Core\Content\Seo\SeoUrlRoute\EntityRouteResolver;
use Contena\Core\Content\Sitemap\Event\SitemapQueryEvent;
use Contena\Core\Content\Sitemap\Service\ConfigHandler;
use Contena\Core\Content\Sitemap\Struct\Url;
use Contena\Core\Content\Sitemap\Struct\UrlResult;
use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class LandingPageUrlProvider extends AbstractUrlProvider
{
    final public const CHANGE_FREQ = 'daily';

    final public const QUERY_EVENT_NAME = 'sitemap.query.landing_page';

    /**
     * @internal
     */
    public function __construct(
        private readonly ConfigHandler $configHandler,
        private readonly Connection $connection,
        private readonly EntityRouteResolver $entityRouteResolver,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function getDecorated(): AbstractUrlProvider
    {
        throw new DecorationPatternException(self::class);
    }

    public function getName(): string
    {
        return 'landing_page';
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Exception
     */
    public function getUrls(ChannelContext $context, int $limit, ?int $offset = null): UrlResult
    {
        $landingPages = $this->getLandingPages($context, $limit, $offset);

        if ($landingPages === []) {
            return new UrlResult([], null);
        }

        $ids = array_column($landingPages, 'id');

        $channelTypeId = $context->getChannel()->getTypeId();
        $routeName = $this->entityRouteResolver->getRouteNameForEntityName(LandingPageDefinition::ENTITY_NAME, $channelTypeId);
        $seoUrls = $this->getSeoUrls($ids, $routeName, $context, $this->connection);

        /** @var array<string, array{seo_path_info: string}> $seoUrls */
        $seoUrls = FetchModeHelper::groupUnique($seoUrls);

        $urls = [];
        foreach ($landingPages as $landingPage) {
            $url = new Url();

            if (isset($seoUrls[$landingPage['id']])) {
                $url->setLoc($seoUrls[$landingPage['id']]['seo_path_info']);
            } else {
                $url->setLoc($this->entityRouteResolver->generateUrl(LandingPageDefinition::ENTITY_NAME, $landingPage['id'], $channelTypeId));
            }

            $lastMod = $landingPage['updated_at'] ?: $landingPage['created_at'];

            $url->setLastmod(new \DateTime($lastMod));
            $url->setChangefreq(self::CHANGE_FREQ);
            $url->setResource(LandingPageEntity::class);
            $url->setIdentifier($landingPage['id']);

            $urls[] = $url;
        }

        $nextOffset = null;
        if (\count($landingPages) === $limit) {
            $nextOffset = (int) $offset + $limit;
        }

        return new UrlResult($urls, $nextOffset);
    }

    /**
     * @return list<array{id: string, created_at: string, updated_at: string}>
     */
    private function getLandingPages(ChannelContext $context, int $limit, ?int $offset): array
    {
        $query = $this->connection->createQueryBuilder();

        $query
            ->select('lp.id', 'lp.created_at', 'lp.updated_at')
            ->from('landing_page', 'lp')
            ->join('lp', 'landing_page_channel', 'lp_sc', 'lp_sc.landing_page_id = lp.id AND lp_sc.landing_page_version_id = lp.version_id')
            ->where('lp.version_id = :versionId')
            ->andWhere('lp.active = 1')
            ->andWhere('lp_sc.channel_id = :channelId')
            ->setMaxResults($limit);

        $query->setFirstResult(0);
        if ($offset !== null) {
            $query->setFirstResult($offset);
        }

        $excludedLandingPageIds = $this->getExcludedLandingPageIds($context);
        if ($excludedLandingPageIds !== []) {
            $query->andWhere('lp.id NOT IN (:landingPageIds)');
            $query->setParameter('landingPageIds', Uuid::fromHexToBytesList($excludedLandingPageIds), ArrayParameterType::BINARY);
        }

        $query->setParameter('versionId', Uuid::fromHexToBytes(Defaults::LIVE_VERSION));
        $query->setParameter('channelId', Uuid::fromHexToBytes($context->getChannelId()));

        $this->eventDispatcher->dispatch(
            new SitemapQueryEvent($query, $limit, $offset, $context, self::QUERY_EVENT_NAME)
        );

        /** @var list<array{id: string, created_at: string, updated_at: string}> $result */
        $result = $query->executeQuery()->fetchAllAssociative();

        return array_map(static function (array $landingPage): array {
            $landingPage['id'] = Uuid::fromBytesToHex($landingPage['id']);

            return $landingPage;
        }, $result);
    }

    /**
     * @return array<string>
     */
    private function getExcludedLandingPageIds(ChannelContext $channelContext): array
    {
        $channelId = $channelContext->getChannelId();

        $excludedUrls = $this->configHandler->get(ConfigHandler::EXCLUDED_URLS_KEY);
        if ($excludedUrls === []) {
            return [];
        }

        $excludedUrls = array_filter($excludedUrls, static function (array $excludedUrl) use ($channelId) {
            if ($excludedUrl['resource'] !== LandingPageEntity::class) {
                return false;
            }

            if ($excludedUrl['channelId'] !== $channelId) {
                return false;
            }

            return true;
        });

        return array_column($excludedUrls, 'identifier');
    }
}
