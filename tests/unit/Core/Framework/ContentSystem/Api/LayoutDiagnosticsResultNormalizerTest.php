<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Api;

use Contena\Core\Framework\ContentSystem\Api\LayoutDiagnosticsResultNormalizer;
use Contena\Core\Framework\ContentSystem\Resolution\CandidateOrigin;
use Contena\Core\Framework\ContentSystem\Resolution\PropertyKind;
use Contena\Core\Framework\ContentSystem\Resolution\PropertyResolution;
use Contena\Core\Framework\ContentSystem\Resolution\ResolutionCandidate;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(LayoutDiagnosticsResultNormalizer::class)]
class LayoutDiagnosticsResultNormalizerTest extends TestCase
{
    #[DataProvider('normalizesCandidateConfigCompletePerOriginProvider')]
    #[TestDox('normalizes a candidate configComplete per its origin, regardless of the constructed value')]
    public function testNormalizesCandidateConfigCompletePerOrigin(CandidateOrigin $origin, bool $constructed, ?bool $expected): void
    {
        $candidate = new ResolutionCandidate(origin: $origin, contextKey: 'blog', configComplete: $constructed);
        $resolution = new PropertyResolution('blog', PropertyKind::Reference, false, null, null, 'Some\\Fqcn', $candidate);

        $normalized = new LayoutDiagnosticsResultNormalizer()->normalizeResolutions(['el-1' => [$resolution]]);

        static::assertSame($expected, $normalized['el-1'][0]['resolved']['configComplete']);
    }

    #[TestDox('serializes a root-ambient candidate as origin root, carrying its context key and a null provider element id')]
    public function testSerializesRootOriginCandidate(): void
    {
        $candidate = new ResolutionCandidate(origin: CandidateOrigin::Root, contextKey: 'blog');
        $resolution = new PropertyResolution('blog', PropertyKind::Reference, true, null, null, 'Some\\Fqcn', $candidate, [$candidate]);

        $normalized = new LayoutDiagnosticsResultNormalizer()->normalizeResolutions(['el-1' => [$resolution]]);

        static::assertSame('root', $normalized['el-1'][0]['resolved']['origin']);
        static::assertSame('blog', $normalized['el-1'][0]['resolved']['contextKey']);
        // The wire's null provider address is what tells a client the value comes from the layout's bound root
        // source rather than from an element it could point at.
        static::assertNull($normalized['el-1'][0]['resolved']['providerElementId']);
        static::assertSame('root', $normalized['el-1'][0]['candidates'][0]['origin']);
    }

    /**
     * @return iterable<string, array{CandidateOrigin, bool, ?bool}>
     */
    public static function normalizesCandidateConfigCompletePerOriginProvider(): iterable
    {
        // Parent and Root are pinned false per the wire contract, so neither can leak a true into the response
        // schema when constructed with configComplete=true.
        yield 'parent pins false even when constructed true' => [CandidateOrigin::Parent, true, false];
        yield 'parent stays false when constructed false' => [CandidateOrigin::Parent, false, false];
        yield 'root pins false even when constructed true' => [CandidateOrigin::Root, true, false];
        yield 'root stays false when constructed false' => [CandidateOrigin::Root, false, false];
        yield 'loader passes through true' => [CandidateOrigin::Loader, true, true];
        yield 'loader passes through false' => [CandidateOrigin::Loader, false, false];
        yield 'stored is null even when constructed true' => [CandidateOrigin::Stored, true, null];
        yield 'stored is null when constructed false' => [CandidateOrigin::Stored, false, null];
    }
}
