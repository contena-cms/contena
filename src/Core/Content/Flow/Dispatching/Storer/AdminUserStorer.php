<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Dispatching\Storer;

use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Content\Flow\Events\BeforeLoadStorableFlowDataEvent;
use Contena\Core\Content\Shared\MailFlow\DataProvider\AdminUserRecoveryProvider;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Event\AdminUserAware;
use Contena\Core\Framework\Event\FlowEventAware;
use Contena\Core\Framework\Feature;
use Contena\Core\System\AdminUser\Aggregate\AdminUserRecovery\AdminUserRecoveryCollection;
use Contena\Core\System\AdminUser\Aggregate\AdminUserRecovery\AdminUserRecoveryDefinition;
use Contena\Core\System\AdminUser\Aggregate\AdminUserRecovery\AdminUserRecoveryEntity;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class AdminUserStorer extends FlowStorer
{
    /**
     * @internal
     *
     * @param EntityRepository<AdminUserRecoveryCollection> $adminUserRecoveryRepository
     */
    public function __construct(
        private readonly EntityRepository $adminUserRecoveryRepository,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly AdminUserRecoveryProvider $adminUserRecoveryProvider,
    ) {
    }

    public function store(FlowEventAware $event, array $stored): array
    {
        if (!$event instanceof AdminUserAware || isset($stored[AdminUserAware::USER_RECOVERY_ID])) {
            return $stored;
        }

        $stored[AdminUserAware::USER_RECOVERY_ID] = $event->getUserId();

        return $stored;
    }

    public function restore(StorableFlow $storable): void
    {
        if (!$storable->hasStore(AdminUserAware::USER_RECOVERY_ID)) {
            return;
        }

        $storable->lazy(
            AdminUserAware::USER_RECOVERY,
            $this->lazyLoad(...)
        );
    }

    private function lazyLoad(StorableFlow $storableFlow): ?AdminUserRecoveryEntity
    {
        $id = $storableFlow->getStore(AdminUserAware::USER_RECOVERY_ID);
        if ($id === null) {
            return null;
        }

        if (!Feature::isActive('v6.8.0.0')) {
            $criteria = $this->adminUserRecoveryProvider->getCriteria($id, $storableFlow->getContext());

            $event = new BeforeLoadStorableFlowDataEvent(
                AdminUserRecoveryDefinition::ENTITY_NAME,
                $criteria,
                $storableFlow->getContext(),
            );

            $this->dispatcher->dispatch($event, $event->getName());

            $adminUserRecovery = $this->adminUserRecoveryRepository->search($criteria, $storableFlow->getContext())->getEntities()->get($id);

            if ($adminUserRecovery) {
                return $adminUserRecovery;
            }

            return null;
        }

        return $this->adminUserRecoveryProvider->getData($id, $storableFlow->getContext());
    }
}
