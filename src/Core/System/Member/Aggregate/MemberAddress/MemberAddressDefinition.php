<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Aggregate\MemberAddress;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Country\CountryDefinition;
use Contena\Core\System\Member\MemberDefinition;
use Contena\Core\System\Region\RegionDefinition;

class MemberAddressDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'member_address';
    final public const int MAX_LENGTH_PHONE_NUMBER = 40;
    final public const int MAX_LENGTH_FIRST_NAME = 255;
    final public const int MAX_LENGTH_LAST_NAME = 255;
    final public const int MAX_LENGTH_TITLE = 100;
    final public const int MAX_LENGTH_ZIPCODE = 50;

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return MemberAddressCollection::class;
    }

    public function getEntityClass(): string
    {
        return MemberAddressEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function getParentDefinitionClass(): ?string
    {
        return MemberDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of member address.'),
            new TenantField()->setDescription('Unique identity of the owning tenant, or null for a platform-owned member address.'),
            new FkField('member_id', 'memberId', MemberDefinition::class)->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of member.'),
            new FkField('country_id', 'countryId', CountryDefinition::class)->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of country.'),
            new FkField('region_id', 'regionId', RegionDefinition::class)->addFlags(new ApiAware())->setDescription('Unique identity of the administrative region.'),
            new StringField('first_name', 'firstName', self::MAX_LENGTH_FIRST_NAME)->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING))->setDescription('First name of the member.'),
            new StringField('last_name', 'lastName', self::MAX_LENGTH_LAST_NAME)->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING))->setDescription('Last name of the member.'),
            new StringField('zipcode', 'zipcode', self::MAX_LENGTH_ZIPCODE)->addFlags(new ApiAware())->setDescription('Postal or zip code of member address.'),
            new StringField('city', 'city')->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING))->setDescription('City of member address.'),
            new StringField('street', 'street')->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING))->setDescription('Street of member address.'),
            new StringField('title', 'title', self::MAX_LENGTH_TITLE)->addFlags(new ApiAware())->setDescription('Title used for member address.'),
            new StringField('phone_number', 'phoneNumber', self::MAX_LENGTH_PHONE_NUMBER)->addFlags(new ApiAware())->setDescription('Phone number of member address.'),
            new StringField('additional_address_line1', 'additionalAddressLine1')->addFlags(new ApiAware(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING))->setDescription('Additional member address information.'),
            new StringField('additional_address_line2', 'additionalAddressLine2')->addFlags(new ApiAware(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING))->setDescription('Additional member address information.'),
            new StringField('hash', 'hash')->addFlags(new ApiAware(), new Runtime()),
            new CustomFields()->addFlags(new ApiAware())->setDescription('Additional fields for the member address.'),
            new ManyToOneAssociationField('member', 'member_id', MemberDefinition::class, 'id', false),
            new ManyToOneAssociationField('country', 'country_id', CountryDefinition::class, 'id', false)->addFlags(new ApiAware()),
            new ManyToOneAssociationField('region', 'region_id', RegionDefinition::class, 'id', false)->addFlags(new ApiAware()),
        ]);
    }
}
