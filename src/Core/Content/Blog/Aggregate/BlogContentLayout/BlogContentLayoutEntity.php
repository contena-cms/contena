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
    protected string $dataScopeId;

    protected string $blogId;

    public function getDataScopeId(): string
    {
        return $this->dataScopeId;
    }

    public function setDataScopeId(string $dataScopeId): void
    {
        $this->dataScopeId = $dataScopeId;
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
