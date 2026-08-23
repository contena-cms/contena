<?php declare(strict_types=1);

namespace Contena\Core\System\Integration;

use Contena\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Contena\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\PasswordField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\Notification\NotificationDefinition;
use Contena\Core\System\Integration\Aggregate\IntegrationRole\IntegrationRoleDefinition;
use Contena\Core\System\StateMachine\Aggregation\StateMachineHistory\StateMachineHistoryDefinition;

class IntegrationDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'integration';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return IntegrationCollection::class;
    }

    public function getEntityClass(): string
    {
        return IntegrationEntity::class;
    }

    public function getDefaults(): array
    {
        return [
            'admin' => false,
        ];
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantField()->setDescription('Unique identity of the owning tenant, or null for a platform-owned integration.'),
            new ManyToOneAssociationField('tenant', 'tenant_id', 'tenant', 'id', false)->setDescription('Owning tenant of the integration.'),
            new IdField('id', 'id')->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of Integration.'),
            new StringField('label', 'label')->addFlags(new Required())->setDescription('Label given to Integration.'),
            new StringField('access_key', 'accessKey')->addFlags(new Required())->setDescription('Access key to store api.'),
            new PasswordField('secret_access_key', 'secretAccessKey')->addFlags(new Required())->setDescription('Secret key required for secure communication.'),
            new DateTimeField('last_usage_at', 'lastUsageAt')->setDescription('Date and time when teh integration was last used.'),
            new BoolField('admin', 'admin')->addFlags(new WriteProtected(Context::SYSTEM_SCOPE))->setDescription('When boolean value is `true`, it indicates this is a administrative integration that requires elevated permissions.'),
            new CustomFields()->setDescription('Additional fields that offer a possibility to add own fields for the different program-areas.'),
            new DateTimeField('deleted_at', 'deletedAt')->setDescription('Date and time when the integration was deleted.'),

            new OneToManyAssociationField('stateMachineHistoryEntries', StateMachineHistoryDefinition::class, 'integration_id', 'id'),
            new OneToManyAssociationField('createdNotifications', NotificationDefinition::class, 'created_by_integration_id', 'id'),
            new ManyToManyAssociationField('aclRoles', AclRoleDefinition::class, IntegrationRoleDefinition::class, 'integration_id', 'acl_role_id'),
        ]);
    }
}
