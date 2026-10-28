<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Dispatching\Action;

use Contena\Core\Content\Flow\Dispatching\DelayableAction;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Event\CustomerAware;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
class RemoveCustomerTagAction extends FlowAction implements DelayableAction
{
    /**
     * @internal
     *
     * @param EntityRepository<EntityCollection<Entity>> $customerTagRepository
     */
    public function __construct(private readonly EntityRepository $customerTagRepository)
    {
    }

    public static function getName(): string
    {
        return 'action.remove.customer.tag';
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
        if (!\array_key_exists('tagIds', $config)) {
            return;
        }

        $tagIds = array_keys($config['tagIds']);

        if ($tagIds === []) {
            return;
        }

        $tags = array_map(static fn ($tagId) => [
            'customerId' => $customerId,
            'tagId' => $tagId,
        ], $tagIds);

        $this->customerTagRepository->delete($tags, $context);
    }
}
