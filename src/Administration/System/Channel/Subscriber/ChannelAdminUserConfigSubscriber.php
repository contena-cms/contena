<?php declare(strict_types=1);

namespace Contena\Administration\System\Channel\Subscriber;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\System\AdminUser\Aggregate\AdminUserConfig\AdminUserConfigCollection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class ChannelAdminUserConfigSubscriber implements EventSubscriberInterface
{
    final public const CONFIG_KEY = 'sales-channel-favorites';

    /**
     * @internal
     *
     * @param EntityRepository<AdminUserConfigCollection> $admin_userConfigRepository
     */
    public function __construct(private readonly EntityRepository $admin_userConfigRepository)
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
        foreach ($this->getAllFavoriteUserConfigs($context) as $admin_userConfigEntity) {
            $channelIds = $admin_userConfigEntity->getValue();

            if ($channelIds === null) {
                continue;
            }

            // Find matching IDs
            $matchingIds = array_intersect($deletedChannelIds, $channelIds);

            if (!$matchingIds) {
                continue;
            }

            // Removes the IDs from $matchingIds from the array
            $newUserConfigArray = array_diff($channelIds, $matchingIds);
            $writeUserConfigs[] = [
                'id' => $admin_userConfigEntity->getId(),
                'value' => array_values($newUserConfigArray),
            ];
        }

        $this->admin_userConfigRepository->upsert($writeUserConfigs, $context);
    }

    private function getAllFavoriteUserConfigs(Context $context): AdminUserConfigCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('key', self::CONFIG_KEY));

        return $this->admin_userConfigRepository->search($criteria, $context)->getEntities();
    }
}
