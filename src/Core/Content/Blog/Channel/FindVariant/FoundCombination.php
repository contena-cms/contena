<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\FindVariant;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\Struct;

class FoundCombination extends Struct
{
    /**
     * @param string[] $options
     */
    public function __construct(
        protected string $variantId,
        protected array $options
    ) {
    }

    public function getVariantId(): string
    {
        return $this->variantId;
    }

    public function getOptions(): array
    {
        return $this->options;
    }
}
