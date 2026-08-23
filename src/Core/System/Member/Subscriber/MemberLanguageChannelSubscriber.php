<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Subscriber;

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
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\WriteConstraintViolationException;
use Contena\Core\System\Member\MemberDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
class MemberLanguageChannelSubscriber implements EventSubscriberInterface
{
    final public const VIOLATION_LANGUAGE_NOT_IN_CHANNEL = 'member_language_not_in_channel';

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
            $channelId = $this->findChannelIdForMember($candidate, $channels);
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
     * @return list<array{memberId: string|null, languageId: string, channelId: string|null}>
     */
    private function collectCandidatesCommands(PreWriteValidationEvent $event): array
    {
        $candidates = [];

        foreach ($event->getCommandsForEntity(MemberDefinition::ENTITY_NAME) as $command) {
            if (!$command instanceof InsertCommand && !$command instanceof UpdateCommand) {
                continue;
            }

            $payload = $command->getPayload();
            if (!isset($payload['language_id'])) {
                continue;
            }

            $pk = $command->getPrimaryKey();

            $candidates[] = [
                'memberId' => $command instanceof UpdateCommand && isset($pk['id']) ? Uuid::fromBytesToHex($pk['id']) : null,
                'languageId' => Uuid::fromBytesToHex($payload['language_id']),
                'channelId' => isset($payload['channel_id']) ? Uuid::fromBytesToHex($payload['channel_id']) : null,
            ];
        }

        return $candidates;
    }

    /**
     * @param array{memberId: string|null, languageId: string, channelId: string|null} $candidate
     * @param EntityCollection<PartialEntity> $channels
     */
    private function findChannelIdForMember(array $candidate, EntityCollection $channels): ?string
    {
        if ($candidate['channelId'] !== null) {
            return $candidate['channelId'];
        }

        $memberId = $candidate['memberId'];
        if ($memberId === null) {
            return null;
        }

        foreach ($channels as $channel) {
            /** @var EntityCollection<PartialEntity>|null $members */
            $members = $channel->get('members');
            if ($members?->has($memberId)) {
                return $channel->getId();
            }
        }

        return null;
    }

    /**
     * @param list<array{memberId: string|null, languageId: string, channelId: string|null}> $candidates
     *
     * @return EntityCollection<PartialEntity>
     */
    private function fetchChannels(array $candidates, Context $context): EntityCollection
    {
        $memberIds = \array_filter(\array_column($candidates, 'memberId'));
        $channelIds = \array_filter(\array_column($candidates, 'channelId'));

        if ($memberIds === [] && $channelIds === []) {
            return new EntityCollection();
        }

        $criteria = new Criteria()->addFields(['id', 'languages.id'])
            ->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
                new EqualsAnyFilter('id', $channelIds),
                new EqualsAnyFilter('members.id', $memberIds),
            ]));

        $criteria->getAssociation('languages')
            ->addFilter(new EqualsAnyFilter('id', \array_column($candidates, 'languageId')));

        if ($memberIds !== []) {
            $criteria->addFields(['members.id']);
            $criteria->getAssociation('members')
                ->addFilter(new EqualsAnyFilter('id', $memberIds));
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
            \sprintf('The language "%s" is not assigned to the channel.', $languageId),
            'The language "{{ languageId }}" is not assigned to the channel.',
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
