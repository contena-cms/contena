<?php declare(strict_types=1);

namespace Contena\Core\Framework\Webhook\Service;

use Contena\Core\Defaults;
use Contena\Core\Framework\App\AppLocaleProvider;
use Contena\Core\Framework\App\Event\AppChangedEvent;
use Contena\Core\Framework\App\Event\AppDeletedEvent;
use Contena\Core\Framework\App\Event\AppFlowActionEvent;
use Contena\Core\Framework\App\Event\AppPermissionsUpdated;
use Contena\Core\Framework\App\Exception\InstallationIdChangeSuggestedException;
use Contena\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\Event\FlowEventAware;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Webhook\AclPrivilegeCollection;
use Contena\Core\Framework\Webhook\Health\WebhookDispatchDecision;
use Contena\Core\Framework\Webhook\Hookable;
use Contena\Core\Framework\Webhook\Hookable\HookableEntityWrittenEvent;
use Contena\Core\Framework\Webhook\Hookable\HookableEventFactory;
use Contena\Core\Framework\Webhook\Message\WebhookEventMessage;
use Contena\Core\Framework\Webhook\Webhook;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
class WebhookManager implements ResetInterface
{
    /**
     * @var array<string, list<Webhook>>
     */
    private ?array $webhooks = null;

    /**
     * @var array<string, mixed>
     */
    private array $privileges = [];

    public function __construct(
        private readonly WebhookLoader $webhookLoader,
        private readonly HookableEventFactory $eventFactory,
        private readonly AppLocaleProvider $appLocaleProvider,
        private readonly AppPayloadServiceHelper $appPayloadServiceHelper,
        private readonly string $installationUrl,
        private readonly string $contenaVersion,
        private readonly WebhookDeliveryService $webhookDeliveryService,
        private readonly WebhookHealthService $webhookHealthService,
    ) {
    }

    public function dispatch(object $event): void
    {
        $context = Context::createDefaultContext();

        foreach ($this->eventFactory->createHookablesFor($event) as $hookable) {
            $useEventContext = $event instanceof FlowEventAware || $event instanceof AppChangedEvent || $event instanceof EntityWrittenContainerEvent;

            $this->callWebhooks($hookable, $useEventContext ? $event->getContext() : $context);
        }
    }

    public function reset(): void
    {
        $this->webhooks = null;
        $this->privileges = [];
    }

    public function clearInternalWebhookCache(): void
    {
        $this->webhooks = null;
    }

    public function clearInternalPrivilegesCache(): void
    {
        $this->privileges = [];
    }

    private function callWebhooks(Hookable $event, Context $context): void
    {
        $webhooksForEvent = $this->filterWebhooksByLiveVersion($this->getWebhooks($event->getName()), $event);

        if ($webhooksForEvent === []) {
            return;
        }

        $languageId = $context->getLanguageId();
        $userLocale = $this->appLocaleProvider->getLocaleFromContext($context);

        $affectedRoleIds = array_values(array_filter(array_map(static fn (Webhook $webhook) => $webhook->appAclRoleId, $webhooksForEvent)));
        $this->loadPrivileges($event->getName(), $affectedRoleIds);

        $this->dispatchWebhooksWithHealth($webhooksForEvent, $event, $languageId, $userLocale);
    }

    /**
     * @param list<Webhook> $webhooks
     */
    private function dispatchWebhooksWithHealth(
        array $webhooks,
        Hookable $event,
        string $languageId,
        string $userLocale,
    ): void {
        $deliver = [];
        $hold = [];
        $pendingInstallationIdChangeByApp = [];

        foreach ($webhooks as $webhook) {
            if (!$this->isEventDispatchingAllowed($webhook, $event)) {
                continue;
            }

            // An undeliverable app source must not consume a recovery trial.
            if ($webhook->appId !== null && $webhook->appVersion !== null) {
                $pendingInstallationIdChangeByApp[$webhook->appId] ??= !$this->canBuildAppSource($webhook->appVersion, $webhook->appName ?? '');

                if ($pendingInstallationIdChangeByApp[$webhook->appId]) {
                    continue;
                }
            }

            $decision = $this->webhookHealthService->gateFor($webhook->id);
            if ($decision === WebhookDispatchDecision::Skip) {
                continue;
            }

            $message = $this->createWebhookMessage($webhook, $event, $languageId, $userLocale);
            if ($message === null) {
                continue;
            }

            if ($decision === WebhookDispatchDecision::Hold) {
                $hold[] = $message;

                continue;
            }

            $deliver[] = $message;
        }

        if ($hold !== []) {
            $this->webhookDeliveryService->hold($hold);
        }

        if ($deliver === []) {
            return;
        }

        $this->webhookDeliveryService->process($deliver);
    }

    private function canBuildAppSource(string $appVersion, string $appName): bool
    {
        try {
            $this->appPayloadServiceHelper->buildSource($appVersion, $appName);

            return true;
        } catch (InstallationIdChangeSuggestedException) {
            return false;
        }
    }

    private function createWebhookMessage(
        Webhook $webhook,
        Hookable $event,
        string $languageId,
        string $userLocale
    ): ?WebhookEventMessage {
        if (!$this->isEventDispatchingAllowed($webhook, $event)) {
            return null;
        }

        try {
            $webhookData = $this->getPayloadForWebhook($webhook, $event);
        } catch (InstallationIdChangeSuggestedException) {
            // don't dispatch webhooks for apps if url changed
            return null;
        }

        $webhookHeaders = $event instanceof AppFlowActionEvent
            ? $event->getWebhookHeaders()
            : [];

        // partition by app for now. Later, PartitionAwareHookable allows event-level partitioning.
        $partitionKey = $webhook->appId ?? WebhookEventMessage::DEFAULT_PARTITION_KEY;

        return new WebhookEventMessage(
            $webhookData['source']['eventId'],
            $webhookData,
            $webhook->appId,
            $webhook->id,
            $this->contenaVersion,
            $webhook->url,
            $webhook->appSecret,
            $languageId,
            $userLocale,
            $webhookHeaders,
            $partitionKey,
            $webhook->appName,
        );
    }

    /**
     * @return array{
     *     data: array{payload: array<string, mixed>, event: string},
     *     source: array{url: string, eventId: string, action?: string}
     * }|array<string, mixed>
     */
    private function getPayloadForWebhook(Webhook $webhook, Hookable $event): array
    {
        $source = [
            'url' => $this->installationUrl,
            'eventId' => Uuid::randomHex(),
        ];

        if ($webhook->appId !== null && $webhook->appVersion !== null) {
            $source = \array_merge(
                $source,
                $this->appPayloadServiceHelper->buildSource($webhook->appVersion, $webhook->appName ?? '')->jsonSerialize()
            );
        }

        if ($event instanceof AppFlowActionEvent) {
            $source['action'] = $event->getName();
            $payload = $event->getWebhookPayload();
            $payload['source'] = $source;

            return $payload;
        }

        $data = [
            'payload' => $this->filterPayloadByLiveVersion($event->getWebhookPayload(), $webhook, $event),
            'event' => $event->getName(),
        ];

        return [
            'data' => $data,
            'source' => $source,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function filterPayloadByLiveVersion(array $payload, Webhook $webhook, Hookable $event): array
    {
        if (!$event instanceof HookableEntityWrittenEvent || $webhook->onlyLiveVersion === false) {
            return $payload;
        }

        return array_filter($payload, static function ($writeResult) {
            return isset($writeResult['versionId']) && $writeResult['versionId'] === Defaults::LIVE_VERSION;
        });
    }

    private function isEventDispatchingAllowed(Webhook $webhook, Hookable $event): bool
    {
        if ($webhook->appId === null) {
            return true;
        }

        // Only app lifecycle hooks can be received if app is deactivated
        if ($webhook->appActive === false && !($event instanceof AppChangedEvent || $event instanceof AppDeletedEvent || $event instanceof AppPermissionsUpdated)) {
            return false;
        }

        $privileges = $this->privileges[$event->getName()][$webhook->appAclRoleId] ?? new AclPrivilegeCollection([]);

        return $event->isAllowed($webhook->appId, $privileges);
    }

    /**
     * @param list<string> $affectedRoleIds
     */
    private function loadPrivileges(string $eventName, array $affectedRoleIds): void
    {
        if (\array_key_exists($eventName, $this->privileges)) {
            return;
        }

        $this->privileges[$eventName] = $this->webhookLoader->getPrivilegesForRoles($affectedRoleIds);
    }

    /**
     * @return list<Webhook>
     */
    private function getWebhooks(string $eventName): array
    {
        $this->loadWebhooks();

        return $this->webhooks[$eventName] ?? [];
    }

    private function loadWebhooks(): void
    {
        if ($this->webhooks !== null) {
            return;
        }

        $webhooks = $this->webhookLoader->getWebhooks();
        foreach ($webhooks as $webhook) {
            $this->webhooks[$webhook->eventName][] = $webhook;
        }
    }

    /**
     * @param list<Webhook> $webhooks
     *
     * @return list<Webhook>
     */
    private function filterWebhooksByLiveVersion(array $webhooks, Hookable $event): array
    {
        if (!$event instanceof HookableEntityWrittenEvent) {
            return $webhooks;
        }

        return array_values(array_filter($webhooks, static function (Webhook $webhook) use ($event): bool {
            if (!$webhook->onlyLiveVersion) {
                return true;
            }

            $isVersioned = false;

            foreach ($event->getWebhookPayload() as $writeResult) {
                if (isset($writeResult['versionId']) && $writeResult['versionId'] === Defaults::LIVE_VERSION) {
                    return true;
                }

                if (isset($writeResult['versionId'])) {
                    $isVersioned = true;
                }
            }

            // If the event is not versioned we should send the webhook,
            // only if it is versioned all results are not in the live version we skip it
            return !$isVersioned;
        }));
    }
}
