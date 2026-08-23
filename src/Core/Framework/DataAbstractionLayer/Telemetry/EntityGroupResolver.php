<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Telemetry;

/**
 * Buckets a DAL entity name into a small, bounded set of groups, so the large, plugin-extensible set of
 * entity names does not blow up the metric label cardinality.
 *
 * Classification is O(1) in two steps: an exact full-name lookup for the entities that don't follow the convention,
 * then a fallback where the first underscore-delimited token determines the group (`blog_media` -> `blog`).
 *
 * Owns its bounded output set (closed map, `other` as default), so the consuming metric labels may use
 * `policy: open`. Known outputs: blog, category, member, media, content, rule, system, other.
 *
 * Shared resolver — reused by the HTTP request (admin-CRUD `domain`) and DAL search collectors.
 *
 * The hardcoded maps are intentional (optimized for deletion): while the label set is still changing,
 * one map with no extension API is simpler to maintain. Once the groups are stable we can switch to a cleaner approach,
 * e.g. a telemetry-group attribute on the EntityDefinition.
 *
 * @internal
 *
 * @final
 */
class EntityGroupResolver
{
    /**
     * Exact full entity names mapping for when root-token mapping is misleading or fragile (e.g. `main` as a key).
     *
     * @var array<string, string>
     */
    private const ENTITIES = [
        'blog_main_category' => 'category',
        'header_content_layout' => 'content',
        'footer_content_layout' => 'content',

        // custom fields are platform configuration
        'custom_field' => 'system',
        'custom_field_set' => 'system',
        'custom_field_set_relation' => 'system',
    ];

    /**
     * Root entity token → group. The root token is the part before the first underscore (or the whole name
     * when it has none), so e.g. `mail` covers `mail_template`/`mail_header_footer`. Unlisted roots fall through to `other`.
     *
     * Third-party entities use a vendor prefix, so they don't collide with these first-party roots. Generic-looking roots
     * (`state`, `user`, `log`, …) would only catch a convention-violating plugin entity — a rare, low-impact
     * mislabel — so they stay as tokens rather than being exhaustively exact-mapped.
     *
     * @var array<string, string>
     */
    private const ROOTS = [
        // basic domains
        'blog' => 'blog',
        'category' => 'category',
        'member' => 'member',
        'media' => 'media',
        'rule' => 'rule',

        // content
        'landing' => 'content',
        'mail' => 'content',
        'content' => 'content',

        // platform configuration & infrastructure
        'acl' => 'system',
        'country' => 'system',
        'region' => 'system',
        'language' => 'system',
        'locale' => 'system',
        'unit' => 'system',
        'channel' => 'system',
        'plugin' => 'system',
        'integration' => 'system',
        'system' => 'system',         // system_config
        'user' => 'system',
        'scheduled' => 'system',      // scheduled_task
        'number' => 'system',         // number_range*
        'state' => 'system',          // state_machine*
        'seo' => 'system',            // seo_url*
        'import' => 'system',         // import_export*
        'flow' => 'system',
        'webhook' => 'system',
        'snippet' => 'system',
        'theme' => 'system',
        'log' => 'system',            // log_entry
        'notification' => 'system',
        'tag' => 'system',
    ];

    public function resolve(string $entityName): string
    {
        return self::ENTITIES[$entityName]
            ?? self::ROOTS[strstr($entityName, '_', true) ?: $entityName]
            ?? 'other';
    }
}
