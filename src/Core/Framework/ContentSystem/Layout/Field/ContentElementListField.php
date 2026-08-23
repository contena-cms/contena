<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Layout\Field;

use Contena\Core\Framework\DataAbstractionLayer\Field\ListField;

/**
 * @internal
 */
class ContentElementListField extends ListField
{
    public function __construct(
        string $storageName,
        string $propertyName
    ) {
        parent::__construct($storageName, $propertyName, ContentElementField::class);
    }

    protected function getSerializerClass(): string
    {
        return ContentElementListFieldSerializer::class;
    }
}
