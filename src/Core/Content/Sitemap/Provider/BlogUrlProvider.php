<?php declare(strict_types=1);

namespace Contena\Core\Content\Sitemap\Provider;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Seo\SeoUrlRoute\EntityRouteResolver;
use Contena\Core\Content\Sitemap\Event\SitemapQueryEvent;
use Contena\Core\Content\Sitemap\Service\ConfigHandler;
use Contena\Core\Content\Sitemap\Struct\Url;
use Contena\Core\Content\Sitemap\Struct\UrlResult;
use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Contena\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class BlogUrlProvider extends AbstractUrlProvider
{
    final public const CHANGE_FREQ = 'hourly';

    final public const QUERY_EVENT_NAME = 'sitemap.query.blog';

    private const CONFIG_EXCLUDE_LINKED_BLOGS = 'core.sitemap.excludeLinkedBlogs';

    /**
     * @internal
     */
    public function __construct(
        private readonly ConfigHandler $configHandler,
        private readonly Connection $connection,
        private readonly BlogDefinition $definition,
        private readonly IteratorFactory $iteratorFactory,
        private readonly EntityRouteResolver $entityRouteResolver,
        private readonly SystemConfigService $systemConfigService,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function getDecorated(): AbstractUrlProvider
    {
        throw new DecorationPatternException(self::class);
    }

    public function getName(): string
    {
        return 'blog';
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Exception
     */
    public function getUrls(ChannelContext $context, int $limit, ?int $offset = null): UrlResult
    {
        $blogs = $this->getBlogs($context, $limit, $offset);

        if ($blogs === []) {
            return new UrlResult([], null);
        }

        $keys = FetchModeHelper::keyPair($blogs);

        $channelTypeId = $context->getChannel()->getTypeId();
        $routeName = $this->entityRouteResolver->getRouteNameForEntityName(BlogDefinition::ENTITY_NAME, $channelTypeId);
        $seoUrls = $this->getSeoUrls(array_values($keys), $routeName, $context, $this->connection);

        /** @var array<string, array{seo_path_info: string}> $seoUrls */
        $seoUrls = FetchModeHelper::groupUnique($seoUrls);

        $urls = [];
        $url = new Url();

        foreach ($blogs as $blog) {
            $lastMod = $blog['updated_at'] ?: $blog['created_at'];

            $lastMod = new \DateTime($lastMod)->format(Defaults::STORAGE_DATE_TIME_FORMAT);

            $newUrl = clone $url;

            if (isset($seoUrls[$blog['id']])) {
                $newUrl->setLoc($seoUrls[$blog['id']]['seo_path_info']);
            } else {
                $newUrl->setLoc($this->entityRouteResolver->generateUrl(BlogDefinition::ENTITY_NAME, $blog['id'], $channelTypeId));
            }

            $newUrl->setLastmod(new \DateTime($lastMod));
            $newUrl->setChangefreq(self::CHANGE_FREQ);
            $newUrl->setResource(BlogEntity::class);
            $newUrl->setIdentifier($blog['id']);

            $urls[] = $newUrl;
        }

        $keys = array_keys($keys);
        $nextOffset = array_pop($keys);

        return new UrlResult($urls, $nextOffset !== null ? (int) $nextOffset : null);
    }

    /**
     * @return list<array{id: string, created_at: string, updated_at: string}>
     */
    private function getBlogs(ChannelContext $context, int $limit, ?int $offset): array
    {
        $lastId = null;
        if ($offset) {
            $lastId = ['offset' => $offset];
        }

        $iterator = $this->iteratorFactory->createIterator($this->definition, $lastId);
        $query = $iterator->getQuery();
        $query->setMaxResults($limit);

        $query->addSelect(
            '`blog`.created_at as created_at',
            '`blog`.updated_at as updated_at',
        );

        $query->innerJoin('`blog`', 'blog_visibility', 'visibilities', '`blog`.id = visibilities.blog_id AND `blog`.version_id = visibilities.blog_version_id');

        $query->andWhere('`blog`.version_id = :versionId');

        $query->andWhere('`blog`.active = 1');
        $query->andWhere('visibilities.blog_version_id = :versionId');
        $query->andWhere('visibilities.channel_id = :channelId');

        $excludedBlogIds = $this->getExcludedBlogIds($context);
        if ($excludedBlogIds !== []) {
            $query->andWhere('`blog`.id NOT IN (:blogIds)');
            $query->setParameter('blogIds', Uuid::fromHexToBytesList($excludedBlogIds), ArrayParameterType::BINARY);
        }

        $excludeLinkedBlogs = $this->systemConfigService->getBool(self::CONFIG_EXCLUDE_LINKED_BLOGS, $context->getChannelId());
        if ($excludeLinkedBlogs) {
            $query->andWhere('visibilities.visibility != :excludedVisibility');
            $query->setParameter('excludedVisibility', BlogVisibilityDefinition::VISIBILITY_LINK);
        }

        $query->setParameter('versionId', Uuid::fromHexToBytes(Defaults::LIVE_VERSION));
        $query->setParameter('channelId', Uuid::fromHexToBytes($context->getChannelId()));

        $this->eventDispatcher->dispatch(
            new SitemapQueryEvent($query, $limit, $offset, $context, self::QUERY_EVENT_NAME)
        );

        /** @var list<array{id: string, created_at: string, updated_at: string}> $result */
        $result = $query->executeQuery()->fetchAllAssociative();

        return $result;
    }

    /**
     * @return array<string>
     */
    private function getExcludedBlogIds(ChannelContext $channelContext): array
    {
        $channelId = $channelContext->getChannelId();

        $excludedUrls = $this->configHandler->get(ConfigHandler::EXCLUDED_URLS_KEY);
        if ($excludedUrls === []) {
            return [];
        }

        $excludedUrls = array_filter($excludedUrls, static function (array $excludedUrl) use ($channelId) {
            if ($excludedUrl['resource'] !== BlogEntity::class) {
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
