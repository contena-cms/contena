<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Framework\Health\Util;

use Contena\Frontend\Framework\SystemCheck\Util\FrontendHealthCheckResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(FrontendHealthCheckResult::class)]
class FrontendHealthCheckResultTest extends TestCase
{
    public function testCreate(): void
    {
        $result = FrontendHealthCheckResult::create('http://localhost:8000', 200, 0.123);

        static::assertSame('http://localhost:8000', $result->frontendUrl);
        static::assertSame(200, $result->responseCode);
        static::assertSame(0.123, $result->responseTime);
    }
}
