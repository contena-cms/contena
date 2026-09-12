<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Field;

use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\DataScopeFieldSerializer;

/**
 * Marks a business entity as owned by exactly one data scope.
 *
 * The non-null value is injected from Context and is immutable. It is never
 * accepted from an API payload. Platform and tenant rows follow the same
 * storage and constraint rules.
 *
 * @internal
 */
class DataScopeField extends FkField
{
    public function __construct(string $storageName = 'data_scope_id', string $propertyName = 'dataScopeId')
    {
        parent::__construct($storageName, $propertyName, 'data_scope');

        $this->removeFlag(ApiAware::class);
        $this->addFlags(new Required());
    }

    protected function getSerializerClass(): string
    {
        return DataScopeFieldSerializer::class;
    }
}
