<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Dispatching\Storer;

use Contena\Core\Content\Flow\Dispatching\Aware\MemberRecoveryAware;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Content\Shared\MailFlow\DataProvider\MemberRecoveryProvider;
use Contena\Core\Framework\Event\FlowEventAware;
use Contena\Core\System\Member\Aggregate\MemberRecovery\MemberRecoveryEntity;

class MemberRecoveryStorer extends FlowStorer
{
    /**
     * @internal
     */
    public function __construct(private readonly MemberRecoveryProvider $memberRecoveryProvider)
    {
    }

    /**
     * @param array<string, mixed> $stored
     *
     * @return array<string, mixed>
     */
    public function store(FlowEventAware $event, array $stored): array
    {
        if (!$event instanceof MemberRecoveryAware || isset($stored[MemberRecoveryAware::MEMBER_RECOVERY_ID])) {
            return $stored;
        }

        $stored[MemberRecoveryAware::MEMBER_RECOVERY_ID] = $event->getMemberRecoveryId();

        return $stored;
    }

    public function restore(StorableFlow $storable): void
    {
        if (!$storable->hasStore(MemberRecoveryAware::MEMBER_RECOVERY_ID)) {
            return;
        }

        $storable->lazy(
            MemberRecoveryAware::MEMBER_RECOVERY,
            $this->lazyLoad(...)
        );
    }

    private function lazyLoad(StorableFlow $storableFlow): ?MemberRecoveryEntity
    {
        $id = $storableFlow->getStore(MemberRecoveryAware::MEMBER_RECOVERY_ID);
        if ($id === null) {
            return null;
        }

        return $this->memberRecoveryProvider->getData($id, $storableFlow->getContext());
    }
}
