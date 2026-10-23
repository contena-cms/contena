<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Event\EventData;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Event\EventData\EntityCollectionType;
use Contena\Core\System\User\UserDefinition;

/**
 * @internal
 */
#[CoversClass(EntityCollectionType::class)]
class EntityCollectionTypeTest extends TestCase
{
    public function testToArray(): void
    {
        $expected = [
            'type' => 'collection',
            'entityClass' => UserDefinition::class,
        ];

        static::assertSame($expected, (new EntityCollectionType(UserDefinition::class))->toArray());
    }
}
