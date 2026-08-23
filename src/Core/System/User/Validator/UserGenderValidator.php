<?php declare(strict_types=1);

namespace Contena\Core\System\User\Validator;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Contena\Core\Framework\Validation\WriteConstraintViolationException;
use Contena\Core\System\DataDictionary\DataDictionaryDefinition;
use Contena\Core\System\User\UserDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Validates the user-owned gender value against the shared core gender dictionary.
 *
 * @internal
 */
class UserGenderValidator implements EventSubscriberInterface
{
    final public const string VIOLATION_INVALID_GENDER = 'DATA_DICTIONARY__INVALID_USER_GENDER';

    public function __construct(private readonly Connection $connection)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [PreWriteValidationEvent::class => 'preValidate'];
    }

    public function preValidate(PreWriteValidationEvent $event): void
    {
        foreach ($event->getCommands() as $command) {
            if ($command instanceof DeleteCommand || $command->getEntityName() !== UserDefinition::ENTITY_NAME) {
                continue;
            }

            $payload = $command->getPayload();
            if (!\array_key_exists('gender', $payload) || $payload['gender'] === null) {
                continue;
            }

            $gender = $payload['gender'];
            if (\is_string($gender) && $this->genderExists($gender)) {
                continue;
            }

            $message = 'The user gender must be the code of an item in the core.gender data dictionary.';
            $violations = new ConstraintViolationList([
                new ConstraintViolation(
                    $message,
                    $message,
                    [],
                    null,
                    '/gender',
                    $gender,
                    null,
                    self::VIOLATION_INVALID_GENDER
                ),
            ]);

            $event->getExceptions()->add(new WriteConstraintViolationException($violations, $command->getPath()));
        }
    }

    private function genderExists(string $gender): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT 1
             FROM `data_dictionary_item`
             INNER JOIN `data_dictionary`
                ON `data_dictionary`.`id` = `data_dictionary_item`.`dictionary_id`
             WHERE `data_dictionary`.`technical_name` = :technicalName
               AND `data_dictionary_item`.`code` = :gender',
            ['technicalName' => DataDictionaryDefinition::CORE_GENDER, 'gender' => $gender]
        );
    }
}
