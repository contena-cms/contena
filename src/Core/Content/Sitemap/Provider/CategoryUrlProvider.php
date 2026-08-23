<?php declare(strict_types=1);

namespace Contena\Core\Content\Sitemap\Provider;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Content\Category\Event\ChannelCategoryIdsFetchedEvent;
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
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class CategoryUrlProvider extends AbstractUrlProvider
{
    final public const CHANGE_FREQ = 'daily';

    final public const QUERY_EVENT_NAME = 'sitemap.query.category';

    /**
     * @internal
     */
    public function __construct(
        private readonly ConfigHandler $configHandler,
        private readonly Connection $connection,
        private readonly CategoryDefinition $definition,
        private readonly IteratorFactory $iteratorFactory,
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
        return 'category';
    }

    public function getUrls(ChannelContext $context, int $limit, ?int $offset = null): UrlResult
    {
        $categories = $this->getCategories($context, $limit, $offset);

        if ($categories === []) {
            return new UrlResult([], null);
        }

        $keys = FetchModeHelper::keyPair($categories);
        $autoIncrementIds = array_keys($keys);

        // The next offset must be taken from all results before the event can filter any ids out to prevent fetching
        // the same ids again
        $nextOffset = array_pop($autoIncrementIds);
        \assert(\is_int($nextOffset) || $nextOffset === null);

        $categoryIdsFetchedEvent = $this->eventDispatcher->dispatch(
            new ChannelCategoryIdsFetchedEvent(\array_column($categories, 'id'), $context)
        );

        if ($categoryIdsFetchedEvent->getIds() === []) {
            return new UrlResult([], $nextOffset);
        }

        $availableCategories = \array_filter(
            $categories,
            static fn (array $category) => $categoryIdsFetchedEvent->hasId($category['id'])
        );

        $channelTypeId = $context->getChannel()->getTypeId();
        $routeName = $this->entityRouteResolver->getRouteNameForEntityName(CategoryDefinition::ENTITY_NAME, $channelTypeId);
        $seoUrls = $this->getSeoUrls($categoryIdsFetchedEvent->getIds(), $routeName, $context, $this->connection);

        /** @var array<string, array{seo_path_info: string}> $seoUrls */
        $seoUrls = FetchModeHelper::groupUnique($seoUrls);

        $urls = [];
        $url = new Url();

        foreach ($availableCategories as $category) {
            $lastMod = $category['updated_at'] ?: $category['created_at'];

            $lastMod = new \DateTime($lastMod)->format(Defaults::STORAGE_DATE_TIME_FORMAT);

            $newUrl = clone $url;

            if (isset($seoUrls[$category['id']])) {
                $newUrl->setLoc($seoUrls[$category['id']]['seo_path_info']);
            } else {
                $newUrl->setLoc($this->entityRouteResolver->generateUrl(CategoryDefinition::ENTITY_NAME, $category['id'], $channelTypeId));
            }

            $newUrl->setLastmod(new \DateTime($lastMod));
            $newUrl->setChangefreq(self::CHANGE_FREQ);
            $newUrl->setResource(CategoryEntity::class);
            $newUrl->setIdentifier($category['id']);

            $urls[] = $newUrl;
        }

        return new UrlResult($urls, $nextOffset);
    }

    /**
     * @return list<array{id: string, created_at: string, updated_at: string}>
     */
    private function getCategories(ChannelContext $context, int $limit, ?int $offset): array
    {
        $lastId = null;
        if ($offset) {
            $lastId = ['offset' => $offset];
        }

        $iterator = $this->iteratorFactory->createIterator($this->definition, $lastId);
        $query = $iterator->getQuery();
        $query->setMaxResults($limit);

        $query->addSelect(
            '`category`.created_at',
            '`category`.updated_at',
        );

        $wheres = [];
        $categoryIds = array_filter([
            $context->getChannel()->getNavigationCategoryId(),
            $context->getChannel()->getFooterCategoryId(),
            $context->getChannel()->getServiceCategoryId(),
        ]);

        foreach ($categoryIds as $id) {
            $wheres[] = '`category`.path LIKE ' . $query->createNamedParameter('%|' . $id . '|%');
        }

        $query->andWhere('(' . implode(' OR ', $wheres) . ')');
        $query->andWhere('`category`.version_id = :versionId');
        $query->andWhere('`category`.active = 1');
        $query->andWhere('`category`.type != :linkType');
        $query->andWhere('`category`.type != :folderType');

        $excludedCategoryIds = $this->getExcludedCategoryIds($context);
        if ($excludedCategoryIds !== []) {
            $query->andWhere('`category`.id NOT IN (:categoryIds)');
            $query->setParameter('categoryIds', Uuid::fromHexToBytesList($excludedCategoryIds), ArrayParameterType::BINARY);
        }

        $query->setParameter('versionId', Uuid::fromHexToBytes(Defaults::LIVE_VERSION));
        $query->setParameter('linkType', CategoryDefinition::TYPE_LINK);
        $query->setParameter('folderType', CategoryDefinition::TYPE_FOLDER);

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
    private function getExcludedCategoryIds(ChannelContext $channelContext): array
    {
        $channelId = $channelContext->getChannelId();

        $excludedUrls = $this->configHandler->get(ConfigHandler::EXCLUDED_URLS_KEY);
        if ($excludedUrls === []) {
            return [];
        }

        $excludedUrls = array_filter($excludedUrls, static function (array $excludedUrl) use ($channelId) {
            if ($excludedUrl['resource'] !== CategoryEntity::class) {
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
