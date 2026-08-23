<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Aggregate\MemberGroupTranslation;

use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Contena\Core\System\Member\Aggregate\MemberGroup\MemberGroupEntity;

class MemberGroupTranslationEntity extends TranslationEntity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected ?string $tenantId = null;

    protected string $memberGroupId;

    protected ?string $name = null;

    protected ?MemberGroupEntity $memberGroup = null;

    protected ?string $registrationTitle = null;

    protected ?string $registrationIntroduction = null;

    protected ?string $registrationSeoMetaDescription = null;

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function setTenantId(?string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function getMemberGroupId(): string
    {
        return $this->memberGroupId;
    }

    public function setMemberGroupId(string $memberGroupId): void
    {
        $this->memberGroupId = $memberGroupId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getMemberGroup(): ?MemberGroupEntity
    {
        return $this->memberGroup;
    }

    public function setMemberGroup(MemberGroupEntity $memberGroup): void
    {
        $this->memberGroup = $memberGroup;
    }

    public function getRegistrationTitle(): ?string
    {
        return $this->registrationTitle;
    }

    public function setRegistrationTitle(string $registrationTitle): void
    {
        $this->registrationTitle = $registrationTitle;
    }

    public function getRegistrationIntroduction(): ?string
    {
        return $this->registrationIntroduction;
    }

    public function setRegistrationIntroduction(string $registrationIntroduction): void
    {
        $this->registrationIntroduction = $registrationIntroduction;
    }

    public function getRegistrationSeoMetaDescription(): ?string
    {
        return $this->registrationSeoMetaDescription;
    }

    public function setRegistrationSeoMetaDescription(string $registrationSeoMetaDescription): void
    {
        $this->registrationSeoMetaDescription = $registrationSeoMetaDescription;
    }
}
