<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Dispatching\Storer;

use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Content\Shared\MailFlow\DataProvider\MemberProvider;
use Contena\Core\Framework\Event\FlowEventAware;
use Contena\Core\Framework\Event\MemberAware;
use Contena\Core\System\Member\MemberEntity;

class MemberStorer extends FlowStorer
{
    /**
     * @internal
     */
    public function __construct(private readonly MemberProvider $memberProvider)
    {
    }

    /**
     * @param array<string, mixed> $stored
     *
     * @return array<string, mixed>
     */
    public function store(FlowEventAware $event, array $stored): array
    {
        if (!$event instanceof MemberAware || isset($stored[MemberAware::MEMBER_ID])) {
            return $stored;
        }

        $stored[MemberAware::MEMBER_ID] = $event->getMemberId();

        return $stored;
    }

    public function restore(StorableFlow $storable): void
    {
        if (!$storable->hasStore(MemberAware::MEMBER_ID)) {
            return;
        }

        $storable->setData(MemberAware::MEMBER_ID, $storable->getStore(MemberAware::MEMBER_ID));

        $storable->lazy(
            MemberAware::MEMBER,
            $this->lazyLoad(...)
        );
    }

    private function lazyLoad(StorableFlow $storableFlow): ?MemberEntity
    {
        $id = $storableFlow->getStore(MemberAware::MEMBER_ID);
        if ($id === null) {
            return null;
        }

        return $this->memberProvider->getData($id, $storableFlow->getContext());
    }
}
