<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Dispatching\Storer;

use Contena\Core\System\User\Aggregate\UserGroup\UserGroupCollection;
use Contena\Core\System\User\Aggregate\UserGroup\UserGroupDefinition;
use Contena\Core\System\User\Aggregate\UserGroup\UserGroupEntity;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Content\Flow\Events\BeforeLoadStorableFlowDataEvent;
use Contena\Core\Content\Shared\MailFlow\DataProvider\UserGroupProvider;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Event\UserGroupAware;
use Contena\Core\Framework\Event\FlowEventAware;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class UserGroupStorer extends FlowStorer
{
    /**
     * @internal
     *
     * @param EntityRepository<UserGroupCollection> $userGroupRepository
     */
    public function __construct(
        private readonly EntityRepository $userGroupRepository,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly UserGroupProvider $userGroupProvider,
    ) {
    }

    /**
     * @param array<string, mixed> $stored
     *
     * @return array<string, mixed>
     */
    public function store(FlowEventAware $event, array $stored): array
    {
        if (!$event instanceof UserGroupAware || isset($stored[UserGroupAware::CUSTOMER_GROUP_ID])) {
            return $stored;
        }

        $stored[UserGroupAware::CUSTOMER_GROUP_ID] = $event->getUserGroupId();

        return $stored;
    }

    public function restore(StorableFlow $storable): void
    {
        if (!$storable->hasStore(UserGroupAware::CUSTOMER_GROUP_ID)) {
            return;
        }

        $storable->setData(UserGroupAware::CUSTOMER_GROUP_ID, $storable->getStore(UserGroupAware::CUSTOMER_GROUP_ID));

        $storable->lazy(
            UserGroupAware::CUSTOMER_GROUP,
            $this->lazyLoad(...)
        );
    }

    private function lazyLoad(StorableFlow $storableFlow): ?UserGroupEntity
    {
        $id = $storableFlow->getStore(UserGroupAware::CUSTOMER_GROUP_ID);
        if ($id === null) {
            return null;
        }

        if (!Feature::isActive('v6.8.0.0')) {
            $criteria = $this->userGroupProvider->getCriteria($id, $storableFlow->getContext());

            $event = new BeforeLoadStorableFlowDataEvent(
                UserGroupDefinition::ENTITY_NAME,
                $criteria,
                $storableFlow->getContext(),
            );

            $this->dispatcher->dispatch($event, $event->getName());

            $userGroup = $this->userGroupRepository->search($criteria, $storableFlow->getContext())->getEntities()->get($id);

            if ($userGroup) {
                return $userGroup;
            }

            return null;
        }

        return $this->userGroupProvider->getData($id, $storableFlow->getContext());
    }
}
