<?php declare(strict_types=1);

namespace Contena\Core\System\NumberRange\Aggregate\NumberRangeType;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\NumberRange\Aggregate\NumberRangeChannel\NumberRangeChannelDefinition;
use Contena\Core\System\NumberRange\Aggregate\NumberRangeTypeTranslation\NumberRangeTypeTranslationDefinition;
use Contena\Core\System\NumberRange\NumberRangeDefinition;

/**
 * @codeCoverageIgnore
 */
class NumberRangeTypeDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'number_range_type';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return NumberRangeTypeCollection::class;
    }

    public function getEntityClass(): string
    {
        return NumberRangeTypeEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of number range\'s type.'),
            (new StringField('technical_name', 'technicalName'))->addFlags(new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING))->setDescription('Name of the number range type.'),
            (new BoolField('global', 'global'))->addFlags(new Required())->setDescription('When set to `true`, the defined number range type is used across all sales channels.'),
            new TranslatedField('customFields'),

            (new OneToManyAssociationField('numberRanges', NumberRangeDefinition::class, 'type_id'))->addFlags(new RestrictDelete()),
            (new OneToManyAssociationField('numberRangeChannels', NumberRangeChannelDefinition::class, 'number_range_type_id'))->addFlags(new CascadeDelete()),
            (new TranslationsAssociationField(NumberRangeTypeTranslationDefinition::class, 'number_range_type_id'))->addFlags(new Required()),
        ]);
    }
}
