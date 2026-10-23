<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Blog\Repository;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\SearchKeyword\BlogSearchBuilder;
use Contena\Core\Content\Blog\SearchKeyword\BlogSearchTermInterpreterInterface;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Contena\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Contena\Core\Framework\DataAbstractionLayer\Search\Term\EntityScoreQueryBuilder;
use Contena\Core\Framework\DataAbstractionLayer\Search\Term\SearchPattern;
use Contena\Core\Framework\DataAbstractionLayer\Search\Term\SearchTerm;
use Contena\Core\Framework\Struct\ArrayEntity;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class BlogSearchScoringTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<BlogCollection>
     */
    private EntityRepository $repository;

    protected function setUp(): void
    {
        $this->repository = static::getContainer()->get('blog.repository');
    }

    public function testScoringExtensionExists(): void
    {
        $context = Context::createDefaultContext();
        $pattern = new SearchPattern(new SearchTerm('test'));
        $builder = new EntityScoreQueryBuilder();
        $queries = $builder->buildScoreQueries(
            $pattern,
            static::getContainer()->get(BlogDefinition::class),
            static::getContainer()->get(BlogDefinition::class)->getEntityName(),
            $context
        );

        $criteria = new Criteria();
        $criteria->addQuery(...$queries);

        $this->repository->create([
            ['id' => Uuid::randomHex(), 'blogNumber' => Uuid::randomHex(), 'stock' => 10, 'name' => 'blog 1 test', 'tax' => ['name' => 'test', 'taxRate' => 5], 'manufacturer' => ['name' => 'test'], 'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]]],
            ['id' => Uuid::randomHex(), 'blogNumber' => Uuid::randomHex(), 'stock' => 10, 'name' => 'blog 2 test', 'tax' => ['name' => 'test', 'taxRate' => 5], 'manufacturer' => ['name' => 'test'], 'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]]],
        ], $context);

        foreach ($this->repository->search($criteria, $context)->getEntities() as $entity) {
            static::assertArrayHasKey('search', $entity->getExtensions());
            $extension = $entity->getExtension('search');

            static::assertInstanceOf(ArrayEntity::class, $extension);
            static::assertArrayHasKey('_score', $extension);
            static::assertGreaterThan(0, (float) $extension->get('_score'));
        }
    }

    public function testMultipleMatchingKeywordsHaveHigherScore(): void
    {
        $context = Context::createDefaultContext();
        $mockChannelContext = static::createStub(ChannelContext::class);
        $mockChannelContext->method('getContext')->willReturn($context);
        $mockChannelContext->method('getLanguageId')->willReturn($context->getLanguageId());

        $blogMultipleMatchId = Uuid::randomHex();
        $blogFirstWordMatchId = Uuid::randomHex();
        $blogSecondWordMatchId = Uuid::randomHex();
        $blogNoWordMatchId = Uuid::randomHex();

        $this->repository->create([
            [
                'id' => $blogMultipleMatchId,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 10,
                'name' => 'Saphir Ring Gold',
                'tax' => ['name' => 'test', 'taxRate' => 5],
                'manufacturer' => ['name' => 'test'],
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            ],
            [
                'id' => $blogFirstWordMatchId,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 10,
                'name' => 'Saphir Necklace Gold',
                'tax' => ['name' => 'test', 'taxRate' => 5],
                'manufacturer' => ['name' => 'test'],
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            ],
            [
                'id' => $blogSecondWordMatchId,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 10,
                'name' => 'Diamond Ring Silver',
                'tax' => ['name' => 'test', 'taxRate' => 5],
                'manufacturer' => ['name' => 'test'],
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            ],
            [
                'id' => $blogNoWordMatchId,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 10,
                'name' => 'Diamond Necklace Silver',
                'tax' => ['name' => 'test', 'taxRate' => 5],
                'manufacturer' => ['name' => 'test'],
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            ],
        ], $context);

        $pattern = new SearchPattern(new SearchTerm('ring saphir', 1.0));
        $pattern->addTerm(new SearchTerm('ring', 1.1));
        $pattern->addTerm(new SearchTerm('saphir', 1.1));
        $pattern->addTerm(new SearchTerm('saphir ring gold', 0.9));
        $pattern->addTerm(new SearchTerm('saphir necklace gold', 0.5));
        $pattern->setTokenTerms([
            ['ring'],
            ['saphir', 'saphir necklace gold', 'saphir ring gold'],
        ]);

        $termInterpreter = $this->createMock(BlogSearchTermInterpreterInterface::class);
        $termInterpreter->expects($this->once())
            ->method('interpret')
            ->with('ring saphir', static::isInstanceOf(Context::class))
            ->willReturn($pattern);
        $logger = static::createStub(LoggerInterface::class);
        $searchBuilder = new BlogSearchBuilder(
            $termInterpreter,
            $logger,
            20
        );

        $criteria = new Criteria();
        $request = new Request();

        $request->query->set('search', 'ring saphir');

        $searchBuilder->build($request, $criteria, $mockChannelContext);

        $result = $this->repository->search($criteria, $context);

        $blogResults = [];

        foreach ($result->getEntities() as $entity) {
            static::assertArrayHasKey('search', $entity->getExtensions());
            $extension = $entity->getExtension('search');

            static::assertInstanceOf(ArrayEntity::class, $extension);
            static::assertArrayHasKey('_score', $extension);

            $blogResults[$entity->getId()] = [
                'name' => $entity->getName(),
                'score' => (float) $extension->get('_score'),
            ];
        }

        static::assertArrayHasKey($blogMultipleMatchId, $blogResults);
        static::assertArrayHasKey($blogFirstWordMatchId, $blogResults);
        static::assertArrayHasKey($blogSecondWordMatchId, $blogResults);
        static::assertArrayNotHasKey($blogNoWordMatchId, $blogResults);
        static::assertSame(2170.0, round($blogResults[$blogMultipleMatchId]['score']));
        static::assertSame(1120.0, round($blogResults[$blogFirstWordMatchId]['score']));
        static::assertSame(770.0, round($blogResults[$blogSecondWordMatchId]['score']));
    }

    public function testGroupedVariantsScoreLikeAComparableSingleBlog(): void
    {
        $context = Context::createDefaultContext();

        $singleBlogId = Uuid::randomHex();
        $variantIds = [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()];

        $this->repository->create([
            $this->buildBlog($singleBlogId, 'Arbeitshandschuhe'),
            $this->buildVariantBlog(Uuid::randomHex(), 'Arbeitshandschuhe', $variantIds),
        ], $context);

        $result = $this->repository->searchIds($this->buildGroupedScoreCriteria(['arbeitshandschuhe']), $context);

        $ids = $result->getIds();

        // one entry for the single blog, one for the group of three variants
        static::assertCount(2, $ids);

        $singleBlogScore = (float) $result->getDataFieldOfId($singleBlogId, '_score');
        static::assertEqualsWithDelta(700000.0, $singleBlogScore, 1.0);

        $groupId = $result->getIds()[0] === $singleBlogId ? $result->getIds()[1] : $result->getIds()[0];
        static::assertIsString($groupId);
        static::assertContains($groupId, $variantIds);
        static::assertEqualsWithDelta(
            $singleBlogScore,
            (float) $result->getDataFieldOfId($groupId, '_score'),
            1.0,
            'The variant group must not be scored higher than a comparable single blog'
        );
    }

    public function testGroupedVariantsAreScoredByTheirBestMatchingVariant(): void
    {
        $context = Context::createDefaultContext();

        $bestVariantId = Uuid::randomHex();
        $variantIds = [$bestVariantId, Uuid::randomHex(), Uuid::randomHex()];

        $blog = $this->buildVariantBlog(Uuid::randomHex(), 'Arbeitshandschuhe', $variantIds);
        // only this variant matches the second score query, so it is the best match of the group
        $blog['children'][0]['name'] = 'Arbeitshandschuhe Leder';

        $this->repository->create([$blog], $context);

        $criteria = $this->buildGroupedScoreCriteria(['arbeitshandschuhe', 'leder']);

        $result = $this->repository->searchIds($criteria, $context);

        static::assertSame([$bestVariantId], $result->getIds());
        // the group scores like its best variant, not like the average of its variants
        static::assertEqualsWithDelta(1400000.0, (float) $result->getDataFieldOfId($bestVariantId, '_score'), 1.0);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildBlog(string $id, string $name): array
    {
        return [
            'id' => $id,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 10,
            'name' => $name,
            'tax' => ['name' => 'test', 'taxRate' => 5],
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
        ];
    }

    /**
     * @param array<string> $variantIds
     *
     * @return array<string, mixed>
     */
    private function buildVariantBlog(string $parentId, string $name, array $variantIds): array
    {
        $groupId = Uuid::randomHex();

        $blog = $this->buildBlog($parentId, $name);
        $blog['children'] = array_map(static fn (string $variantId) => [
            'id' => $variantId,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 10,
            'options' => [
                ['id' => Uuid::randomHex(), 'name' => $variantId, 'group' => ['id' => $groupId, 'name' => 'color']],
            ],
        ], $variantIds);

        return $blog;
    }

    /**
     * @param array<string> $keywords
     */
    private function buildGroupedScoreCriteria(array $keywords): Criteria
    {
        $criteria = new Criteria();

        foreach ($keywords as $keyword) {
            $criteria->addQuery(new ScoreQuery(new EqualsFilter('searchKeywords.keyword', $keyword), 500, 'searchKeywords.ranking'));
        }

        // the same grouping the blog listing applies to display variants as one blog
        $criteria->addGroupField(new FieldGrouping('displayGroup'));
        $criteria->addFilter(new NotEqualsFilter('displayGroup', null));

        return $criteria;
    }
}
