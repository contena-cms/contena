<?php declare(strict_types=1);

namespace Contena\Core\Framework\Webhook\Authorization;

use Contena\Core\Framework\DataAbstractionLayer\Write\Command\CascadeDeleteCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Contena\Core\Framework\Webhook\WebhookDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class RestrictWriteSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly WriteAuthorizer $authorizer)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'validate',
        ];
    }

    public function validate(PreWriteValidationEvent $event): void
    {
        $commands = array_values(array_filter(
            $event->getCommandsForEntity(WebhookDefinition::ENTITY_NAME),
            static fn (WriteCommand $command): bool => !$command instanceof CascadeDeleteCommand
        ));

        foreach ($this->authorizer->getViolations($commands, $event->getContext()) as $violation) {
            $event->getExceptions()->add($violation);
        }
    }
}
