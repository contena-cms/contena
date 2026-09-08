<?php declare(strict_types=1);

namespace Contena\Core\Test\Assert;

use Contena\Core\Test\Constraint\StrictIsEmpty;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Constraint\LogicalNot;

/**
 * @internal
 */
final class StrictEmpty
{
    public static function assertEmpty(mixed $actual, string $message = ''): void
    {
        Assert::assertThat($actual, new StrictIsEmpty(), $message);
    }

    public static function assertNotEmpty(mixed $actual, string $message = ''): void
    {
        Assert::assertThat($actual, new LogicalNot(new StrictIsEmpty()), $message);
    }
}
