# Upcoming

## Administration

### Runtime guards for Administration JavaScript deprecations

`Contena.Feature.triggerDeprecationOrThrow(majorFlag, message)` now gives Administration deprecations the same lifecycle as PHP deprecations. Before the target major it emits a development warning with the migration message and call site; once the major flag is active it throws. Deprecated native SFC components and props are guarded by the Administration deprecation plugin when they are mounted or supplied, and `ct-deprecation-rules/require-deprecation-guard` enforces the contract for future public deprecations.

### Inspect native Administration extension blocks with Vue DevTools

The development-only Vue DevTools plugin now includes a **Contena Extension Blocks** inspector for native `<ct-block>` extension points. It shows the blocks rendered on the current page as a DOM-shaped tree, supports filtering and element picking, highlights selected blocks, reports their owning component and active native extensions, and provides a copy-ready `<ct-block extends>` snippet.

DOM markers are disabled by default. Enable them with the inspector's power action or `localStorage.setItem('ct-admin-block-inspector', 'true')`, then reload the Administration. The `data-ct-block` markers are never rendered in production builds.

### Native-setup build errors point at the author's source

Errors raised by the native-setup transform now carry the line and column of the offending code in the original `.vue` file and print a code frame, in Vite, Jest and the `valid-contena-setup` ESLint rule alike. Syntax errors previously reported block-relative Babel coordinates, and marker or reserved-name errors pointed at the start of the file or block.

The transform now also rejects `v-model` on a forwarded override binding inside `<ct-block extends>` content, the same way it rejects `count++` or `count = 1` there. Such a binding arrives read-only through the slot scope, so the write never took effect. Member writes such as `v-model="form.name"` remain allowed; mutate override state from a handler defined in the override setup instead.

## Core

### SEO URLs for App frontend routes

Apps can give script-rendered frontend pages SEO URLs by declaring `<seo-url>` elements inside `<frontend>` in `manifest.xml`.

```xml
<frontend>
    <seo-url name="imprint">
        <path>imprint</path>
        <path lang="zh-CN">legal-notice</path>
    </seo-url>
    <seo-url name="blog-detail" entity="blog">
        <default-template>blog/{{ blog.translated.name }}</default-template>
    </seo-url>
</frontend>
```

A static entry maps the given path to the script hook `frontend-<name>` on every Web Channel domain; the `hook` attribute overrides the hook name. An entity-bound entry generates one SEO URL per entity from the Twig template. Administrators can adjust that template per Channel in Settings > SEO, where the route is listed as `frontend.app.<appName>.<name>`. The template context exposes the entity under its camel-cased name. URLs are regenerated whenever the entity is written, marked as deleted while the App is inactive, and removed on uninstall.

The script receives the entity id as `hook.query.id`. Templates link to such pages with `seoUrl('frontend.script_endpoint', { hook: 'blog-detail', id: blog.id })`; the placeholder is replaced with the SEO path like for Blog and Category pages.

Query parameters stored in `seo_url.path_info` are merged into the request when the SEO URL is resolved and take precedence over the browser's query string. The `seo_url.route_name` field allows 255 characters.

### `EntitySearchResult` supports result subclasses

`Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult` is no longer marked `@final` and can be extended by specialized search-result types. Its constructor remains `final`; subclasses should use their own factory around the inherited constructor and initialize their additional state afterwards.

### New method `IdSearchResult::getPrimaryKeyData`

The new `Contena\Core\Framework\DataAbstractionLayer\Search\IdSearchResult::getPrimaryKeyData()` method returns IDs in repository write format.
Single ID lists are formatted like this: `list<['id' => $id]>`.
Composite primary keys remain unchanged.
For example, the returned array can be passed directly to `EntityRepository::delete()`:

```php
$result = $repository->searchIds($criteria, $context);
$repository->delete($result->getPrimaryKeyData(), $context);
```

### Blog comments and direct replies

Blog pages now expose a Channel API comment contract for authenticated members. Clients can read paginated root comments
with visible direct replies through `GET|POST /channel-api/blog/{blogId}/comments` and submit a pending comment or direct
reply through `POST /channel-api/blog/{blogId}/comment`. Replies use `parentId` and are limited to one level; ratings,
purchase verification and product-specific review fields are not part of the CMS contract. Channel configuration exposes
the `core.listing.showComments` and `core.listing.commentsPerPage` values through `site-settings`.

### Configurable last-modified version strategy for theme assets

The `FlysystemLastModifiedVersionStrategy` used for theme assets can now be disabled per installation in `config/packages/contena.yaml`. When disabled, theme asset URLs use an empty version strategy instead of fetching the last-modified timestamp from the filesystem on every request, eliminating the associated cache lookups.

This is safe to disable whenever the default `SeedingThemePathBuilder` is active, because the seed already rotates on every theme compilation and serves as the cache-invalidation mechanism. It is particularly useful with a remote theme filesystem such as S3 or GCS, where last-modified lookups involve additional latency.

```yaml
contena:
  filesystem:
    theme:
      use_last_modified_version_strategy: false
```

The option defaults to `true`.

### Data-scope-aware entity indexing

Full DAL indexing now carries an explicit `Context` through total calculation, ID iteration, batch messages, and handlers. Cross-scope management requests are expanded into one exact-scope indexing run for the platform and each tenant, so derived writes never execute with cross-scope access. Custom entity indexers must adopt the Context-aware method signatures described in `UPGRADE-6.8.md`.

### Canonical data scopes replace nullable tenant ownership

Scope-owned business data now carries a required, immutable `dataScopeId`. The canonical platform scope and every tenant scope use the same storage, filtering, foreign-key, cache, background-work, and OpenSearch rules, so platform-only installations no longer depend on nullable-tenant fallback behavior. Default and tenant Contexts read one exact scope; Global and CLI Contexts may read all scopes but still write only to the platform scope.

Administration users are shared identities with explicit grants in `user_data_scope`. Active state, administrator state, user code, and cross-scope read authority belong to a grant; no access is inferred from the absence of tenant memberships. Tenant scope IDs equal their tenant IDs, while ownership code should use `Context::getDataScopeId()` and reserve `getTenantId()` for tenant-domain behavior.

### Initial installations create a default member

Web installer setups and `system:install --basic-setup` now create a member for the default Web channel with the initial administrator's name, email address, and password. Running `user:create` independently continues to create only an administration user.

### Concurrent sitemap generation is skipped gracefully

`sitemap:generate` without `--force` no longer aborts when another process is already generating the sitemap for the same channel and language. The affected channel is reported and skipped, and generation continues for the remaining channels. The lock now throws `Contena\Core\Content\Sitemap\Exception\AlreadyLockedException` again as a subtype of `SitemapException`, so plugin `catch (AlreadyLockedException)` blocks work as intended; the error code and HTTP status remain unchanged.

### Server-side cookie consent logging

The built-in cookie banner can now record every consent decision server-side, so operators can demonstrate that consent was obtained (GDPR Art. 7(1), Recital 42). Recording is off by default and is enabled with `contena.cookie_consent.log_storage`.

- The frontend sends each banner interaction to `POST /cookie/consent-log`; headless clients use `POST /channel-api/cookie-consent-log`. The client reports only a generated `consentId`, the action and selected cookie names. Contena derives the accepted, partial or rejected verdict for every group from the server configuration.
- The `cookie-consent-id` cookie links later decisions by the same browser. It is listed as a required cookie while logging is enabled and uses `contena.cookie_consent.retention_days` as its lifetime. No IP address, user agent, session id or member id is stored.
- `contena.cookie_consent.log_storage` selects `none`, `database`, `filesystem`, or a custom `AbstractCookieConsentLogStorage` service tagged `contena.cookie_consent.log_storage`. Database and filesystem records preserve the originating data scope; the filesystem storage writes below the fixed `cookie-consent/` folder on `contena.filesystem.private`, and snapshots are separated by data scope.
- `bin/console cookie:consent:export [--from] [--to] [--channel] [--format=json|csv]` streams records for compliance requests. The daily `cookie_consent_log.cleanup` task removes decisions older than the configured retention period and is skipped while logging is off.
- `cookie_consent_log` is rate limited to 60 requests per 60 seconds and client IP is used only as the limiter key.

This feature records proof collected through the built-in banner; it is not an IAB TCF or Google-certified consent-management platform. Operators remain responsible for selecting an appropriate consent solution and retention period.

### Modular payment services and plugin boundaries

Incoming payments (`Payment`), refunds, transfers and subscription agreements now expose separate abstract service contracts. Each business owns its orders/agreements, queries and callbacks; there is no shared order workflow or standalone query domain. Gateways implement capability-specific interfaces; routing plugins can provide candidates, veto candidates and select a route. Events distinguish vetoable pre-gateway hooks, transactional state changes and best-effort gateway completion observers, without a duplicate operation event cycle. See `src/Core/System/Payment/README.md` for contracts, tags, ordering and limitations.

The order converter returns flat order data and dispatches `PaymentOrderConvertedEvent` for metadata/custom-field enrichment before persistence. It no longer allocates identifiers, creates transactions or returns an `order` envelope. Provider execution records are created explicitly before provider I/O.

Payment status queries now reconcile the primary payment transaction instead of creating synthetic query transactions. Payment transactions represent provider-facing payment attempts, so the unused operation discriminator and operator placeholder were removed.

Gateway capability methods now return `GatewayResult`, while business services return `PaymentResult` with the platform resource numbers. Provider response metadata/raw data and client actions are represented by `GatewayResponse` and `PaymentAction`; the ambiguous mutating `PaymentResult::withResource()` API was removed.

Input-format validation is centralized in the request objects under `OpenApi\Struct`. PHP business-service callers must supply validated inputs; ownership, idempotency and financial state constraints remain in core. Catchable failures now have dedicated classes under `Payment\Exception`, retaining existing factory methods, error codes and HTTP statuses. Transfer replay rejects a changed payee name. Orders no longer eagerly load transaction histories during result processing.

Gateway SDK handling is consolidated in the internal `YansongdaPayClient`; plugins can register capability-specific gateways with their own clients. WeChat single transfers select the matching SDK protocol. Unrecognized responses and abnormal refunds remain unresolved rather than releasing reservations as confirmed failures, and Alipay amount conversion preserves integer precision.

Core routing no longer includes rule-engine integration. Existing installations using payment rule assignments must provide their policy through a plugin before upgrading; the legacy database values are not deleted or evaluated by core.

### Shared currency catalog and payment currency validation

Contena now provides the shared `currency` and `currency_translation` DAL entities with ISO code, display symbol, decimal precision and an optional exchange factor. CNY is the system currency, and common international currencies are installed as configurable reference data. The factor is never applied automatically by payment services.

Incoming payments and transfers reject currencies that are absent from the catalog with `PAYMENT__CURRENCY_NOT_SUPPORTED`. Payment gateway plugins whose settlement currencies are restricted can implement `CurrencyAwareGatewayInterface`; routing then excludes the gateway for unsupported currency codes. Existing gateways that do not implement the interface remain currency-agnostic. The bundled Alipay and WeChat integrations explicitly advertise CNY only.

## Hosting & Configuration

### `No-Vary-Search` header on cacheable responses

Cacheable Frontend and Channel API responses send [`No-Vary-Search: key-order`](https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/No-Vary-Search), declaring that the order of query parameters does not change the response. The server already normalizes query order before looking up its cache entry, so the header only tells clients what was always true.

The header is a specification draft, support differs per browser and cache, and it does not replace query sorting in a reverse proxy such as Varnish or Fastly. A client that ignores it continues treating a reordered query string as a different URL.

Set a custom value per policy under `headers.no_vary_search`, for example `no_vary_search: 'key-order, params=("gclid")'`. It is passed through verbatim and validated only as a single line of printable ASCII. If the key is omitted, no `No-Vary-Search` header is sent and any value a controller or plugin set earlier is removed. Unlike `Cache-Control`, the header cannot be influenced by a `#[HttpCache]` attribute; the policy is its only source.

Never list parameters that change rendered content, such as `p`, `order`, `search`, or filter names. A client could otherwise match a stored response against the wrong URL. Tracking parameters are safe because reuse does not rewrite the document URL.

## API

### Channel API resolves context from the frontend session on request

A Channel API request that sends the frontend session cookie together with `ct-access-key` and the new `ct-context-source: session` header is resolved with the context token held in that session. A client rendered on a frontend page therefore shares the member login without handling a token; login, logout and password-change token rotations are written back into the session.

A session is only ever resumed, never created, so this requires a frontend: on an API-only channel there is no session to share and the header always fails.

The header is an explicit contract. The request fails with `FRAMEWORK__ROUTING_SESSION_CONTEXT_NOT_RESOLVABLE` (HTTP 400) when the session cannot be used, including a missing session cookie, a simultaneous `ct-context-token` header, or a session without a token for the requested channel. Requests without the header behave as before.

Browser access stays constrained by the default CORS configuration, which excludes `ct-context-source` from the allowed headers and answers with `Access-Control-Allow-Origin: *` and no credentials, so a cross-origin page can send neither the opt-in header nor the session cookie. Deployments that widen CORS must keep `ct-context-source` out of the allowed headers, or the session cookie out of cross-origin reach.

Session-resolved responses are always private and `no-store`, omit the `ct-context-token` response header, and bypass the built-in HTTP cache before validation. `ct-context-source` is included in the `Vary` set for external reverse proxies.

### Blog and category Channel API routes return the breadcrumb

`GET|POST /channel-api/blog/{blogId}` and `GET|POST /channel-api/category/{navigationId}` now return a `seoBreadcrumb` field, so headless clients no longer need a second request to `GET /channel-api/breadcrumb/{id}`. It contains the category ID, type, resolved path and SEO URLs of every category on the path. Pass `skipBreadcrumb=1` to skip the two additional breadcrumb queries.

The blog detail route also accepts `referrerCategoryId` in the query string or request body and builds both `seoCategory` and `seoBreadcrumb` along that category when it belongs to the blog's category tree and is reachable in the current channel. Internal request attributes take precedence over client parameters. Without a referrer, visible active categories are preferred by depth and ties are resolved by `autoIncrement`; categories hidden from navigation remain valid breadcrumb sources.

`slotConfig` is no longer exposed inside a breadcrumb entry's `translated` object because it is not Channel-API-aware on the category definition. Category-scoped custom fields marked as not Channel-API-aware are also removed. The standalone breadcrumb route remains available.

### Content system layout property mutations

Administration clients can update or remove declared primitive properties on one content-layout element through the new draft and persisted `update-element-properties` mutation actions. Both actions preserve every unmentioned property, validate values against the registered element type, and return the standard mutation response; persisted updates retain the existing optimistic-concurrency contract.

Payment HTTP paths and JSON request/response field names remain unchanged. HTTP DTOs now belong to OpenApi; PHP callers must use the four modules' domain inputs. Synchronous aggregate status transitions now enqueue merchant notifications through the same transactional path as provider callbacks. Outbound delivery rejects private-network destinations.

Missing refund, transfer, subscription and execution-record lookups now return their respective `PAYMENT__*_NOT_FOUND` codes with HTTP 404, instead of a generic invalid request or an unrelated order-not-found error.
