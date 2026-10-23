<?php declare(strict_types=1);

namespace Contena\Core\Content\Cms\DataAbstractionLayer\Field;

use Contena\Core\Content\Cms\DataAbstractionLayer\FieldSerializer\SlotConfigFieldSerializer;
use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;

/**
 * @codeCoverageIgnore
 */
class SlotConfigField extends JsonField
{
    public function __construct(
        string $storageName,
        string $propertyName
    ) {
        $this->storageName = $storageName;
        parent::__construct($storageName, $propertyName);
    }

    protected function getSerializerClass(): string
    {
        return SlotConfigFieldSerializer::class;
    }
}
