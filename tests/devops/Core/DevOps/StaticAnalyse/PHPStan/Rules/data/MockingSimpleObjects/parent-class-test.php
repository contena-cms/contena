<?php

declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Foo;

use Contena\Core\Checkout\Order\OrderEntity;
use Contena\Tests\Unit\Administration\AdministrationTest;

class BarTest extends AdministrationTest
{
    public function testFoo(): void
    {
        $this->createMock(OrderEntity::class);
    }
}
