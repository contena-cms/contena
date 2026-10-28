<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Dispatching\Action;

use Contena\Core\Checkout\Customer\CustomerCollection;
use Contena\Core\Content\Flow\Dispatching\DelayableAction;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Event\CustomerAware;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
class ChangeCustomerStatusAction extends FlowAction implements DelayableAction
{
    /**
     * @internal
     *
     * @param EntityRepository<CustomerCollection> $customerRepository
     */
    public function __construct(private readonly EntityRepository $customerRepository)
    {
    }

    public static function getName(): string
    {
        return 'action.change.customer.status';
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
    private function update(Context $context, array $config, string $customerID): void
    {
        if (!\array_key_exists('active', $config)) {
            return;
        }

        $active = $config['active'];

        if (!\is_bool($active)) {
            return;
        }

        $this->customerRepository->update([
            [
                'id' => $customerID,
                'active' => $active,
            ],
        ], $context);
    }
}
