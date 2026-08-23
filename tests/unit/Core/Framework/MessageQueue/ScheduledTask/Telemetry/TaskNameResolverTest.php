<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\MessageQueue\ScheduledTask\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\MessageQueue\ScheduledTask\Telemetry\TaskNameResolver;

/**
 * @internal
 */
#[CoversClass(TaskNameResolver::class)]
class TaskNameResolverTest extends TestCase
{
    #[DataProvider('taskNameProvider')]
    public function testResolve(string $taskName, string $expected): void
    {
        static::assertSame($expected, new TaskNameResolver()->resolve($taskName));
    }

    public static function taskNameProvider(): \Generator
    {
        // core task names pass through unchanged (closed allowlist)
        yield 'version.cleanup passes through' => ['version.cleanup', 'version.cleanup'];
        yield 'contena.invalidate_cache passes through' => ['contena.invalidate_cache', 'contena.invalidate_cache'];
        yield 'theme.delete_files passes through' => ['theme.delete_files', 'theme.delete_files'];
        yield 'telemetry.collect_periodic_metrics passes through' => ['telemetry.collect_periodic_metrics', 'telemetry.collect_periodic_metrics'];
        yield 'contena.elasticsearch.create.alias passes through' => ['contena.elasticsearch.create.alias', 'contena.elasticsearch.create.alias'];
        yield 'mcp_toolset_session.cleanup passes through' => ['mcp_toolset_session.cleanup', 'mcp_toolset_session.cleanup'];
        yield 'translation.update passes through' => ['translation.update', 'translation.update'];

        // unknown / plugin names collapse to other, bounding label cardinality
        yield 'plugin custom task is other' => ['my_plugin.custom_task', 'other'];
        yield 'empty string is other' => ['', 'other'];
    }
}
