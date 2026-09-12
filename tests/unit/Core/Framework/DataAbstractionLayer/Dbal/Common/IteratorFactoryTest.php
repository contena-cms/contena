<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Dbal\Common;

use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\DataScopeField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\Uuid\Uuid;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(IteratorFactory::class)]
class IteratorFactoryTest extends TestCase
{
    public function testCreateIteratorAddsVersionFilterWhenVersionAwareAndProvided(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $registry = static::createStub(DefinitionInstanceRegistry::class);

        $definition = new class extends EntityDefinition {
            public function getEntityName(): string
            {
                return 'order';
            }

            protected function defineFields(): FieldCollection
            {
                return new FieldCollection([]);
            }

            public function isVersionAware(): bool
            {
                return true;
            }
        };

        $definition->compile($registry);

        $factory = new IteratorFactory($connection, $registry);

        $iterator = $factory->createIterator($definition, Context::createDefaultContext(), null, 50, Defaults::LIVE_VERSION);

        $params = $iterator->getQuery()->getParameters();
        static::assertArrayHasKey('versionId', $params);
        static::assertSame(50, $iterator->getQuery()->getMaxResults());
    }

    #[DataProvider('provideDataScopeContexts')]
    public function testCreateIteratorAlwaysRestrictsScopedDefinitionsToTheWriteScope(Context $context): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $registry = static::createStub(DefinitionInstanceRegistry::class);

        $definition = new class extends EntityDefinition {
            public function getEntityName(): string
            {
                return 'scoped_entity';
            }

            protected function defineFields(): FieldCollection
            {
                return new FieldCollection([new DataScopeField()]);
            }
        };
        $definition->compile($registry);

        $iterator = new IteratorFactory($connection, $registry)
            ->createIterator($definition, $context);

        static::assertStringContainsString('`scoped_entity`.`data_scope_id` = :dataScopeId', $iterator->getQuery()->getSQL());
        static::assertSame(
            Uuid::fromHexToBytes($context->getDataScopeId()),
            $iterator->getQuery()->getParameter('dataScopeId'),
        );
    }

    /**
     * @return iterable<string, array{Context}>
     */
    public static function provideDataScopeContexts(): iterable
    {
        yield 'platform context' => [Context::createDefaultContext()];
        yield 'target tenant context' => [Context::createTenantContext('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa')];
        yield 'another tenant context' => [Context::createTenantContext('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb')];
        yield 'global read context remains platform write scoped' => [Context::createGlobalContext()];
    }
}
