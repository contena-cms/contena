<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Subscriber;

use Contena\Core\Framework\Api\Context\ChannelApiSource;
use Contena\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\Util\Random;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Channel\Context\ChannelContextServiceInterface;
use Contena\Core\System\Channel\Context\ChannelContextServiceParameters;
use Contena\Core\System\Member\Event\MemberDeletedEvent;
use Contena\Core\System\Member\MemberCollection;
use Contena\Core\System\Member\MemberDefinition;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class MemberBeforeDeleteSubscriber implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<MemberCollection> $memberRepository
     * @param EntityRepository<ChannelCollection> $channelRepository
     *
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $memberRepository,
        private readonly EntityRepository $channelRepository,
        private readonly ChannelContextServiceInterface $channelContextService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly JsonEntityEncoder $jsonEntityEncoder
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            EntityDeleteEvent::class => 'beforeDelete',
        ];
    }

    public function beforeDelete(EntityDeleteEvent $event): void
    {
        $context = $event->getContext();

        $ids = $event->getIds(MemberDefinition::ENTITY_NAME);

        if ($ids === []) {
            return;
        }

        $source = $context->getSource();
        $channelId = null;

        if ($source instanceof ChannelApiSource) {
            $channelId = $source->getChannelId();
        }

        $criteria = new Criteria($ids)
            ->addAssociations([
                'addresses.country',
                'addresses.region',
            ]);

        $members = $this->memberRepository->search($criteria, $context)->getEntities();

        $channelLanguages = $this->loadChannelLanguages($members, $channelId, $context);

        $event->addSuccess(function () use ($members, $context, $channelId, $criteria, $channelLanguages): void {
            foreach ($members as $member) {
                $languageId = $member->getLanguageId();

                $effectiveChannelId = $channelId ?? $member->getChannelId();

                $effectiveLanguageId = $channelLanguages
                    ->get($effectiveChannelId)
                    ?->getLanguages()
                    ?->has($languageId)
                        ? $languageId
                        : null;

                $channelContext = $this->channelContextService->get(
                    new ChannelContextServiceParameters(
                        channelId: $effectiveChannelId,
                        token: Random::getAlphanumericString(32),
                        languageId: $effectiveLanguageId,
                        originalContext: $context,
                    )
                );

                $this->eventDispatcher->dispatch(new MemberDeletedEvent(
                    $channelContext,
                    $member,
                    $this->jsonEntityEncoder->encode(
                        $criteria,
                        $this->memberRepository->getDefinition(),
                        $member,
                        '/api/member'
                    )
                ));
            }
        });
    }

    private function loadChannelLanguages(MemberCollection $members, ?string $channelIdFromSource, Context $context): ChannelCollection
    {
        $channelIds = $channelIdFromSource ? [$channelIdFromSource] : $members->getChannelIds();

        $criteria = new Criteria($channelIds);
        $association = $criteria->getAssociation('languages');

        $association
            ->addFields(['id'])
            ->addFilter(new EqualsAnyFilter('id', $members->getLanguageIds()));

        return $this->channelRepository->search($criteria, $context)->getEntities();
    }
}
