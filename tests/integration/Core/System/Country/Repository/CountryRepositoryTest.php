<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Country\Repository;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Term\EntityScoreQueryBuilder;
use Contena\Core\Framework\DataAbstractionLayer\Search\Term\SearchTermInterpreter;
use Contena\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Country\CountryCollection;

/**
 * @internal
 */
class CountryRepositoryTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    /**
     * @var EntityRepository<CountryCollection>
     */
    private EntityRepository $repository;

    protected function setUp(): void
    {
        $this->repository = static::getContainer()->get('country.repository');
    }

    public function testSearchRanking(): void
    {
        $recordA = Uuid::randomHex();
        $recordB = Uuid::randomHex();

        $records = [
            ['id' => $recordA, 'name' => 'match'],
            ['id' => $recordB, 'name' => 'not', 'iso' => 'match'],
        ];

        $this->repository->create($records, Context::createDefaultContext());

        $criteria = new Criteria();

        $context = Context::createDefaultContext();
        $builder = static::getContainer()->get(EntityScoreQueryBuilder::class);
        $pattern = static::getContainer()->get(SearchTermInterpreter::class)->interpret('match');
        $queries = $builder->buildScoreQueries(
            $pattern,
            $this->repository->getDefinition(),
            $this->repository->getDefinition()->getEntityName(),
            $context
        );
        $criteria->addQuery(...$queries);

        $result = $this->repository->searchIds($criteria, Context::createDefaultContext());

        static::assertCount(2, $result->getIds());

        static::assertSame(
            [$recordA, $recordB],
            $result->getIds()
        );

        static::assertGreaterThan(
            $result->getDataFieldOfId($recordB, '_score'),
            $result->getDataFieldOfId($recordA, '_score')
        );
    }
}
