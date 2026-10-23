<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Comment;

use Contena\Core\Content\Blog\Aggregate\BlogComment\BlogCommentCollection;
use Contena\Core\Content\Blog\Aggregate\BlogComment\BlogCommentEntity;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Deprecation\BCChange\BecomesReadonly;
use Contena\Core\Framework\Deprecation\BCChange\ClassHierarchyChange;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Struct\Struct;

/**
 * @extends EntitySearchResult<BlogCommentCollection>
 */
#[ClassHierarchyChange(version: 'v6.8.0', description: 'Will no longer extend EntitySearchResult, but will keep extending Struct.', newParentClass: Struct::class)]
class BlogCommentResult extends EntitySearchResult
{
    #[BecomesReadonly(version: 'v6.8.0')]
    protected ?string $parentId = null;

    #[BecomesReadonly(version: 'v6.8.0')]
    protected string $blogId;

    #[BecomesReadonly(version: 'v6.8.0')]
    protected int $totalCommentsInCurrentLanguage;

    #[BecomesReadonly(version: 'v6.8.0')]
    protected ?BlogCommentEntity $userComment = null;

    public static function fromSearchResult(
        EntitySearchResult $result,
        string $blogId,
        int $totalCommentsInCurrentLanguage,
        ?BlogCommentEntity $userComment = null,
        ?string $parentId = null,
    ): self {
        $instance = self::createFrom($result);
        $instance->blogId = $blogId;
        $instance->totalCommentsInCurrentLanguage = $totalCommentsInCurrentLanguage;
        $instance->userComment = $userComment;
        $instance->parentId = $parentId;

        return $instance;
    }

    public function getBlogId(): string
    {
        return $this->blogId;
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed; the property becomes readonly. Pass the value via fromSearchResult() instead.
     */
    public function setBlogId(string $blogId): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'fromSearchResult()'));

        $this->blogId = $blogId;
    }

    public function getTotalCommentsInCurrentLanguage(): int
    {
        return $this->totalCommentsInCurrentLanguage;
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed; the property becomes readonly. Pass the value via fromSearchResult() instead.
     */
    public function setTotalCommentsInCurrentLanguage(int $totalCommentsInCurrentLanguage): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'fromSearchResult()'));

        $this->totalCommentsInCurrentLanguage = $totalCommentsInCurrentLanguage;
    }

    public function getUserComment(): ?BlogCommentEntity
    {
        return $this->userComment;
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed; the property becomes readonly. Pass the value via fromSearchResult() instead.
     */
    public function setUserComment(?BlogCommentEntity $userComment): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'fromSearchResult()'));

        $this->userComment = $userComment;
    }

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed; the property becomes readonly. Pass the value via fromSearchResult() instead.
     */
    public function setParentId(?string $parentId): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'fromSearchResult()'));

        $this->parentId = $parentId;
    }
}
