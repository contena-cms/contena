<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Field;

use Contena\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\ConfigJsonFieldAccessorBuilder;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\ConfigJsonFieldSerializer;

class ConfigJsonField extends JsonField
{
    final public const string STORAGE_KEY = '_value';

    public function __construct(
        string $storageName,
        string $propertyName,
        array $propertyMapping = []
    ) {
        $wrappedPropertyMapping = [
            new JsonField(self::STORAGE_KEY, self::STORAGE_KEY, $propertyMapping),
        ];
        parent::__construct($storageName, $propertyName, $wrappedPropertyMapping);
    }

    protected function getSerializerClass(): string
    {
        return ConfigJsonFieldSerializer::class;
    }

    protected function getAccessorBuilderClass(): ?string
    {
        return ConfigJsonFieldAccessorBuilder::class;
    }
}
