<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Event\EventData;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Event\EventData\EntityType;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\DateDefinition;

/**
 * @internal
 */
#[CoversClass(EntityType::class)]
class EntityTypeTest extends TestCase
{
    public function testToArray(): void
    {
        $definition = DateDefinition::class;

        $expected = [
            'type' => 'entity',
            'entityClass' => DateDefinition::class,
            'entityName' => DateDefinition::ENTITY_NAME,
        ];

        static::assertSame($expected, new EntityType($definition)->toArray());
        static::assertSame($expected, new EntityType(new DateDefinition())->toArray());
    }
}
