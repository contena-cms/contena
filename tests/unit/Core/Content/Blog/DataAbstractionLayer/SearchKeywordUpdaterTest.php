<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogTranslation\BlogTranslationDefinition;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Blog\DataAbstractionLayer\SearchKeywordUpdater;
use Contena\Core\Content\Blog\SearchKeyword\AnalyzedKeywordCollection;
use Contena\Core\Content\Blog\SearchKeyword\BlogSearchKeywordAnalyzerInterface;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Language\LanguageCollection;
use Contena\Core\System\Language\LanguageEntity;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityWriterGateway;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[CoversClass(SearchKeywordUpdater::class)]
class SearchKeywordUpdaterTest extends TestCase
{
    public function testDisabledIndexingSkipsUpdate(): void
    {
        $languageRepository = $this->createMock(EntityRepository::class);
        $blogRepository = $this->createMock(EntityRepository::class);
        $analyzer = $this->createMock(BlogSearchKeywordAnalyzerInterface::class);

        $languageRepository->expects($this->never())->method('search');
        $blogRepository->expects($this->never())->method('search');
        $analyzer->expects($this->never())->method('analyze');

        $updater = new SearchKeywordUpdater(
            static::createStub(Connection::class),
            $languageRepository,
            $blogRepository,
            $analyzer,
            new MockClock(),
            false
        );

        $updater->update(['f70db8f6eb884b1ea2a691da3f74dc93'], Context::createDefaultContext());
    }

    public function testUpdateHydratesParentNameForVariants(): void
    {
        $parentId = Uuid::randomHex();
        $childId = Uuid::randomHex();
        $standaloneId = Uuid::randomHex();

        $child = $this->createBlog($childId, $parentId);
        $standalone = $this->createBlog($standaloneId, null);

        $parent = $this->createBlog($parentId, null);
        $parent->setName('Parent blog');
        $parent->setTranslated(['name' => 'Parent blog']);

        /** @var StaticEntityRepository<BlogCollection> $blogRepository */
        $blogRepository = new StaticEntityRepository([
            // blogs to index, the second (empty) search stops the iterator
            new BlogCollection([$child, $standalone]),
            new BlogCollection(),
            // parent blogs, hydrated for the `parent.name` config field
            new BlogCollection([$parent]),
            new BlogCollection(),
        ], $this->createBlogDefinition());

        $analyzedBlogs = [];
        $analyzer = $this->createMock(BlogSearchKeywordAnalyzerInterface::class);
        $analyzer->expects($this->exactly(2))
            ->method('analyze')
            ->willReturnCallback(function (BlogEntity $blog) use (&$analyzedBlogs): AnalyzedKeywordCollection {
                $analyzedBlogs[$blog->getId()] = $blog;

                return new AnalyzedKeywordCollection();
            });

        $updater = $this->createUpdater(
            $blogRepository,
            $this->createConnection([$this->createConfigField('parent.name')]),
            $analyzer
        );

        $updater->update([$childId, $standaloneId], Context::createDefaultContext());

        // the analyzer must see the variant with its parent name available for indexing
        static::assertArrayHasKey($childId, $analyzedBlogs);
        $analyzedChild = $analyzedBlogs[$childId]->getParent();
        static::assertInstanceOf(BlogEntity::class, $analyzedChild);
        static::assertSame('Parent blog', $analyzedChild->getName());

        // blogs without a parent are left untouched
        static::assertArrayHasKey($standaloneId, $analyzedBlogs);
        static::assertNull($analyzedBlogs[$standaloneId]->getParent());
    }

    public function testUpdateDoesNotLoadParentBlogsWhenFieldIsNotConfigured(): void
    {
        $childId = Uuid::randomHex();
        $child = $this->createBlog($childId, Uuid::randomHex());

        // exactly the two searches of the blog iterator: loading parent blogs would exhaust the repository and let it throw
        /** @var StaticEntityRepository<BlogCollection> $blogRepository */
        $blogRepository = new StaticEntityRepository([
            new BlogCollection([$child]),
            new BlogCollection(),
        ], $this->createBlogDefinition());

        $updater = $this->createUpdater(
            $blogRepository,
            $this->createConnection([
                $this->createConfigField('name'),
                $this->createConfigField('description'),
            ])
        );

        $updater->update([$childId], Context::createDefaultContext());

        static::assertNull($child->getParent());
    }

    public function testUpdateFiltersTranslationsByLanguageChain(): void
    {
        $languageId = Uuid::randomHex();
        $parentLanguageId = Uuid::randomHex();

        $criteria = null;
        /** @var StaticEntityRepository<BlogCollection> $blogRepository */
        $blogRepository = new StaticEntityRepository([
            function (Criteria $searchCriteria) use (&$criteria): BlogCollection {
                $criteria = $searchCriteria;

                return new BlogCollection();
            },
        ], $this->createBlogDefinition());

        $updater = $this->createUpdater(
            $blogRepository,
            languageRepository: new StaticEntityRepository([
                new LanguageCollection([$this->createLanguage($languageId, $parentLanguageId)]),
            ])
        );

        $updater->update([Uuid::randomHex()], Context::createDefaultContext());

        static::assertInstanceOf(Criteria::class, $criteria);

        $translationFilters = [];
        foreach ($this->flattenFilters($criteria->getFilters()) as $filter) {
            if ($filter instanceof EqualsAnyFilter
                && \in_array($filter->getField(), ['translations.languageId', 'parent.translations.languageId'], true)) {
                $translationFilters[$filter->getField()] = $filter->getValue();
            }
        }

        // both the blog and the parent translations must be matched against the full
        // language inheritance chain, not just the current sales channel language
        $chain = [$languageId, $parentLanguageId, Defaults::LANGUAGE_SYSTEM];
        static::assertArrayHasKey('translations.languageId', $translationFilters);
        static::assertArrayHasKey('parent.translations.languageId', $translationFilters);
        static::assertSame($chain, $translationFilters['translations.languageId']);
        static::assertSame($chain, $translationFilters['parent.translations.languageId']);
    }

    /**
     * @param EntityRepository<BlogCollection> $blogRepository
     * @param EntityRepository<LanguageCollection>|null $languageRepository
     */
    private function createUpdater(
        EntityRepository $blogRepository,
        ?Connection $connection = null,
        ?BlogSearchKeywordAnalyzerInterface $analyzer = null,
        ?EntityRepository $languageRepository = null,
    ): SearchKeywordUpdater {
        if ($analyzer === null) {
            $analyzer = static::createStub(BlogSearchKeywordAnalyzerInterface::class);
            $analyzer->method('analyze')->willReturn(new AnalyzedKeywordCollection());
        }

        return new SearchKeywordUpdater(
            $connection ?? $this->createConnection(),
            $languageRepository ?? new StaticEntityRepository([
            ]),
            $blogRepository,
            $analyzer,
            new MockClock()
        );
    }

    /**
     * The searchable fields are configured in `blog_search_config_field`, so the
     * config rows have to be provided through the connection.
     *
     * @param list<array{field: string, tokenize: '1'|'0', ranking: numeric-string, language_id: string}> $configFields
     */
    private function createConnection(array $configFields = []): Connection&Stub
    {
        $result = static::createStub(Result::class);
        $result->method('fetchAllAssociative')->willReturn($configFields);

        $queryBuilder = static::createStub(QueryBuilder::class);
        $queryBuilder->method('executeQuery')->willReturn($result);

        $connection = static::createStub(Connection::class);
        $connection->method('createQueryBuilder')->willReturn($queryBuilder);

        return $connection;
    }

    /**
     * @return array{field: string, tokenize: '1'|'0', ranking: numeric-string, language_id: string}
     */
    private function createConfigField(string $field, string $languageId = Defaults::LANGUAGE_SYSTEM): array
    {
        return [
            'field' => $field,
            'tokenize' => '1',
            'ranking' => '500',
            'language_id' => $languageId,
        ];
    }

    /**
     * The searchable fields are resolved through the blog definition, so the translation
     * definition has to be registered as well to resolve accessors like `name`.
     */
    private function createBlogDefinition(): BlogDefinition
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [BlogDefinition::class, BlogTranslationDefinition::class],
            Validation::createValidator(),
            new StaticEntityWriterGateway()
        );

        $definition = $registry->getByEntityName(BlogDefinition::ENTITY_NAME);
        static::assertInstanceOf(BlogDefinition::class, $definition);

        return $definition;
    }

    private function createLanguage(string $id, ?string $parentId = null): LanguageEntity
    {
        $language = new LanguageEntity();
        $language->setId($id);
        $language->setUniqueIdentifier($id);
        $language->setParentId($parentId);

        return $language;
    }

    private function createBlog(string $id, ?string $parentId): BlogEntity
    {
        $blog = new BlogEntity();
        $blog->setUniqueIdentifier($id);
        $blog->setId($id);
        $blog->setAutoIncrement(1);
        $blog->setParentId($parentId);

        return $blog;
    }

    /**
     * @param Filter[] $filters
     *
     * @return Filter[]
     */
    private function flattenFilters(array $filters): array
    {
        $result = [];
        foreach ($filters as $filter) {
            $result[] = $filter;
            if ($filter instanceof MultiFilter) {
                $result = array_merge($result, $this->flattenFilters($filter->getQueries()));
            }
        }

        return $result;
    }
}
