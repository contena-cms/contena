<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Dispatching\Storer;

use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Content\Shared\MailFlow\DataProvider\MemberGroupProvider;
use Contena\Core\Framework\Event\FlowEventAware;
use Contena\Core\Framework\Event\MemberGroupAware;
use Contena\Core\System\Member\Aggregate\MemberGroup\MemberGroupEntity;

class MemberGroupStorer extends FlowStorer
{
    /**
     * @internal
     */
    public function __construct(private readonly MemberGroupProvider $memberGroupProvider)
    {
    }

    /**
     * @param array<string, mixed> $stored
     *
     * @return array<string, mixed>
     */
    public function store(FlowEventAware $event, array $stored): array
    {
        if (!$event instanceof MemberGroupAware || isset($stored[MemberGroupAware::MEMBER_GROUP_ID])) {
            return $stored;
        }

        $stored[MemberGroupAware::MEMBER_GROUP_ID] = $event->getMemberGroupId();

        return $stored;
    }

    public function restore(StorableFlow $storable): void
    {
        if (!$storable->hasStore(MemberGroupAware::MEMBER_GROUP_ID)) {
            return;
        }

        $storable->setData(MemberGroupAware::MEMBER_GROUP_ID, $storable->getStore(MemberGroupAware::MEMBER_GROUP_ID));

        $storable->lazy(
            MemberGroupAware::MEMBER_GROUP,
            $this->lazyLoad(...)
        );
    }

    private function lazyLoad(StorableFlow $storableFlow): ?MemberGroupEntity
    {
        $id = $storableFlow->getStore(MemberGroupAware::MEMBER_GROUP_ID);
        if ($id === null) {
            return null;
        }

        return $this->memberGroupProvider->getData($id, $storableFlow->getContext());
    }
}
