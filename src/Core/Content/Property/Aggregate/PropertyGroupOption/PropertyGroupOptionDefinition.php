<?php declare(strict_types=1);

namespace Contena\Core\Content\Property\Aggregate\PropertyGroupOption;

use Contena\Core\Content\Media\MediaDefinition;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Property\Aggregate\PropertyGroupOptionTranslation\PropertyGroupOptionTranslationDefinition;
use Contena\Core\Content\Property\PropertyGroupDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ReverseInherited;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
class PropertyGroupOptionDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'property_group_option';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return PropertyGroupOptionCollection::class;
    }

    public function getEntityClass(): string
    {
        return PropertyGroupOptionEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    public function getHydratorClass(): string
    {
        return PropertyGroupOptionHydrator::class;
    }

    protected function getParentDefinitionClass(): ?string
    {
        return PropertyGroupDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of property group option.'),
            (new FkField('property_group_id', 'groupId', PropertyGroupDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of property group.'),
            (new TranslatedField('name'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new TranslatedField('position'))->addFlags(new ApiAware()),
            (new StringField('color_hex_code', 'colorHexCode'))->addFlags(new ApiAware())->setDescription('Property group options can be displayed in the form of color. For example: #98e3f5ff.'),
            (new FkField('media_id', 'mediaId', MediaDefinition::class))->addFlags(new ApiAware())->setDescription('Unique identity of media.'),
            (new BoolField('combinable', 'combinable'))->addFlags(new ApiAware(), new Runtime()),
            (new TranslatedField('customFields'))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('media', 'media_id', MediaDefinition::class, 'id'))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('group', 'property_group_id', PropertyGroupDefinition::class, 'id'))->addFlags(new ApiAware()),
            (new TranslationsAssociationField(PropertyGroupOptionTranslationDefinition::class, 'property_group_option_id'))->addFlags(new Required()),
        ]);
    }
}
