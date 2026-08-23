<?php declare(strict_types=1);

namespace Contena\Administration\System\Channel\Subscriber;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\System\User\Aggregate\UserConfig\UserConfigCollection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class ChannelUserConfigSubscriber implements EventSubscriberInterface
{
    final public const string CONFIG_KEY = 'channel-favorites';

    /**
     * @param EntityRepository<UserConfigCollection> $userConfigRepository
     */
    public function __construct(private readonly EntityRepository $userConfigRepository)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'channel.deleted' => 'onChannelDeleted',
        ];
    }

    public function onChannelDeleted(EntityDeletedEvent $deletedEvent): void
    {
        $context = $deletedEvent->getContext();

        $deletedChannelIds = $deletedEvent->getIds();

        $writeUserConfigs = [];
        foreach ($this->getAllFavoriteUserConfigs($context) as $userConfigEntity) {
            $channelIds = $userConfigEntity->getValue();

            if ($channelIds === null) {
                continue;
            }

            $matchingIds = array_intersect($deletedChannelIds, $channelIds);

            if (!$matchingIds) {
                continue;
            }

            $newUserConfigArray = array_diff($channelIds, $matchingIds);
            $writeUserConfigs[] = [
                'id' => $userConfigEntity->getId(),
                'value' => array_values($newUserConfigArray),
            ];
        }

        $this->userConfigRepository->upsert($writeUserConfigs, $context);
    }

    private function getAllFavoriteUserConfigs(Context $context): UserConfigCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('key', self::CONFIG_KEY));

        return $this->userConfigRepository->search($criteria, $context)->getEntities();
    }
}
