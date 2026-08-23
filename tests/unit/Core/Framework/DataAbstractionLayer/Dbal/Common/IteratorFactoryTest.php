<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Dbal\Common;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @internal
 */
#[CoversClass(IteratorFactory::class)]
class IteratorFactoryTest extends TestCase
{
    public function testCreateIteratorAddsVersionFilterWhenVersionAwareAndProvided(): void
    {
        $connection = static::createStub(Connection::class);
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

        $iterator = $factory->createIterator($definition, null, 50, Defaults::LIVE_VERSION);

        $params = $iterator->getQuery()->getParameters();
        static::assertArrayHasKey('versionId', $params);
        static::assertSame(50, $iterator->getQuery()->getMaxResults());
    }
}
