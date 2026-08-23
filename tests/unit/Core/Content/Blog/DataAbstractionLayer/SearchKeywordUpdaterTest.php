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
use Contena\Core\Content\Blog\DataAbstractionLayer\SearchKeywordUpdater;
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

        $updater->update([Uuid::randomHex()], Context::createDefaultContext());
    }

    public function testUpdateFiltersTranslationsByLanguageChain(): void
    {
        $languageId = Uuid::randomHex();
        $parentLanguageId = Uuid::randomHex();
        $tenantId = Uuid::randomHex();
        $criteria = null;
        $repositoryContext = null;

        /** @var StaticEntityRepository<BlogCollection> $blogRepository */
        $blogRepository = new StaticEntityRepository([
            function (Criteria $searchCriteria, Context $context) use (&$criteria, &$repositoryContext): BlogCollection {
                $criteria = $searchCriteria;
                $repositoryContext = $context;

                return new BlogCollection();
            },
        ], $this->createBlogDefinition());

        $languageRepository = StaticEntityRepository::of(LanguageCollection::class, [
            new LanguageCollection([$this->createLanguage($languageId, $parentLanguageId)]),
        ]);

        $updater = new SearchKeywordUpdater(
            $this->createConnection([[
                'field' => 'name',
                'tokenize' => '1',
                'ranking' => '500',
                'language_id' => $languageId,
            ]]),
            $languageRepository,
            $blogRepository,
            static::createStub(BlogSearchKeywordAnalyzerInterface::class),
            new MockClock(),
        );

        $updater->update([Uuid::randomHex()], Context::createTenantContext($tenantId));

        static::assertInstanceOf(Criteria::class, $criteria);
        static::assertInstanceOf(Context::class, $repositoryContext);
        static::assertSame($tenantId, $repositoryContext->getTenantId());
        $translationFilters = [];
        foreach ($this->flattenFilters($criteria->getFilters()) as $filter) {
            if ($filter instanceof EqualsAnyFilter && $filter->getField() === 'translations.languageId') {
                $translationFilters[$filter->getField()] = $filter->getValue();
            }
        }

        static::assertSame(
            [$languageId, $parentLanguageId, Defaults::LANGUAGE_SYSTEM],
            $translationFilters['translations.languageId'] ?? null
        );
    }

    /**
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
