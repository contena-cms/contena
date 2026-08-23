<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Subscriber;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\WriteConstraintViolationException;
use Contena\Core\System\Member\MemberDefinition;
use Contena\Core\System\Member\Validation\Constraint\MemberEmailUnique;
use Contena\Core\System\Member\Validation\MemberEmailUniqueCheck;
use Contena\Core\System\Member\Validation\MemberEmailUniqueChecker;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 *
 * @codeCoverageIgnore Tested via integration tests.
 *
 * @see \Contena\Tests\Integration\Core\System\Member\Subscriber\MemberEmailUniqueSubscriberTest
 */
class MemberEmailUniqueSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly MemberEmailUniqueChecker $memberEmailUniqueChecker,
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
        $memberCommands = $this->collectMemberCommands($event);
        if ($memberCommands === []) {
            return;
        }

        $relevantMemberIds = $this->getRelevantMemberIds($memberCommands);
        // Avoid loading current member state unless the write can affect email uniqueness.
        if ($relevantMemberIds === []) {
            return;
        }

        $memberStates = $this->resolveMemberStates($memberCommands);
        $validatedMemberStates = \array_intersect_key($memberStates, \array_flip($relevantMemberIds));
        if ($validatedMemberStates === []) {
            return;
        }

        $checks = [];
        foreach ($validatedMemberStates as $memberId => $state) {
            $checks[] = new MemberEmailUniqueCheck(
                email: $state['email'],
                channelId: $state['channelId'],
                memberId: $memberId,
            );
        }

        $conflictingChecks = $this->memberEmailUniqueChecker->findConflictingChecks(...$checks);
        if ($conflictingChecks === []) {
            return;
        }

        $violations = new ConstraintViolationList();

        foreach ($conflictingChecks as $check) {
            \assert($check->memberId !== null);

            $this->addViolation($violations, $memberCommands[$check->memberId]->getPath(), $check->email);
        }

        $event->getExceptions()->add(new WriteConstraintViolationException($violations));
    }

    /**
     * @return array<string, WriteCommand>
     */
    private function collectMemberCommands(PreWriteValidationEvent $event): array
    {
        $commands = [];

        foreach ($event->getCommandsForEntity(MemberDefinition::ENTITY_NAME) as $command) {
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
    private function getRelevantMemberIds(array $commands): array
    {
        $memberIds = [];

        foreach ($commands as $memberId => $command) {
            if ($command instanceof InsertCommand) {
                $memberIds[] = $memberId;

                continue;
            }

            if ($command->hasAnyField('email', 'channel_id')) {
                $memberIds[] = $memberId;
            }
        }

        return $memberIds;
    }

    /**
     * @param array<string, WriteCommand> $commands
     *
     * @return array<string, array{email: string, channelId: string}>
     */
    private function resolveMemberStates(array $commands): array
    {
        $currentStates = $this->fetchCurrentMemberStates($commands);
        $states = [];

        foreach ($commands as $memberId => $command) {
            $payload = $command->getPayload();
            $currentState = $currentStates[$memberId] ?? null;

            $email = $payload['email'] ?? $currentState['email'] ?? null;
            if (!\is_string($email)) {
                continue;
            }

            $channelId = \array_key_exists('channel_id', $payload)
                ? $this->normalizeChannelId($payload['channel_id'])
                : ($currentState['channelId'] ?? null);
            if ($channelId === null) {
                continue;
            }

            $states[$memberId] = [
                'email' => $email,
                'channelId' => $channelId,
            ];
        }

        return $states;
    }

    /**
     * @param array<string, WriteCommand> $commands
     *
     * @return array<string, array{email: string, channelId: string}>
     */
    private function fetchCurrentMemberStates(array $commands): array
    {
        $memberIds = [];
        foreach ($commands as $memberId => $command) {
            if ($command instanceof UpdateCommand) {
                $memberIds[] = $memberId;
            }
        }

        if ($memberIds === []) {
            return [];
        }

        /** @var list<array{id: string, email: string, channel_id: string}> $members */
        $members = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(`id`)) as `id`, `email`, LOWER(HEX(`channel_id`)) as `channel_id`
             FROM `member`
             WHERE `id` IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($memberIds)],
            ['ids' => ArrayParameterType::BINARY],
        );

        $states = [];
        foreach ($members as $member) {
            $states[$member['id']] = [
                'email' => $member['email'],
                'channelId' => $member['channel_id'],
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
            MemberEmailUnique::MEMBER_EMAIL_NOT_UNIQUE,
        ));
    }

    private function normalizeChannelId(mixed $channelId): ?string
    {
        if ($channelId === null) {
            return null;
        }

        if (!\is_string($channelId)) {
            return null;
        }

        if (Uuid::isValid($channelId)) {
            return $channelId;
        }

        return Uuid::fromBytesToHex($channelId);
    }
}
