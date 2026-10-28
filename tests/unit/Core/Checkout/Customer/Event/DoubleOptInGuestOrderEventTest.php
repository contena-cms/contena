<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Checkout\Customer\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Customer\CustomerEntity;
use Contena\Core\Checkout\Customer\Event\DoubleOptInGuestOrderEvent;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Content\Flow\Dispatching\Storer\ScalarValuesStorer;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(DoubleOptInGuestOrderEvent::class)]
class DoubleOptInGuestOrderEventTest extends TestCase
{
    public function testScalarValuesCorrectly(): void
    {
        $event = new DoubleOptInGuestOrderEvent(
            new CustomerEntity(),
            static::createStub(SalesChannelContext::class),
            'my-confirm-url'
        );

        $storer = new ScalarValuesStorer();

        $stored = $storer->store($event, []);

        $flow = new StorableFlow('foo', Context::createDefaultContext(), $stored);

        $storer->restore($flow);

        static::assertArrayHasKey('confirmUrl', $flow->data());
        static::assertSame('my-confirm-url', $flow->data()['confirmUrl']);
    }
}
