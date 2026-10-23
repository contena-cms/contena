<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Review;

use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Deprecation\BCChange\BecomesReadonly;
use Contena\Core\Framework\Deprecation\BCChange\ClassHierarchyChange;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Struct\Struct;

/**
 * @extends EntitySearchResult<BlogReviewCollection>
 */
#[ClassHierarchyChange(version: 'v6.8.0', description: 'Will no longer extend EntitySearchResult, but will keep extending Struct.', newParentClass: Struct::class)]
class BlogReviewResult extends EntitySearchResult
{
    #[BecomesReadonly(version: 'v6.8.0')]
    protected ?string $parentId = null;

    #[BecomesReadonly(version: 'v6.8.0')]
    protected string $blogId;

    #[BecomesReadonly(version: 'v6.8.0')]
    protected RatingMatrix $matrix;


    #[BecomesReadonly(version: 'v6.8.0')]
    protected int $totalReviewsInCurrentLanguage;


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

    public function getMatrix(): RatingMatrix
    {
        return $this->matrix;
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed; the property becomes readonly. Pass the value via fromSearchResult() instead.
     */
    public function setMatrix(RatingMatrix $matrix): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'fromSearchResult()'));

        $this->matrix = $matrix;
    }



    public function getTotalReviewsInCurrentLanguage(): int
    {
        return $this->totalReviewsInCurrentLanguage;
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed; the property becomes readonly. Pass the value via fromSearchResult() instead.
     */
    public function setTotalReviewsInCurrentLanguage(int $totalReviewsInCurrentLanguage): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'fromSearchResult()'));

        $this->totalReviewsInCurrentLanguage = $totalReviewsInCurrentLanguage;
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
