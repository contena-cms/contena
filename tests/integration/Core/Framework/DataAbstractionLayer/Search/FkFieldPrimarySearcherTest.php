<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\DataAbstractionLayer\Search;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogTranslation\BlogTranslationCollection;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEventFactory;
use Contena\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Contena\Core\Framework\DataAbstractionLayer\VersionManager;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\ArrayEntity;
use Contena\Core\Framework\Test\DataAbstractionLayer\Search\Definition\FkFieldPrimaryTestDefinition;
use Contena\Core\Framework\Test\DataAbstractionLayer\Search\Definition\MultiFkFieldPrimaryTestDefinition;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class FkFieldPrimarySearcherTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<BlogCollection>
     */
    private EntityRepository $blogRepository;

    private string $blogId;

    public static function tearDownAfterClass(): void
    {
        KernelLifecycleManager::getKernel()->getContainer()->get(Connection::class)->executeStatement('DROP TABLE IF EXISTS multi_fk_field_primary');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $connection = static::getContainer()->get(Connection::class);
        $connection->rollBack();
        $connection->executeStatement('DROP TABLE IF EXISTS `fk_field_primary`');
        $connection->beginTransaction();
    }

    public function testSearchByPrimaryFkKey(): void
    {
        $this->addPrimaryFkField();

        $definition = new FkFieldPrimaryTestDefinition();
        $this->blogRepository = static::getContainer()->get('blog.repository');
        $this->blogId = Uuid::randomHex();

        $this->blogRepository->create(
            [
                [
                    'id' => $this->blogId,
                    'blogNumber' => Uuid::randomHex(),
                    'stock' => 1,
                    'name' => 'Test',
                    'tax' => ['name' => 'test', 'taxRate' => 5],
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 5, 'linked' => false]],
                ],
            ],
            Context::createDefaultContext()
        );

        /** @var EntityRepository<EntityCollection<Entity>> $fkFieldPrimaryRepository */
        $fkFieldPrimaryRepository = static::getContainer()->get($definition->getEntityName() . '.repository');

        $fkFieldPrimaryRepository->create(
            [
                [
                    'blogId' => $this->blogId,
                    'name' => 'TestPrimary',
                ],
            ],
            Context::createDefaultContext()
        );

        $criteria = new Criteria([$this->blogId]);
        /** @var EntityRepository<EntityCollection<Entity>> $fkFieldPrimaryRepository */
        $fkFieldPrimaryRepository = static::getContainer()->get('fk_field_primary.repository');
        /** @var array<string, ArrayEntity> $fkFieldPrimaryTupel */
        $fkFieldPrimaryTupel = $fkFieldPrimaryRepository->search($criteria, Context::createDefaultContext())->getEntities()->getElements();
        static::assertArrayHasKey($this->blogId, $fkFieldPrimaryTupel);
        static::assertTrue($fkFieldPrimaryTupel[$this->blogId]->has('name'));
        static::assertSame('TestPrimary', $fkFieldPrimaryTupel[$this->blogId]->get('name'));
    }

    public function testSearchByMultiPrimaryFkKey(): void
    {
        $this->addMultiPrimaryFkField();

        /** @var EntityRepository<EntityCollection<Entity>> $multiPrimaryRepository */
        $multiPrimaryRepository = static::getContainer()->get('multi_fk_field_primary.repository');
        $firstId = Uuid::randomHex();
        $secondId = Uuid::randomHex();

        $multiPrimaryRepository->create(
            [
                [
                    'firstId' => $firstId,
                    'secondId' => $secondId,
                ],
            ],
            Context::createDefaultContext()
        );

        $criteria = new Criteria([['firstId' => $firstId, 'secondId' => $secondId]]);
        $multiFkFieldPrimaryTupel = $multiPrimaryRepository->search($criteria, Context::createDefaultContext());
        $key = $firstId . '-' . $secondId;
        static::assertArrayHasKey($key, $multiFkFieldPrimaryTupel->getEntities()->getElements());
        static::assertSame($firstId, $multiFkFieldPrimaryTupel->getEntities()->getElements()[$key]->get('firstId'));
    }

    public function testSearchForTranslation(): void
    {
        $this->blogRepository = static::getContainer()->get('blog.repository');
        $this->blogId = Uuid::randomHex();

        $this->blogRepository->create(
            [
                [
                    'id' => $this->blogId,
                    'blogNumber' => Uuid::randomHex(),
                    'stock' => 1,
                    'name' => 'Test',
                    'tax' => ['name' => 'test', 'taxRate' => 5],
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 5, 'linked' => false]],
                ],
            ],
            Context::createDefaultContext()
        );

        $criteria = new Criteria([['blogId' => $this->blogId, 'languageId' => Defaults::LANGUAGE_SYSTEM]]);

        /** @var EntityRepository<BlogTranslationCollection> */
        $blogTranslationRepository = static::getContainer()->get('blog_translation.repository');
        $blogTranslation = $blogTranslationRepository->search($criteria, Context::createDefaultContext())->getEntities();

        $key = $this->blogId . '-' . Defaults::LANGUAGE_SYSTEM;
        static::assertArrayHasKey($key, $blogTranslation->getElements());
        static::assertSame('Test', $blogTranslation->getElements()[$key]->getName());
    }

    private function addPrimaryFkField(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $connection->rollBack();
        $connection->executeStatement('DROP TABLE IF EXISTS `fk_field_primary`');
        $connection->executeStatement('
            CREATE TABLE `fk_field_primary` (
              `blog_id` BINARY(16) NOT NULL PRIMARY KEY,
              `name` varchar(255) DEFAULT NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL
        )');

        $definition = new FkFieldPrimaryTestDefinition();

        if (!static::getContainer()->has($definition->getEntityName() . '.repository')) {
            $repository = new EntityRepository(
                $definition,
                static::getContainer()->get(EntityReaderInterface::class),
                static::getContainer()->get(VersionManager::class),
                static::getContainer()->get(EntitySearcherInterface::class),
                static::getContainer()->get(EntityAggregatorInterface::class),
                static::getContainer()->get('event_dispatcher'),
                static::getContainer()->get(EntityLoadedEventFactory::class)
            );

            static::getContainer()->set($definition->getEntityName() . '.repository', $repository);
            static::getContainer()->get(DefinitionInstanceRegistry::class)->register($definition);
            $definition->compile(static::getContainer()->get(DefinitionInstanceRegistry::class));
        }

        $connection->beginTransaction();
    }

    private function addMultiPrimaryFkField(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $connection->rollBack();
        $connection->executeStatement('DROP TABLE IF EXISTS `multi_fk_field_primary`');
        $connection->executeStatement(
            '
                CREATE TABLE `multi_fk_field_primary` (
                  `first_id` BINARY(16) NOT NULL,
                  `second_id` BINARY(16) NOT NULL,
                  `created_at` DATETIME(3) NOT NULL,
                  `updated_at` DATETIME(3) NULL,
                  PRIMARY KEY (`first_id`, `second_id`)
            )'
        );

        $definition = new MultiFkFieldPrimaryTestDefinition();

        if (!static::getContainer()->has($definition->getEntityName() . '.repository')) {
            $repository = new EntityRepository(
                $definition,
                static::getContainer()->get(EntityReaderInterface::class),
                static::getContainer()->get(VersionManager::class),
                static::getContainer()->get(EntitySearcherInterface::class),
                static::getContainer()->get(EntityAggregatorInterface::class),
                static::getContainer()->get('event_dispatcher'),
                static::getContainer()->get(EntityLoadedEventFactory::class)
            );

            static::getContainer()->set($definition->getEntityName() . '.repository', $repository);
            static::getContainer()->get(DefinitionInstanceRegistry::class)->register($definition);
            $definition->compile(static::getContainer()->get(DefinitionInstanceRegistry::class));
        }

        $connection->beginTransaction();
    }
}
