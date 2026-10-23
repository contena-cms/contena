<?php declare(strict_types=1);

namespace Contena\Core\Framework\MessageQueue\ScheduledTask\Telemetry;

use Contena\Core\Framework\Log\Package;

/**
 * Passes a core scheduled-task name through as a metric label value; anything outside the allowlist
 * (plugin/app tasks) collapses to `other`, bounding the label cardinality.
 *
 * Owns its bounded output set (closed allowlist, `other` as default), so the consuming metric label may
 * use `policy: open`. The hardcoded list is intentional — see the rationale on
 * {@see \Contena\Core\Framework\DataAbstractionLayer\Telemetry\EntityGroupResolver}.
 *
 * @internal
 *
 * @final
 *
 * @experimental feature:TELEMETRY_METRICS stableVersion:v6.8.0
 */
class TaskNameResolver
{
    /**
     * All core task names ({@see \Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask::getTaskName()}
     * of every core task class), as a hash set for O(1) lookup.
     *
     * @var array<string, true>
     */
    private const CORE_TASKS = [
        'app.system_heartbeat' => true,
        'app_delete' => true,
        'app_update' => true,
        'cart.cleanup' => true,
        'user.cleanup_customer_recovery' => true,
        'user.delete_unused_guests' => true,
        'delete_newsletter_recipient_task' => true,
        'import_export_file.cleanup' => true,
        'in-app-purchase.update' => true,
        'log_entry.cleanup' => true,
        'media.cleanup_corrupted_media' => true,
        'payment_token.cleanup' => true,
        'blog_download.media.cleanup' => true,
        'blog_export_generate_task' => true,
        'blog_keyword_dictionary.cleanup' => true,
        'content_stream.mapping.update' => true,
        'channel_context.cleanup' => true,
        'services.install' => true,
        'contena.elasticsearch.create.alias' => true,
        'contena.invalidate_cache' => true,
        'contena.sitemap_generate' => true,
        'telemetry.collect_periodic_metrics' => true,
        'theme.delete_files' => true,
        'usage_data.entity_data.collect' => true,
        'version.cleanup' => true,
        'webhook_event_log.cleanup' => true,
    ];

    public function resolve(string $taskName): string
    {
        return isset(self::CORE_TASKS[$taskName]) ? $taskName : 'other';
    }
}
