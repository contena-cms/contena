<?php declare(strict_types=1);

namespace Contena\Core\System\User\Validator;

use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\WriteConstraintViolationException;
use Contena\Core\System\User\Aggregate\UserDataScope\UserDataScopeDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Prevents tenant grants from acquiring platform-wide read authority.
 *
 * @internal
 */
class UserDataScopeWriteValidator implements EventSubscriberInterface
{
    final public const string VIOLATION_CROSS_SCOPE_READ_REQUIRES_PLATFORM = 'USER__CROSS_SCOPE_READ_REQUIRES_PLATFORM';

    public static function getSubscribedEvents(): array
    {
        return [PreWriteValidationEvent::class => 'preValidate'];
    }

    public function preValidate(PreWriteValidationEvent $event): void
    {
        foreach ($event->getCommandsForEntity(UserDataScopeDefinition::ENTITY_NAME) as $command) {
            if ($command instanceof DeleteCommand || !(bool) ($command->getPayload()['read_all_scopes'] ?? false)) {
                continue;
            }

            $dataScopeId = $command->getPayload()['data_scope_id']
                ?? $command->getPrimaryKey()['data_scope_id']
                ?? null;
            if ($dataScopeId === Uuid::fromHexToBytes(Defaults::PLATFORM_DATA_SCOPE)) {
                continue;
            }

            $message = 'Cross-scope read access may only be granted in the canonical platform data scope.';
            $violations = new ConstraintViolationList([
                new ConstraintViolation(
                    $message,
                    $message,
                    [],
                    null,
                    '/readAllScopes',
                    true,
                    null,
                    self::VIOLATION_CROSS_SCOPE_READ_REQUIRES_PLATFORM,
                ),
            ]);

            $event->getExceptions()->add(new WriteConstraintViolationException($violations, $command->getPath()));
        }
    }
}
