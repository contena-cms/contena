<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\Aggregate\FlowAction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\App\Aggregate\FlowAction\AppFlowActionCollection;
use Contena\Core\Framework\App\Aggregate\FlowAction\AppFlowActionDefinition;
use Contena\Core\Framework\App\Aggregate\FlowAction\AppFlowActionEntity;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(AppFlowActionDefinition::class)]
class AppFlowActionDefinitionTest extends TestCase
{
    public function testEntityConfiguration(): void
    {
        $definition = $this->createDefinition();

        static::assertSame(AppFlowActionDefinition::ENTITY_NAME, $definition->getEntityName());
        static::assertSame(AppFlowActionEntity::class, $definition->getEntityClass());
        static::assertSame(AppFlowActionCollection::class, $definition->getCollectionClass());
        static::assertSame('6.4.10.0', $definition->since());
    }

    public function testFieldsAreDefined(): void
    {
        static::assertNotNull($this->createDefinition()->getFields()->get('id'));
    }

    private function createDefinition(): AppFlowActionDefinition
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [AppFlowActionDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGateway::class),
        );

        $definition = $registry->getByEntityName(AppFlowActionDefinition::ENTITY_NAME);
        static::assertInstanceOf(AppFlowActionDefinition::class, $definition);

        return $definition;
    }
}
