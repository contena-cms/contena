<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Dispatching\Storer;

use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserDefinition;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Content\Flow\Events\BeforeLoadStorableFlowDataEvent;
use Contena\Core\Content\Flow\Exception\UserDeletedException;
use Contena\Core\Content\Shared\MailFlow\DataProvider\UserProvider;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Event\UserAware;
use Contena\Core\Framework\Event\FlowEventAware;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class UserStorer extends FlowStorer
{
    /**
     * @internal
     *
     * @param EntityRepository<UserCollection> $userRepository
     */
    public function __construct(
        private readonly EntityRepository $userRepository,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly UserProvider $userProvider,
    ) {
    }

    /**
     * @param array<string, mixed> $stored
     *
     * @return array<string, mixed>
     */
    public function store(FlowEventAware $event, array $stored): array
    {
        if (!$event instanceof UserAware || isset($stored[UserAware::CUSTOMER_ID])) {
            return $stored;
        }

        try {
            $stored[UserAware::CUSTOMER_ID] = $event->getUserId();
        } catch (UserDeletedException) {
        }

        return $stored;
    }

    public function restore(StorableFlow $storable): void
    {
        if (!$storable->hasStore(UserAware::CUSTOMER_ID)) {
            return;
        }

        $storable->setData(UserAware::CUSTOMER_ID, $storable->getStore(UserAware::CUSTOMER_ID));

        $storable->lazy(
            UserAware::CUSTOMER,
            $this->lazyLoad(...)
        );
    }

    private function lazyLoad(StorableFlow $storableFlow): ?UserEntity
    {
        $id = $storableFlow->getStore(UserAware::CUSTOMER_ID);
        if ($id === null) {
            return null;
        }

        if (!Feature::isActive('v6.8.0.0')) {
            $criteria = $this->userProvider->getCriteria($id, $storableFlow->getContext());

            $event = new BeforeLoadStorableFlowDataEvent(
                UserDefinition::ENTITY_NAME,
                $criteria,
                $storableFlow->getContext(),
            );

            $this->dispatcher->dispatch($event, $event->getName());

            $user = $this->userRepository->search($criteria, $storableFlow->getContext())->getEntities()->get($id);

            if ($user) {
                return $user;
            }

            return null;
        }

        return $this->userProvider->getData($id, $storableFlow->getContext());
    }
}
