<?php declare(strict_types=1);

namespace Contena\Core\System\Unit;

use Contena\Core\Content\Product\ProductDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ReverseInherited;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Unit\Aggregate\UnitTranslation\UnitTranslationDefinition;

/**
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class UnitDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'unit';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return UnitCollection::class;
    }

    public function getEntityClass(): string
    {
        return UnitEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of Unit.'),
            (new TranslatedField('customFields'))->addFlags(new ApiAware()),
            (new OneToManyAssociationField('products', ProductDefinition::class, 'unit_id', 'id'))->addFlags(new RestrictDelete(), new ReverseInherited('unit')),
            (new TranslationsAssociationField(UnitTranslationDefinition::class, 'unit_id'))->addFlags(new Required()),
        ]);
    }
}
