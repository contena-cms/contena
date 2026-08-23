<?php declare(strict_types=1);

namespace Contena\Core\System\Locale\Subscriber;

use Contena\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Contena\Core\System\Locale\Exception\InvalidLocaleCodeException;
use Contena\Core\System\Locale\LocaleDefinition;
use Contena\Core\System\Locale\Util\LocaleHelper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class LocaleValidator implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'preWriteValidateEvent',
        ];
    }

    public function preWriteValidateEvent(PreWriteValidationEvent $event): void
    {
        foreach ($event->getCommands() as $command) {
            if ($command instanceof DeleteCommand || $command->getEntityName() !== LocaleDefinition::ENTITY_NAME) {
                continue;
            }

            $code = $command->getPayload()['code'] ?? null;

            if (!\is_string($code)) {
                continue;
            }

            if (LocaleHelper::isLocale($code)) {
                continue;
            }

            $event->getExceptions()->add(new InvalidLocaleCodeException($code));
        }
    }
}
