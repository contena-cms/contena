<?php declare(strict_types=1);

namespace Contena\Core\System\Region;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ChildCountField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ParentFkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TreeLevelField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TreePathField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Country\CountryDefinition;
use Contena\Core\System\Region\Aggregate\RegionTranslation\RegionTranslationDefinition;

class RegionDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'region';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return RegionCollection::class;
    }

    public function getEntityClass(): string
    {
        return RegionEntity::class;
    }

    public function since(): ?string
    {
        return '6.8.0.0';
    }

    protected function getParentDefinitionClass(): ?string
    {
        return CountryDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of the administrative region.'),
            new FkField('country_id', 'countryId', CountryDefinition::class)->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of the country.'),
            new ParentFkField(self::class)->addFlags(new ApiAware())->setDescription('Unique identity of the parent administrative region.'),
            new TreeLevelField('level', 'level')->addFlags(new ApiAware())->setDescription('Administrative hierarchy level, starting at one for country roots.'),
            new StringField('type', 'type', 32)->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING))->setDescription('Code of an item in the core.region.type data dictionary.'),
            new StringField('code', 'code', 64)->addFlags(new ApiAware(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING))->setDescription('Official administrative region code.'),
            new TreePathField('path', 'path')->addFlags(new ApiAware())->setDescription('Ancestor path maintained by the DAL tree updater.'),
            new ChildCountField()->addFlags(new ApiAware())->setDescription('Number of direct child regions.'),
            new TranslatedField('name')->addFlags(new ApiAware(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            new TranslatedField('shortName')->addFlags(new ApiAware()),
            new IntField('position', 'position')->addFlags(new ApiAware())->setDescription('Numerical value that indicates the display order.'),
            new BoolField('active', 'active')->addFlags(new ApiAware())->setDescription('Whether the region is available for selection.'),
            new CustomFields()->addFlags(new ApiAware()),
            new ParentAssociationField(self::class, 'id')->addFlags(new ApiAware()),
            new ChildrenAssociationField(self::class)->addFlags(new ApiAware(), new CascadeDelete()),
            new ManyToOneAssociationField('country', 'country_id', CountryDefinition::class, 'id', false),
            new TranslationsAssociationField(RegionTranslationDefinition::class, 'region_id')->addFlags(new ApiAware(), new Required()),
        ]);
    }
}
