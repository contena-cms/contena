<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\Acl;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Api\Acl\Event\CommandAclValidationEvent;
use Contena\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Contena\Core\Framework\Api\Acl\Role\AclUserRoleDefinition;
use Contena\Core\Framework\Api\ApiException;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Integration\Aggregate\IntegrationRole\IntegrationRoleDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
class AclWriteValidator implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly DefinitionInstanceRegistry $definitionRegistry,
        private readonly Connection $connection,
    ) {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [PreWriteValidationEvent::class => 'preValidate'];
    }

    public function preValidate(PreWriteValidationEvent $event): void
    {
        $context = $event->getContext();
        $source = $event->getContext()->getSource();
        if (!$source instanceof AdminApiSource || $source->isAdmin()) {
            return;
        }

        $commands = $event->getCommands();
        $this->validatePrivilegeAssignments($commands, $source);

        if ($context->getScope() === Context::SYSTEM_SCOPE) {
            return;
        }

        $missingPrivileges = [];

        foreach ($commands as $command) {
            $resource = $command->getEntityName();
            $privilege = $command->getPrivilege();

            if ($privilege === null) {
                continue;
            }

            $definition = $this->definitionRegistry->getByEntityName($command->getEntityName());

            if (is_subclass_of($definition, EntityTranslationDefinition::class)) {
                $resource = $definition->getParentDefinition()->getEntityName();

                if ($privilege !== AclRoleDefinition::PRIVILEGE_DELETE) {
                    $privilege = $this->getPrivilegeForParentWriteOperation($command, $commands);
                }
            }

            if (!$source->isAllowed($resource . ':' . $privilege)) {
                $missingPrivileges[] = $resource . ':' . $privilege;
            }

            $event = new CommandAclValidationEvent($missingPrivileges, $source, $command);
            $this->eventDispatcher->dispatch($event);
            $missingPrivileges = $event->getMissingPrivileges();
        }

        $this->tryToThrow($missingPrivileges);
    }

    /**
     * A non-admin may only create roles and assign roles whose effective
     * privileges are a subset of their own. This validation also runs for the
     * controller-managed system-scope writes used by users and integrations.
     *
     * @param list<WriteCommand> $commands
     */
    private function validatePrivilegeAssignments(array $commands, AdminApiSource $source): void
    {
        $writtenRoles = [];
        $missingPrivileges = [];

        foreach ($commands as $command) {
            if ($command->getEntityName() !== AclRoleDefinition::ENTITY_NAME || !$command->hasField('privileges')) {
                continue;
            }

            $roleId = $command->getPrimaryKey()['id'] ?? null;
            $privileges = $this->decodePrivileges($command->getPayload()['privileges']);

            if (\is_string($roleId)) {
                $writtenRoles[$roleId] = $privileges;
            }

            $missingPrivileges = [...$missingPrivileges, ...$this->getMissingPrivileges($privileges, $source)];
        }

        foreach ($commands as $command) {
            if (!$command instanceof InsertCommand || !\in_array($command->getEntityName(), [
                AclUserRoleDefinition::ENTITY_NAME,
                IntegrationRoleDefinition::ENTITY_NAME,
            ], true)) {
                continue;
            }

            $roleId = $command->getPrimaryKey()['acl_role_id'] ?? null;
            if (!\is_string($roleId)) {
                continue;
            }

            $privileges = $writtenRoles[$roleId] ?? $this->fetchRolePrivileges($roleId);
            $missingPrivileges = [...$missingPrivileges, ...$this->getMissingPrivileges($privileges, $source)];
        }

        $this->tryToThrow(array_values(array_unique($missingPrivileges)));
    }

    /**
     * @return list<string>
     */
    private function fetchRolePrivileges(string $roleId): array
    {
        $privileges = $this->connection->fetchOne(
            'SELECT `privileges` FROM `acl_role` WHERE `id` = :id',
            ['id' => $roleId],
        );

        if (!\is_string($privileges)) {
            return [];
        }

        return $this->decodePrivileges($privileges);
    }

    /**
     * @return list<string>
     */
    private function decodePrivileges(mixed $privileges): array
    {
        if (!\is_string($privileges)) {
            return [];
        }

        $decoded = json_decode($privileges, true, 512, \JSON_THROW_ON_ERROR);
        if (!\is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, \is_string(...)));
    }

    /**
     * @param list<string> $privileges
     *
     * @return list<string>
     */
    private function getMissingPrivileges(array $privileges, AdminApiSource $source): array
    {
        return array_values(array_filter(
            $privileges,
            static fn (string $privilege): bool => !$source->isAllowed($privilege),
        ));
    }

    /**
     * @param list<string> $missingPrivileges
     */
    private function tryToThrow(array $missingPrivileges): void
    {
        if ($missingPrivileges !== []) {
            throw ApiException::missingPrivileges($missingPrivileges);
        }
    }

    /**
     * @param WriteCommand[] $commands
     */
    private function getPrivilegeForParentWriteOperation(WriteCommand $command, array $commands): string
    {
        $pathSuffix = '/translations/' . Uuid::fromBytesToHex($command->getPrimaryKey()['language_id']);
        $parentCommandPath = str_replace($pathSuffix, '', $command->getPath());
        $parentCommand = $this->findCommandByPath($parentCommandPath, $commands);

        // writes to translation need privilege from parent command
        // if we update e.g. a product and add translations for a new language
        // the writeCommand on the translation would be an insert
        if ($parentCommand) {
            return (string) $parentCommand->getPrivilege();
        }

        // if we don't have a parentCommand it must be a update,
        // because the parentEntity must already exist
        return AclRoleDefinition::PRIVILEGE_UPDATE;
    }

    /**
     * @param WriteCommand[] $commands
     */
    private function findCommandByPath(string $commandPath, array $commands): ?WriteCommand
    {
        foreach ($commands as $command) {
            if ($command->getPath() === $commandPath) {
                return $command;
            }
        }

        return null;
    }
}
