<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Dispatching\Storer;

use Contena\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryCollection;
use Contena\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryDefinition;
use Contena\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryEntity;
use Contena\Core\Content\Flow\Dispatching\Aware\CustomerRecoveryAware;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Content\Flow\Events\BeforeLoadStorableFlowDataEvent;
use Contena\Core\Content\Shared\MailFlow\DataProvider\CustomerRecoveryProvider;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Event\FlowEventAware;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('after-sales')]
class CustomerRecoveryStorer extends FlowStorer
{
    /**
     * @internal
     *
     * @param EntityRepository<CustomerRecoveryCollection> $customerRecoveryRepository
     */
    public function __construct(
        private readonly EntityRepository $customerRecoveryRepository,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly CustomerRecoveryProvider $customerRecoveryProvider,
    ) {
    }

    /**
     * @param array<string, mixed> $stored
     *
     * @return array<string, mixed>
     */
    public function store(FlowEventAware $event, array $stored): array
    {
        if (!$event instanceof CustomerRecoveryAware || isset($stored[CustomerRecoveryAware::CUSTOMER_RECOVERY_ID])) {
            return $stored;
        }

        $stored[CustomerRecoveryAware::CUSTOMER_RECOVERY_ID] = $event->getCustomerRecoveryId();

        return $stored;
    }

    public function restore(StorableFlow $storable): void
    {
        if (!$storable->hasStore(CustomerRecoveryAware::CUSTOMER_RECOVERY_ID)) {
            return;
        }

        $storable->lazy(
            CustomerRecoveryAware::CUSTOMER_RECOVERY,
            $this->lazyLoad(...)
        );
    }

    private function lazyLoad(StorableFlow $storableFlow): ?CustomerRecoveryEntity
    {
        $id = $storableFlow->getStore(CustomerRecoveryAware::CUSTOMER_RECOVERY_ID);
        if ($id === null) {
            return null;
        }

        if (!Feature::isActive('v6.8.0.0')) {
            $criteria = $this->customerRecoveryProvider->getCriteria($id, $storableFlow->getContext());

            $event = new BeforeLoadStorableFlowDataEvent(
                CustomerRecoveryDefinition::ENTITY_NAME,
                $criteria,
                $storableFlow->getContext(),
            );

            $this->dispatcher->dispatch($event, $event->getName());

            $customerRecovery = $this->customerRecoveryRepository->search($criteria, $storableFlow->getContext())->getEntities()->get($id);

            if ($customerRecovery) {
                return $customerRecovery;
            }

            return null;
        }

        return $this->customerRecoveryProvider->getData($id, $storableFlow->getContext());
    }
}
