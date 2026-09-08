<?php declare(strict_types=1);

namespace Contena\Core\Framework\Webhook\Validation;

use Contena\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Contena\Core\Framework\Validation\WriteConstraintViolationException;
use Contena\Core\Framework\Webhook\WebhookDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
final readonly class WebhookUrlWriteValidator implements EventSubscriberInterface
{
    public function __construct(private WebhookTargetValidator $targetValidator)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'preValidate',
        ];
    }

    public function preValidate(PreWriteValidationEvent $event): void
    {
        foreach ($event->getCommandsForEntity(WebhookDefinition::ENTITY_NAME) as $command) {
            if (!$command instanceof InsertCommand && !$command instanceof UpdateCommand) {
                continue;
            }

            $payload = $command->getPayload();
            if (!\array_key_exists('url', $payload)) {
                continue;
            }

            $url = $payload['url'];
            if (!\is_string($url) || $this->targetValidator->validate($url) === null) {
                $event->getExceptions()->add(new WriteConstraintViolationException(
                    new ConstraintViolationList([
                        new ConstraintViolation(
                            'The webhook URL is not allowed by the configured webhook network policy.',
                            'The webhook URL is not allowed by the configured webhook network policy.',
                            [],
                            null,
                            '/url',
                            $url,
                        ),
                    ]),
                    $command->getPath()
                ));
            }
        }
    }
}
