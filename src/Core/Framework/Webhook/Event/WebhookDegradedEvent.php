<?php declare(strict_types=1);

namespace Contena\Core\Framework\Webhook\Event;

use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\Event\EventData\EventDataCollection;
use Contena\Core\Framework\Event\EventData\ScalarValueType;
use Contena\Core\Framework\Event\FlowEventAware;
use Contena\Core\Framework\Webhook\Health\EndpointState;
use Contena\Core\Framework\Webhook\Hookable;

/**
 * @internal
 */
final readonly class WebhookDegradedEvent implements Hookable, FlowEventAware
{
    use WebhookHealthEventBehaviour;

    public const NAME = 'webhook.health.degraded';

    public function __construct(
        public string $webhookId,
        public ?string $appId,
        public EndpointState $fromState,
        public ?string $webhookName,
        public ?string $eventName,
        public \DateTimeImmutable $occurredAt,
    ) {
    }

    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * @return array<string, mixed>
     */
    public function getWebhookPayload(?AppEntity $app = null): array
    {
        return [
            'webhookId' => $this->webhookId,
            'fromState' => $this->fromState->value,
            'webhookName' => $this->webhookName,
            'eventName' => $this->eventName,
            'occurredAt' => $this->getOccurredAt(),
        ];
    }

    public static function getAvailableData(): EventDataCollection
    {
        return new EventDataCollection()
            ->add('webhookId', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('fromState', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('webhookName', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('eventName', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('occurredAt', new ScalarValueType(ScalarValueType::TYPE_STRING));
    }
}
