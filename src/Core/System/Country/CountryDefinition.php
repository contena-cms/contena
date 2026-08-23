<?php declare(strict_types=1);

namespace Contena\Core\System\Country;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Channel\Aggregate\ChannelCountry\ChannelCountryDefinition;
use Contena\Core\System\Channel\ChannelDefinition;
use Contena\Core\System\Country\Aggregate\CountryTranslation\CountryTranslationDefinition;
use Contena\Core\System\Region\RegionDefinition;

class CountryDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'country';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return CountryCollection::class;
    }

    public function getEntityClass(): string
    {
        return CountryEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of the country.'),

            new TranslatedField('name')->addFlags(new ApiAware(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            new StringField('iso', 'iso')->addFlags(new ApiAware(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING))->setDescription('Internationally recognized two-letter country codes. For example, DE, IN, NO, etc.'),
            new IntField('position', 'position')->addFlags(new ApiAware())->setDescription('Numerical value that indicates the order in which the defined countries must be displayed in the frontend.'),
            new BoolField('active', 'active')->addFlags(new ApiAware())->setDescription('When boolean value is `true`, the country is available for selection in the storefront.'),
            new StringField('iso3', 'iso3')->addFlags(new ApiAware(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING))->setDescription('Internationally recognized three-letter country codes. For example, DEU, IND, NOR, etc.'),
            new TranslatedField('customFields')->addFlags(new ApiAware()),
            new OneToManyAssociationField('regions', RegionDefinition::class, 'country_id', 'id')
                ->addFlags(new ApiAware(), new CascadeDelete())->setDescription('Hierarchical administrative regions within the country'),

            new OneToManyAssociationField('channelDefaultAssignments', ChannelDefinition::class, 'country_id', 'id')
                ->addFlags(new RestrictDelete()),

            new ManyToManyAssociationField('channels', ChannelDefinition::class, ChannelCountryDefinition::class, 'country_id', 'channel_id'),

            new TranslationsAssociationField(CountryTranslationDefinition::class, 'country_id')
                ->addFlags(new ApiAware(), new Required()),
        ]);
    }
}
