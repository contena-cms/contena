<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Event\EventData;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Event\EventData\EntityType;
use Contena\Core\Framework\Event\EventData\EventDataCollection;
use Contena\Core\Framework\Event\EventData\ScalarValueType;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\DateDefinition;

/**
 * @internal
 */
#[CoversClass(EventDataCollection::class)]
class EventDataCollectionTest extends TestCase
{
    public function testToArray(): void
    {
        $collection = new EventDataCollection()
            ->add('date', new EntityType(DateDefinition::class))
            ->add('myBool', new ScalarValueType(ScalarValueType::TYPE_BOOL))
        ;

        $expected = [
            'date' => [
                'type' => 'entity',
                'entityClass' => DateDefinition::class,
                'entityName' => '_date_field_test',
            ],
            'myBool' => [
                'type' => 'bool',
            ],
        ];

        static::assertSame($expected, $collection->toArray());
    }
}
