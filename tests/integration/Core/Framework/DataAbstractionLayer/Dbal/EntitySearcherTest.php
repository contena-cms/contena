<?php

declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogCategory\BlogCategoryDefinition;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Content\Test\Category\CategoryBuilder;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Context\SystemSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\CriteriaQueryBuilder;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\EntitySearcher;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Contena\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
class EntitySearcherTest extends TestCase
{
    use IntegrationTestBehaviour;

    private EntitySearcher $entitySearcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entitySearcher = new EntitySearcher(
            static::getContainer()->get(Connection::class),
            static::getContainer()->get(EntityDefinitionQueryHelper::class),
            static::getContainer()->get(CriteriaQueryBuilder::class),
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        static::getContainer()->get(Connection::class)->executeQuery('SET FOREIGN_KEY_CHECKS=1;');
    }

    public function testSearchFiltersByTranslatedFieldsInAssociations(): void
    {
        $ids = new IdsCollection();
        $this->createCategory(
            defaultTranslation: 'Category 1',
            deDeTranslation: null,
            ids: $ids,
        );
        $this->createCategory(
            defaultTranslation: 'Category 2',
            deDeTranslation: 'Kategorie 2',
            ids: $ids,
        );
        $blogId1 = $this->createBlog(
            blogNumber: 'blog-1',
            deDeTranslation: 'Deutscher Name',
            defaultTranslation: 'German name',
            categories: ['Category 1'],
            ids: $ids,
        );
        $this->createBlog(
            blogNumber: 'blog-2',
            deDeTranslation: 'Deutsches Produkt',
            defaultTranslation: 'German blog',
            categories: ['Category 2'],
            ids: $ids,
        );

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'Deutscher Name'));
        $criteria->addFilter(new EqualsFilter('categories.name', 'Category 1'));

        $blogIds = $this->entitySearcher->search(
            static::getContainer()->get(BlogDefinition::class),
            $criteria,
            self::createLocalizedContext([
                $this->getDeDeLanguageId(),
                Defaults::LANGUAGE_SYSTEM,
            ]),
        );

        static::assertSame([$blogId1], $blogIds->getIds());
    }

    public function testSearchFiltersByTranslatedFieldsInAssociationsByApplyingLanguageOverrides(): void
    {
        $ids = new IdsCollection();
        $this->createCategory(
            defaultTranslation: 'category-1',
            deDeTranslation: 'Kategorie 1',
            ids: $ids,
        );
        $this->createCategory(
            defaultTranslation: 'category-2',
            deDeTranslation: 'Kategorie 2',
            ids: $ids,
        );
        $blogId1 = $this->createBlog(
            blogNumber: 'blog-1',
            deDeTranslation: 'Deutscher Name',
            categories: ['category-1'],
            ids: $ids,
        );
        $this->createBlog(
            blogNumber: 'blog-2',
            deDeTranslation: 'Deutsches Produkt',
            categories: ['category-2'],
            ids: $ids,
        );

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'Deutscher Name'));
        $criteria->addFilter(new EqualsFilter('categories.name', 'Kategorie 1'));

        $blogIds = $this->entitySearcher->search(
            static::getContainer()->get(BlogDefinition::class),
            $criteria,
            self::createLocalizedContext([
                $this->getDeDeLanguageId(),
                Defaults::LANGUAGE_SYSTEM,
            ]),
        );

        static::assertSame([$blogId1], $blogIds->getIds());
    }

    public function testSearchFiltersByTranslatedFieldsByApplyingInheritanceAndLanguageOverridesPreferringOwnTranslation(): void
    {
        $ids = new IdsCollection();
        $blogId1 = $this->createBlog(
            blogNumber: 'blog-1',
            deDeTranslation: 'Parent: Deutscher Name',
            defaultTranslation: 'Parent: Fallback name',
            ids: $ids,
        );
        $blogId2 = $this->createBlog(
            blogNumber: 'blog-2',
            deDeTranslation: 'Deutscher Name',
            parentBlogNumber: 'blog-1',
            ids: $ids,
        );
        // The following blog should not be matched because its deDeTranslation takes precedence over the parent's
        $this->createBlog(
            blogNumber: 'blog-3',
            deDeTranslation: 'Deutsches Produkt',
            defaultTranslation: 'German blog',
            parentBlogNumber: 'blog-1',
            ids: $ids,
        );

        $criteria = new Criteria();
        $criteria->addFilter(new ContainsFilter('name', 'Deutscher Name'));

        $blogIds = $this->entitySearcher->search(
            static::getContainer()->get(BlogDefinition::class),
            $criteria,
            self::createLocalizedContext([
                $this->getDeDeLanguageId(),
                Defaults::LANGUAGE_SYSTEM,
            ]),
        );

        static::assertSame(
            [
                $blogId1,
                $blogId2,
            ],
            $blogIds->getIds(),
        );
    }

    public function testSearchAppliesTermToTranslatedFields(): void
    {
        $blogId1 = $this->createBlog(
            blogNumber: 'blog-1',
            deDeTranslation: null,
            defaultTranslation: 'German name',
        );
        $this->createBlog(
            blogNumber: 'blog-2',
            deDeTranslation: 'Deutsches Produkt',
            defaultTranslation: 'German blog',
        );

        $criteria = new Criteria();
        $criteria->setTerm('German name');

        $blogIds = $this->entitySearcher->search(
            static::getContainer()->get(BlogDefinition::class),
            $criteria,
            self::createLocalizedContext([
                $this->getDeDeLanguageId(),
                Defaults::LANGUAGE_SYSTEM,
            ]),
        );

        static::assertSame([$blogId1], $blogIds->getIds());
    }

    public function testSearchAppliesTermToTranslatedFieldsByApplyingLanguageOverrides(): void
    {
        $blogId1 = $this->createBlog(
            blogNumber: 'blog-1',
            deDeTranslation: 'Deutscher Name',
            defaultTranslation: 'German name',
        );
        $this->createBlog(
            blogNumber: 'blog-2',
            deDeTranslation: 'Deutsches Produkt',
            defaultTranslation: 'German blog',
        );

        $criteria = new Criteria();
        $criteria->setTerm('Deutscher Name');

        $blogIds = $this->entitySearcher->search(
            static::getContainer()->get(BlogDefinition::class),
            $criteria,
            self::createLocalizedContext([
                $this->getDeDeLanguageId(),
                Defaults::LANGUAGE_SYSTEM,
            ]),
        );

        static::assertSame([$blogId1], $blogIds->getIds());
    }

    public function testSearchAppliesQueryToTranslatedFields(): void
    {
        $blogId1 = $this->createBlog(
            blogNumber: 'blog-1',
            deDeTranslation: null,
            defaultTranslation: 'German name',
        );
        $this->createBlog(
            blogNumber: 'blog-2',
            deDeTranslation: 'Deutsches Produkt',
            defaultTranslation: 'German blog',
        );

        $criteria = new Criteria();
        $criteria->addQuery(new ScoreQuery(new EqualsFilter('name', 'German name'), score: 100));

        $blogIds = $this->entitySearcher->search(
            static::getContainer()->get(BlogDefinition::class),
            $criteria,
            self::createLocalizedContext([
                $this->getDeDeLanguageId(),
                Defaults::LANGUAGE_SYSTEM,
            ]),
        );

        static::assertSame([$blogId1], $blogIds->getIds());
    }

    public function testSearchAppliesQueryToTranslatedFieldsByApplyingLanguageOverrides(): void
    {
        $blogId1 = $this->createBlog(
            blogNumber: 'blog-1',
            deDeTranslation: 'Deutscher Name',
            defaultTranslation: 'German name',
        );
        $this->createBlog(
            blogNumber: 'blog-2',
            deDeTranslation: 'Deutsches Produkt',
            defaultTranslation: 'German blog',
        );

        $criteria = new Criteria();
        $criteria->addQuery(new ScoreQuery(new EqualsFilter('name', 'Deutscher Name'), score: 100));

        $blogIds = $this->entitySearcher->search(
            static::getContainer()->get(BlogDefinition::class),
            $criteria,
            self::createLocalizedContext([
                $this->getDeDeLanguageId(),
                Defaults::LANGUAGE_SYSTEM,
            ]),
        );

        static::assertSame([$blogId1], $blogIds->getIds());
    }

    public function testSearchFiltersByAndAppliesQueryToTranslatedFields(): void
    {
        $ids = new IdsCollection();
        $blogBuilder1 = $this->buildBlog(
            deDeTranslation: 'Deutscher Name',
            blogNumber: 'blog-1',
            ids: $ids,
        );
        $blogBuilder1->translation($this->getDeDeLanguageId(), 'keywords', 'Schlagwort');
        $blogBuilder2 = $this->buildBlog(
            deDeTranslation: 'Deutsches Produkt',
            blogNumber: 'blog-2',
            ids: $ids,
        );
        static::getContainer()->get('blog.repository')->create(
            [
                $blogBuilder1->build(),
                $blogBuilder2->build(),
            ],
            Context::createDefaultContext(),
        );
        $blogId1 = $ids->get('blog-1');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('keywords', 'Schlagwort'));
        $criteria->addQuery(new ScoreQuery(new ContainsFilter('name', 'Deutsch'), score: 100));

        $blogIds = $this->entitySearcher->search(
            static::getContainer()->get(BlogDefinition::class),
            $criteria,
            self::createLocalizedContext([
                $this->getDeDeLanguageId(),
                Defaults::LANGUAGE_SYSTEM,
            ]),
        );

        static::assertSame([$blogId1], $blogIds->getIds());
    }

    public function testScoreRankingPicksHighestScoredRowPerGroup(): void
    {
        $ids = $this->createSportBlogs();

        $criteria = $this->createScoreRankedCriteria($ids);
        $criteria->addQuery(new ScoreQuery(new ContainsFilter('name', 'Sport'), score: 100));
        $criteria->addQuery(new ScoreQuery(new ContainsFilter('name', 'Premium'), score: 300));
        $criteria->addQuery(new ScoreQuery(new ContainsFilter('name', 'Green'), score: 500));

        $result = $this->entitySearcher->search(
            static::getContainer()->get(BlogDefinition::class),
            $criteria,
            Context::createDefaultContext(),
        );

        $resultIds = array_values($result->getIds());

        static::assertCount(2, $resultIds);
        static::assertSame($ids->get('b2'), $resultIds[0], 'Highest overall score (Sport Gear Premium Green) should be first');
        static::assertSame($ids->get('a3'), $resultIds[1], 'Highest score in Group A (Sport Bottle Green) should be second');
    }

    public function testScoreRankingRespectsTheSortingOfTheCriteria(): void
    {
        $ids = $this->createSportBlogs();

        $criteria = $this->createScoreRankedCriteria($ids);
        $criteria->addQuery(new ScoreQuery(new ContainsFilter('name', 'Sport'), score: 100));
        $criteria->addQuery(new ScoreQuery(new ContainsFilter('name', 'Premium'), score: 300));
        $criteria->addQuery(new ScoreQuery(new ContainsFilter('name', 'Green'), score: 500));

        $criteria->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));
        $criteria->addSorting(new FieldSorting(Criteria::SCORE_FIELD, FieldSorting::DESCENDING));

        $result = $this->entitySearcher->search(
            static::getContainer()->get(BlogDefinition::class),
            $criteria,
            Context::createDefaultContext(),
        );

        static::assertSame(
            [$ids->get('a3'), $ids->get('b2')],
            array_values($result->getIds()),
            'The sorting of the criteria has to win over the score'
        );
    }

    public function testScoreRankingDoesNotLeakItsHelperColumnsIntoTheResult(): void
    {
        $ids = $this->createSportBlogs();

        $criteria = $this->createScoreRankedCriteria($ids);
        $criteria->addQuery(new ScoreQuery(new ContainsFilter('name', 'Sport'), score: 100));
        // a sorting other than _score is what makes the ranking add its `_sort_*` columns
        $criteria->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));

        $result = $this->entitySearcher->search(
            static::getContainer()->get(BlogDefinition::class),
            $criteria,
            Context::createDefaultContext(),
        );

        $data = $result->getDataOfId($ids->get('a1'));

        static::assertIsArray($data);
        static::assertArrayHasKey(Criteria::SCORE_FIELD, $data);
        static::assertArrayNotHasKey('_rn', $data);
        static::assertArrayNotHasKey('_group_0', $data);
        static::assertArrayNotHasKey('_sort_0', $data);
    }

    public function testScoreRankingSupportsCombinedPrimaryKeys(): void
    {
        $ids = new IdsCollection();

        static::getContainer()->get('blog.repository')->create(
            [(new BlogBuilder($ids, 'mapped'))->price(100)->name('Sport Bottle')->category('sports')->build()],
            Context::createDefaultContext(),
        );

        $criteria = new Criteria();
        $criteria->addQuery(new ScoreQuery(new EqualsFilter('categoryId', $ids->get('sports')), score: 100));
        $criteria->addGroupField(new FieldGrouping('categoryId'));

        $result = $this->entitySearcher->search(
            static::getContainer()->get(BlogCategoryDefinition::class),
            $criteria,
            Context::createDefaultContext(),
        );

        static::assertSame(
            [['blogId' => $ids->get('mapped'), 'categoryId' => $ids->get('sports')]],
            $result->getIds()
        );
    }

    private function createSportBlogs(): IdsCollection
    {
        $ids = new IdsCollection();

        // Group A: "Sport Bottle" variants — all share a displayGroup via parent-a
        $parentA = (new BlogBuilder($ids, 'parent-a'))->price(100)
            ->name('Parent A');
        $a1 = (new BlogBuilder($ids, 'a1'))->parent('parent-a')->tax(null)
            ->name('Sport Bottle Blue');
        $a2 = (new BlogBuilder($ids, 'a2'))->parent('parent-a')->tax(null)
            ->name('Sport Bottle Red');
        $a3 = (new BlogBuilder($ids, 'a3'))->parent('parent-a')->tax(null)
            ->name('Sport Bottle Green');

        // Group B: "Sport Gear" variants — all share a displayGroup via parent-b
        $parentB = (new BlogBuilder($ids, 'parent-b'))->price(100)
            ->name('Parent B');
        $b1 = (new BlogBuilder($ids, 'b1'))->parent('parent-b')->tax(null)
            ->name('Sport Gear Standard');
        $b2 = (new BlogBuilder($ids, 'b2'))->parent('parent-b')->tax(null)
            ->name('Sport Gear Premium Green');

        static::getContainer()->get('blog.repository')->create(
            [$parentA->build(), $a1->build(), $a2->build(), $a3->build(), $parentB->build(), $b1->build(), $b2->build()],
            Context::createDefaultContext(),
        );

        return $ids;
    }

    private function createScoreRankedCriteria(IdsCollection $ids): Criteria
    {
        $criteria = new Criteria();
        $criteria->addGroupField(new FieldGrouping('displayGroup'));
        $criteria->addFilter(new EqualsAnyFilter(
            'parentId',
            [$ids->get('parent-a'), $ids->get('parent-b')]
        ));

        return $criteria;
    }

    /**
     * @param list<string> $categories
     */
    private function createBlog(
        string $blogNumber,
        ?string $deDeTranslation,
        ?string $defaultTranslation = null,
        array $categories = [],
        ?string $parentBlogNumber = null,
        ?IdsCollection $ids = null,
    ): string {
        $ids ??= new IdsCollection();
        $blogBuilder = $this->buildBlog(
            deDeTranslation: $deDeTranslation,
            defaultTranslation: $defaultTranslation,
            blogNumber: $blogNumber,
            parentBlogNumber: $parentBlogNumber,
            ids: $ids,
        );
        $blogBuilder->categories($categories);

        static::getContainer()->get('blog.repository')->create(
            [$blogBuilder->build()],
            Context::createDefaultContext()
        );

        return $ids->get($blogNumber);
    }

    private function buildBlog(
        ?string $deDeTranslation = null,
        ?string $defaultTranslation = null,
        string $blogNumber = 'blog-1',
        ?string $parentBlogNumber = null,
        ?IdsCollection $ids = null,
    ): BlogBuilder {
        $ids ??= new IdsCollection();
        $blogBuilder = new BlogBuilder($ids, $blogNumber);
        $blogBuilder->price(100);
        if ($deDeTranslation !== null) {
            $blogBuilder->translation($this->getDeDeLanguageId(), 'name', $deDeTranslation);
        }
        if ($defaultTranslation !== null) {
            $blogBuilder->translation(Defaults::LANGUAGE_SYSTEM, 'name', $defaultTranslation);
        }
        if ($parentBlogNumber !== null) {
            $blogBuilder->parent($parentBlogNumber);
        }

        return $blogBuilder;
    }

    private function createCategory(
        string $defaultTranslation,
        ?string $deDeTranslation,
        ?IdsCollection $ids = null
    ): string {
        $ids ??= new IdsCollection();
        // Category does not have a name filed but only translations, hence the default translation must be passed to
        // the builder as the name
        $categoryBuilder = new CategoryBuilder($ids, categoryName: $defaultTranslation);
        if ($deDeTranslation !== null) {
            $categoryBuilder->translation($this->getDeDeLanguageId(), 'name', $deDeTranslation);
        }

        static::getContainer()->get('category.repository')->create(
            [$categoryBuilder->build()],
            Context::createDefaultContext(),
        );

        return $ids->get($defaultTranslation);
    }

    /**
     * @param non-empty-list<string> $languageIdChain
     */
    private static function createLocalizedContext(array $languageIdChain): Context
    {
        return new Context(
            source: new SystemSource(),
            ruleIds: [],
            currencyId: Defaults::CURRENCY,
            languageIdChain: $languageIdChain,
        );
    }
}
