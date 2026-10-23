<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\Aggregate\AppScriptCondition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\App\Aggregate\AppScriptCondition\AppScriptConditionCollection;
use Contena\Core\Framework\App\Aggregate\AppScriptCondition\AppScriptConditionDefinition;
use Contena\Core\Framework\App\Aggregate\AppScriptCondition\AppScriptConditionEntity;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(AppScriptConditionDefinition::class)]
class AppScriptConditionDefinitionTest extends TestCase
{
    public function testEntityConfiguration(): void
    {
        $definition = $this->createDefinition();

        static::assertSame(AppScriptConditionDefinition::ENTITY_NAME, $definition->getEntityName());
        static::assertSame(AppScriptConditionEntity::class, $definition->getEntityClass());
        static::assertSame(AppScriptConditionCollection::class, $definition->getCollectionClass());
        static::assertSame('6.4.10.3', $definition->since());
    }

    public function testFieldsAreDefined(): void
    {
        static::assertNotNull($this->createDefinition()->getFields()->get('id'));
    }

    private function createDefinition(): AppScriptConditionDefinition
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [AppScriptConditionDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGateway::class),
        );

        $definition = $registry->getByEntityName(AppScriptConditionDefinition::ENTITY_NAME);
        static::assertInstanceOf(AppScriptConditionDefinition::class, $definition);

        return $definition;
    }
}
