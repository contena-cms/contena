<?php declare(strict_types=1);

namespace Contena\Core\Framework\Webhook\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Webhook\AclPrivilegeCollection;

/**
 * @internal
 */
trait WebhookHealthEventBehaviour
{
    public function isAllowed(string $appId, AclPrivilegeCollection $permissions): bool
    {
        return $this->appId !== null && $appId === $this->appId;
    }

    public function getContext(): Context
    {
        // Health transitions have no request context.
        return Context::createDefaultContext();
    }

    public function getWebhookId(): string
    {
        return $this->webhookId;
    }

    public function getFromState(): string
    {
        return $this->fromState->value;
    }

    public function getWebhookName(): ?string
    {
        return $this->webhookName;
    }

    public function getEventName(): ?string
    {
        return $this->eventName;
    }

    public function getOccurredAt(): string
    {
        return $this->occurredAt->format(\DateTimeInterface::ATOM);
    }
}
