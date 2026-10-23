<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Administration\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Administration\Framework\Search\CriteriaCollection;
use Contena\Administration\Service\AdminSearcher;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\ArrayEntity;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class AdminSearcherTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<BlogCollection>
     */
    private EntityRepository $blogRepository;

    private AdminSearcher $searcher;

    private Context $context;

    private string $admin_userId;

    protected function setUp(): void
    {
        $this->blogRepository = static::getContainer()->get('blog.repository');

        $this->searcher = static::getContainer()->get(AdminSearcher::class);

        $connection = static::getContainer()->get(Connection::class);
        $admin_userId = (string) $connection->fetchOne('SELECT id FROM `admin_user` WHERE username = "admin"');
        $this->admin_userId = Uuid::fromBytesToHex($admin_userId);
        $this->context = Context::createDefaultContext();
    }

    public function testSearch(): void
    {
        $blogId1 = Uuid::randomHex();
        $blogId2 = Uuid::randomHex();

        $this->blogRepository->upsert([
            ['id' => $blogId1, 'blogNumber' => Uuid::randomHex(), 'stock' => 1, 'name' => 'test_product 1', 'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]], 'tax' => ['name' => 'test', 'taxRate' => 5], 'manufacturer' => ['name' => 'test']],
            ['id' => $blogId2, 'blogNumber' => Uuid::randomHex(), 'stock' => 2, 'name' => 'test_product 2', 'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]], 'tax' => ['name' => 'test', 'taxRate' => 5], 'manufacturer' => ['name' => 'test']],
            ['id' => Uuid::randomHex(), 'blogNumber' => Uuid::randomHex(), 'stock' => 1, 'name' => 'notmatch', 'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]], 'tax' => ['name' => 'notmatch', 'taxRate' => 5], 'manufacturer' => ['name' => 'notmatch']],
            ['id' => Uuid::randomHex(), 'blogNumber' => Uuid::randomHex(), 'stock' => 1, 'name' => 'notmatch', 'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]], 'tax' => ['name' => 'notmatch', 'taxRate' => 5], 'manufacturer' => ['name' => 'notmatch']],
        ], $this->context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('stock', 1));
        $criteria->addQuery(new ScoreQuery(new ContainsFilter('manufacturer.name', 'test'), 500));
        $criteria->addQuery(new ScoreQuery(new ContainsFilter('name', 'test'), 2500));

        $queries = new CriteriaCollection([
            'blog' => $criteria,
        ]);

        $result = $this->searcher->search($queries, $this->context);

        static::assertCount(1, $result);

        static::assertArrayHasKey('blog', $result);

        /** @var BlogCollection $blogs */
        $blogs = $result['blog']['data'];
        $first = $blogs->first();
        $last = $blogs->last();

        static::assertInstanceOf(BlogEntity::class, $first);
        static::assertInstanceOf(BlogEntity::class, $last);

        /** @var ArrayEntity $firstSearchExtension */
        $firstSearchExtension = $first->getExtension('search');
        $firstScore = $firstSearchExtension->get('_score');

        /** @var ArrayEntity $secondSearchExtension */
        $secondSearchExtension = $first->getExtension('search');
        $secondScore = $secondSearchExtension->get('_score');

        static::assertSame($secondScore, $firstScore);
    }

    public function testSearchWithoutReadPermission(): void
    {
        $blogId1 = Uuid::randomHex();
        $blogId2 = Uuid::randomHex();

        $this->blogRepository->upsert([
            ['id' => $blogId1, 'blogNumber' => Uuid::randomHex(), 'categories' => [['name' => 'test']], 'stock' => 1, 'name' => 'test_product 1', 'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]], 'tax' => ['name' => 'test', 'taxRate' => 5], 'manufacturer' => ['name' => 'test']],
            ['id' => $blogId2, 'blogNumber' => Uuid::randomHex(), 'categories' => [['name' => 'test']], 'stock' => 2, 'name' => 'test_product 2', 'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]], 'tax' => ['name' => 'test', 'taxRate' => 5], 'manufacturer' => ['name' => 'test']],
            ['id' => Uuid::randomHex(), 'blogNumber' => Uuid::randomHex(), 'stock' => 1, 'name' => 'notmatch', 'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]], 'tax' => ['name' => 'notmatch', 'taxRate' => 5], 'manufacturer' => ['name' => 'notmatch']],
            ['id' => Uuid::randomHex(), 'blogNumber' => Uuid::randomHex(), 'stock' => 1, 'name' => 'notmatch', 'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]], 'tax' => ['name' => 'notmatch', 'taxRate' => 5], 'manufacturer' => ['name' => 'notmatch']],
        ], $this->context);

        $criteria = new Criteria();
        $criteria->addQuery(new ScoreQuery(new ContainsFilter('name', 'test'), 2500));

        $queries = new CriteriaCollection([
            'blog' => $criteria,
            'category' => $criteria,
        ]);

        $resultWithPermissions = $this->searcher->search($queries, $this->context);

        static::assertCount(2, $resultWithPermissions);

        static::assertArrayHasKey('category', $resultWithPermissions);
        static::assertArrayHasKey('blog', $resultWithPermissions);

        $adminSource = new AdminApiSource($this->admin_userId);
        $adminSource->setIsAdmin(false);
        $adminSource->setPermissions(['category:read']);

        $this->context->assign([
            'source' => $adminSource,
        ]);

        $resultWithoutPermissions = $this->searcher->search($queries, $this->context);

        static::assertCount(1, $resultWithoutPermissions);
        static::assertArrayHasKey('category', $resultWithoutPermissions);
        static::assertArrayNotHasKey('blog', $resultWithoutPermissions);
    }
}
