<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Telemetry\Metrics\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Telemetry\Metrics\Config\LabelPolicy;

/**
 * @internal
 */
#[CoversClass(LabelPolicy::class)]
class LabelPolicyTest extends TestCase
{
    public function testValuesReturnsAllCaseValues(): void
    {
        $values = LabelPolicy::values();

        static::assertSame(['replace', 'discard', 'open'], $values);
    }
}
