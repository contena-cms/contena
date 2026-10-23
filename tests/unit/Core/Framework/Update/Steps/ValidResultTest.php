<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Update\Steps;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Update\Steps\ValidResult;

/**
 * @internal
 */
#[CoversClass(ValidResult::class)]
class ValidResultTest extends TestCase
{
    public function testConstructor(): void
    {
        $result = new ValidResult(1, 2);

        static::assertSame(1, $result->getOffset());
        static::assertSame(2, $result->getTotal());
    }
}
