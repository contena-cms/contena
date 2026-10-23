<?php declare(strict_types=1);

namespace Contena\Core\System\Integration;

use Contena\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Contena\Core\Framework\App\AppDefinition;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityProtection\CloneProtection;
use Contena\Core\Framework\DataAbstractionLayer\EntityProtection\EntityProtectionCollection;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Contena\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\PasswordField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Webhook\WebhookDefinition;
use Contena\Core\System\Integration\Aggregate\IntegrationRole\IntegrationRoleDefinition;
use Contena\Core\System\StateMachine\Aggregation\StateMachineHistory\StateMachineHistoryDefinition;

/**
 * @codeCoverageIgnore
 */
class IntegrationDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'integration';

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

    protected function defineProtections(): EntityProtectionCollection
    {
        return new EntityProtectionCollection([new CloneProtection(Context::SYSTEM_SCOPE)]);
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of Integration.'),
            (new StringField('label', 'label'))->addFlags(new Required())->setDescription('Label given to Integration.'),
            (new StringField('access_key', 'accessKey'))->addFlags(new Required())->setDescription('Access key to store api.'),
            (new PasswordField('secret_access_key', 'secretAccessKey'))->addFlags(new Required())->setDescription('Secret key required for secure communication.'),
            (new DateTimeField('last_usage_at', 'lastUsageAt'))->setDescription('Date and time when teh integration was last used.'),
            // The regular Admin API CRUD endpoint authorizes elevated admin changes in the controller; direct DAL writes remain write-protected.
            // @see \Contena\Core\Framework\Api\Controller\IntegrationController
            (new BoolField('admin', 'admin'))->addFlags((new WriteProtected(Context::SYSTEM_SCOPE))->allowWriteThroughAdminApi())->setDescription('When boolean value is `true`, it indicates this is a administrative integration that requires elevated permissions.'),
            (new JsonField('mcp_allowlist', 'mcpAllowlist'))->setDescription('Optional per-type MCP allowlist for this integration. Structured as {tools, resources, prompts} where each key is null (unrestricted) or a list of allowed names/URIs. When null all capabilities are accessible.'),
            (new CustomFields())->setDescription('Additional fields that offer a possibility to add own fields for the different program-areas.'),
            (new DateTimeField('deleted_at', 'deletedAt'))->setDescription('Date and time when the integration was deleted.'),

            (new OneToOneAssociationField('app', 'id', 'integration_id', AppDefinition::class, false))->addFlags(new RestrictDelete()),
            new OneToManyAssociationField('stateMachineHistoryEntries', StateMachineHistoryDefinition::class, 'integration_id', 'id'),
            // an integration that still owns webhooks cannot be deleted, so its webhooks are not lost unnoticed
            (new OneToManyAssociationField('webhooks', WebhookDefinition::class, 'owner_integration_id', 'id'))->removeFlag(ApiAware::class)->addFlags(new RestrictDelete()),
            new ManyToManyAssociationField('aclRoles', AclRoleDefinition::class, IntegrationRoleDefinition::class, 'integration_id', 'acl_role_id'),
        ]);
    }
}
