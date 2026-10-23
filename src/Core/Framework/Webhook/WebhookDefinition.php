<?php declare(strict_types=1);

namespace Contena\Core\Framework\Webhook;

use Contena\Core\Framework\App\AppDefinition;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityProtection\CloneProtection;
use Contena\Core\Framework\DataAbstractionLayer\EntityProtection\EntityProtectionCollection;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ListField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Integration\IntegrationDefinition;
use Contena\Core\System\AdminUser\AdminUserDefinition;

/**
 * @codeCoverageIgnore
 */
class WebhookDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'webhook';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return WebhookEntity::class;
    }

    public function getCollectionClass(): string
    {
        return WebhookCollection::class;
    }

    public function since(): ?string
    {
        return '6.3.1.0';
    }

    public function getDefaults(): array
    {
        return [
            'active' => true,
            'errorCount' => 0,
            'onlyLiveVersion' => false,
        ];
    }

    /**
     * A clone keeps the app and owner of the original, so it would let a caller deliver an app's webhook to their own url.
     */
    protected function defineProtections(): EntityProtectionCollection
    {
        return new EntityProtectionCollection([new CloneProtection(Context::SYSTEM_SCOPE)]);
    }

    protected function defineFields(): FieldCollection
    {
        $collection = new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of the webhook.'),
            (new StringField('name', 'name'))->addFlags(new Required())->setDescription('Name of the webhook.'),
            (new StringField('event_name', 'eventName', 500))->addFlags(new Required())->setDescription('The event the webhook is sent for, e.g. `blog.written`. Its owner\'s roles must allow the event.'),
            (new StringField('url', 'url', 500))->addFlags(new Required())->setDescription('URL the webhook is sent to.'),
            (new BoolField('only_live_version', 'onlyLiveVersion'))->setDescription('When `true`, the webhook is only sent for changes to the live version, not for drafts.'),
            (new IntField('error_count', 'errorCount', 0))->addFlags(new Required(), new WriteProtected(Context::SYSTEM_SCOPE))->setDescription('Number of failed deliveries since the last successful one. With the default failure strategy, the webhook is switched off when it reaches 10.'),
            (new BoolField('active', 'active'))->setDescription('When `false`, the webhook is not sent. It is switched off automatically after too many failed deliveries.'),
            (new FkField('app_id', 'appId', AppDefinition::class))->addFlags(new WriteProtected(Context::SYSTEM_SCOPE))->setDescription('The app that registered the webhook in its manifest. Such webhooks cannot be changed through the API, except for `active` by an admin.'),
            new ManyToOneAssociationField('app', 'app_id', AppDefinition::class),
            (new FkField('owner_user_id', 'ownerUserId', AdminUserDefinition::class))->addFlags(new WriteProtected(Context::SYSTEM_SCOPE))->removeFlag(ApiAware::class),
            (new FkField('owner_integration_id', 'ownerIntegrationId', IntegrationDefinition::class))->addFlags(new WriteProtected(Context::SYSTEM_SCOPE))->removeFlag(ApiAware::class),
            (new ListField('acl_role_ids', 'aclRoleIds', IdField::class))->setDescription('IDs of ACL roles the webhook is limited to. It only receives events whose required permissions are covered by these roles. Each role must be one its owner has. Leave it out to use all of the owner\'s roles. An empty list is not allowed.'),
        ]);

        return $collection;
    }
}
