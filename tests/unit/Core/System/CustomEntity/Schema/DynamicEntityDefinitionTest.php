<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\CustomEntity\Schema;

use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Contena\Core\System\CustomEntity\Schema\DynamicEntityDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[CoversClass(DynamicEntityDefinition::class)]
class DynamicEntityDefinitionTest extends TestCase
{
    public function testCreateExposesNameAndFlags(): void
    {
        $flags = [new CascadeDelete()];

        $definition = DynamicEntityDefinition::create('custom_entity_blog', [], $flags, new ContainerBuilder());

        static::assertSame('custom_entity_blog', $definition->getEntityName());
        static::assertSame($flags, $definition->getFlags());
    }

    public function testGetDefaultsCollectsOnlyFieldsWithADefault(): void
    {
        $definition = DynamicEntityDefinition::create('custom_entity_blog', [
            ['name' => 'position', 'type' => 'int', 'reference' => '', 'onDelete' => '', 'default' => 1],
            ['name' => 'rating', 'type' => 'float', 'reference' => '', 'onDelete' => ''],
            ['name' => 'payload', 'type' => 'json', 'reference' => '', 'onDelete' => '', 'default' => ['top' => true]],
        ], [], new ContainerBuilder());

        static::assertSame(
            ['position' => 1, 'payload' => ['top' => true]],
            $definition->getDefaults()
        );
    }
}
