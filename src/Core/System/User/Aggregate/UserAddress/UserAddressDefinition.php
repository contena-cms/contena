<?php declare(strict_types=1);

namespace Contena\Core\System\User\Aggregate\UserAddress;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\Feature;
use Contena\Core\System\Country\Aggregate\CountryState\CountryStateDefinition;
use Contena\Core\System\Country\CountryDefinition;
use Contena\Core\System\User\UserDefinition;

class UserAddressDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'user_address';

    public const MAX_LENGTH_PHONE_NUMBER = 40;
    public const MAX_LENGTH_NAME = 255;
    public const MAX_LENGTH_TITLE = 100;
    public const MAX_LENGTH_ZIPCODE = 50;

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return UserAddressCollection::class;
    }

    public function getEntityClass(): string
    {
        return UserAddressEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function getParentDefinitionClass(): ?string
    {
        return UserDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        $fields = new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of user\'s address.'),

            (new FkField('user_id', 'userId', UserDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of user.'),

            (new FkField('country_id', 'countryId', CountryDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of country.'),
            (new FkField('country_state_id', 'countryStateId', CountryStateDefinition::class))->addFlags(new ApiAware())->setDescription('Unique identity of country\'s state.'),

            (new StringField('department', 'department'))->addFlags(new ApiAware())->setDescription('Name of user\'s department.'),
            (new StringField('hash', 'hash'))->addFlags(new ApiAware(), new Runtime()),
            (new CustomFields())->addFlags(new ApiAware())->setDescription('Additional fields that offer a possibility to add own fields for the different program-areas.'),
            new ManyToOneAssociationField('user', 'user_id', UserDefinition::class, 'id', false),
            (new ManyToOneAssociationField('country', 'country_id', CountryDefinition::class, 'id', false))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('countryState', 'country_state_id', CountryStateDefinition::class, 'id', false))->addFlags(new ApiAware()),
        ]);

        if (Feature::isActive('v6.8.0.0')) {
            $fields->add(
                new OneToOneAssociationField('defaultBillingAddressUser', 'id', 'default_billing_address_id', UserDefinition::class, false)
            );
            $fields->add(
                new OneToOneAssociationField('defaultShippingAddressUser', 'id', 'default_shipping_address_id', UserDefinition::class, false)
            );
        }

        return $fields;
    }
}
