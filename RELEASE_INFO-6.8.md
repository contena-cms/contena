# Upcoming

## Core

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
- `contena.cookie_consent.log_storage` selects `none`, `database`, `filesystem`, or a custom `AbstractCookieConsentLogStorage` service tagged `contena.cookie_consent.log_storage`. Database and filesystem records preserve the originating data scope; filesystem snapshots are separated by data scope.
- `bin/console cookie:consent:export [--from] [--to] [--channel] [--format=json|csv]` streams records for compliance requests. The daily `cookie_consent_log.cleanup` task removes decisions older than the configured retention period.
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

## API

### Channel API resolves context from the frontend session on request

A Channel API request that sends the frontend session cookie together with `ct-access-key` and the new `ct-context-source: session` header is resolved with the context token held in that session. Same-origin clients rendered by the frontend can therefore share the member login without handling a token; login, logout and password-change token rotations are written back into the session.

The header is an explicit contract. The request fails with `FRAMEWORK__ROUTING_SESSION_CONTEXT_NOT_RESOLVABLE` (HTTP 400) when the session cannot be used, including a missing session cookie, a cross-site fetch, a simultaneous `ct-context-token` header, or a session without a token for the requested channel. The `contena.routing.session_context_token.enabled` container parameter can disable session resolution; clients that still request it then receive the same error.

Session-resolved responses are always private and `no-store`, omit the `ct-context-token` response header, and bypass the built-in HTTP cache before validation. `ct-context-source` is included in the `Vary` set for external reverse proxies.

### Blog and category Channel API routes return the breadcrumb

`GET|POST /channel-api/blog/{blogId}` and `GET|POST /channel-api/category/{navigationId}` now return a `seoBreadcrumb` field, so headless clients no longer need a second request to `GET /channel-api/breadcrumb/{id}`. It contains the category ID, type, resolved path and SEO URLs of every category on the path. Pass `skipBreadcrumb=1` to skip the two additional breadcrumb queries.

The blog detail route also accepts `referrerCategoryId` in the query string or request body and builds both `seoCategory` and `seoBreadcrumb` along that category when it belongs to the blog's category tree and is reachable in the current channel. Internal request attributes take precedence over client parameters. Without a referrer, visible active categories are preferred by depth and ties are resolved by `autoIncrement`; categories hidden from navigation remain valid breadcrumb sources.

`slotConfig` is no longer exposed inside a breadcrumb entry's `translated` object because it is not Channel-API-aware on the category definition. Category-scoped custom fields marked as not Channel-API-aware are also removed. The standalone breadcrumb route remains available.

### Content system layout property mutations

Administration clients can update or remove declared primitive properties on one content-layout element through the new draft and persisted `update-element-properties` mutation actions. Both actions preserve every unmentioned property, validate values against the registered element type, and return the standard mutation response; persisted updates retain the existing optimistic-concurrency contract.

Payment HTTP paths and JSON request/response field names remain unchanged. HTTP DTOs now belong to OpenApi; PHP callers must use the four modules' domain inputs. Synchronous aggregate status transitions now enqueue merchant notifications through the same transactional path as provider callbacks. Outbound delivery rejects private-network destinations.

Missing refund, transfer, subscription and execution-record lookups now return their respective `PAYMENT__*_NOT_FOUND` codes with HTTP 404, instead of a generic invalid request or an unrelated order-not-found error.
