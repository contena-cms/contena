<?php declare(strict_types=1);

namespace Contena\Core\System\User\Aggregate\UserTenant;

use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantMembershipField;
use Contena\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Contena\Core\System\User\UserDefinition;

/**
 * Stores the tenant membership of a global administration user.
 *
 * @internal
 */
class UserTenantDefinition extends MappingEntityDefinition
{
    final public const string ENTITY_NAME = 'user_tenant';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function since(): ?string
    {
        return '6.8.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantField('tenant_id', 'tenantId')->addFlags(new PrimaryKey(), new Required()),
            new TenantMembershipField('user_id', 'userId', UserDefinition::class)->addFlags(new PrimaryKey(), new Required()),
            new BoolField('active', 'active')->setDescription('Whether the user may access this tenant.'),
            new BoolField('admin', 'admin')->setDescription('Whether the user is an administrator in this tenant.'),
            new StringField('user_code', 'userCode')->setDescription('Optional user code in this tenant.'),
            new CreatedAtField(),
            new UpdatedAtField(),
            new ManyToOneAssociationField('user', 'user_id', UserDefinition::class, 'id', false),
            new ManyToOneAssociationField('tenant', 'tenant_id', 'tenant', 'id', false),
        ]);
    }
}
