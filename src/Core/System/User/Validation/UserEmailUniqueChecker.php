<?php declare(strict_types=1);

namespace Contena\Core\System\User\Validation;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SystemConfig\SystemConfigService;

/**
 * @final
 *
 * @codeCoverageIgnore Tested via integration tests.
 *
 * @see \Contena\Tests\Integration\Core\System\User\Subscriber\UserEmailUniqueSubscriberTest
 */
class UserEmailUniqueChecker
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    public function isUnique(UserEmailUniqueCheck $check): bool
    {
        return $this->findConflictingUserId($check) === null;
    }

    public function findConflictingUserId(UserEmailUniqueCheck $check): ?string
    {
        if ($check->guest) {
            return null;
        }

        $isUserBoundToChannel = $this->isUserBoundToChannel();

        foreach ($this->fetchExistingUsers([$check->email]) as $user) {
            if ($user['id'] === $check->userId) {
                continue;
            }

            if (!$this->isSameEmail($check->email, $user['email'])) {
                continue;
            }

            if (!$this->isInSameEmailUniquenessScope($check->boundChannelId, $user['boundChannelId'], $isUserBoundToChannel)) {
                continue;
            }

            return $user['id'];
        }

        return null;
    }

    /**
     * Returns the submitted checks that conflict with another submitted check
     * or with an existing non-guest user.
     *
     * @return list<UserEmailUniqueCheck>
     */
    public function findConflictingChecks(UserEmailUniqueCheck ...$checks): array
    {
        $checks = \array_values(\array_filter($checks, static fn (UserEmailUniqueCheck $check): bool => !$check->guest));
        if ($checks === []) {
            return [];
        }

        $conflictingChecks = [];
        $conflictingIndexes = [];
        $candidateUserIds = [];
        $isUserBoundToChannel = $this->isUserBoundToChannel();

        foreach ($checks as $check) {
            if ($check->userId !== null) {
                $candidateUserIds[$check->userId] = true;
            }
        }

        foreach ($checks as $index => $check) {
            foreach ($checks as $comparedIndex => $comparedCheck) {
                if ($index === $comparedIndex || $this->isSameUser($check, $comparedCheck)) {
                    continue;
                }

                if (!$this->isSameEmail($check->email, $comparedCheck->email)) {
                    continue;
                }

                if (!$this->isInSameEmailUniquenessScope($check->boundChannelId, $comparedCheck->boundChannelId, $isUserBoundToChannel)) {
                    continue;
                }

                $this->addConflictingCheck($conflictingChecks, $conflictingIndexes, $index, $check);

                break;
            }
        }

        $existingUsers = $this->fetchExistingUsers(\array_values(\array_unique(\array_map(
            static fn (UserEmailUniqueCheck $check): string => $check->email,
            $checks,
        ))));

        foreach ($checks as $index => $check) {
            if (isset($conflictingIndexes[$index])) {
                continue;
            }

            foreach ($existingUsers as $user) {
                // Submitted users are checked in memory using their final state.
                if (isset($candidateUserIds[$user['id']])) {
                    continue;
                }

                if (!$this->isSameEmail($check->email, $user['email'])) {
                    continue;
                }

                if (!$this->isInSameEmailUniquenessScope($check->boundChannelId, $user['boundChannelId'], $isUserBoundToChannel)) {
                    continue;
                }

                $this->addConflictingCheck($conflictingChecks, $conflictingIndexes, $index, $check);

                break;
            }
        }

        return $conflictingChecks;
    }

    private function isUserBoundToChannel(): bool
    {
        return (bool) $this->systemConfigService->get('core.systemWideLoginRegistration.isUserBoundToChannel');
    }

    private function isInSameEmailUniquenessScope(?string $boundChannelId, ?string $existingBoundChannelId, bool $isUserBoundToChannel): bool
    {
        if (!$isUserBoundToChannel) {
            return true;
        }

        if ($boundChannelId === null || $existingBoundChannelId === null) {
            return true;
        }

        return $boundChannelId === $existingBoundChannelId;
    }

    private function isSameEmail(string $email, string $comparedEmail): bool
    {
        return hash_equals(mb_strtolower($email), mb_strtolower($comparedEmail));
    }

    private function isSameUser(UserEmailUniqueCheck $check, UserEmailUniqueCheck $comparedCheck): bool
    {
        return $check->userId !== null
            && $comparedCheck->userId !== null
            && $check->userId === $comparedCheck->userId;
    }

    /**
     * @param list<UserEmailUniqueCheck> $conflictingChecks
     * @param array<int, true> $conflictingIndexes
     */
    private function addConflictingCheck(array &$conflictingChecks, array &$conflictingIndexes, int $index, UserEmailUniqueCheck $check): void
    {
        if (isset($conflictingIndexes[$index])) {
            return;
        }

        $conflictingChecks[] = $check;
        $conflictingIndexes[$index] = true;
    }

    /**
     * @param list<string> $emails
     *
     * @return list<array{id: string, email: string, boundChannelId: string|null}>
     */
    private function fetchExistingUsers(array $emails): array
    {
        if ($emails === []) {
            return [];
        }

        /** @var list<array{id: string, email: string, bound_channel_id: string|null}> $users */
        $users = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(`id`)) as `id`, `email`, LOWER(HEX(`bound_channel_id`)) as `bound_channel_id`
             FROM `user`
             WHERE `email` IN (:emails)
             AND `guest` = 0',
            ['emails' => $emails],
            ['emails' => ArrayParameterType::STRING],
        );

        return array_map(static fn (array $user): array => [
            'id' => $user['id'],
            'email' => $user['email'],
            'boundChannelId' => $user['bound_channel_id'],
        ], $users);
    }
}
