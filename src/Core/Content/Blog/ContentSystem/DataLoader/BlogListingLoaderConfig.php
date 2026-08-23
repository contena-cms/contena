<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\ContentSystem\DataLoader;

use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;

/**
 * @phpstan-type BlogListingLoaderConfigData array{
 *   property?: non-empty-string,
 *   associations?: list<non-empty-string>
 * }
 *
 * @internal
 */
final readonly class BlogListingLoaderConfig extends AbstractContentDataLoaderConfig
{
    /**
     * @param non-empty-string|null $property Element property name to read navigation ID from
     * @param list<non-empty-string> $associations
     */
    public function __construct(
        public ?string $property = null,
        public array $associations = []
    ) {
    }

    /**
     * @return BlogListingLoaderConfigData
     */
    public function jsonSerialize(): array
    {
        $data = [];

        if ($this->property !== null) {
            $data['property'] = $this->property;
        }

        if ($this->associations !== []) {
            $data['associations'] = $this->associations;
        }

        return $data;
    }
}
