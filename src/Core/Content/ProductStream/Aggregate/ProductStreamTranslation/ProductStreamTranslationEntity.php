<?php declare(strict_types=1);

namespace Contena\Core\Content\ProductStream\Aggregate\ProductStreamTranslation;

use Contena\Core\Content\ProductStream\ProductStreamEntity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Contena\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class ProductStreamTranslationEntity extends TranslationEntity
{
    use EntityCustomFieldsTrait;

    protected string $productStreamId;

    protected ?string $name = null;

    protected ?string $description = null;

    protected ?ProductStreamEntity $productStream = null;

    public function getProductStreamId(): string
    {
        return $this->productStreamId;
    }

    public function setProductStreamId(string $productStreamId): void
    {
        $this->productStreamId = $productStreamId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getProductStream(): ?ProductStreamEntity
    {
        return $this->productStream;
    }

    public function setProductStream(?ProductStreamEntity $productStream): void
    {
        $this->productStream = $productStream;
    }
}
