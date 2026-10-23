<?php declare(strict_types=1);

namespace Contena\Core\Framework\Webhook\Authorization\Subscription;

use Contena\Core\Framework\Api\Acl\ApiCallerType;
use Contena\Core\Framework\App\Manifest\Manifest;
use Contena\Core\Framework\Log\Package;

/**
 * Identifies who is asking to subscribe a webhook to an event, so a policy can decide
 * whether to permit the subscription.
 *
 * @internal only for use by the app-system
 */
final class Subscriber
{
    private function __construct(
        public readonly SubscriberType $type,
        public readonly ?Manifest $manifest,
    ) {
    }

    /**
     * An app or service subscribing through its manifest.
     */
    public static function app(Manifest $manifest): self
    {
        return new self(SubscriberType::App, $manifest);
    }

    /**
     * A admin_user or integration creating a webhook through the Admin API.
     */
    public static function api(ApiCallerType $caller): self
    {
        return new self(SubscriberType::of($caller), null);
    }

    /**
     * No subscriber at all: asks whether an event is generally available, e.g. for documentation.
     */
    public static function none(): self
    {
        return new self(SubscriberType::None, null);
    }
}
