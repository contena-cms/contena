<?php

declare(strict_types=1);

namespace Contena\Commercial\Tests\Unit\Foo;

use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Order\OrderEntity;
use Contena\Core\System\Channel\ChannelContext;

class BarTest extends TestCase
{
    public function testFoo(): void
    {
        // not allowed
        $this->createMock(OrderEntity::class);

        // allowed
        $this->createMock(ChannelContext::class);
    }
}
