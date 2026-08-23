<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\ContentSystem\DataLoader;

use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;

/**
 * @phpstan-type BlogSuggestLoaderConfigData array{
 *   searchTermProperty?: non-empty-string,
 *   associations?: list<non-empty-string>
 * }
 *
 * @internal
 */
final readonly class BlogSuggestLoaderConfig extends AbstractContentDataLoaderConfig
{
    /**
     * @param non-empty-string|null $searchTermProperty Element property name to read search term from
     * @param list<non-empty-string> $associations
     */
    public function __construct(
        public ?string $searchTermProperty = null,
        public array $associations = [],
    ) {
    }

    /**
     * @return BlogSuggestLoaderConfigData
     */
    public function jsonSerialize(): array
    {
        $data = [];

        if ($this->searchTermProperty !== null) {
            $data['searchTermProperty'] = $this->searchTermProperty;
        }

        if ($this->associations !== []) {
            $data['associations'] = $this->associations;
        }

        return $data;
    }
}
