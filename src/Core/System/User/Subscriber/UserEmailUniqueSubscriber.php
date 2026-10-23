<?php declare(strict_types=1);

namespace Contena\Core\System\User\Subscriber;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Contena\Core\System\User\UserDefinition;
use Contena\Core\System\User\Validation\Constraint\UserEmailUnique;
use Contena\Core\System\User\Validation\UserEmailUniqueCheck;
use Contena\Core\System\User\Validation\UserEmailUniqueChecker;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 *
 * @codeCoverageIgnore Tested via integration tests.
 *
 * @see \Contena\Tests\Integration\Core\System\User\Subscriber\UserEmailUniqueSubscriberTest
 */
class UserEmailUniqueSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly UserEmailUniqueChecker $userEmailUniqueChecker,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'validate',
        ];
    }

    public function validate(PreWriteValidationEvent $event): void
    {
        $userCommands = $this->collectUserCommands($event);
        if ($userCommands === []) {
            return;
        }

        $relevantUserIds = $this->getRelevantUserIds($userCommands);
        // Avoid loading current user state unless the write can affect email uniqueness.
        if ($relevantUserIds === []) {
            return;
        }

        $userStates = $this->resolveUserStates($userCommands);
        $validatedUserStates = \array_intersect_key($userStates, \array_flip($relevantUserIds));
        if ($validatedUserStates === []) {
            return;
        }

        $checks = [];
        foreach ($validatedUserStates as $userId => $state) {
            $checks[] = new UserEmailUniqueCheck(
                email: $state['email'],
                userId: $userId,
                boundChannelId: $state['boundChannelId'],
                guest: $state['guest'],
            );
        }

        $conflictingChecks = $this->userEmailUniqueChecker->findConflictingChecks(...$checks);
        if ($conflictingChecks === []) {
            return;
        }

        $violations = new ConstraintViolationList();

        foreach ($conflictingChecks as $check) {
            \assert($check->userId !== null);

            $this->addViolation($violations, $userCommands[$check->userId]->getPath(), $check->email);
        }

        $event->getExceptions()->add(new WriteConstraintViolationException($violations));
    }

    /**
     * @return array<string, WriteCommand>
     */
    private function collectUserCommands(PreWriteValidationEvent $event): array
    {
        $commands = [];

        foreach ($event->getCommandsForEntity(UserDefinition::ENTITY_NAME) as $command) {
            if (!$command instanceof InsertCommand && !$command instanceof UpdateCommand) {
                continue;
            }

            $commands[$command->getDecodedPrimaryKey()['id']] = $command;
        }

        return $commands;
    }

    /**
     * @param array<string, WriteCommand> $commands
     *
     * @return list<string>
     */
    private function getRelevantUserIds(array $commands): array
    {
        $userIds = [];

        foreach ($commands as $userId => $command) {
            if ($command instanceof InsertCommand) {
                $userIds[] = $userId;

                continue;
            }

            if ($command->hasAnyField('email', 'guest', 'bound_channel_id')) {
                $userIds[] = $userId;
            }
        }

        return $userIds;
    }

    /**
     * @param array<string, WriteCommand> $commands
     *
     * @return array<string, array{email: string, guest: bool, boundChannelId: string|null}>
     */
    private function resolveUserStates(array $commands): array
    {
        $currentStates = $this->fetchCurrentUserStates($commands);
        $states = [];

        foreach ($commands as $userId => $command) {
            $payload = $command->getPayload();
            $currentState = $currentStates[$userId] ?? null;

            $email = $payload['email'] ?? $currentState['email'] ?? null;
            if (!\is_string($email)) {
                continue;
            }

            $states[$userId] = [
                'email' => $email,
                'guest' => \array_key_exists('guest', $payload) ? (bool) $payload['guest'] : ($currentState['guest'] ?? false),
                'boundChannelId' => \array_key_exists('bound_channel_id', $payload)
                    ? $this->normalizeBoundChannelId($payload['bound_channel_id'])
                    : ($currentState['boundChannelId'] ?? null),
            ];
        }

        return $states;
    }

    /**
     * @param array<string, WriteCommand> $commands
     *
     * @return array<string, array{email: string, guest: bool, boundChannelId: string|null}>
     */
    private function fetchCurrentUserStates(array $commands): array
    {
        $userIds = [];
        foreach ($commands as $userId => $command) {
            if ($command instanceof UpdateCommand) {
                $userIds[] = $userId;
            }
        }

        if ($userIds === []) {
            return [];
        }

        /** @var list<array{id: string, email: string, guest: int, bound_channel_id: string|null}> $users */
        $users = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(`id`)) as `id`, `email`, `guest`, LOWER(HEX(`bound_channel_id`)) as `bound_channel_id`
             FROM `user`
             WHERE `id` IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($userIds)],
            ['ids' => ArrayParameterType::BINARY],
        );

        $states = [];
        foreach ($users as $user) {
            $states[$user['id']] = [
                'email' => $user['email'],
                'guest' => (bool) $user['guest'],
                'boundChannelId' => $user['bound_channel_id'],
            ];
        }

        return $states;
    }

    private function addViolation(ConstraintViolationList $violations, string $path, string $email): void
    {
        $message = 'The email address {{ email }} is already in use.';

        $violations->add(new ConstraintViolation(
            str_replace('{{ email }}', $email, $message),
            $message,
            ['{{ email }}' => $email],
            null,
            $path . '/email',
            $email,
            null,
            UserEmailUnique::CUSTOMER_EMAIL_NOT_UNIQUE,
        ));
    }

    private function normalizeBoundChannelId(mixed $boundChannelId): ?string
    {
        if ($boundChannelId === null) {
            return null;
        }

        if (!\is_string($boundChannelId)) {
            return null;
        }

        if (Uuid::isValid($boundChannelId)) {
            return $boundChannelId;
        }

        return Uuid::fromBytesToHex($boundChannelId);
    }
}
