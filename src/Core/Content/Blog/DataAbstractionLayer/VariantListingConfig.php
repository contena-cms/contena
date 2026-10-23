<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\DataAbstractionLayer;

use Contena\Core\Framework\Struct\Struct;

class VariantListingConfig extends Struct
{
    /**
     * @param list<array{id: string, representation?: string, expressionForListings?: bool}>|null $configuratorGroupConfig
     */
    public function __construct(
        protected ?bool $displayParent,
        protected ?string $mainVariantId,
        protected ?array $configuratorGroupConfig
    ) {
    }

    public function getDisplayParent(): ?bool
    {
        return $this->displayParent;
    }

    public function getMainVariantId(): ?string
    {
        return $this->mainVariantId;
    }

    /**
     * @return list<array{id: string, representation?: string, expressionForListings?: bool}>|null
     */
    public function getConfiguratorGroupConfig(): ?array
    {
        return $this->configuratorGroupConfig;
    }
}
