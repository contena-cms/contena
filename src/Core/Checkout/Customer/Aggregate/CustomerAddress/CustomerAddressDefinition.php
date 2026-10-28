<?php declare(strict_types=1);

namespace Contena\Core\Checkout\Customer\Aggregate\CustomerAddress;

use Contena\Core\Checkout\Customer\CustomerDefinition;
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
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Country\Aggregate\CountryState\CountryStateDefinition;
use Contena\Core\System\Country\CountryDefinition;

#[Package('checkout')]
class CustomerAddressDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'customer_address';

    public const MAX_LENGTH_PHONE_NUMBER = 40;
    public const MAX_LENGTH_FIRST_NAME = 255;
    public const MAX_LENGTH_LAST_NAME = 255;
    public const MAX_LENGTH_TITLE = 100;
    public const MAX_LENGTH_ZIPCODE = 50;

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return CustomerAddressCollection::class;
    }

    public function getEntityClass(): string
    {
        return CustomerAddressEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function getParentDefinitionClass(): ?string
    {
        return CustomerDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        $fields = new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of customer\'s address.'),

            (new FkField('customer_id', 'customerId', CustomerDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of customer.'),

            (new FkField('country_id', 'countryId', CountryDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of country.'),
            (new FkField('country_state_id', 'countryStateId', CountryStateDefinition::class))->addFlags(new ApiAware())->setDescription('Unique identity of country\'s state.'),

            (new StringField('department', 'department'))->addFlags(new ApiAware())->setDescription('Name of customer\'s department.'),
            (new StringField('hash', 'hash'))->addFlags(new ApiAware(), new Runtime()),
            (new CustomFields())->addFlags(new ApiAware())->setDescription('Additional fields that offer a possibility to add own fields for the different program-areas.'),
            new ManyToOneAssociationField('customer', 'customer_id', CustomerDefinition::class, 'id', false),
            (new ManyToOneAssociationField('country', 'country_id', CountryDefinition::class, 'id', false))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('countryState', 'country_state_id', CountryStateDefinition::class, 'id', false))->addFlags(new ApiAware()),
        ]);

        if (Feature::isActive('v6.8.0.0')) {
            $fields->add(
                new OneToOneAssociationField('defaultBillingAddressCustomer', 'id', 'default_billing_address_id', CustomerDefinition::class, false)
            );
            $fields->add(
                new OneToOneAssociationField('defaultShippingAddressCustomer', 'id', 'default_shipping_address_id', CustomerDefinition::class, false)
            );
        }

        return $fields;
    }
}
