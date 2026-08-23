<?php declare(strict_types=1);

namespace Contena\Core\Framework\MessageQueue\ScheduledTask\Telemetry;

/**
 * Passes a core scheduled-task name through as a metric label value; anything outside the allowlist
 * (plugin tasks) collapses to `other`, bounding the label cardinality.
 *
 * Owns its bounded output set (closed allowlist, `other` as default), so the consuming metric label may
 * use `policy: open`. The hardcoded list is intentional - see the rationale on
 * {@see \Contena\Core\Framework\DataAbstractionLayer\Telemetry\EntityGroupResolver}.
 *
 * @internal
 *
 * @final
 */
class TaskNameResolver
{
    /**
     * All core task names ({@see \Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask::getTaskName()}
     * of every core task class), as a hash set for O(1) lookup.
     *
     * @var array<string, true>
     */
    private const array CORE_TASKS = [
        'channel_context.cleanup' => true,
        'log_entry.cleanup' => true,
        'mcp_toolset_session.cleanup' => true,
        'media.cleanup_corrupted_media' => true,
        'member.cleanup_member_recovery' => true,
        'contena.elasticsearch.create.alias' => true,
        'contena.invalidate_cache' => true,
        'contena.sitemap_generate' => true,
        'telemetry.collect_periodic_metrics' => true,
        'theme.delete_files' => true,
        'translation.update' => true,
        'version.cleanup' => true,
    ];

    public function resolve(string $taskName): string
    {
        return isset(self::CORE_TASKS[$taskName]) ? $taskName : 'other';
    }
}
