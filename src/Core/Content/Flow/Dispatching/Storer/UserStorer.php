<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Dispatching\Storer;

use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Content\Shared\MailFlow\DataProvider\UserRecoveryProvider;
use Contena\Core\Framework\Event\FlowEventAware;
use Contena\Core\Framework\Event\UserAware;
use Contena\Core\System\User\Aggregate\UserRecovery\UserRecoveryEntity;
use Contena\Core\System\User\Recovery\UserRecoveryRequestEvent;

class UserStorer extends FlowStorer
{
    /**
     * @internal
     */
    public function __construct(private readonly UserRecoveryProvider $provider)
    {
    }

    public function store(FlowEventAware $event, array $stored): array
    {
        if ($event instanceof UserAware) {
            $stored[UserAware::USER_ID] = $event->getUserId();
        }

        if ($event instanceof UserRecoveryRequestEvent) {
            $stored[UserAware::USER_RECOVERY_ID] = $event->getUserRecovery()->getId();
        }

        return $stored;
    }

    public function restore(StorableFlow $storable): void
    {
        if ($storable->hasStore(UserAware::USER_ID)) {
            $storable->setData(UserAware::USER_ID, $storable->getStore(UserAware::USER_ID));
        }

        if ($storable->hasStore(UserAware::USER_RECOVERY_ID)) {
            $storable->lazy(UserAware::USER_RECOVERY, $this->load(...));
        }
    }

    private function load(StorableFlow $flow): ?UserRecoveryEntity
    {
        $id = $flow->getStore(UserAware::USER_RECOVERY_ID);
        if (!\is_string($id)) {
            return null;
        }

        return $this->provider->getData($id, $flow->getContext());
    }
}
