<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Dispatching\Action;

use Doctrine\DBAL\Connection;
use Contena\Core\Checkout\Customer\CustomerCollection;
use Contena\Core\Content\Flow\Dispatching\DelayableAction;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Event\CustomerAware;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
class SetCustomerCustomFieldAction extends FlowAction implements DelayableAction
{
    use CustomFieldActionTrait;

    /**
     * @internal
     *
     * @param EntityRepository<CustomerCollection> $customerRepository
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityRepository $customerRepository
    ) {
    }

    public static function getName(): string
    {
        return 'action.set.customer.custom.field';
    }

    /**
     * @return list<string>
     */
    public function requirements(): array
    {
        return [CustomerAware::class];
    }

    public function handleFlow(StorableFlow $flow): void
    {
        if (!$flow->hasData(CustomerAware::CUSTOMER_ID)) {
            return;
        }

        $this->update($flow->getContext(), $flow->getConfig(), $flow->getData(CustomerAware::CUSTOMER_ID));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function update(Context $context, array $config, string $customerId): void
    {
        $customer = $this->customerRepository->search(new Criteria([$customerId]), $context)->getEntities()->first();

        $customFields = $this->getCustomFieldForUpdating($customer?->getCustomFields(), $config);
        if ($customFields === null) {
            return;
        }

        $customFields = $customFields === [] ? null : $customFields;

        $this->customerRepository->update([
            [
                'id' => $customerId,
                'customFields' => $customFields,
            ],
        ], $context);
    }
}
