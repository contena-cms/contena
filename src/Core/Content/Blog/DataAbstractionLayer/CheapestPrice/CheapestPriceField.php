<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\DataAbstractionLayer\CheapestPrice;

use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\PHPUnserializeFieldSerializer;
use Contena\Core\Framework\Log\Package;

class CheapestPriceField extends JsonField
{
    public function __construct(
        string $storageName,
        string $propertyName,
        array $propertyMapping = []
    ) {
        parent::__construct($storageName, $propertyName, $propertyMapping);
        $this->addFlags(new WriteProtected());
    }

    protected function getSerializerClass(): string
    {
        return PHPUnserializeFieldSerializer::class;
    }

    protected function getAccessorBuilderClass(): ?string
    {
        return CheapestPriceAccessorBuilder::class;
    }
}
