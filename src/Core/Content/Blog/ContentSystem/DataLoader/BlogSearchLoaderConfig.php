<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\ContentSystem\DataLoader;

use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;

/**
 * @phpstan-type BlogSearchLoaderConfigData array{
 *   searchTermProperty?: non-empty-string,
 *   associations?: list<non-empty-string>,
 *   associationOverride?: non-empty-string
 * }
 *
 * @internal
 */
final readonly class BlogSearchLoaderConfig extends AbstractContentDataLoaderConfig
{
    /**
     * @param non-empty-string|null $searchTermProperty Element property name to read search term from
     * @param list<non-empty-string> $associations
     * @param non-empty-string|null $associationOverride Element property name to read additional associations from
     */
    public function __construct(
        public ?string $searchTermProperty = null,
        public array $associations = [],
        public ?string $associationOverride = null,
    ) {
    }

    /**
     * @return BlogSearchLoaderConfigData
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

        if ($this->associationOverride !== null) {
            $data['associationOverride'] = $this->associationOverride;
        }

        return $data;
    }
}
