# Cache

HTTP cache integration for content system routes. Manages cache tag collection during hydration and invalidation when entities change.

## Key Classes

- `CacheFinalizer` - Applies accumulated cache state to HTTP response after hydration
- `CacheInvalidationSubscriber` - Invalidates cached pages when content entities change
- `EntityCacheTagResolver` - Resolves entity definitions to cache tag patterns
- `RenderingCacheContext` - Tracks tags + disabled state through the pipeline

## Cache Tag Patterns

| Entity         | Tag Pattern               |
|----------------|---------------------------|
| blog        | `blog-{id}`            |
| category       | `category-route-{id}`     |
| landing_page   | `landing-page-route-{id}` |
| cms_page       | `cms-page-{id}`           |
| blog_stream | `blog-stream-{id}`     |

Unsupported entities return null → page becomes uncacheable.

## Invalidation

`CacheInvalidationSubscriber` listens to `EntityWrittenContainerEvent`:
- **content_layout** → `content-layout-{id}`
- **assignment tables** (blog/category/landing_page/header/footer) → looks up associated entity and invalidates its tag

Every table name comes from its definition's `ENTITY_NAME`. Header and footer are Frontend-owned, so
the Frontend hands those two to the subscriber through the container parameter
`contena.content_system.section_assignment_entities`; Core declares it empty.
