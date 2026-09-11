<?php declare(strict_types=1);

namespace Contena\Core\Framework\App;

use Contena\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Contena\Core\Framework\App\Aggregate\ActionButton\ActionButtonDefinition;
use Contena\Core\Framework\App\Aggregate\AppContentSystemBindingSpecification\AppContentSystemBindingSpecificationDefinition;
use Contena\Core\Framework\App\Aggregate\AppContentSystemElementType\AppContentSystemElementTypeDefinition;
use Contena\Core\Framework\App\Aggregate\AppContentSystemLayoutPreset\AppContentSystemLayoutPresetDefinition;
use Contena\Core\Framework\App\Aggregate\AppSeoUrlRoute\AppSeoUrlRouteDefinition;
use Contena\Core\Framework\App\Aggregate\AppContentSystemStyleOption\AppContentSystemStyleOptionDefinition;
use Contena\Core\Framework\App\Aggregate\AppScriptCondition\AppScriptConditionDefinition;
use Contena\Core\Framework\App\Aggregate\AppTranslation\AppTranslationDefinition;
use Contena\Core\Framework\App\Aggregate\FlowAction\AppFlowActionDefinition;
use Contena\Core\Framework\App\Aggregate\FlowEvent\AppFlowEventDefinition;
use Contena\Core\Framework\App\Template\TemplateDefinition;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BlobField;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Since;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ListField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\Script\ScriptDefinition;
use Contena\Core\Framework\Webhook\WebhookDefinition;
use Contena\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetDefinition;
use Contena\Core\System\Integration\IntegrationDefinition;

/**
 * @internal
 *
 * @phpstan-type SourceConfig array<string, mixed>
 */
class AppDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'app';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return AppEntity::class;
    }

    public function getCollectionClass(): string
    {
        return AppCollection::class;
    }

    public function getDefaults(): array
    {
        return [
            'active' => false,
            'configurable' => false,
            'allowDisable' => true,
            'allowedHosts' => [],
            'templateLoadPriority' => 0,
            'sourceType' => 'local',
            'selfManaged' => false,
            'requestedPrivileges' => [],
        ];
    }

    public function since(): ?string
    {
        return '6.3.1.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id')->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of App.'),
            new StringField('name', 'name')->addFlags(new Required())->setDescription('Name of the app.'),
            new StringField('path', 'path', 4096)->addFlags(new Required())->setDescription('A relative URL to the app.'),
            new StringField('author', 'author')->setDescription('Creator of the App.'),
            new StringField('copyright', 'copyright')->setDescription('Legal rights on the created app.'),
            new StringField('license', 'license')->setDescription('Software license\'s like MIT, etc.'),
            new BoolField('active', 'active')->addFlags(new Required())->setDescription('When boolean value is `true`, the app is enabled for selection.'),
            new BoolField('configurable', 'configurable')->addFlags(new Required())->setDescription('When boolean value is `true`, the app is configurable for further customizations.'),
            new StringField('privacy', 'privacy')->setDescription('Privacy-related configuration properties like user data protection, consent mechanisms, or data privacy compliance for an app.'),
            new StringField('version', 'version')->addFlags(new Required())->setDescription('Version of the plugin.'),
            new BlobField('icon', 'iconRaw')->removeFlag(ApiAware::class),
            new StringField('icon', 'icon')->addFlags(new WriteProtected(), new Runtime())->setDescription('Icon for the app.'),
            new StringField('app_secret', 'appSecret')->removeFlag(ApiAware::class)->addFlags(new WriteProtected(Context::SYSTEM_SCOPE)),
            new ListField('unconfirmed_app_secrets', 'unconfirmedAppSecrets', StringField::class)->removeFlag(ApiAware::class)->addFlags(new WriteProtected(Context::SYSTEM_SCOPE)),
            new BoolField('allow_disable', 'allowDisable')->addFlags(new Required())->setDescription('When boolean value is `true`, then the users have the option to deactivate specific aspects of the app.'),
            new StringField('base_app_url', 'baseAppUrl', 1024)->setDescription('Root URL for an app.'),
            new ListField('allowed_hosts', 'allowedHosts', StringField::class)->setDescription('Indicates the allowed or permitted hosts that the application can communicate with or accept requests from.'),
            new IntField('template_load_priority', 'templateLoadPriority')->setDescription('A numerical value to prioritize one of the templates from the list.'),
            new StringField('source_type', 'sourceType'),
            new JsonField('source_config', 'sourceConfig'),
            new BoolField('self_managed', 'selfManaged'),
            new ListField('requested_privileges', 'requestedPrivileges', StringField::class)->addFlags(new Required()),

            new TranslationsAssociationField(AppTranslationDefinition::class, 'app_id')->addFlags(new Required(), new CascadeDelete()),
            new TranslatedField('label'),
            new TranslatedField('description'),
            new TranslatedField('privacyPolicyExtensions'),
            new TranslatedField('customFields')->addFlags(new Since('6.4.1.0')),

            new FkField('integration_id', 'integrationId', IntegrationDefinition::class)->addFlags(new Required())->setDescription('Unique identity of integration.'),
            new OneToOneAssociationField('integration', 'integration_id', 'id', IntegrationDefinition::class),

            new FkField('acl_role_id', 'aclRoleId', AclRoleDefinition::class)->addFlags(new Required())->setDescription('Unique identity of ACL Role.'),
            new OneToOneAssociationField('aclRole', 'acl_role_id', 'id', AclRoleDefinition::class),

            new OneToManyAssociationField('customFieldSets', CustomFieldSetDefinition::class, 'app_id')->addFlags(new CascadeDelete()),
            new OneToManyAssociationField('actionButtons', ActionButtonDefinition::class, 'app_id')->addFlags(new CascadeDelete()),
            new OneToManyAssociationField('templates', TemplateDefinition::class, 'app_id')->addFlags(new CascadeDelete()),
            new OneToManyAssociationField('scripts', ScriptDefinition::class, 'app_id')->addFlags(new CascadeDelete())->removeFlag(ApiAware::class),
            new OneToManyAssociationField('webhooks', WebhookDefinition::class, 'app_id')->addFlags(new CascadeDelete()),
            new OneToManyAssociationField('scriptConditions', AppScriptConditionDefinition::class, 'app_id')->addFlags(new CascadeDelete())->removeFlag(ApiAware::class),
            new OneToManyAssociationField('contentElementTypes', AppContentSystemElementTypeDefinition::class, 'app_id')->addFlags(new CascadeDelete()),
            new OneToManyAssociationField('styleOptions', AppContentSystemStyleOptionDefinition::class, 'app_id')->addFlags(new CascadeDelete()),
            new OneToManyAssociationField('bindingSpecifications', AppContentSystemBindingSpecificationDefinition::class, 'app_id')->addFlags(new CascadeDelete()),
            new OneToManyAssociationField('layoutPresets', AppContentSystemLayoutPresetDefinition::class, 'app_id')->addFlags(new CascadeDelete()),
            new OneToManyAssociationField('seoUrlRoutes', AppSeoUrlRouteDefinition::class, 'app_id')->addFlags(new CascadeDelete()),
            new OneToManyAssociationField('flowActions', AppFlowActionDefinition::class, 'app_id')->addFlags(new CascadeDelete()),
            new OneToManyAssociationField('flowEvents', AppFlowEventDefinition::class, 'app_id')->addFlags(new CascadeDelete()),
        ]);
    }
}
