<?php declare(strict_types=1);

namespace Contena\Core\System\User\Subscriber;

use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserDefinition;
use Contena\Core\System\User\Event\UserDeletedEvent;
use Contena\Core\Framework\Api\Context\ChannelApiSource;
use Contena\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Util\Random;
use Contena\Core\System\Channel\Context\ChannelContextServiceInterface;
use Contena\Core\System\Channel\Context\ChannelContextServiceParameters;
use Contena\Core\System\Channel\ChannelCollection;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class UserBeforeDeleteSubscriber implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<UserCollection> $userRepository
     * @param EntityRepository<ChannelCollection> $channelRepository
     *
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $userRepository,
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

        $ids = $event->getIds(UserDefinition::ENTITY_NAME);

        if ($ids === []) {
            return;
        }

        $source = $context->getSource();
        $channelId = null;

        if ($source instanceof ChannelApiSource) {
            $channelId = $source->getChannelId();
        }

        $criteria = (new Criteria($ids))
            ->addAssociations([
                'salutation',
                'defaultBillingAddress.country',
                'defaultBillingAddress.countryState',
                'defaultBillingAddress.salutation',
                'defaultShippingAddress.country',
                'defaultShippingAddress.countryState',
                'defaultShippingAddress.salutation',
            ]);

        $users = $this->userRepository->search($criteria, $context)->getEntities();

        $channelLanguages = $this->loadChannelLanguages($users, $channelId, $context);

        $event->addSuccess(function () use ($users, $context, $channelId, $criteria, $channelLanguages): void {
            foreach ($users as $user) {
                $languageId = $user->getLanguageId();

                $effectiveChannelId = $channelId ?? $user->getChannelId();

                $effectiveLanguageId = $channelLanguages
                    ->get($effectiveChannelId)
                    ?->getLanguages()
                    ?->has($languageId)
                        ? $languageId
                        : null;

                $channelContext = $this->channelContextService->get(
                    new ChannelContextServiceParameters(
                        $effectiveChannelId,
                        Random::getAlphanumericString(32),
                        $effectiveLanguageId,
                        null,
                        null,
                        $context,
                    )
                );

                $this->eventDispatcher->dispatch(new UserDeletedEvent(
                    $channelContext,
                    $user,
                    $this->jsonEntityEncoder->encode(
                        $criteria,
                        $this->userRepository->getDefinition(),
                        $user,
                        '/api/user'
                    )
                ));
            }
        });
    }

    private function loadChannelLanguages(UserCollection $users, ?string $channelIdFromSource, Context $context): ChannelCollection
    {
        $channelIds = $channelIdFromSource ? [$channelIdFromSource] : $users->getChannelIds();

        $criteria = new Criteria($channelIds);
        $association = $criteria->getAssociation('languages');

        $association
            ->addFields(['id'])
            ->addFilter(new EqualsAnyFilter('id', $users->getLanguageIds()));

        return $this->channelRepository->search($criteria, $context)->getEntities();
    }
}
