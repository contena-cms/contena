<?php declare(strict_types=1);

namespace Contena\Core\Content\LandingPage\Aggregate\LandingPageContentLayout;

use Contena\Core\Framework\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignmentEntity;

/**
 * @internal
 *
 * @final
 */
class LandingPageContentLayoutEntity extends AbstractContentLayoutAssignmentEntity
{
    protected ?string $tenantId = null;

    protected string $landingPageId;

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function setTenantId(?string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function getLandingPageId(): string
    {
        return $this->landingPageId;
    }

    public function setLandingPageId(string $landingPageId): void
    {
        $this->landingPageId = $landingPageId;
    }
}
