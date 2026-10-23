<?php declare(strict_types=1);

namespace Contena\Core\System\User;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\AutoIncrementField;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Contena\Core\Framework\DataAbstractionLayer\Field\DateField;
use Contena\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\IgnoreInOpenapiSchema;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\NoConstraint;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ListField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToManyIdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\RemoteAddressField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\Feature;
use Contena\Core\System\AdminUser\AdminUserDefinition;
use Contena\Core\System\Channel\ChannelDefinition;
use Contena\Core\System\Language\LanguageDefinition;
use Contena\Core\System\User\Aggregate\UserAddress\UserAddressDefinition;
use Contena\Core\System\User\Aggregate\UserGroup\UserGroupDefinition;
use Contena\Core\System\User\Aggregate\UserRecovery\UserRecoveryDefinition;
use Contena\Core\System\User\Aggregate\UserWishlist\UserWishlistDefinition;

class UserDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'user';

    public const MAX_LENGTH_NAME = 255;
    public const MAX_LENGTH_TITLE = 100;

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return UserCollection::class;
    }

    public function getEntityClass(): string
    {
        return UserEntity::class;
    }

    public function getDefaults(): array
    {
        return [
            'accountType' => UserEntity::ACCOUNT_TYPE_PRIVATE,
        ];
    }

    public function hasManyToManyIdFields(): bool
    {
        return true;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        $defaultBillingAddressAssociation = Feature::isActive('v6.8.0.0')
            ? new OneToOneAssociationField('defaultBillingAddress', 'default_billing_address_id', 'id', UserAddressDefinition::class, false)
            : new ManyToOneAssociationField('defaultBillingAddress', 'default_billing_address_id', UserAddressDefinition::class, 'id', false);
        $defaultBillingAddressAssociation->addFlags(new ApiAware(), new SearchRanking(SearchRanking::ASSOCIATION_SEARCH_RANKING))->setDescription('Default billing address for the user');

        $defaultShippingAddressAssociation = Feature::isActive('v6.8.0.0')
            ? new OneToOneAssociationField('defaultShippingAddress', 'default_shipping_address_id', 'id', UserAddressDefinition::class, false)
            : new ManyToOneAssociationField('defaultShippingAddress', 'default_shipping_address_id', UserAddressDefinition::class, 'id', false);
        $defaultShippingAddressAssociation->addFlags(new ApiAware(), new SearchRanking(SearchRanking::ASSOCIATION_SEARCH_RANKING))->setDescription('Default shipping address for the user');

        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of the user.'),
            (new FkField('user_group_id', 'groupId', UserGroupDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of user group.'),
            (new FkField('channel_id', 'channelId', ChannelDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of sales channel.'),
            (new FkField('language_id', 'languageId', LanguageDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of language.'),
            (new FkField('default_billing_address_id', 'defaultBillingAddressId', UserAddressDefinition::class))->addFlags(new ApiAware(), new Required(), new NoConstraint())->setDescription('Unique identity of default billing address.'),
            (new FkField('default_shipping_address_id', 'defaultShippingAddressId', UserAddressDefinition::class))->addFlags(new ApiAware(), new Required(), new NoConstraint())->setDescription('Unique identity of default shipping address.'),
            new AutoIncrementField(),
            (new NumberRangeField('user_number', 'userNumber', 255))->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING))->setDescription('Unique  number assigned to identity a user.'),
            (new StringField('company', 'company'))->addFlags(new ApiAware(), new IgnoreInOpenapiSchema(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING))->setDescription('Company name of the user.'),
            (new PasswordField('password', 'password', \PASSWORD_DEFAULT, [], PasswordField::FOR_CUSTOMER))->removeFlag(ApiAware::class),
            (new EmailField('email', 'email'))->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING, false))->setDescription('Email ID of the user.'),
            (new StringField('name', 'name', self::MAX_LENGTH_NAME))->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING))->setDescription('Full name of the user.'),
            (new StringField('phone', 'phone'))->addFlags(new ApiAware())->setDescription('Phone number of the user.'),
            (new StringField('title', 'title', self::MAX_LENGTH_TITLE))->addFlags(new ApiAware())->setDescription('Titles or honorifics like Mr, Mrs, etc.'),
            (new ListField('vat_ids', 'vatIds', StringField::class))->addFlags(new ApiAware(), new IgnoreInOpenapiSchema())->setDescription('Unique identity of VAT.'),
            (new StringField('affiliate_code', 'affiliateCode'))->addFlags(new ApiAware())->setDescription('An affiliate code is an identification option with which website operators can mark outgoing links.'),
            (new StringField('campaign_code', 'campaignCode'))->addFlags(new ApiAware())->setDescription('A campaign code is the globally unique identifier for a campaign.'),
            (new BoolField('active', 'active'))->addFlags(new ApiAware())->setDescription('To keep the status of the user active, the boolean value is set to `true`.'),
            (new BoolField('double_opt_in_registration', 'doubleOptInRegistration'))->addFlags(new ApiAware())->setDescription('Set to `true` to allow admin_user subscriptions to an email marketing list.'),
            (new DateTimeField('double_opt_in_email_sent_date', 'doubleOptInEmailSentDate'))->addFlags(new ApiAware())->setDescription('Date and time when the double opt-in email was sent.'),
            (new DateTimeField('double_opt_in_confirm_date', 'doubleOptInConfirmDate'))->addFlags(new ApiAware())->setDescription('Date and time when the double opt-in email was confirmed.'),
            (new StringField('hash', 'hash'))->setDescription('User registration double opt-in hash for confirming the user account.'),
            (new BoolField('guest', 'guest'))->addFlags(new ApiAware())->setDescription('Boolean value is `true` if it is to be a guest account.'),
            (new DateTimeField('first_login', 'firstLogin'))->addFlags(new ApiAware())->setDescription('To capture date and time of user\'s first login.'),
            (new DateTimeField('last_login', 'lastLogin'))->addFlags(new ApiAware())->setDescription('To capture date and time of user\'s last login.'),
            (new JsonField('newsletter_channel_ids', 'newsletterChannelIds'))->addFlags(new WriteProtected(Context::SYSTEM_SCOPE))->removeFlag(ApiAware::class),
            (new DateField('birthday', 'birthday'))->addFlags(new ApiAware())->setDescription('To capture user\'s birthday details.'),
            (new IntField('order_count', 'orderCount'))->addFlags(new ApiAware(), new WriteProtected(Context::SYSTEM_SCOPE))->setDescription('Captures the number of orders placed.'),
            (new FloatField('order_total_amount', 'orderTotalAmount'))->addFlags(new ApiAware(), new WriteProtected(Context::SYSTEM_SCOPE))->setDescription('Sum of total amount to be paid.'),
            (new IntField('review_count', 'reviewCount'))->addFlags(new ApiAware(), new WriteProtected(Context::SYSTEM_SCOPE))->setDescription('Number of reviews the user has given.'),
            (new CustomFields())->addFlags(new ApiAware())->setDescription('Additional fields that offer a possibility to add own fields for the different program-areas.'),
            (new StringField('legacy_password', 'legacyPassword'))->removeFlag(ApiAware::class),
            (new StringField('legacy_encoder', 'legacyEncoder'))->removeFlag(ApiAware::class),
            (new ManyToOneAssociationField('group', 'user_group_id', UserGroupDefinition::class, 'id', false))->addFlags(new ApiAware())->setDescription('User group determining pricing and permissions'),
            new ManyToOneAssociationField('channel', 'channel_id', ChannelDefinition::class, 'id', false),
            (new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, 'id', false))->addFlags(new ApiAware())->setDescription('Preferred language for user communication'),
            $defaultBillingAddressAssociation,
            (new ManyToOneAssociationField('activeBillingAddress', 'active_billing_address_id', UserAddressDefinition::class, 'id', false))->addFlags(new ApiAware(), new Runtime())->setDescription('Currently active billing address in the session'),
            $defaultShippingAddressAssociation,
            (new ManyToOneAssociationField('activeShippingAddress', 'active_shipping_address_id', UserAddressDefinition::class, 'id', false))->addFlags(new ApiAware(), new Runtime())->setDescription('Currently active shipping address in the session'),
            (new OneToManyAssociationField('addresses', UserAddressDefinition::class, 'user_id', 'id'))->addFlags(new ApiAware(), new CascadeDelete())->setDescription('All addresses saved for the user'),
            (new ManyToManyAssociationField('tags', TagDefinition::class, UserTagDefinition::class, 'user_id', 'tag_id'))->addFlags(new SearchRanking(SearchRanking::ASSOCIATION_SEARCH_RANKING), new ApiAware())->setDescription('Tags assigned to the user for organization and segmentation'),
            new OneToOneAssociationField('recoveryUser', 'id', 'user_id', UserRecoveryDefinition::class, false),
            (new RemoteAddressField('remote_address', 'remoteAddress'))->setDescription('Anonymous IP address of the user for last session.'),
            (new ManyToManyIdField('tag_ids', 'tagIds', 'tags'))->addFlags(new ApiAware())->setDescription('Unique identity of tag.'),
            (new FkField('requested_customer_group_id', 'requestedGroupId', UserGroupDefinition::class))->addFlags(new ApiAware())->setDescription('Unique identity of requested group.'),
            new ManyToOneAssociationField('requestedGroup', 'requested_customer_group_id', UserGroupDefinition::class, 'id', false),
            (new FkField('bound_channel_id', 'boundChannelId', ChannelDefinition::class))->setDescription('Unique identity of bonus sales channel.'),
            (new StringField('account_type', 'accountType'))->addFlags(new ApiAware(), new Required(), new IgnoreInOpenapiSchema())->setDescription('Account type can be personal or business.'),
            new ManyToOneAssociationField('boundChannel', 'bound_channel_id', ChannelDefinition::class, 'id', false),
            (new OneToManyAssociationField('wishlists', UserWishlistDefinition::class, 'user_id'))->addFlags(new CascadeDelete()),
            new ManyToOneAssociationField('createdBy', 'created_by_id', AdminUserDefinition::class, 'id', false),
            new ManyToOneAssociationField('updatedBy', 'updated_by_id', AdminUserDefinition::class, 'id', false),
        ]);
    }
}
