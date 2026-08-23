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
| content_layout | `content-layout-{id}`      |

Unsupported entities return null → page becomes uncacheable.

## Invalidation

`CacheInvalidationSubscriber` listens to `EntityWrittenContainerEvent`:
- **content_layout** → `content-layout-{id}`
- **assignment tables** (blog/category/landing_page/header/footer) → looks up associated entity and invalidates its tag
