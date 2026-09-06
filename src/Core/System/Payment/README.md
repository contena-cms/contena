# Payment architecture

Payment is a set of four business modules, not a generic workflow engine. Its core has no dependency on `OpenApi`, an HTTP request, or the rule engine. Plugin-specific routing rules, fees, risk policies and orchestration belong in plugins.

## Ownership and public contracts

| Module | Supported entry point | Responsibility |
| --- | --- | --- |
| `Payment` | `AbstractPaymentOrderService` | Incoming payments and their payment orders |
| `Refund` | `AbstractPaymentRefundService` | Refund requests and refundable-amount reservation |
| `Transfer` | `AbstractPaymentTransferService` | Transfers to a beneficiary |
| `Subscription` | `AbstractPaymentSubscriptionService` | Subscription agreement creation |
| `Routing` | `AbstractPaymentRouteResolver` | Choose a route for a new operation |
| `Gateway` | Capability-specific handler interfaces | Translate between a provider and the payment domain |
| `Notification` | `PaymentNotificationHandlerInterface` | Apply verified provider callbacks to their owning module |
| `OpenApi` | Existing HTTP endpoints | Authentication, HTTP validation, request mapping and merchant notification delivery |

Modules are named for business responsibilities, not a shared notion of an order. Payment, refund and transfer orders have separate state and invariants; subscription agreements are not payment orders. Query belongs to the business it reads, not a separate domain. Each module keeps its callback handler alongside its service and persister; a one-file notification subdirectory adds no useful boundary.

Every public business-service entry validates application status and the concrete tenant/platform scope before loading or creating data. There is no generic business-workflow executor or duplicate operation-level event cycle. Decorate the module contract for entry-point policies that need different timing.

The existing `AbstractPaymentService` is a convenience facade. It only delegates; business logic belongs in the four modules. Decorate the abstract contract, never inherit an internal implementation. Prefer an event when its timing matches the customization. New methods on supported abstract contracts should delegate to `getDecorated()` by default, so existing decorators remain compatible.

DAL entity/table names remain stable except for removal of the native routing `ruleId`/`rule` fields. In particular, `payment_recurring` is the existing persistence name for subscription agreements; it is not renamed as part of the service refactor.

## Conversion, execution and persistence

`PaymentOrderConverter::convert()` returns the flat order data array. It does not return an `order` envelope, allocate IDs or numbers, create execution records, or return a persistence result. Like Shopware's order conversion event, `PaymentOrderConvertedEvent` exposes the converted array to plugins before persistence and returns the modified array. The core persister still allocates its own ID, number and initial state, and the application service creates execution records explicitly after the order exists.

`PaymentOrderPersister` allocates the order identity and number and writes the order. Only after the order exists, the service creates the payment transaction, pins it as the primary transaction and then calls the gateway. A query updates that same payment transaction; it is not a new financial transaction or a separate audit row. Conversion itself never calls a provider or persists data.

`payment_order_transaction` represents the provider-facing payment attempt for an order, similar to Shopware's order transaction. It is neither a database transaction nor a financial ledger, and therefore has no generic operation `type`. Status queries reconcile the primary transaction instead of creating synthetic query transactions. Future authorization, capture and retry support must model those payment operations explicitly instead of adding speculative discriminators to this record.

Persisters only create their module's records. The application services own orchestration and the state handlers own concurrent result application, failure recording and status transitions. Refund creation and amount reservation still commit together because they are one financial write. Result updates and outgoing notification enqueueing also commit together. Provider I/O is outside these local write transactions. Callers must not wrap a financial operation in a longer outer transaction: it would hold locks across I/O and could roll back local records after the provider accepted the operation.

Gateway capability methods return `GatewayResult`: normalized provider status, an optional client `PaymentAction`, and `GatewayResponse` metadata/raw data. Business services return `PaymentResult`, which adds the platform resource numbers without copying gateway fields or mutating the gateway result after construction.

State decisions use the values returned by the locking read, not a subsequent ordinary DAL snapshot read. Under an older repeatable-read snapshot, a conflicting nonterminal state-machine update fails with `PAYMENT__CONCURRENT_MODIFICATION` rather than overwriting another execution. Reconcile in a new transaction; never repeat a debit automatically.

## Gateway and routing plugins

Implement `GatewayInterface::code()` and only the capabilities the provider actually supports (`PaymentHandlerInterface`, `PaymentQueryHandlerInterface`, `RefundHandlerInterface`, `TransferHandlerInterface`, `SubscriptionHandlerInterface`, `GatewayNotificationHandlerInterface`). Register the service with `contena.payment.gateway`. Empty and duplicate codes fail registration. `PaymentQueryHandlerInterface` specifically queries payment orders, not refunds or transfer orders.

```php
$services->set(AcmeGateway::class)->autowire()->tag(GatewayInterface::SERVICE_TAG);
```

Create the matching channel/configuration and, for payment-method routing, method assignments through the DAL during plugin installation/configuration. No registry switch or core service edit is needed. Gateways may inject their own HTTP client or SDK. The internal `YansongdaPayClient` only serves the bundled channels: it owns SDK initialization/cleanup and normalizes SDK response containers. The concrete channels own protocol fields, amount formatting and financial-status interpretation. There is no required gateway base class or generic response factory. Gateway inputs do not eagerly load complete transaction histories.

Missing or unrecognized provider response fields are not a confirmed financial rejection. Unknown refunds keep their reservations; WeChat `ABNORMAL` needs reconciliation rather than releasing funds. Alipay amount serialization uses integer arithmetic. Accepted transfers can remain processing until a terminal outcome is confirmed.

Routing has two different extension contracts:

| Extension | Tag | Contract |
| --- | --- | --- |
| Candidate source | `contena.payment.route_provider` | `PaymentRouteProviderInterface::provide()` |
| Selection policy | `contena.payment.route_selection_strategy` | `PaymentRouteSelectionStrategyInterface::select()` |

Providers return candidates in preference order and must enforce application/tenant ownership. Core configuration checks enabled method assignments and channel configurations, using shared platform configurations as fallback. Unsupported capabilities and mismatched explicitly requested channels are excluded before selection. There is no core rule evaluation or `PaymentRuleScope`.

`PaymentRouteCandidateEvent` lets a plugin veto a candidate. Strategies run in tagged priority order; `null` declines. A strategy must return one of the eligible candidate objects, not invent a new route. If no plugin strategy selects one, core uses the first eligible candidate. `PaymentRouteResolvedEvent` observes the immutable selection. Add a rule engine, weights or circuit-breaker policy in a plugin through these contracts; do not introduce those policies into the core resolver.

Queries and refunds load the order's persisted configuration using `PaymentGatewayResolver`; they must not run new-payment selection. A failed/unknown response never triggers automatic rerouting or a second financial call.

## Event semantics

| Stage | Event | Failure semantics |
| --- | --- | --- |
| Eligible candidate / selected route | `PaymentRouteCandidateEvent` / `PaymentRouteResolvedEvent` | Runs before new resource creation |
| Order data conversion | `Event\PaymentOrderConvertedEvent` | May modify order data or reject before persistence |
| Aggregate creation | `PaymentEntityCreatedEvent` | Inside the local creation transaction; failure rolls it back |
| Before provider call | `PaymentGatewayStartedEvent` | May reject before provider I/O |
| Provider returned / threw | `PaymentGatewayCompletedEvent` / `PaymentGatewayFailedEvent` | Observational; listener failures are logged, not substituted for the provider outcome |
| Aggregate state changed | `PaymentStatusChangedEvent` | Inside the result transaction; failure rolls back state and transactional subscribers |
| Verified inbound notification applied | `GatewayNotificationProcessedEvent` | Inside the notification transaction |

“Completed” means a PHP call returned, not that money moved successfully. Pending/unknown outcomes remain pending/unknown. Success/failure observers are best-effort; if a listener throws, later listeners in that dispatch might not run. They are not a durable message bus.

`PaymentEntityReference` contains only `entityName` and `entityId`, identifying the aggregate affected by an event. It does not contain a notify URL, outgoing payload or foreign-key field name. Subscribers load the entity using the event's `Context`. OpenApi alone translates the reference into its merchant-notification envelope.

Transactional subscribers must only perform local transactional work. Do not send HTTP, emails or messages to a nontransactional transport from them. OpenApi's `AppNotificationSubscriber` only projects a scoped payment result into `payment_notify_record` in the result transaction. Its small, internal envelope mapping defines the four existing HTTP notification types; it is not a new provider/handler framework. `AppNotificationService` and its delivery task own HTTP delivery and retries. Repeated callbacks that do not change the aggregate status do not enqueue another status notification.

## Plugin example: enrich order metadata

```php
use Contena\Core\System\Payment\Event\PaymentOrderConvertedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class MerchantOrderSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [PaymentOrderConvertedEvent::class => 'enrich'];
    }

    public function enrich(PaymentOrderConvertedEvent $event): void
    {
        $event->convertedOrder['customFields']['merchant_source'] = 'partner_checkout';
    }
}
```

Register the subscriber as `kernel.event_subscriber`. Register the custom field through the plugin's normal DAL custom-field installation. No core service replacement is needed.

## Catchable failures

`PaymentException` remains the factory and error-code owner. `Exception/` exposes typed lookup, routing/capability, duplicate-reference, concurrency, notification-target and refund-eligibility failures so plugins can catch a specific condition without parsing a message. Existing error codes and HTTP statuses remain unchanged. Invalid input and invalid extension registration remain generic domain errors. A concurrency exception calls for reconciliation, never an automatic repeat of a financial call.

## Scope and intentional limits

Execute with a platform or owning-tenant `Context`, never Global. Authentication and verified callback lookup may use Global only to resolve an owner; writes then use that concrete scope. Preserve this scope in jobs and subscribers. An entity reference does not grant permission to read another tenant.

This architecture refactor is not certification of a complete commercial payment product. The next business phase must address platform-safe uniqueness and concurrent idempotency, durable recovery after a provider succeeds but result persistence fails, normalized callback amount/currency/account verification, unambiguous versioned OpenApi signatures, refund/transfer/agreement query recovery, agreement cancellation, and any required capture/close/renewal/reconciliation workflows. No placeholder implementations or generic workflow/transaction frameworks are added for those features.

## Maintenance boundaries and Shopware alignment

The implementation follows Shopware's explicit domain services, tagged handler discovery, converter events, DAL persistence and supported abstract decoration contracts. It does not copy Shopware's cart/HTTP-specific payment handler or its checkout/refund lifecycle: an aggregation platform has independent transfer orders and agreements, and an unknown provider result must not be treated as a confirmed failure.

The minimum core retains tenant ownership, money/state consistency, refund reservation, callback verification/deduplication and transactional notification records. Routing rules, fees, risk decisions, orchestration and business-specific schedules belong in plugins. Removing a safety invariant is not an architectural simplification.

The private-method review keeps protocol mappings, shared SDK conversion, exact amount formatting, reusable persistence/scoping rules, idempotent response reconstruction and observer exception isolation. Single-use forwarding wrappers and duplicate snapshot-state checks were removed. Input-format validation belongs to `OpenApi\Struct` DTO constraints, not business services. Other PHP adapters must supply validated inputs; core still enforces application ownership, idempotency, state transitions and refundable balances. No validator factory, generic comparison engine or request pipeline was introduced. New private methods must name a real rule, boundary or reusable operation; new classes/interfaces must represent an actual module or extension contract, not merely shorten a method.

Missing refund, transfer, subscription and execution-record lookups now return their respective `PAYMENT__*_NOT_FOUND` codes with HTTP 404, instead of a generic invalid request or an unrelated order-not-found error.
