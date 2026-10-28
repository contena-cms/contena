<?php declare(strict_types=1);

namespace Contena\Core\Checkout\Customer\Aggregate\CustomerGroup;

use Contena\Core\Checkout\Customer\Aggregate\CustomerGroupRegistrationSalesChannel\CustomerGroupRegistrationSalesChannelDefinition;
use Contena\Core\Checkout\Customer\Aggregate\CustomerGroupTranslation\CustomerGroupTranslationDefinition;
use Contena\Core\Checkout\Customer\CustomerDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelDefinition;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class CustomerGroupDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'customer_group';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return CustomerGroupCollection::class;
    }

    public function getEntityClass(): string
    {
        return CustomerGroupEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of the customer\'s group.'),
            (new BoolField('display_gross', 'displayGross'))->addFlags(new ApiAware())->setDescription('If boolean value is `true` gross value is displayed else, net value will be displayed to the customer.'),
            (new TranslatedField('customFields'))->addFlags(new ApiAware()),
            // Merchant Registration
            (new BoolField('registration_active', 'registrationActive'))->addFlags(new ApiAware())->setDescription('To enable the registration of partner customer group.'),
            (new TranslatedField('registrationTitle'))->addFlags(new ApiAware()),
            (new TranslatedField('registrationIntroduction'))->addFlags(new ApiAware()),
            (new TranslatedField('registrationOnlyCompanyRegistration'))->addFlags(new ApiAware()),
            (new TranslatedField('registrationSeoMetaDescription'))->addFlags(new ApiAware()),
            (new OneToManyAssociationField('customers', CustomerDefinition::class, 'customer_group_id', 'id'))->addFlags(new RestrictDelete()),
            (new OneToManyAssociationField('salesChannels', SalesChannelDefinition::class, 'customer_group_id', 'id'))->addFlags(new RestrictDelete()),
            (new TranslationsAssociationField(CustomerGroupTranslationDefinition::class, 'customer_group_id'))->addFlags(new Required()),
            new ManyToManyAssociationField('registrationSalesChannels', SalesChannelDefinition::class, CustomerGroupRegistrationSalesChannelDefinition::class, 'customer_group_id', 'sales_channel_id'),
        ]);
    }
}
