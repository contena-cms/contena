<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Event\EventData;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Event\EventData\EntityType;
use Contena\Core\System\User\UserDefinition;

/**
 * @internal
 */
#[CoversClass(EntityType::class)]
class EntityTypeTest extends TestCase
{
    public function testToArray(): void
    {
        $definition = UserDefinition::class;

        $expected = [
            'type' => 'entity',
            'entityClass' => UserDefinition::class,
            'entityName' => 'user',
        ];

        static::assertSame($expected, (new EntityType($definition))->toArray());
        static::assertSame($expected, (new EntityType(new UserDefinition()))->toArray());
    }
}
