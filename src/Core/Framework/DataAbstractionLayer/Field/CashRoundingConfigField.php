<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Field;

use Contena\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\JsonFieldAccessorBuilder;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\CashRoundingConfigFieldSerializer;
use Contena\Core\Framework\Log\Package;

#[Package('framework')]
class CashRoundingConfigField extends JsonField
{
    public function __construct(
        string $storageName,
        string $propertyName
    ) {
        parent::__construct($storageName, $propertyName, [
            (new IntField('decimals', 'decimals', 0))->addFlags(new Required()),
            (new FloatField('interval', 'interval'))->addFlags(new Required()),
            (new BoolField('roundForNet', 'roundForNet'))->addFlags(new Required()),
        ]);
    }

    protected function getSerializerClass(): string
    {
        return CashRoundingConfigFieldSerializer::class;
    }

    protected function getAccessorBuilderClass(): ?string
    {
        return JsonFieldAccessorBuilder::class;
    }
}
