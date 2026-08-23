<?php declare(strict_types=1);

namespace Contena\Core\Test\Integration\Traits;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Before;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 */
trait EntityFixturesBase
{
    private Context $entityFixtureContext;

    #[Before]
    public function initializeFixtureContext(): void
    {
        $this->entityFixtureContext = Context::createDefaultContext();
    }

    public function setFixtureContext(Context $context): void
    {
        $this->entityFixtureContext = $context;
    }

    /**
     * @return EntityRepository<covariant EntityCollection<covariant Entity>>
     */
    // PHPStan loses trait method PHPDoc while analysing consuming test classes.
    // @phpstan-ignore missingType.generics
    public static function getFixtureRepository(string $fixtureName): EntityRepository
    {
        $container = KernelLifecycleManager::getKernel()->getContainer();

        if ($container->has('test.service_container')) {
            $testContainer = $container->get('test.service_container');
            static::assertInstanceOf(ContainerInterface::class, $testContainer);
            $container = $testContainer;
        }

        $registry = $container->get(DefinitionInstanceRegistry::class);
        static::assertInstanceOf(DefinitionInstanceRegistry::class, $registry);

        return $registry->getRepository($fixtureName);
    }

    /**
     * @param array<string, array<string, mixed>> $fixtureData
     * @param EntityRepository<covariant EntityCollection<covariant Entity>> $repository
     */
    // PHPStan loses trait method PHPDoc while analysing consuming test classes.
    // @phpstan-ignore missingType.iterableValue, missingType.generics
    public function createFixture(string $fixtureName, array $fixtureData, EntityRepository $repository): Entity
    {
        self::ensureATransactionIsActive();

        $repository->create([$fixtureData[$fixtureName]], $this->entityFixtureContext);

        if (\array_key_exists('mediaType', $fixtureData[$fixtureName])) {
            $connection = KernelLifecycleManager::getKernel()
                ->getContainer()
                ->get(Connection::class);
            $connection->update(
                'media',
                [
                    'media_type' => serialize($fixtureData[$fixtureName]['mediaType']),
                ],
                ['id' => Uuid::fromHexToBytes($fixtureData[$fixtureName]['id'])]
            );
        }

        $criteria = new Criteria([$fixtureData[$fixtureName]['id']]);

        $entity = $repository
            ->search($criteria, $this->entityFixtureContext)
            ->getEntities()
            ->get($fixtureData[$fixtureName]['id']);

        static::assertInstanceOf(Entity::class, $entity);

        return $entity;
    }

    private static function ensureATransactionIsActive(): void
    {
        $connection = KernelLifecycleManager::getKernel()
            ->getContainer()
            ->get(Connection::class);

        if (!$connection->isTransactionActive()) {
            throw new \BadMethodCallException('You should not start writing to the database outside of transactions');
        }
    }
}
