<?php

declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @internal
 */
#[CoversClass(EntityDefinition::class)]
class EntityDefinitionTest extends TestCase
{
    public function testGetFieldsOverridesDefaultFields(): void
    {
        $definition = new class extends EntityDefinition {
            public function getEntityName(): string
            {
                return 'test-definition';
            }

            protected function defineFields(): FieldCollection
            {
                return new FieldCollection([
                    // New UpdatedAtField overwrites the default field
                    new UpdatedAtField()->setDescription('This is a test'),
                ]);
            }
        };
        $definition->compile(static::createStub(DefinitionInstanceRegistry::class));

        $updatedAtField = $definition->getFields()->get('updatedAt');
        static::assertInstanceOf(UpdatedAtField::class, $updatedAtField);
        static::assertSame('This is a test', $updatedAtField->getDescription());
    }
}
