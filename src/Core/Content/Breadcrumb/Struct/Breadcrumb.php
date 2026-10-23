<?php declare(strict_types=1);

namespace Contena\Core\Content\Breadcrumb\Struct;

use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Framework\Struct\Struct;

class Breadcrumb extends Struct
{
    /**
     * @param array<string, mixed> $translated
     * @param list<array<string, string>> $seoUrls
     */
    public function __construct(
        public string $name,
        public string $categoryId = '',
        public string $type = '',
        public array $translated = [],
        public string $path = '',
        public array $seoUrls = []
    ) {
    }

    public function shouldOpenInNewTab(): bool
    {
        return $this->type === CategoryDefinition::TYPE_LINK && ($this->translated['linkNewTab'] ?? false);
    }

    public function getApiAlias(): string
    {
        return 'breadcrumb';
    }
}
