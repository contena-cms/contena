<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Dispatching\Storer;

use Contena\Core\Checkout\Customer\CustomerCollection;
use Contena\Core\Checkout\Customer\CustomerDefinition;
use Contena\Core\Checkout\Customer\CustomerEntity;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Content\Flow\Events\BeforeLoadStorableFlowDataEvent;
use Contena\Core\Content\Flow\Exception\CustomerDeletedException;
use Contena\Core\Content\Shared\MailFlow\DataProvider\CustomerProvider;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Event\CustomerAware;
use Contena\Core\Framework\Event\FlowEventAware;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('after-sales')]
class CustomerStorer extends FlowStorer
{
    /**
     * @internal
     *
     * @param EntityRepository<CustomerCollection> $customerRepository
     */
    public function __construct(
        private readonly EntityRepository $customerRepository,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly CustomerProvider $customerProvider,
    ) {
    }

    /**
     * @param array<string, mixed> $stored
     *
     * @return array<string, mixed>
     */
    public function store(FlowEventAware $event, array $stored): array
    {
        if (!$event instanceof CustomerAware || isset($stored[CustomerAware::CUSTOMER_ID])) {
            return $stored;
        }

        try {
            $stored[CustomerAware::CUSTOMER_ID] = $event->getCustomerId();
        } catch (CustomerDeletedException) {
        }

        return $stored;
    }

    public function restore(StorableFlow $storable): void
    {
        if (!$storable->hasStore(CustomerAware::CUSTOMER_ID)) {
            return;
        }

        $storable->setData(CustomerAware::CUSTOMER_ID, $storable->getStore(CustomerAware::CUSTOMER_ID));

        $storable->lazy(
            CustomerAware::CUSTOMER,
            $this->lazyLoad(...)
        );
    }

    private function lazyLoad(StorableFlow $storableFlow): ?CustomerEntity
    {
        $id = $storableFlow->getStore(CustomerAware::CUSTOMER_ID);
        if ($id === null) {
            return null;
        }

        if (!Feature::isActive('v6.8.0.0')) {
            $criteria = $this->customerProvider->getCriteria($id, $storableFlow->getContext());

            $event = new BeforeLoadStorableFlowDataEvent(
                CustomerDefinition::ENTITY_NAME,
                $criteria,
                $storableFlow->getContext(),
            );

            $this->dispatcher->dispatch($event, $event->getName());

            $customer = $this->customerRepository->search($criteria, $storableFlow->getContext())->getEntities()->get($id);

            if ($customer) {
                return $customer;
            }

            return null;
        }

        return $this->customerProvider->getData($id, $storableFlow->getContext());
    }
}
