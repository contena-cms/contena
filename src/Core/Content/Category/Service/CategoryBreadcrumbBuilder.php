<?php declare(strict_types=1);

namespace Contena\Core\Content\Category\Service;

use Contena\Core\Content\Blog\Aggregate\BlogMainCategory\BlogMainCategoryCollection;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Blog\Channel\ChannelBlogCollection;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Content\Breadcrumb\BreadcrumbException;
use Contena\Core\Content\Breadcrumb\Struct\Breadcrumb;
use Contena\Core\Content\Breadcrumb\Struct\BreadcrumbCollection;
use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Content\Category\Util\CategoryBreadcrumbHelper;
use Contena\Core\Content\Seo\SeoUrlRoute\EntityRouteResolver;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

class CategoryBreadcrumbBuilder
{
    /**
     * Translated category fields that are safe to expose in a breadcrumb, see filterTranslated().
     */
    private const EXPOSED_TRANSLATED_FIELDS = [
        'linkType',
        'internalLink',
        'externalLink',
        'linkNewTab',
        'description',
        'metaTitle',
        'metaDescription',
        'keywords',
    ];

    /**
     * @internal
     *
     * @param EntityRepository<CategoryCollection> $categoryRepository
     * @param ChannelRepository<ChannelBlogCollection> $blogRepository
     */
    public function __construct(
        private readonly EntityRepository $categoryRepository,
        private readonly ChannelRepository $blogRepository,
        private readonly Connection $connection,
        private readonly EntityRouteResolver $entityRouteResolver,
    ) {
    }

    public function getBlogBreadcrumbUrls(string $blogId, string $referrerCategoryId, ChannelContext $channelContext): BreadcrumbCollection
    {
        $blog = $this->loadBlog($blogId, $channelContext);
        $category = $this->getBlogCategoryByReferrer($referrerCategoryId, $blog, $channelContext);
        if ($category === null) {
            throw BreadcrumbException::categoryNotFoundForBlog($blogId);
        }

        return $this->getCategoryBreadcrumbUrls(
            $category,
            $channelContext->getContext(),
            $channelContext->getChannel()
        );
    }

    public function loadCategory(string $categoryId, Context $context): ?CategoryEntity
    {
        $criteria = new Criteria([$categoryId]);
        $criteria->setTitle('breadcrumb::category::data');

        return $this->categoryRepository
            ->search($criteria, $context)
            ->getEntities()
            ->get($categoryId);
    }

    public function getBlogSeoCategory(BlogEntity $blog, ChannelContext $context): ?CategoryEntity
    {
        $category = $this->getMainCategory($blog, $context);
        if ($category !== null) {
            return $category;
        }

        $categoryIds = $blog->getCategoryIds() ?? [];
        if ($categoryIds === []) {
            return null;
        }

        $criteria = new Criteria();
        $criteria->setTitle('breadcrumb-builder');
        $criteria->setLimit(1);
        $criteria->addFilter($this->getCategoryAvailableForMemberFilter($context));

        $criteria->setIds($categoryIds);

        // categories hidden in the navigation must still yield a breadcrumb, but visible ones are preferred
        $criteria->addSorting(new FieldSorting('visible', FieldSorting::DESCENDING));
        $criteria->addSorting(new FieldSorting('level', FieldSorting::DESCENDING));
        // tiebreaker for equally deep categories in different branches. Without it the winner is whatever the database
        // returns first, so the same blog can produce different breadcrumbs on two requests and whichever path won
        // gets frozen into the http cache.
        $criteria->addSorting(new FieldSorting('autoIncrement'));

        return $this->categoryRepository->search($criteria, $context->getContext())->getEntities()->first();
    }

    public function getBlogCategoryByReferrer(
        string $referrerCategoryId,
        ChannelBlogEntity $blog,
        ChannelContext $channelContext
    ): ?CategoryEntity {
        if (\in_array($referrerCategoryId, $blog->getCategoryTree() ?? [], true)) {
            $referrerCategory = $this->loadCategory($referrerCategoryId, $channelContext->getContext());

            if ($referrerCategory instanceof CategoryEntity && $this->isCategoryAvailableForMember($referrerCategory, $channelContext)) {
                return $referrerCategory;
            }
        }

        return $this->getBlogSeoCategory($blog, $channelContext);
    }

    public function getCategoryBreadcrumbUrls(CategoryEntity $category, Context $context, ChannelEntity $channel): BreadcrumbCollection
    {
        $seoBreadcrumb = $this->build($category, $channel);
        $categoryIds = array_keys($seoBreadcrumb ?? []);

        if ($categoryIds === []) {
            return new BreadcrumbCollection();
        }

        $categories = $this->loadCategories($categoryIds, $context, $channel);
        $seoUrls = $this->loadSeoUrls($categoryIds, $context, $channel);

        return $this->convertCategoriesToBreadcrumbUrls($categories, $seoUrls);
    }

    /**
     * @return array<string, string>|null
     */
    public function build(CategoryEntity $category, ?ChannelEntity $channel = null, ?string $navigationCategoryId = null): ?array
    {
        return CategoryBreadcrumbHelper::build($category, $channel, $navigationCategoryId);
    }

    private function loadBlog(string $blogId, ChannelContext $channelContext): ChannelBlogEntity
    {
        $criteria = new Criteria();
        $criteria->setIds([$blogId]);
        $criteria->setTitle('breadcrumb::blog::data');

        $blog = $this->blogRepository
            ->search($criteria, $channelContext)
            ->getEntities()
            ->first();

        if (!$blog instanceof ChannelBlogEntity) {
            throw BreadcrumbException::blogNotFound($blogId);
        }

        return $blog;
    }

    private function getMainCategory(BlogEntity $blog, ChannelContext $context): ?CategoryEntity
    {
        if ($mainCategory = $this->getMainCategoryFromBlog($blog, $context)) {
            return $mainCategory;
        }

        $categoryIds = $blog->getCategoryIds() ?? [];

        if ($categoryIds === []) {
            return null;
        }

        $criteria = new Criteria([$blog->getId()]);
        $criteria->setTitle('breadcrumb-builder::main-category');
        $criteria->addAssociation('mainCategories.category');
        $criteria->getAssociation('mainCategories')
            ->setLimit(1)
            ->addFilter(new AndFilter([
                new EqualsFilter('channelId', $context->getChannelId()),
                new EqualsAnyFilter('category.id', $categoryIds),
                $this->getCategoryAvailableForMemberFilter($context, 'category.'),
            ]));

        $blog = $context->getContext()->enableInheritance(fn (): ?BlogEntity => $this->blogRepository->search($criteria, $context)->getEntities()->first());

        if (!$blog instanceof BlogEntity || !$blog->getMainCategories() instanceof BlogMainCategoryCollection) {
            return null;
        }

        return $blog->getMainCategories()->first()?->getCategory();
    }

    private function getMainCategoryFromBlog(BlogEntity $blog, ChannelContext $context): ?CategoryEntity
    {
        if (!$blog->getMainCategories()?->count()) {
            return null;
        }

        $category = $blog->getMainCategories()->filterByChannelId($context->getChannelId())->first()?->getCategory();

        if (
            !$category instanceof CategoryEntity
            || !\in_array($category->getId(), $blog->getCategoryIds() ?? [], true)
            || !$this->isCategoryAvailableForMember($category, $context)
        ) {
            return null;
        }

        return $category;
    }

    /**
     * @param array<string> $categoryIds
     */
    private function loadCategories(array $categoryIds, Context $context, ChannelEntity $channel): CategoryCollection
    {
        $criteria = new Criteria($categoryIds);
        $criteria->setTitle('breadcrumb::categories::data');
        $criteria->addFilter($this->getChannelFilter($channel));

        return $this->categoryRepository->search($criteria, $context)->getEntities();
    }

    /**
     * @param array<string> $categoryIds
     *
     * @return list<array<string, string|mixed>>
     */
    private function loadSeoUrls(array $categoryIds, Context $context, ChannelEntity $channel): array
    {
        $query = $this->connection->createQueryBuilder();
        $query->select(
            'LOWER(HEX(id)) as id',
            'LOWER(HEX(foreign_key)) as categoryId',
            'path_info as pathInfo',
            'seo_path_info as seoPathInfo',
        );
        $query->from('seo_url');
        $query->where('seo_url.is_canonical = 1');
        $query->andWhere('seo_url.route_name = :routeName');
        $query->andWhere('seo_url.language_id = :languageId');
        $query->andWhere('seo_url.channel_id = :channelId');
        $query->andWhere('seo_url.foreign_key IN (:categoryIds)');
        $routeName = $this->entityRouteResolver->getRouteNameForEntityName(CategoryDefinition::ENTITY_NAME, $channel->getTypeId());
        $query->setParameter('routeName', $routeName);
        $query->setParameter('languageId', Uuid::fromHexToBytes($context->getLanguageId()));
        $query->setParameter('channelId', Uuid::fromHexToBytes($channel->getId()));
        $query->setParameter('categoryIds', Uuid::fromHexToBytesList($categoryIds), ArrayParameterType::BINARY);

        return $query->executeQuery()->fetchAllAssociative();
    }

    /**
     * @param list<array<string, string|mixed>> $seoUrls
     */
    private function convertCategoriesToBreadcrumbUrls(CategoryCollection $categories, array $seoUrls): BreadcrumbCollection
    {
        $blockedCustomFields = $this->getBlockedCustomFields($categories);

        $seoBreadcrumbCollection = [];
        foreach ($categories as $category) {
            $categoryId = $category->getId();
            $categorySeoUrls = $this->filterCategorySeoUrls($seoUrls, $categoryId);
            $categoryBreadcrumb = new Breadcrumb(
                $category->getTranslation('name'),
                $categoryId,
                $category->getType(),
                $this->filterTranslated($category, $blockedCustomFields),
            );

            if ($categorySeoUrls === []) {
                if ($category->getType() !== CategoryDefinition::TYPE_FOLDER) {
                    $categoryBreadcrumb->path = 'navigation/' . $categoryId;
                }
            } else {
                foreach ($categorySeoUrls as $categorySeoUrl) {
                    if ($categoryBreadcrumb->path === '') {
                        $categoryBreadcrumb->path = (isset($categorySeoUrl['seoPathInfo']) && $categorySeoUrl['seoPathInfo'] !== '')
                            ? $categorySeoUrl['seoPathInfo'] : $categorySeoUrl['pathInfo'];
                    }
                    if ($categoryId === $categorySeoUrl['categoryId']) {
                        unset($categorySeoUrl['categoryId']); // remove redundant data
                    }
                    $categoryBreadcrumb->seoUrls[] = $categorySeoUrl;
                }
            }

            $seoBreadcrumbCollection[$categoryId] = $categoryBreadcrumb;
        }

        return new BreadcrumbCollection(array_values($seoBreadcrumbCollection));
    }

    /**
     * The breadcrumb is a plain struct, so `StructEncoder::isProtected()` bails out for its alias and the `ApiAware`
     * filter a `category` payload gets is never applied. Therefore only fields that are explicitly safe to expose are
     * copied over, which leaves out `slotConfig` because it is not `ApiAware` on the category definition.
     *
     * `customFields` is `ApiAware`, so it stays part of the payload, but the encoder would only strip its `global`
     * scoped blocked entries for this alias. The ones scoped to `category` are therefore removed here.
     *
     * @param list<string> $blockedCustomFields
     *
     * @return array<string, mixed>
     */
    private function filterTranslated(CategoryEntity $category, array $blockedCustomFields): array
    {
        $translated = [];

        foreach (self::EXPOSED_TRANSLATED_FIELDS as $field) {
            $translated[$field] = $category->getTranslation($field);
        }

        $customFields = $category->getTranslation('customFields');

        if (\is_array($customFields) && $blockedCustomFields !== []) {
            $customFields = array_diff_key($customFields, array_flip($blockedCustomFields));
        }

        $translated['customFields'] = $customFields;

        return $translated;
    }

    /**
     * Mirrors what `StructEncoder` does for an entity payload, for the `category` and the unscoped sets. It has to be
     * repeated here because the encoder keys its lookup by api alias and `breadcrumb` is not a registered entity.
     *
     * @return list<string>
     */
    private function getBlockedCustomFields(CategoryCollection $categories): array
    {
        $hasCustomFields = false;

        foreach ($categories as $category) {
            $customFields = $category->getTranslation('customFields');

            if (\is_array($customFields) && $customFields !== []) {
                $hasCustomFields = true;

                break;
            }
        }

        if (!$hasCustomFields) {
            return [];
        }

        return $this->connection->fetchFirstColumn(
            '# breadcrumb-builder::blocked-custom-fields
            SELECT cf.name
            FROM custom_field cf
            LEFT JOIN custom_field_set_relation cfsr ON cfsr.set_id = cf.set_id
            WHERE cf.channel_api_aware = 0
              AND (cfsr.entity_name = :entityName OR cfsr.entity_name IS NULL)',
            ['entityName' => CategoryDefinition::ENTITY_NAME]
        );
    }

    /**
     * @param array<int, array<string, string|mixed>> $seoUrls
     *
     * @return array<int, array<string, string|mixed>>
     */
    private function filterCategorySeoUrls(array $seoUrls, string $categoryId): array
    {
        return array_filter($seoUrls, static function (array $seoUrl) use ($categoryId): bool {
            return $seoUrl['categoryId'] === $categoryId;
        });
    }

    /**
     * Categories hidden in the navigation are still available as breadcrumb source, only inactive ones are not
     */
    private function isCategoryAvailableForMember(CategoryEntity $category, ChannelContext $context): bool
    {
        $channel = $context->getChannel();

        if (!$category->getActive()) {
            return false;
        }

        if (array_intersect(\array_slice(explode('|', $category->getPath() ?? ''), 1, -1), array_filter([
            $channel->getNavigationCategoryId(),
            $channel->getServiceCategoryId(),
            $channel->getFooterCategoryId(),
        ])) === []) {
            return false;
        }

        return true;
    }

    private function getChannelFilter(ChannelEntity $channel, string $fieldPath = ''): MultiFilter
    {
        return new OrFilter(array_map(static fn (string $id) => new ContainsFilter($fieldPath . 'path', '|' . $id . '|'), array_filter([
            $channel->getNavigationCategoryId(),
            $channel->getServiceCategoryId(),
            $channel->getFooterCategoryId(),
        ])));
    }

    private function getCategoryAvailableForMemberFilter(ChannelContext $context, string $fieldPath = ''): AndFilter
    {
        $channel = $context->getChannel();

        return new AndFilter([
            new EqualsFilter($fieldPath . 'active', true),
            $this->getChannelFilter($channel, $fieldPath),
        ]);
    }
}
