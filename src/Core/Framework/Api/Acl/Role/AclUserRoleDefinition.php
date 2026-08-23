<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\Acl\Role;

use Contena\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Contena\Core\System\User\UserDefinition;

class AclUserRoleDefinition extends MappingEntityDefinition
{
    final public const string ENTITY_NAME = 'acl_user_role';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantField()->setDescription('Unique identity of the owning tenant, or null for a platform-owned user role assignment.'),
            new FkField('user_id', 'userId', UserDefinition::class)->addFlags(new PrimaryKey(), new Required()),
            new FkField('acl_role_id', 'aclRoleId', AclRoleDefinition::class)->addFlags(new PrimaryKey(), new Required()),
            // MappingEntityDefinitions have no default fields, so createdAt and updatedAt need to be defined here
            new CreatedAtField(),
            new UpdatedAtField(),
            new ManyToOneAssociationField('user', 'user_id', UserDefinition::class),
            new ManyToOneAssociationField('aclRole', 'acl_role_id', AclRoleDefinition::class),
        ]);
    }
}
