<?php declare(strict_types=1);

namespace Contena\Core\System\DataDictionary;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Contena\Core\Framework\Validation\WriteConstraintViolationException;
use Contena\Core\System\DataDictionary\Aggregate\DataDictionaryItem\DataDictionaryItemDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
class DataDictionaryWriteValidator implements EventSubscriberInterface
{
    final public const string VIOLATION_SYSTEM_LOCKED = 'DATA_DICTIONARY__SYSTEM_LOCKED';

    private const array ALLOWED_FIELDS = [
        DataDictionaryDefinition::ENTITY_NAME => ['active', 'updated_at'],
        DataDictionaryItemDefinition::ENTITY_NAME => ['active', 'position', 'updated_at'],
    ];

    public function __construct(private readonly Connection $connection)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [PreWriteValidationEvent::class => 'preValidate'];
    }

    public function preValidate(PreWriteValidationEvent $event): void
    {
        if ($event->getContext()->getScope() === Context::SYSTEM_SCOPE) {
            return;
        }

        foreach ($event->getCommands() as $command) {
            $entityName = $command->getEntityName();
            if (!isset(self::ALLOWED_FIELDS[$entityName]) || $command instanceof InsertCommand) {
                continue;
            }

            $id = $command->getPrimaryKey()['id'] ?? null;
            if (!\is_string($id) || !$this->isSystemLocked($entityName, $id)) {
                continue;
            }

            $invalidFields = [];
            if ($command instanceof UpdateCommand) {
                $invalidFields = array_values(array_diff(array_keys($command->getPayload()), self::ALLOWED_FIELDS[$entityName]));
                if ($invalidFields === []) {
                    continue;
                }
            } elseif (!$command instanceof DeleteCommand) {
                continue;
            }

            $message = 'The system-locked {{ entity }} can only change its display state, position or translations.';
            $violations = new ConstraintViolationList([
                new ConstraintViolation(
                    str_replace('{{ entity }}', $entityName, $message),
                    $message,
                    ['{{ entity }}' => $entityName],
                    null,
                    '/',
                    $invalidFields,
                    null,
                    self::VIOLATION_SYSTEM_LOCKED
                ),
            ]);

            $event->getExceptions()->add(new WriteConstraintViolationException($violations, $command->getPath()));
        }
    }

    private function isSystemLocked(string $entityName, string $id): bool
    {
        return (bool) $this->connection->fetchOne(
            \sprintf('SELECT 1 FROM `%s` WHERE `id` = :id AND `system_locked` = 1', $entityName),
            ['id' => $id]
        );
    }
}
