<?php

declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Exception;

use Contena\Core\Framework\DataAbstractionLayer\Exception\PropertyNotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(PropertyNotFoundException::class)]
class PropertyNotFoundExceptionTest extends TestCase
{
    public function testExceptionMessage(): void
    {
        $exception = new PropertyNotFoundException('property', 'entityClassName');

        static::assertSame('Property "property" does not exist in entity "entityClassName".', $exception->getMessage());
    }
}
