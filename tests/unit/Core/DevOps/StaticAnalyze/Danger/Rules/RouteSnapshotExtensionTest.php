<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use Danger\Struct\File;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\DevOps\StaticAnalyze\Danger\Rules\RouteSnapshotExtension;
use Contena\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubFile;
use Contena\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubPlatform;
use Contena\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubPullRequest;

/**
 * @internal
 */
#[CoversClass(RouteSnapshotExtension::class)]
class RouteSnapshotExtensionTest extends TestCase
{
    private const string SNAPSHOT_PATH = 'tests/integration/Core/Framework/_snapshots/routes_without_schema/snapshot.json';

    #[TestDox('Fails when the routes-without-schema snapshot gains lines')]
    #[DataProvider('snapshotDiffProvider')]
    public function testSnapshotGrowth(int $additions, int $deletions, bool $expectFailure): void
    {
        $context = new Context(new StubPlatform(new StubPullRequest([
            new StubFile(self::SNAPSHOT_PATH, File::STATUS_MODIFIED, '', '', $additions, $deletions),
        ])));

        (new RouteSnapshotExtension())($context);

        static::assertSame($expectFailure, $context->hasFailures());
        if ($expectFailure) {
            static::assertStringContainsString('open API schema', $context->getFailures()[0]);
        }
    }

    public static function snapshotDiffProvider(): \Generator
    {
        yield 'added snapshot entries fail' => [3, 0, true];
        yield 'even one added line fails, regardless of removals' => [1, 5, true];
        yield 'pure removal passes' => [0, 4, false];
    }

    #[TestDox('Stays silent when the snapshot is untouched')]
    public function testSilentWithoutSnapshotChange(): void
    {
        $context = new Context(new StubPlatform(new StubPullRequest([new StubFile('src/Core/Framework/Framework.php')])));

        (new RouteSnapshotExtension())($context);

        static::assertFalse($context->hasReports());
    }
}
