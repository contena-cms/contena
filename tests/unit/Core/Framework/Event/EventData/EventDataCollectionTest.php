<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Event\EventData;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Customer\CustomerDefinition;
use Contena\Core\Framework\Event\EventData\EntityType;
use Contena\Core\Framework\Event\EventData\EventDataCollection;
use Contena\Core\Framework\Event\EventData\ScalarValueType;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(EventDataCollection::class)]
class EventDataCollectionTest extends TestCase
{
    public function testToArray(): void
    {
        $collection = (new EventDataCollection())
            ->add('customer', new EntityType(CustomerDefinition::class))
            ->add('myBool', new ScalarValueType(ScalarValueType::TYPE_BOOL))
        ;

        $expected = [
            'customer' => [
                'type' => 'entity',
                'entityClass' => CustomerDefinition::class,
                'entityName' => 'customer',
            ],
            'myBool' => [
                'type' => 'bool',
            ],
        ];

        static::assertSame($expected, $collection->toArray());
    }

    public function testOptionsAreMergedIntoTheDeclaredType(): void
    {
        $collection = (new EventDataCollection())
            ->add('contextToken', new ScalarValueType(ScalarValueType::TYPE_STRING), [EventDataCollection::HIDDEN_FROM_WEBHOOK => true])
            ->add('customer', new EntityType(CustomerDefinition::class), [EventDataCollection::HIDDEN_FROM_WEBHOOK => true]);

        static::assertSame([
            'contextToken' => [
                'type' => 'string',
                'hiddenFromWebhook' => true,
            ],
            'customer' => [
                'type' => 'entity',
                'entityClass' => CustomerDefinition::class,
                'entityName' => 'customer',
                'hiddenFromWebhook' => true,
            ],
        ], $collection->toArray());
    }
}
