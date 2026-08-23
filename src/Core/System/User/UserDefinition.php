<?php declare(strict_types=1);

namespace Contena\Core\System\User;

use Contena\Core\Content\Media\MediaDefinition;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Contena\Core\Framework\Api\Acl\Role\AclUserRoleDefinition;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityProtection\EntityProtectionCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityProtection\WriteProtection;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Contena\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Contena\Core\Framework\DataAbstractionLayer\Field\EmailField;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\PasswordField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TimeZoneField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\Notification\NotificationDefinition;
use Contena\Core\System\Locale\LocaleDefinition;
use Contena\Core\System\NumberRange\DataAbstractionLayer\NumberRangeField;
use Contena\Core\System\Position\PositionDefinition;
use Contena\Core\System\StateMachine\Aggregation\StateMachineHistory\StateMachineHistoryDefinition;
use Contena\Core\System\Tag\TagDefinition;
use Contena\Core\System\User\Aggregate\UserAccessKey\UserAccessKeyDefinition;
use Contena\Core\System\User\Aggregate\UserConfig\UserConfigDefinition;
use Contena\Core\System\User\Aggregate\UserPosition\UserPositionDefinition;
use Contena\Core\System\User\Aggregate\UserRecovery\UserRecoveryDefinition;
use Contena\Core\System\User\Aggregate\UserTag\UserTagDefinition;

class UserDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'user';

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

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    public function getDefaults(): array
    {
        return [
            'timeZone' => Defaults::DEFAULT_TIME_ZONE,
        ];
    }

    protected function defineProtections(): EntityProtectionCollection
    {
        return new EntityProtectionCollection([new WriteProtection(Context::SYSTEM_SCOPE)]);
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantField()->setDescription('Unique identity of the owning tenant, or null for a platform-owned user.'),
            new ManyToOneAssociationField('tenant', 'tenant_id', 'tenant', 'id', false)->addFlags(new ApiAware())->setDescription('Owning tenant of the user.'),
            new IdField('id', 'id')->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of the user.'),
            new FkField('locale_id', 'localeId', LocaleDefinition::class)->addFlags(new Required())->setDescription('Unique identity of locale.'),
            new NumberRangeField('user_code', 'userCode', 255)->addFlags(new ApiAware(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING))->setDescription('Code assigned to the user, unique within the owning tenant or platform.'),
            new StringField('username', 'username')->addFlags(new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING))->setDescription('Username of the user, unique within the owning tenant or platform.'),
            new PasswordField('password', 'password', \PASSWORD_DEFAULT, [], PasswordField::FOR_ADMIN)->removeFlag(ApiAware::class)->addFlags(new Required()),
            new StringField('name', 'name')->addFlags(new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING))->setDescription('Display name of the user.'),
            new StringField('phone_number', 'phoneNumber')->addFlags(new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING))->setDescription('Phone number of the user.'),
            new StringField('gender', 'gender')->addFlags(new ApiAware())->setDescription('Stable code of an item in the core.gender data dictionary.'),
            new EmailField('email', 'email')->addFlags(new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING))->setDescription('Email of the user, unique within the owning tenant or platform.'),
            new BoolField('active', 'active')->setDescription('When boolean value is `true`, the user is enabled.'),
            new BoolField('admin', 'admin')->setDescription('Parameter that indicates if the user is an admin.'),
            new DateTimeField('first_login', 'firstLogin')->addFlags(new ApiAware())->setDescription('Date and time of the user\'s first successful login.'),
            new DateTimeField('last_login', 'lastLogin')->addFlags(new ApiAware())->setDescription('Date and time of the user\'s most recent successful login.'),
            new DateTimeField('last_updated_password_at', 'lastUpdatedPasswordAt')->setDescription('Parameter that indicates when the password was last updated by the user.'),
            new TimeZoneField('time_zone', 'timeZone')->addFlags(new Required())->setDescription('Time configuration in the user\'s profile.'),
            new CustomFields()->setDescription('Additional fields that offer a possibility to add own fields for the different program-areas.'),
            new ManyToOneAssociationField('locale', 'locale_id', LocaleDefinition::class, 'id', false),
            new FkField('avatar_id', 'avatarId', MediaDefinition::class)->setDescription('Unique identity of the avatar.'),
            new ManyToOneAssociationField('avatarMedia', 'avatar_id', MediaDefinition::class),
            new OneToManyAssociationField('media', MediaDefinition::class, 'user_id')->addFlags(new SetNullOnDelete()),
            new OneToManyAssociationField('accessKeys', UserAccessKeyDefinition::class, 'user_id', 'id')->addFlags(new CascadeDelete()),
            new OneToManyAssociationField('configs', UserConfigDefinition::class, 'user_id', 'id')->addFlags(new CascadeDelete()),
            new OneToManyAssociationField('stateMachineHistoryEntries', StateMachineHistoryDefinition::class, 'user_id', 'id'),
            new OneToManyAssociationField('createdNotifications', NotificationDefinition::class, 'created_by_user_id', 'id'),
            new ManyToManyAssociationField('aclRoles', AclRoleDefinition::class, AclUserRoleDefinition::class, 'user_id', 'acl_role_id'),
            new ManyToManyAssociationField('tags', TagDefinition::class, UserTagDefinition::class, 'user_id', 'tag_id'),
            new ManyToManyAssociationField('positions', PositionDefinition::class, UserPositionDefinition::class, 'user_id', 'position_id')->addFlags(new ApiAware()),
            new OneToOneAssociationField('recoveryUser', 'id', 'user_id', UserRecoveryDefinition::class, false),
        ]);
    }
}
