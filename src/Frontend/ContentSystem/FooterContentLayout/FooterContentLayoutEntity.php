<?php declare(strict_types=1);

namespace Contena\Frontend\ContentSystem\FooterContentLayout;

use Contena\Core\Framework\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignmentEntity;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainEntity;

/**
 * @internal
 *
 * @final
 */
class FooterContentLayoutEntity extends AbstractContentLayoutAssignmentEntity
{
    protected string $dataScopeId;

    protected ?string $domainId = null;

    protected ?ChannelDomainEntity $domain = null;

    public function getDataScopeId(): string
    {
        return $this->dataScopeId;
    }

    public function setDataScopeId(string $dataScopeId): void
    {
        $this->dataScopeId = $dataScopeId;
    }

    public function getDomainId(): ?string
    {
        return $this->domainId;
    }

    public function setDomainId(?string $domainId): void
    {
        $this->domainId = $domainId;
    }

    public function getDomain(): ?ChannelDomainEntity
    {
        return $this->domain;
    }

    public function setDomain(?ChannelDomainEntity $domain): void
    {
        $this->domain = $domain;
    }
}
