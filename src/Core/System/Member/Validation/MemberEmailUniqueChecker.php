<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Validation;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * @final
 *
 * @codeCoverageIgnore Tested via integration tests.
 *
 * @see \Contena\Tests\Integration\Core\System\Member\Subscriber\MemberEmailUniqueSubscriberTest
 */
class MemberEmailUniqueChecker
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function isUnique(MemberEmailUniqueCheck $check): bool
    {
        return $this->findConflictingMemberId($check) === null;
    }

    public function findConflictingMemberId(MemberEmailUniqueCheck $check): ?string
    {
        foreach ($this->fetchExistingMembers([$check->email]) as $member) {
            if ($member['id'] === $check->memberId) {
                continue;
            }

            if (!$this->isSameEmail($check->email, $member['email'])) {
                continue;
            }

            if ($check->channelId !== $member['channelId']) {
                continue;
            }

            return $member['id'];
        }

        return null;
    }

    /**
     * Returns the submitted checks that conflict with another submitted check
     * or with an existing member in the same channel.
     *
     * @return list<MemberEmailUniqueCheck>
     */
    public function findConflictingChecks(MemberEmailUniqueCheck ...$checks): array
    {
        $checks = array_values($checks);

        if ($checks === []) {
            return [];
        }

        $conflictingChecks = [];
        $conflictingIndexes = [];
        $candidateMemberIds = [];
        foreach ($checks as $check) {
            if ($check->memberId !== null) {
                $candidateMemberIds[$check->memberId] = true;
            }
        }

        foreach ($checks as $index => $check) {
            foreach ($checks as $comparedIndex => $comparedCheck) {
                if ($index === $comparedIndex || $this->isSameMember($check, $comparedCheck)) {
                    continue;
                }

                if (!$this->isSameEmail($check->email, $comparedCheck->email)) {
                    continue;
                }

                if ($check->channelId !== $comparedCheck->channelId) {
                    continue;
                }

                $this->addConflictingCheck($conflictingChecks, $conflictingIndexes, $index, $check);

                break;
            }
        }

        $existingMembers = $this->fetchExistingMembers(\array_values(\array_unique(\array_map(
            static fn (MemberEmailUniqueCheck $check): string => $check->email,
            $checks,
        ))));

        foreach ($checks as $index => $check) {
            if (isset($conflictingIndexes[$index])) {
                continue;
            }

            foreach ($existingMembers as $member) {
                // Submitted members are checked in memory using their final state.
                if (isset($candidateMemberIds[$member['id']])) {
                    continue;
                }

                if (!$this->isSameEmail($check->email, $member['email'])) {
                    continue;
                }

                if ($check->channelId !== $member['channelId']) {
                    continue;
                }

                $this->addConflictingCheck($conflictingChecks, $conflictingIndexes, $index, $check);

                break;
            }
        }

        return $conflictingChecks;
    }

    private function isSameEmail(string $email, string $comparedEmail): bool
    {
        return hash_equals(mb_strtolower($email), mb_strtolower($comparedEmail));
    }

    private function isSameMember(MemberEmailUniqueCheck $check, MemberEmailUniqueCheck $comparedCheck): bool
    {
        return $check->memberId !== null
            && $comparedCheck->memberId !== null
            && $check->memberId === $comparedCheck->memberId;
    }

    /**
     * @param list<MemberEmailUniqueCheck> $conflictingChecks
     * @param array<int, true> $conflictingIndexes
     */
    private function addConflictingCheck(array &$conflictingChecks, array &$conflictingIndexes, int $index, MemberEmailUniqueCheck $check): void
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
     * @return list<array{id: string, email: string, channelId: string}>
     */
    private function fetchExistingMembers(array $emails): array
    {
        if ($emails === []) {
            return [];
        }

        /** @var list<array{id: string, email: string, channel_id: string}> $members */
        $members = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(`id`)) as `id`, `email`, LOWER(HEX(`channel_id`)) as `channel_id`
             FROM `member`
             WHERE `email` IN (:emails)',
            ['emails' => $emails],
            ['emails' => ArrayParameterType::STRING],
        );

        return array_map(static fn (array $member): array => [
            'id' => $member['id'],
            'email' => $member['email'],
            'channelId' => $member['channel_id'],
        ], $members);
    }
}
