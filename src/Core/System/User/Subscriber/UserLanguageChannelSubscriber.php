<?php declare(strict_types=1);

namespace Contena\Core\System\User\Subscriber;

use Contena\Core\System\User\UserDefinition;
use Contena\Core\Framework\Api\Context\ChannelApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\PartialEntity;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
class UserLanguageChannelSubscriber implements EventSubscriberInterface
{
    final public const VIOLATION_LANGUAGE_NOT_IN_CHANNEL = 'user_language_not_in_channel';

    /**
     * @param EntityRepository<EntityCollection<PartialEntity>> $channelRepository
     *
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $channelRepository,
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
        $context = $event->getContext();

        // Skip validation for Channel API requests to avoids unnecessary performance overhead
        if ($context->getSource() instanceof ChannelApiSource) {
            return;
        }

        $candidates = $this->collectCandidatesCommands($event);
        if ($candidates === []) {
            return;
        }

        $channels = $this->fetchChannels($candidates, $context);

        foreach ($candidates as $candidate) {
            $channelId = $this->findChannelIdForUser($candidate, $channels);
            if ($channelId === null) {
                continue;
            }

            if ($this->isLanguageInChannel($channelId, $candidate['languageId'], $channels)) {
                continue;
            }

            $event->getExceptions()->add(
                $this->createLanguageNotInChannelViolation($candidate['languageId'])
            );
        }
    }

    /**
     * @return list<array{userId: string|null, languageId: string, channelId: string|null}>
     */
    private function collectCandidatesCommands(PreWriteValidationEvent $event): array
    {
        $candidates = [];

        foreach ($event->getCommandsForEntity(UserDefinition::ENTITY_NAME) as $command) {
            if (!$command instanceof InsertCommand && !$command instanceof UpdateCommand) {
                continue;
            }

            $payload = $command->getPayload();
            if (!isset($payload['language_id'])) {
                continue;
            }

            $pk = $command->getPrimaryKey();

            $candidates[] = [
                'userId' => $command instanceof UpdateCommand && isset($pk['id']) ? Uuid::fromBytesToHex($pk['id']) : null,
                'languageId' => Uuid::fromBytesToHex($payload['language_id']),
                'channelId' => isset($payload['channel_id']) ? Uuid::fromBytesToHex($payload['channel_id']) : null,
            ];
        }

        return $candidates;
    }

    /**
     * @param array{userId: string|null, languageId: string, channelId: string|null} $candidate
     * @param EntityCollection<PartialEntity> $channels
     */
    private function findChannelIdForUser(array $candidate, EntityCollection $channels): ?string
    {
        if ($candidate['channelId'] !== null) {
            return $candidate['channelId'];
        }

        $userId = $candidate['userId'];
        if ($userId === null) {
            return null;
        }

        foreach ($channels as $channel) {
            /** @var EntityCollection<PartialEntity>|null $users */
            $users = $channel->get('users');
            if ($users?->has($userId)) {
                return $channel->getId();
            }
        }

        return null;
    }

    /**
     * @param list<array{userId: string|null, languageId: string, channelId: string|null}> $candidates
     *
     * @return EntityCollection<PartialEntity>
     */
    private function fetchChannels(array $candidates, Context $context): EntityCollection
    {
        $userIds = \array_filter(\array_column($candidates, 'userId'));
        $channelIds = \array_filter(\array_column($candidates, 'channelId'));

        if ($userIds === [] && $channelIds === []) {
            return new EntityCollection();
        }

        $criteria = (new Criteria())->addFields(['id', 'languages.id'])
            ->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
                new EqualsAnyFilter('id', $channelIds),
                new EqualsAnyFilter('users.id', $userIds),
            ]));

        $criteria->getAssociation('languages')
            ->addFilter(new EqualsAnyFilter('id', \array_column($candidates, 'languageId')));

        if ($userIds !== []) {
            $criteria->addFields(['users.id']);
            $criteria->getAssociation('users')
                ->addFilter(new EqualsAnyFilter('id', $userIds));
        }

        return $this->channelRepository->search($criteria, $context)->getEntities();
    }

    /**
     * @param EntityCollection<PartialEntity> $channels
     */
    private function isLanguageInChannel(string $channelId, string $languageId, EntityCollection $channels): bool
    {
        $channel = $channels->get($channelId);

        /** @var EntityCollection<PartialEntity>|null $languages */
        $languages = $channel?->get('languages');

        return $languages?->has($languageId) ?? false;
    }

    private function createLanguageNotInChannelViolation(string $languageId): WriteConstraintViolationException
    {
        $violations = new ConstraintViolationList();
        $violations->add(new ConstraintViolation(
            \sprintf('The language "%s" is not assigned to the sales channel.', $languageId),
            'The language "{{ languageId }}" is not assigned to the sales channel.',
            ['{{ languageId }}' => $languageId],
            '',
            '/languageId',
            $languageId,
            null,
            self::VIOLATION_LANGUAGE_NOT_IN_CHANNEL
        ));

        return new WriteConstraintViolationException($violations);
    }
}
