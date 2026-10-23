<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\DataAbstractionLayer\Facade;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Framework\Api\Exception\MissingPrivilegeException;
use Contena\Core\Framework\App\AppCollection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Facade\RepositoryWriterFacadeHookFactory;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Script\Execution\Script;
use Contena\Core\Framework\Script\Execution\ScriptAppInformation;
use Contena\Core\Framework\Script\Execution\ScriptExecutor;
use Contena\Core\Framework\Test\Script\Execution\TestHook;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\System\Tax\TaxCollection;
use Contena\Core\System\Tax\TaxEntity;
use Contena\Core\Test\AppSystemTestBehaviour;
use Contena\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
class RepositoryWriterFacadeTest extends TestCase
{
    use AppSystemTestBehaviour;
    use IntegrationTestBehaviour;

    private IdsCollection $ids;

    private RepositoryWriterFacadeHookFactory $factory;

    private Context $context;

    /**
     * @var EntityRepository<BlogCollection>
     */
    private EntityRepository $blogRepository;

    protected function setUp(): void
    {
        $this->factory = static::getContainer()->get(RepositoryWriterFacadeHookFactory::class);
        $this->context = Context::createDefaultContext();
        $this->blogRepository = static::getContainer()->get('blog.repository');
    }

    public function testCreateViaUpsert(): void
    {
        $this->ids = new IdsCollection();
        $this->createBlogs();

        $facade = $this->factory->factory(
            new TestHook('test', $this->context),
            new Script('test', '', new \DateTimeImmutable())
        );

        $payload = [
            (new BlogBuilder($this->ids, 'p4'))
                ->visibility()
                ->price(300)
                ->build(),
        ];

        $facade->upsert('blog', $payload);
        $createdBlog = $this->blogRepository->search(new Criteria([$this->ids->get('p4')]), $this->context)->getEntities()->first();

        static::assertInstanceOf(BlogEntity::class, $createdBlog);
    }

    public function testUpdateViaUpsert(): void
    {
        $this->ids = new IdsCollection();
        $this->createBlogs();

        $facade = $this->factory->factory(
            new TestHook('test', $this->context),
            new Script('test', '', new \DateTimeImmutable())
        );

        $payload = [
            [
                'id' => $this->ids->get('p2'),
                'active' => true,
            ],
        ];

        $facade->upsert('blog', $payload);
        $updated = $this->blogRepository->search(new Criteria([$this->ids->get('p2')]), $this->context)->getEntities()->first();

        static::assertInstanceOf(BlogEntity::class, $updated);
        static::assertTrue($updated->getActive());
    }

    public function testDelete(): void
    {
        $this->ids = new IdsCollection();
        $this->createBlogs();

        $facade = $this->factory->factory(
            new TestHook('test', $this->context),
            new Script('test', '', new \DateTimeImmutable())
        );

        $facade->delete('blog', [['id' => $this->ids->get('p2')]]);
        $deleted = $this->blogRepository->search(new Criteria([$this->ids->get('p2')]), $this->context)->getEntities()->first();

        static::assertNull($deleted);
    }

    public function testSync(): void
    {
        $this->ids = new IdsCollection();
        $this->createBlogs();

        $facade = $this->factory->factory(
            new TestHook('test', $this->context),
            new Script('test', '', new \DateTimeImmutable())
        );

        $facade->sync([
            [
                'entity' => 'blog',
                'action' => 'upsert',
                'payload' => [
                    (new BlogBuilder($this->ids, 'p4'))
                        ->visibility()
                        ->price(300)
                        ->build(),
                    [
                        'id' => $this->ids->get('p2'),
                        'active' => true,
                    ],
                ],
            ],
            [
                'entity' => 'blog',
                'action' => 'delete',
                'payload' => [
                    ['id' => $this->ids->get('p3')],
                ],
            ],
        ]);

        $this->blogRepository = static::getContainer()->get('blog.repository');

        $createdBlog = $this->blogRepository->search(new Criteria([$this->ids->get('p4')]), $this->context)->getEntities()->first();
        static::assertInstanceOf(BlogEntity::class, $createdBlog);

        $updated = $this->blogRepository->search(new Criteria([$this->ids->get('p2')]), $this->context)->getEntities()->first();
        static::assertInstanceOf(BlogEntity::class, $updated);
        static::assertTrue($updated->getActive());

        $deleted = $this->blogRepository->search(new Criteria([$this->ids->get('p3')]), $this->context)->getEntities()->first();
        static::assertNull($deleted);
    }

    public function testUpsertWithoutPermission(): void
    {
        $this->ids = new IdsCollection();
        $this->createBlogs();

        $appInfo = $this->installApp(__DIR__ . '/_fixtures/apps/withoutBlogPermission');

        $facade = $this->factory->factory(
            new TestHook('test', Context::createDefaultContext()),
            new Script('test', '', new \DateTimeImmutable(), $appInfo)
        );

        static::expectException(MissingPrivilegeException::class);
        $facade->upsert('blog', [
            [
                'id' => $this->ids->get('p2'),
                'active' => true,
            ],
        ]);
    }

    public function testDeleteWithoutPermission(): void
    {
        $this->ids = new IdsCollection();
        $this->createBlogs();

        $appInfo = $this->installApp(__DIR__ . '/_fixtures/apps/withoutBlogPermission');

        $facade = $this->factory->factory(
            new TestHook('test', Context::createDefaultContext()),
            new Script('test', '', new \DateTimeImmutable(), $appInfo)
        );

        static::expectException(MissingPrivilegeException::class);
        $facade->upsert('blog', [['id' => $this->ids->get('p3')]]);
    }

    public function testSyncWithoutPermission(): void
    {
        $this->ids = new IdsCollection();
        $this->createBlogs();

        $appInfo = $this->installApp(__DIR__ . '/_fixtures/apps/withoutBlogPermission');

        $facade = $this->factory->factory(
            new TestHook('test', Context::createDefaultContext()),
            new Script('test', '', new \DateTimeImmutable(), $appInfo)
        );

        static::expectException(MissingPrivilegeException::class);
        $facade->sync([
            [
                'entity' => 'blog',
                'action' => 'delete',
                'payload' => [['id' => $this->ids->get('p3')]],
            ],
        ]);
    }

    public function testIntegrationCreateCase(): void
    {
        $this->installApp(__DIR__ . '/_fixtures/apps/pageLoadedExample');

        $hook = new TestHook(
            'writer-create',
            $this->context,
            [],
            [
                RepositoryWriterFacadeHookFactory::class,
            ]
        );

        static::getContainer()->get(ScriptExecutor::class)->execute($hook);

        $taxRepository = static::getContainer()->get('tax.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'new tax'));

        $createdTax = $taxRepository->search($criteria, $this->context)->getEntities()->first();
        static::assertInstanceOf(TaxEntity::class, $createdTax);
    }

    public function testIntegrationUpdateCase(): void
    {
        $this->ids = new IdsCollection();
        $this->createBlogs();

        $this->installApp(__DIR__ . '/_fixtures/apps/pageLoadedExample');

        $hook = new TestHook(
            'writer-update',
            $this->context,
            [
                'blogId' => $this->ids->get('p2'),
            ],
            [
                RepositoryWriterFacadeHookFactory::class,
            ]
        );

        static::getContainer()->get(ScriptExecutor::class)->execute($hook);

        $updated = $this->blogRepository->search(new Criteria([$this->ids->get('p2')]), $this->context)->getEntities()->first();
        static::assertInstanceOf(BlogEntity::class, $updated);
        static::assertTrue($updated->getActive());
    }

    public function testIntegrationDeleteCase(): void
    {
        $this->ids = new IdsCollection();
        $this->createBlogs();

        $this->installApp(__DIR__ . '/_fixtures/apps/pageLoadedExample');

        $hook = new TestHook(
            'writer-delete',
            $this->context,
            [
                'blogId' => $this->ids->get('p3'),
            ],
            [
                RepositoryWriterFacadeHookFactory::class,
            ]
        );

        static::getContainer()->get(ScriptExecutor::class)->execute($hook);

        $deleted = $this->blogRepository->search(new Criteria([$this->ids->get('p3')]), $this->context)->getEntities()->first();
        static::assertNull($deleted);
    }

    public function testIntegrationSyncCase(): void
    {
        $this->ids = new IdsCollection();
        $this->createBlogs();

        $this->installApp(__DIR__ . '/_fixtures/apps/pageLoadedExample');

        $hook = new TestHook(
            'writer-sync',
            $this->context,
            [
                'updateBlogId' => $this->ids->get('p2'),
                'deleteBlogId' => $this->ids->get('p3'),
            ],
            [
                RepositoryWriterFacadeHookFactory::class,
            ]
        );

        static::getContainer()->get(ScriptExecutor::class)->execute($hook);

        $updated = $this->blogRepository->search(new Criteria([$this->ids->get('p2')]), $this->context)->getEntities()->first();
        static::assertInstanceOf(BlogEntity::class, $updated);
        static::assertTrue($updated->getActive());

        $deleted = $this->blogRepository->search(new Criteria([$this->ids->get('p3')]), $this->context)->getEntities()->first();
        static::assertNull($deleted);
    }

    private function createBlogs(): void
    {
        $taxId = $this->getExistingTaxId();
        $this->ids->set('t1', $taxId);

        $blog1 = (new BlogBuilder($this->ids, 'p1'))
            ->price(100)
            ->visibility()
            ->manufacturer('m1')
            ->variant(
                (new BlogBuilder($this->ids, 'v1.1'))
                    ->build()
            );

        $blog2 = (new BlogBuilder($this->ids, 'p2'))
            ->price(200)
            ->visibility()
            ->active(false);

        $blog3 = (new BlogBuilder($this->ids, 'p3'))
            ->visibility()
            ->price(300);

        static::getContainer()->get('blog.repository')->create([
            $blog1->build(),
            $blog2->build(),
            $blog3->build(),
        ], $this->context);
    }

    private function installApp(string $appDir): ScriptAppInformation
    {
        $this->loadAppsFromDir($appDir);

        /** @var EntityRepository<AppCollection> $repository */
        $repository = static::getContainer()->get('app.repository');
        $app = $repository
            ->search(new Criteria(), Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertNotNull($app);

        return new ScriptAppInformation(
            $app->getId(),
            $app->getName(),
            $app->getVersion(),
            $app->getIntegrationId()
        );
    }

    private function getExistingTaxId(): string
    {
        /** @var EntityRepository<TaxCollection> $taxRepository */
        $taxRepository = static::getContainer()->get('tax.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'Standard rate'));

        $taxId = $taxRepository->searchIds($criteria, $this->context)->firstId();

        static::assertNotNull($taxId);

        return $taxId;
    }
}
