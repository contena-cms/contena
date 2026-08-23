<?php declare(strict_types=1);

namespace Contena\Core\System\Integration\Aggregate\IntegrationRole;

use Contena\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Contena\Core\System\Integration\IntegrationDefinition;

class IntegrationRoleDefinition extends MappingEntityDefinition
{
    final public const string ENTITY_NAME = 'integration_role';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function since(): ?string
    {
        return '6.3.3.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantField()->setDescription('Unique identity of the owning tenant, or null for a platform-owned integration role assignment.'),
            new FkField('integration_id', 'integrationId', IntegrationDefinition::class)->addFlags(new PrimaryKey(), new Required()),
            new FkField('acl_role_id', 'aclRoleId', AclRoleDefinition::class)->addFlags(new PrimaryKey(), new Required()),
            new ManyToOneAssociationField('integration', 'integration_id', IntegrationDefinition::class, 'id', false),
            new ManyToOneAssociationField('role', 'acl_role_id', AclRoleDefinition::class, 'id', false),
        ]);
    }
}
