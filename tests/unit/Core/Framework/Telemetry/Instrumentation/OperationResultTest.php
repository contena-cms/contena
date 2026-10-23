<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Telemetry\Instrumentation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Telemetry\Instrumentation\OperationResult;

/**
 * @internal
 */
#[CoversClass(OperationResult::class)]
class OperationResultTest extends TestCase
{
    public function testOutcomeWithoutExceptionIsSuccess(): void
    {
        static::assertSame(OperationResult::SUCCESS, OperationResult::fromOutcome(null));
    }

    public function testOutcomeWithExceptionIsFailed(): void
    {
        static::assertSame(OperationResult::FAILED, OperationResult::fromOutcome(new \RuntimeException('instrumented operation failed')));
    }
}
