<?php declare(strict_types=1);

namespace Contena\Core\System\AdminUser;

use Contena\Core\System\User\UserDefinition;
use Contena\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogDefinition;
use Contena\Core\Content\Media\MediaDefinition;
use Contena\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityProtection\CloneProtection;
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
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\PasswordField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TimeZoneField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Webhook\WebhookDefinition;
use Contena\Core\System\Locale\LocaleDefinition;
use Contena\Core\System\StateMachine\Aggregation\StateMachineHistory\StateMachineHistoryDefinition;
use Contena\Core\System\AdminUser\Aggregate\AdminUserAccessKey\AdminUserAccessKeyDefinition;
use Contena\Core\System\AdminUser\Aggregate\AdminUserConfig\AdminUserConfigDefinition;
use Contena\Core\System\AdminUser\Aggregate\AdminUserRecovery\AdminUserRecoveryDefinition;

/**
 * @codeCoverageIgnore
 */
class AdminUserDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'admin_user';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return AdminUserCollection::class;
    }

    public function getEntityClass(): string
    {
        return AdminUserEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    public function getDefaults(): array
    {
        return [
            'admin' => false,
            'timeZone' => 'Asia/Shanghai',
        ];
    }

    protected function defineProtections(): EntityProtectionCollection
    {
        return new EntityProtectionCollection([
            new CloneProtection(Context::SYSTEM_SCOPE),
            new WriteProtection(Context::SYSTEM_SCOPE, Context::USER_SCOPE),
        ]);
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of the admin_user.'),
            (new FkField('locale_id', 'localeId', LocaleDefinition::class))->addFlags(new Required())->setDescription('Unique identity of locale.'),
            (new StringField('username', 'username'))->addFlags(new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING))->setDescription('Username of the admin_user.'),
            (new PasswordField('password', 'password', \PASSWORD_DEFAULT, [], PasswordField::FOR_ADMIN))->removeFlag(ApiAware::class)->addFlags(new Required()),
            (new StringField('name', 'name'))->addFlags(new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING))->setDescription('Full name of the admin_user.'),
            (new StringField('phone', 'phone'))->addFlags(new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING, false))->setDescription('Phone number of the admin_user.'),
            (new StringField('title', 'title'))->addFlags(new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING))->setDescription('Title of the admin_user.'),
            (new EmailField('email', 'email'))->addFlags(new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING))->setDescription('Email of the admin_user.'),
            (new BoolField('active', 'active'))->setDescription('When boolean value is `true`, the admin_user is enabled.'),
            // The regular Admin API CRUD endpoint authorizes elevated admin changes in the controller; direct DAL writes remain write-protected.
            // @see \Contena\Core\Framework\Api\Controller\AdminUserController
            (new BoolField('admin', 'admin'))->addFlags((new WriteProtected(Context::SYSTEM_SCOPE))->allowWriteThroughAdminApi())->setDescription('Parameter that indicates if the admin_user is an admin.'),
            (new JsonField('mcp_allowlist', 'mcpAllowlist'))->setDescription(
                'Optional per-type MCP allowlist for this admin_user. Structured as {tools, resources, prompts} '
                . 'where each key is null (unrestricted) or a list of allowed names/URIs.'
            ),
            (new DateTimeField('last_updated_password_at', 'lastUpdatedPasswordAt'))->setDescription('Parameter that indicates when the password was last updated by the admin_user.'),
            (new TimeZoneField('time_zone', 'timeZone'))->addFlags(new Required())->setDescription('Time configuration in the admin_user\'s profile.'),
            (new CustomFields())->setDescription('Additional fields that offer a possibility to add own fields for the different program-areas.'),
            new ManyToOneAssociationField('locale', 'locale_id', LocaleDefinition::class, 'id', false),
            (new FkField('avatar_id', 'avatarId', MediaDefinition::class))->setDescription('Unique identity of the avatar.'),
            new ManyToOneAssociationField('avatarMedia', 'avatar_id', MediaDefinition::class),
            (new OneToManyAssociationField('media', MediaDefinition::class, 'admin_user_id'))->addFlags(new SetNullOnDelete()),
            (new OneToManyAssociationField('accessKeys', AdminUserAccessKeyDefinition::class, 'admin_user_id', 'id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('configs', AdminUserConfigDefinition::class, 'admin_user_id', 'id'))->addFlags(new CascadeDelete()),
            // a admin_user that still owns webhooks cannot be deleted, so its webhooks are not lost unnoticed
            (new OneToManyAssociationField('webhooks', WebhookDefinition::class, 'owner_user_id', 'id'))->removeFlag(ApiAware::class)->addFlags(new RestrictDelete()),
            new OneToManyAssociationField('stateMachineHistoryEntries', StateMachineHistoryDefinition::class, 'admin_user_id', 'id'),
            (new OneToManyAssociationField('importExportLogEntries', ImportExportLogDefinition::class, 'admin_user_id', 'id'))->addFlags(new SetNullOnDelete()),
            new ManyToManyAssociationField('aclRoles', AclRoleDefinition::class, AclAdminUserRoleDefinition::class, 'admin_user_id', 'acl_role_id'),
            new OneToOneAssociationField('recoveryUser', 'id', 'admin_user_id', AdminUserRecoveryDefinition::class, false),
            (new StringField('store_token', 'storeToken'))->removeFlag(ApiAware::class),
            new OneToManyAssociationField('createdUsers', UserDefinition::class, 'created_by_id', 'id'),
            new OneToManyAssociationField('updatedUsers', UserDefinition::class, 'updated_by_id', 'id'),
        ]);
    }
}
