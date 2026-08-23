<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Aggregate\MemberRecovery;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\System\Member\MemberEntity;

class MemberRecoveryEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $tenantId = null;

    protected string $memberId;

    protected string $hash;

    protected ?MemberEntity $member = null;

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function setTenantId(?string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function getMemberId(): string
    {
        return $this->memberId;
    }

    public function setMemberId(string $memberId): void
    {
        $this->memberId = $memberId;
    }

    public function getHash(): string
    {
        return $this->hash;
    }

    public function setHash(string $hash): void
    {
        $this->hash = $hash;
    }

    public function getMember(): ?MemberEntity
    {
        return $this->member;
    }

    public function setMember(MemberEntity $member): void
    {
        $this->member = $member;
    }
}
