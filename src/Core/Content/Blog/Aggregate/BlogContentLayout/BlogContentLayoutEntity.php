<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogContentLayout;

use Contena\Core\Framework\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignmentEntity;

/**
 * @internal
 *
 * @final
 */
class BlogContentLayoutEntity extends AbstractContentLayoutAssignmentEntity
{
    protected ?string $tenantId = null;

    protected string $blogId;

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function setTenantId(?string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function getBlogId(): string
    {
        return $this->blogId;
    }

    public function setBlogId(string $blogId): void
    {
        $this->blogId = $blogId;
    }
}
