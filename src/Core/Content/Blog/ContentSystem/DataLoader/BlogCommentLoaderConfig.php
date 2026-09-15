<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\ContentSystem\DataLoader;

use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;

/**
 * @phpstan-type BlogCommentLoaderConfigData array{property?: non-empty-string}
 *
 * @internal
 */
final readonly class BlogCommentLoaderConfig extends AbstractContentDataLoaderConfig
{
    /**
     * @param non-empty-string|null $property Element property containing the current blog id
     */
    public function __construct(public ?string $property = null)
    {
    }

    /**
     * @return BlogCommentLoaderConfigData
     */
    public function jsonSerialize(): array
    {
        return $this->property === null ? [] : ['property' => $this->property];
    }
}
