<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Product\Events;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Product\DataAbstractionLayer\UpdatedStates;
use Contena\Core\Content\Product\Events\ProductStatesChangedEvent;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductStatesChangedEvent::class)]
class ProductStatesChangedEventTest extends TestCase
{
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testExposesTheUpdatedStatesAndContext(): void
    {
        $context = Context::createDefaultContext();
        $states = [new UpdatedStates('product-id', ['physical'], ['digital'])];

        $event = new ProductStatesChangedEvent($states, $context);

        static::assertSame($states, $event->getUpdatedStates());
        static::assertSame($context, $event->getContext());
    }
}
