<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\Acl\Role;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityProtection\EntityProtectionCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityProtection\WriteProtection;
use Contena\Core\Framework\DataAbstractionLayer\Field\CreatedByField;
use Contena\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ListField;
use Contena\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Integration\Aggregate\IntegrationRole\IntegrationRoleDefinition;
use Contena\Core\System\Integration\IntegrationDefinition;
use Contena\Core\System\User\UserDefinition;

class AclRoleDefinition extends EntityDefinition
{
    final public const string PRIVILEGE_READ = 'read';
    final public const string PRIVILEGE_CREATE = 'create';
    final public const string PRIVILEGE_UPDATE = 'update';
    final public const string PRIVILEGE_DELETE = 'delete';

    final public const array PRIVILEGE_DEPENDENCE = [
        AclRoleDefinition::PRIVILEGE_READ => [],
        AclRoleDefinition::PRIVILEGE_CREATE => [AclRoleDefinition::PRIVILEGE_READ],
        AclRoleDefinition::PRIVILEGE_UPDATE => [AclRoleDefinition::PRIVILEGE_READ],
        AclRoleDefinition::PRIVILEGE_DELETE => [AclRoleDefinition::PRIVILEGE_READ],
    ];

    final public const string ENTITY_NAME = 'acl_role';
    final public const string ALL_ROLE_KEY = 'all';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return AclRoleCollection::class;
    }

    public function getEntityClass(): string
    {
        return AclRoleEntity::class;
    }

    public function getDefaults(): array
    {
        return ['privileges' => []];
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineProtections(): EntityProtectionCollection
    {
        return new EntityProtectionCollection([
            new WriteProtection(Context::SYSTEM_SCOPE),
        ]);
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantField()->setDescription('Unique identity of the owning tenant, or null for a platform-owned ACL role.'),
            new IdField('id', 'id')->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of ACL role.'),
            new StringField('code', 'code')->addFlags(new Required())->setDescription('Stable technical code of the ACL role.'),
            new StringField('name', 'name')->addFlags(new Required())->setDescription('Name of the ACL role defined.'),
            new LongTextField('description', 'description')->setDescription('A short description of the ACL role.'),
            new ListField('privileges', 'privileges', StringField::class)->addFlags(new Required())->setDescription('Privileges like read, write, delete, etc.'),
            new DateTimeField('deleted_at', 'deletedAt')->setDescription('Time and date when the ACL role was deleted.'),
            new CreatedByField(),
            new ManyToOneAssociationField('createdBy', 'created_by_id', UserDefinition::class, 'id', false),
            new ManyToManyAssociationField('users', UserDefinition::class, AclUserRoleDefinition::class, 'acl_role_id', 'user_id'),
            new ManyToManyAssociationField('integrations', IntegrationDefinition::class, IntegrationRoleDefinition::class, 'acl_role_id', 'integration_id'),
        ]);
    }
}
