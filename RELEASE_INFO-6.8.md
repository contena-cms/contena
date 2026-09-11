# Upcoming

## Core

### Initial installations create a default member

Web installer setups and `system:install --basic-setup` now create a member for the default Web channel with the initial administrator's name, email address, and password. Running `user:create` independently continues to create only an administration user.

### Concurrent sitemap generation is skipped gracefully

`sitemap:generate` without `--force` no longer aborts when another process is already generating the sitemap for the same channel and language. The affected channel is reported and skipped, and generation continues for the remaining channels. The lock now throws `Contena\Core\Content\Sitemap\Exception\AlreadyLockedException` again as a subtype of `SitemapException`, so plugin `catch (AlreadyLockedException)` blocks work as intended; the error code and HTTP status remain unchanged.

### Modular payment services and plugin boundaries

Incoming payments (`Payment`), refunds, transfers and subscription agreements now expose separate abstract service contracts. Each business owns its orders/agreements, queries and callbacks; there is no shared order workflow or standalone query domain. Gateways implement capability-specific interfaces; routing plugins can provide candidates, veto candidates and select a route. Events distinguish vetoable pre-gateway hooks, transactional state changes and best-effort gateway completion observers, without a duplicate operation event cycle. See `src/Core/System/Payment/README.md` for contracts, tags, ordering and limitations.

The order converter returns flat order data and dispatches `PaymentOrderConvertedEvent` for metadata/custom-field enrichment before persistence. It no longer allocates identifiers, creates transactions or returns an `order` envelope. Provider execution records are created explicitly before provider I/O.

Payment status queries now reconcile the primary payment transaction instead of creating synthetic query transactions. Payment transactions represent provider-facing payment attempts, so the unused operation discriminator and operator placeholder were removed.

Gateway capability methods now return `GatewayResult`, while business services return `PaymentResult` with the platform resource numbers. Provider response metadata/raw data and client actions are represented by `GatewayResponse` and `PaymentAction`; the ambiguous mutating `PaymentResult::withResource()` API was removed.

Input-format validation is centralized in the request objects under `OpenApi\Struct`. PHP business-service callers must supply validated inputs; ownership, idempotency and financial state constraints remain in core. Catchable failures now have dedicated classes under `Payment\Exception`, retaining existing factory methods, error codes and HTTP statuses. Transfer replay rejects a changed payee name. Orders no longer eagerly load transaction histories during result processing.

Gateway SDK handling is consolidated in the internal `YansongdaPayClient`; plugins can register capability-specific gateways with their own clients. WeChat single transfers select the matching SDK protocol. Unrecognized responses and abnormal refunds remain unresolved rather than releasing reservations as confirmed failures, and Alipay amount conversion preserves integer precision.

Core routing no longer includes rule-engine integration. Existing installations using payment rule assignments must provide their policy through a plugin before upgrading; the legacy database values are not deleted or evaluated by core.

## API

### Content system layout property mutations

Administration clients can update or remove declared primitive properties on one content-layout element through the new draft and persisted `update-element-properties` mutation actions. Both actions preserve every unmentioned property, validate values against the registered element type, and return the standard mutation response; persisted updates retain the existing optimistic-concurrency contract.

Payment HTTP paths and JSON request/response field names remain unchanged. HTTP DTOs now belong to OpenApi; PHP callers must use the four modules' domain inputs. Synchronous aggregate status transitions now enqueue merchant notifications through the same transactional path as provider callbacks. Outbound delivery rejects private-network destinations.

Missing refund, transfer, subscription and execution-record lookups now return their respective `PAYMENT__*_NOT_FOUND` codes with HTTP 404, instead of a generic invalid request or an unrelated order-not-found error.
