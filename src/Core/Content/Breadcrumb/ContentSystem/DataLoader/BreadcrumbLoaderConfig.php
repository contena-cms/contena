<?php declare(strict_types=1);

namespace Contena\Core\Content\Breadcrumb\ContentSystem\DataLoader;

use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;

/**
 * @phpstan-type BreadcrumbLoaderConfigData array{
 *   property?: non-empty-string,
 *   type?: non-empty-string,
 *   referrerCategoryProperty?: non-empty-string
 * }
 *
 * @internal
 */
final readonly class BreadcrumbLoaderConfig extends AbstractContentDataLoaderConfig
{
    /**
     * @param non-empty-string|null $property Element property name to read entity ID from
     * @param non-empty-string $type Breadcrumb type: 'blog' or 'category'
     * @param non-empty-string|null $referrerCategoryProperty Element property name to read referrer category ID from
     */
    public function __construct(
        public ?string $property = null,
        public string $type = 'blog',
        public ?string $referrerCategoryProperty = null,
    ) {
    }

    /**
     * @return BreadcrumbLoaderConfigData
     */
    public function jsonSerialize(): array
    {
        $data = [];

        if ($this->property !== null) {
            $data['property'] = $this->property;
        }

        if ($this->type !== 'blog') {
            $data['type'] = $this->type;
        }

        if ($this->referrerCategoryProperty !== null) {
            $data['referrerCategoryProperty'] = $this->referrerCategoryProperty;
        }

        return $data;
    }
}
