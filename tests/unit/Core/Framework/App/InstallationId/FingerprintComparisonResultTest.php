<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\InstallationId;

use Contena\Core\Framework\App\InstallationId\FingerprintComparisonResult;
use Contena\Core\Framework\App\InstallationId\FingerprintMismatch;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(FingerprintComparisonResult::class)]
class FingerprintComparisonResultTest extends TestCase
{
    public function testSumsUpScoreOfMisMatchingFingerprints(): void
    {
        $result = new FingerprintComparisonResult(
            [],
            [
                'foo' => new FingerprintMismatch('foo', null, 'FOO', 10),
                'bar' => new FingerprintMismatch('bar', null, 'BAR', 25),
                'baz' => new FingerprintMismatch('baz', null, 'BAZ', 50),
            ],
            75,
        );

        static::assertSame(85, $result->score);
    }

    public function testProvidesFingerprintMismatchForGivenIdentifier(): void
    {
        $result = new FingerprintComparisonResult(
            [],
            [
                'foo' => $mismatchingFingerprint = new FingerprintMismatch('foo', null, 'FOO', 10),
            ],
            75,
        );

        static::assertSame($mismatchingFingerprint, $result->getMismatchingFingerprint('foo'));
        static::assertNull($result->getMismatchingFingerprint('bar'));
        static::assertNull($result->getMismatchingFingerprint('baz'));
    }
}
