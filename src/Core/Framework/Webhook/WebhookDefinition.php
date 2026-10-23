<?php declare(strict_types=1);

namespace Contena\Core\Framework\Webhook;

use Contena\Core\Framework\App\AppDefinition;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityProtection\CloneProtection;
use Contena\Core\Framework\DataAbstractionLayer\EntityProtection\EntityProtectionCollection;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ListField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;

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
            (new BoolField('active', 'active'))->setDescription('When `false`, the webhook is not sent. It is switched off automatically after too many failed deliveries.'),
            new ManyToOneAssociationField('app', 'app_id', AppDefinition::class),
            (new ListField('acl_role_ids', 'aclRoleIds', IdField::class))->setDescription('IDs of ACL roles the webhook is limited to. It only receives events whose required permissions are covered by these roles. Each role must be one its owner has. Leave it out to use all of the owner\'s roles. An empty list is not allowed.'),
        ]);

        return $collection;
    }
}
