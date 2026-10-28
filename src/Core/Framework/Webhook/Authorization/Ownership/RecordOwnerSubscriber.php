<?php declare(strict_types=1);

namespace Contena\Core\Framework\Webhook\Authorization\Ownership;

use Contena\Core\Framework\Api\Acl\ApiCaller;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Webhook\WebhookDefinition;
use Contena\Core\Framework\Webhook\WebhookException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
class RecordOwnerSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'recordOwner',
        ];
    }

    public function recordOwner(PreWriteValidationEvent $event): void
    {
        $inserts = array_filter(
            $event->getCommandsForEntity(WebhookDefinition::ENTITY_NAME),
            static fn (WriteCommand $command): bool => $command instanceof InsertCommand
        );

        if ($inserts === []) {
            return;
        }

        $owner = ApiCaller::of($event->getContext()->getSource());

        foreach ($inserts as $insert) {
            if ($owner !== null) {
                $insert->addPayload(self::ownerColumn($owner), Uuid::fromHexToBytes($owner->id));

                continue;
            }

            $payload = $insert->getPayload();
            if (!isset($payload['app_id']) && !isset($payload['owner_user_id']) && !isset($payload['owner_integration_id'])) {
                $event->getExceptions()->add(WebhookException::webhookOwnerMissing());
            }
        }
    }

    private static function ownerColumn(ApiCaller $owner): string
    {
        return match ($owner->type) {
            ApiCaller::USER => 'owner_user_id',
            ApiCaller::INTEGRATION => 'owner_integration_id',
        };
    }
}
