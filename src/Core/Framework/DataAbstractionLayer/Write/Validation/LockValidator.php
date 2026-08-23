<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Write\Validation;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Contena\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
class LockValidator implements EventSubscriberInterface
{
    final public const string VIOLATION_LOCKED = 'FRAMEWORK__ENTITY_IS_LOCKED';

    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly DefinitionInstanceRegistry $definitionRegistry
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'preValidate',
        ];
    }

    /**
     * @throws WriteConstraintViolationException
     */
    public function preValidate(PreWriteValidationEvent $event): void
    {
        $violations = new ConstraintViolationList();
        $writeCommands = $event->getCommands();
        $lockedEntities = $this->containsLockedEntities($writeCommands);

        if ($lockedEntities === []) {
            return;
        }

        $message = 'The %s entity is locked and can neither be modified nor deleted.';

        foreach ($lockedEntities as $entity => $_isLocked) {
            $violations->add(new ConstraintViolation(
                \sprintf($message, $entity),
                \sprintf($message, '{{ entity }}'),
                ['{{ entity }}' => $entity],
                null,
                '/',
                null,
                null,
                self::VIOLATION_LOCKED
            ));
        }

        $event->getExceptions()->add(new WriteConstraintViolationException($violations));
    }

    /**
     * @param WriteCommand[] $writeCommands
     *
     * @return array<string, bool>
     */
    private function containsLockedEntities(array $writeCommands): array
    {
        $ids = [];
        $locked = [];

        foreach ($writeCommands as $command) {
            if ($command instanceof InsertCommand) {
                continue;
            }

            $definition = $this->definitionRegistry->getByEntityName($command->getEntityName());

            if (!$definition->isLockAware()) {
                continue;
            }

            $ids[$command->getEntityName()][] = $command->getPrimaryKey()['id'];
        }

        /** @var string $entityName */
        foreach ($ids as $entityName => $primaryKeys) {
            $locked[$entityName] = $this->connection->createQueryBuilder()
                ->select('1')
                ->from(EntityDefinitionQueryHelper::escape($entityName))
                ->where('`id` IN (:ids) AND `locked` = 1')
                ->setParameter('ids', $primaryKeys, ArrayParameterType::BINARY)
                ->executeQuery()
                ->rowCount() > 0;
        }

        return array_filter($locked);
    }
}
